<?php

namespace App\Services\Olt\Drivers;

use App\Services\Olt\Contracts\OltConnector;
use App\Services\Olt\Support\ChineseOltParser;
use Exception;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

/**
 * Abstract SSH connector for the "Chinese mini-OLT" family
 * (VSOL / BDCom, Hioso, Global / Globtel, HSGQ, OPTIWAY, ...).
 *
 * These devices share a common command vocabulary but each vendor/firmware
 * renames a few commands and layouts. Instead of guessing, we PROBE a
 * small list of candidate commands and use the first one that yields a
 * parseable result. The winning command is remembered for the session.
 */
abstract class ChineseOltConnector implements OltConnector
{
    protected ?SSH2 $ssh = null;

    protected string $host = '';

    protected int $port = 0;

    protected string $prompt = '/[$#>]/';

    protected bool $inEnableMode = false;

    protected bool $inConfigMode = false;

    protected string $brandLabel = 'Chinese OLT';

    /** Commands that produce a full ONU status list (tried in order). */
    protected array $statusCommands = [
        'show onu status all',
        'show onu info all',
        'show onu baisc-info all',
        'show onu basic-info all',
        'show epon onu-information',
        'show onu-status',
    ];

    /** Commands that produce per-PON totals (Total/Online ONU counts). */
    protected array $totalsCommands = [
        'show pon baisc-info',
        'show pon basic-info',
        'show pon-info',
    ];

    /** Commands used to enter config mode (tried in order). */
    protected array $configCommands = ['config', 'configure terminal', 'configure'];

    protected ?string $activeStatusCommand = null;

    protected bool $probedTotals = false;

    // ── Connection lifecycle ──

    public function connect(string $host, int $port, string $username, string $password): bool
    {
        $this->host = $host;
        $this->port = $port;

        try {
            $this->ssh = new SSH2($host, $port, 20);
            if (! $this->ssh->login($username, $password)) {
                throw new Exception('SSH login failed');
            }
            $this->ssh->setTimeout(20);
            $this->ssh->read($this->prompt, SSH2::READ_REGEX);
            $this->enterEnableMode();
            $this->enterConfigMode();

            $this->logCli('CONNECT', 'SSH connected, entered enable + config mode', [
                'host' => $host,
                'port' => $port,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("{$this->brandLabel} SSH connect failed: {$e->getMessage()}");
            $this->logCli('CONNECT_ERROR', $e->getMessage(), ['host' => $host, 'port' => $port]);

            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->ssh) {
            try {
                $this->ssh->write("exit\n");
                $this->ssh->read($this->prompt, SSH2::READ_REGEX);
                $this->ssh->write("exit\n");
                $this->ssh->read($this->prompt, SSH2::READ_REGEX);
            } catch (\Throwable) {
                // ignore cleanup errors
            }
            $this->logCli('DISCONNECT', 'SSH session closed');
            $this->ssh->disconnect();
            $this->ssh = null;
            $this->inEnableMode = false;
            $this->inConfigMode = false;
            $this->activeStatusCommand = null;
            $this->probedTotals = false;
        }
    }

    // ── System / connection info ──

    public function testConnection(): array
    {
        try {
            $info = $this->sendCommand('show version');

            return [
                'success' => true,
                'message' => "Terhubung ke {$this->brandLabel} OLT",
                'data' => [
                    'raw' => substr($this->cleanOutput($info), 0, 1500),
                ],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getSystemInfo(): array
    {
        try {
            $output = $this->sendCommand('show version');

            return ['raw' => $this->cleanOutput($output)];
        } catch (Exception $e) {
            Log::error("{$this->brandLabel} getSystemInfo failed: {$e->getMessage()}");

            return [];
        }
    }

    // ── ONU listing ──

    public function getOnuList(int $slot, int $port): array
    {
        try {
            $output = $this->getStatusOutput();
            $onus = ChineseOltParser::parseOnus($output);

            $onus = array_values(array_filter($onus, function ($onu) use ($slot, $port) {
                // Keep entries that match the requested slot/port; entries
                // with slot/port 0/0 (online-only lists) are kept as-is.
                if ($onu['slot'] === 0 && $onu['port'] === 0) {
                    return true;
                }

                return $onu['slot'] === $slot && $onu['port'] === $port;
            }));

            return array_map(fn ($onu) => [
                'onu_id' => $onu['onu_id'],
                'sn' => $onu['sn'] ?? null,
                'status' => $onu['status'],
            ], $onus);
        } catch (Exception $e) {
            Log::error("{$this->brandLabel} getOnuList({$slot}/{$port}) failed: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Read ONU counts for multiple ports in one shot (real-time).
     *
     * First tries a "PON totals" command (exact per-port Total/Online counts
     * as shown in the OLT GUI), otherwise falls back to parsing the full ONU
     * listing filtered to the configured ports.
     *
     * @param  array<int, array{slot: int, port: int, type: string|null}>  $ports
     * @return array{total_onus: int, online_onus: int, offline_onus: int, onus: array<int, array{onu_id: string, status: string}>}
     */
    public function getOnuSummaryAll(array $ports): array
    {
        $ports = array_values($ports);

        if ($ports === []) {
            return ['total_onus' => 0, 'online_onus' => 0, 'offline_onus' => 0, 'onus' => []];
        }

        try {
            if (! $this->probedTotals) {
                foreach ($this->totalsCommands as $command) {
                    try {
                        $output = $this->sendCommand($command);
                        $totals = ChineseOltParser::parsePonTotals($output);

                        if ($totals === []) {
                            continue;
                        }

                        $this->probedTotals = true;

                        return $this->summarizeFromTotals($totals, $ports);
                    } catch (\Throwable) {
                        // try next candidate
                    }
                }

                $this->probedTotals = true;
            }

            $output = $this->getStatusOutput();
            $onus = ChineseOltParser::parseOnus($output);

            $kept = array_filter($onus, function ($onu) use ($ports) {
                foreach ($ports as $p) {
                    if ($onu['slot'] === 0 && $onu['port'] === 0) {
                        return true;
                    }
                    if ($onu['slot'] === $p['slot'] && $onu['port'] === $p['port']) {
                        return true;
                    }
                }

                return false;
            });

            $online = 0;
            $offline = 0;
            $flat = [];

            foreach ($kept as $onu) {
                $status = $onu['status'] === 'online' ? 'online' : 'offline';
                if ($status === 'online') {
                    $online++;
                } else {
                    $offline++;
                }
                $flat[] = ['onu_id' => $onu['onu_id'], 'status' => $status];
            }

            return [
                'total_onus' => count($flat),
                'online_onus' => $online,
                'offline_onus' => $offline,
                'onus' => $flat,
            ];
        } catch (Exception $e) {
            Log::error("{$this->brandLabel} getOnuSummaryAll failed: {$e->getMessage()}");

            return ['total_onus' => 0, 'online_onus' => 0, 'offline_onus' => 0, 'onus' => []];
        }
    }

    private function summarizeFromTotals(array $totals, array $ports): array
    {
        $total = 0;
        $online = 0;

        foreach ($totals as $t) {
            foreach ($ports as $p) {
                if ($t['slot'] === $p['slot'] && $t['port'] === $p['port']) {
                    $total += $t['total'];
                    $online += $t['online'];
                }
            }
        }

        return [
            'total_onus' => $total,
            'online_onus' => $online,
            'offline_onus' => max(0, $total - $online),
            'onus' => [],
        ];
    }

    // ── ONU operations (best-effort, vendor CLI varies a lot) ──

    public function getOnuDetail(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;

            $output = $this->sendCommand("show onu running config {$slot}/{$port}");

            return [
                'raw' => $this->cleanOutput($output),
                'onu_id' => $onuId,
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    public function provisionOnu(array $data): array
    {
        try {
            $slot = $data['slot'] ?? 0;
            $port = $data['port'] ?? 0;
            $onuId = $data['onu_id'] ?? 1;
            $sn = strtoupper($data['serial_number'] ?? '');

            if ($sn === '') {
                throw new Exception('Serial number kosong');
            }

            $type = $this->ponType($data['port_type'] ?? null);
            $this->enterConfigMode();
            $this->sendCommand("interface {$type} {$slot}/{$port}");
            $this->sendCommand("onu {$onuId} add sn-auth {$sn}");
            $this->exitConfigMode();

            return ['success' => true, 'message' => "ONU {$onuId} berhasil diprovision"];
        } catch (Exception $e) {
            $this->emergencyCleanup();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeOnu(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 1;

            $type = $this->ponTypeFromOnuId($onuId);
            $this->enterConfigMode();
            $this->sendCommand("interface {$type} {$slot}/{$port}");
            $this->sendCommand("no onu {$idx}");
            $this->exitConfigMode();

            return ['success' => true, 'message' => "ONU {$onuId} berhasil dihapus"];
        } catch (Exception $e) {
            $this->emergencyCleanup();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function rebootOnu(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 1;

            $type = $this->ponTypeFromOnuId($onuId);
            $this->enterConfigMode();
            $this->sendCommand("interface {$type} {$slot}/{$port}");
            $this->sendCommand("onu {$idx} reboot");
            $this->exitConfigMode();

            return ['success' => true, 'message' => "ONU {$onuId} berhasil direboot"];
        } catch (Exception $e) {
            $this->emergencyCleanup();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPortStatus(int $slot, int $port): array
    {
        try {
            $output = $this->sendCommand("show interface {$this->ponType(null)} {$slot}/{$port}");

            return ['raw' => $this->cleanOutput($output), 'slot' => $slot, 'port' => $port];
        } catch (Exception $e) {
            return [];
        }
    }

    public function getOpticalPower(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $idx = $parts[2] ?? null;

            $output = $this->sendCommand('show onu opm-diag all');
            $clean = $this->cleanOutput($output);

            return $this->parseOptical($clean, $onuId, $idx);
        } catch (Exception $e) {
            Log::error("{$this->brandLabel} getOpticalPower({$onuId}) failed: {$e->getMessage()}");

            return ['onu_id' => $onuId, 'rx_power' => null, 'tx_power' => null];
        }
    }

    // ── Command helpers ──

    protected function getStatusOutput(): string
    {
        if ($this->activeStatusCommand !== null) {
            return $this->sendCommand($this->activeStatusCommand);
        }

        foreach ($this->statusCommands as $command) {
            try {
                $output = $this->sendCommand($command);
                $onus = ChineseOltParser::parseOnus($output);

                if ($onus === []) {
                    continue;
                }

                $this->activeStatusCommand = $command;

                $this->logCli('STATUS_COMMAND_SELECTED', $output, [
                    'command' => $command,
                    'parsed_onus' => count($onus),
                ]);

                return $output;
            } catch (\Throwable) {
                // try next candidate
            }
        }

        // No command produced a parseable list — return the first raw output
        // so callers can log/inspect what the device actually returned.
        foreach ($this->statusCommands as $command) {
            try {
                return $this->sendCommand($command);
            } catch (\Throwable) {
                // keep looking
            }
        }

        throw new Exception('Tidak ada command ONU yang berhasil dieksekusi');
    }

    private function sendCommand(string $command): string
    {
        if (! $this->ssh) {
            throw new Exception('SSH not connected');
        }

        $this->ssh->write($command."\n");
        usleep(500000);

        $output = '';
        $maxReads = 20;

        for ($i = 0; $i < $maxReads; $i++) {
            $chunk = $this->ssh->read($this->prompt, SSH2::READ_REGEX);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $output .= $chunk;

            if (preg_match('/--More--/', $chunk)) {
                $this->ssh->write(' ');
                usleep(300000);
            } else {
                break;
            }
        }

        return $output;
    }

    private function enterEnableMode(): void
    {
        if (! $this->ssh) {
            return;
        }

        $this->ssh->write("enable\n");
        $this->ssh->read($this->prompt, SSH2::READ_REGEX);
        $this->inEnableMode = true;
    }

    private function enterConfigMode(): void
    {
        if ($this->inConfigMode || ! $this->ssh) {
            return;
        }

        foreach ($this->configCommands as $command) {
            $this->ssh->write($command."\n");
            $chunk = $this->ssh->read($this->prompt, SSH2::READ_REGEX);

            if ($chunk !== false && preg_match('/\(config[^)]*\)#/', $chunk)) {
                $this->inConfigMode = true;

                return;
            }
        }

        // Fallback: assume config mode even if the prompt did not match
        // (some devices keep the same prompt text in every mode).
        $this->inConfigMode = true;
    }

    private function exitConfigMode(): void
    {
        if (! $this->inConfigMode || ! $this->ssh) {
            return;
        }

        try {
            $this->ssh->write("exit\n");
            $this->ssh->read($this->prompt, SSH2::READ_REGEX);
        } catch (\Throwable) {
            // ignore
        }
        $this->inConfigMode = false;
    }

    private function emergencyCleanup(): void
    {
        try {
            $this->exitConfigMode();
        } catch (\Throwable) {
            // ignore
        }
    }

    private function ponType(?string $portType): string
    {
        if (is_string($portType) && stripos($portType, 'epon') !== false) {
            return 'epon';
        }

        return 'gpon';
    }

    private function ponTypeFromOnuId(string $onuId): string
    {
        // "EPON0/1/1" style prefix or plain "0/1/1" → default gpon
        if (stripos($onuId, 'epon') !== false) {
            return 'epon';
        }

        return 'gpon';
    }

    // ── Parsers ──

    private function parseOptical(string $output, string $onuId, ?string $idx): array
    {
        $rx = null;
        $tx = null;

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/^[-=]+$/', $line)) {
                continue;
            }

            // Key-value format per ONU: "Rx Power(dBm): -19.5  Tx Power(dBm): 2.1"
            if ($idx !== null && preg_match('/^[A-Za-z]*0*'.$idx.'\b/i', $line) === 1) {
                if (preg_match_all('/-?\d+\.?\d*/', $line, $vals)) {
                    $nums = array_map('floatval', $vals[0]);
                    $neg = array_filter($nums, fn ($v) => $v < 0);
                    $pos = array_filter($nums, fn ($v) => $v >= 0);

                    if ($neg !== []) {
                        $rx = max($neg);
                    }
                    if ($pos !== []) {
                        $tx = min($pos);
                    }
                }

                continue;
            }

            if ($rx === null && preg_match('/Rx\s*(?:Optical\s*)?[Pp]ower\s*[:=\-]?\s*(-?\d+\.?\d*)/i', $line, $m)) {
                $rx = (float) $m[1];
            }

            if ($tx === null && preg_match('/Tx\s*(?:Optical\s*)?[Pp]ower\s*[:=\-]?\s*(-?\d+\.?\d*)/i', $line, $m)) {
                $tx = (float) $m[1];
            }
        }

        return ['onu_id' => $onuId, 'rx_power' => $rx, 'tx_power' => $tx];
    }

    // ── Utilities ──

    protected function cleanOutput(string $output): string
    {
        $output = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
        $output = preg_replace('/\x1b\][^\x07\x1b]*(?:\x07|\x1b\\\\)/', '', $output);
        $output = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $output);
        $output = str_replace(["\r\n", "\r"], "\n", $output);

        $lines = explode("\n", $output);
        $cleaned = [];

        foreach ($lines as $line) {
            if (preg_match('/^[\s]*\S+(?:\(config[^)]*\))?[\s#>]\s*$/', trim($line))) {
                continue;
            }
            $cleaned[] = $line;
        }

        return implode("\n", $cleaned);
    }

    private function logCli(string $action, string $output, array $context = []): void
    {
        try {
            $logDir = storage_path('logs');
            if (! is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $entry = array_merge([
                'timestamp' => now()->format('Y-m-d H:i:s.u'),
                'action' => $action,
                'connector' => strtolower(str_replace('Connector', '', class_basename($this))),
                'brand' => strtolower($this->brandLabel),
                'host' => $this->host ?? '-',
                'port' => $this->port ?? 0,
            ], $context, [
                'raw_output' => $output,
            ]);

            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
            file_put_contents($logDir.'/olt.log', $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            Log::warning("{$this->brandLabel} CLI logging failed: {$e->getMessage()}");
        }
    }
}
