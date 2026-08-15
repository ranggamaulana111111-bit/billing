<?php

namespace App\Services\Olt\Drivers;

use App\Models\Setting;
use App\Services\Olt\Contracts\OltConnector;
use App\Services\Olt\Support\ChineseOltParser;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MikrotikSshProxyConnector implements OltConnector
{
    private string $brand;

    private string $oltHost;

    private int $oltPort;

    private string $oltUser;

    private string $oltPass;

    private bool $connected = false;

    private string $mikrotikHost;

    private string $mikrotikUser;

    private string $mikrotikPass;

    private int $mikrotikPort;

    private string $scheme;

    public function __construct(string $brand)
    {
        $this->brand = strtolower($brand);
    }

    public function connect(string $host, int $port, string $username, string $password): bool
    {
        $this->oltHost = $host;
        $this->oltPort = $port;
        $this->oltUser = $username;
        $this->oltPass = $password;

        $this->mikrotikHost = Setting::get('mikrotik_host');
        $this->mikrotikUser = Setting::get('mikrotik_user');
        $this->mikrotikPass = Setting::get('mikrotik_password');
        $this->mikrotikPort = (int) (Setting::get('mikrotik_port', '80'));
        $this->scheme = $this->mikrotikPort === 443 ? 'https' : 'http';

        if (! $this->mikrotikHost || ! $this->mikrotikUser || ! $this->mikrotikPass) {
            Log::error('MikroTik proxy: global settings tidak lengkap');

            return false;
        }

        try {
            $response = Http::withBasicAuth($this->mikrotikUser, $this->mikrotikPass)
                ->withoutVerifying()
                ->timeout(10)
                ->get("{$this->scheme}://{$this->mikrotikHost}:{$this->mikrotikPort}/rest/system/resource");

            $this->connected = $response->successful();

            if (! $this->connected) {
                Log::error('MikroTik proxy: gagal verify MikroTik, status '.$response->status());

                return false;
            }

            $this->mikrotikPing();

            return true;
        } catch (Exception $e) {
            Log::error("MikroTik proxy: gagal konek ke MikroTik: {$e->getMessage()}");

            return false;
        }
    }

    private function mikrotikPing(): void
    {
        try {
            $response = Http::withBasicAuth($this->mikrotikUser, $this->mikrotikPass)
                ->withoutVerifying()
                ->timeout(10)
                ->post("{$this->scheme}://{$this->mikrotikHost}:{$this->mikrotikPort}/rest/ping", [
                    'address' => $this->oltHost,
                    'count' => '2',
                ]);

            if (! $response->successful()) {
                Log::warning("MikroTik proxy: ping OLT {$this->oltHost} gagal, status {$response->status()}");
            }
        } catch (Exception $e) {
            Log::warning("MikroTik proxy: ping OLT {$this->oltHost} error: {$e->getMessage()}");
        }
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    private function execOltCommand(string $command): string
    {
        if (! $this->connected) {
            throw new Exception('MikroTik proxy not connected');
        }

        $url = "{$this->scheme}://{$this->mikrotikHost}:{$this->mikrotikPort}/rest/system/ssh-exec";
        $startTime = microtime(true);

        try {
            $response = Http::withBasicAuth($this->mikrotikUser, $this->mikrotikPass)
                ->withoutVerifying()
                ->timeout(60)
                ->post($url, [
                    'address' => $this->oltHost,
                    'port' => $this->oltPort,
                    'user' => $this->oltUser,
                    'password' => $this->oltPass,
                    'command' => $command,
                ]);

            if (! $response->successful()) {
                throw new Exception("MikroTik API error: {$response->status()} {$response->body()}");
            }

            $output = $this->parseApiResponse($response->json(), $response->body());
            $elapsed = round((microtime(true) - $startTime) * 1000, 1);

            $this->logCli('PROXY_EXEC_COMMAND', $output, [
                'command' => $command,
                'elapsed_ms' => $elapsed,
            ]);

            return $output;
        } catch (Exception $e) {
            $elapsed = round((microtime(true) - $startTime) * 1000, 1);
            $this->logCli('PROXY_EXEC_COMMAND_ERROR', $e->getMessage(), [
                'command' => $command,
                'elapsed_ms' => $elapsed,
            ]);

            if (str_contains($e->getMessage(), 'cURL error 28')) {
                throw new Exception("Timeout saat SSH ke OLT {$this->oltHost} via MikroTik");
            }
            throw $e;
        }
    }

    private function parseApiResponse(?array $json, string $raw): string
    {
        if ($json === null) {
            return trim($raw);
        }

        if (isset($json['output'])) {
            return $json['output'];
        }

        if (is_array($json) && count($json) > 0) {
            if (isset($json[0]) && is_array($json[0]) && isset($json[0]['output'])) {
                return $json[0]['output'];
            }

            $lines = [];
            foreach ($json as $item) {
                if (is_string($item)) {
                    $lines[] = $item;
                } elseif (is_array($item) && isset($item['output'])) {
                    $lines[] = $item['output'];
                }
            }

            if ($lines) {
                return implode("\n", $lines);
            }
        }

        return trim($raw);
    }

    private function execPrivileged(string $command): string
    {
        return match ($this->brand) {
            'huawei' => $this->execOltCommand("system-view\n{$command}"),
            'zte' => $this->execOltCommand("enable\nconfigure terminal\n{$command}"),
            'cdata', 'vsol', 'hioso', 'hsgq', 'global' => $this->execOltCommand("enable\nconfig\n{$command}"),
            default => $this->execOltCommand($command),
        };
    }

    private function isChineseBrand(): bool
    {
        return in_array($this->brand, ['vsol', 'hioso', 'hsgq', 'global'], true);
    }

    /**
     * Candidate status commands per Chinese-brand (probed in order).
     *
     * @return string[]
     */
    private function chineseStatusCommands(): array
    {
        return match ($this->brand) {
            'global' => [
                'show epon onu-information',
                'show gpon onu-information',
                'show onu status all',
                'show onu info all',
                'show onu baisc-info all',
                'show onu-status',
            ],
            'hsgq' => [
                'show epon onu-information',
                'show onu status all',
                'show onu info all',
                'show onu list',
                'show onu-status',
            ],
            default => [
                'show onu status all',
                'show onu info all',
                'show onu baisc-info all',
                'show onu basic-info all',
                'show onu-status',
            ],
        };
    }

    /**
     * Candidate per-PON totals commands (probed in order).
     *
     * @return string[]
     */
    private function chineseTotalsCommands(): array
    {
        return [
            'show pon baisc-info',
            'show pon basic-info',
            'show pon-info',
        ];
    }

    public function testConnection(): array
    {
        try {
            $info = match ($this->brand) {
                'huawei' => $this->execOltCommand('display version'),
                'zte' => $this->execOltCommand('show system information'),
                'fiberhome' => $this->execOltCommand('show system-info'),
                'cdata' => $this->execOltCommand('show version'),
                'vsol', 'hioso', 'hsgq', 'global' => $this->execPrivileged('show version'),
                default => throw new Exception("Unsupported brand: {$this->brand}"),
            };

            return [
                'success' => true,
                'message' => 'Terhubung ke '.ucfirst($this->brand).' OLT via MikroTik proxy',
                'data' => ['raw' => $info],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getSystemInfo(): array
    {
        try {
            $output = match ($this->brand) {
                'huawei' => $this->execOltCommand('display version'),
                'zte' => $this->execOltCommand('show system information'),
                'fiberhome' => $this->execOltCommand('show system-info'),
                'cdata' => $this->execOltCommand('show version'),
                'vsol', 'hioso', 'hsgq', 'global' => $this->execPrivileged('show version'),
                default => '',
            };

            return ['raw' => $output];
        } catch (Exception $e) {
            Log::error("MikroTik proxy getSystemInfo: {$e->getMessage()}");

            return [];
        }
    }

    public function getOnuList(int $slot, int $port): array
    {
        try {
            if ($this->isChineseBrand()) {
                return $this->chineseGetOnuList($slot, $port);
            }

            $output = match ($this->brand) {
                'huawei' => $this->execOltCommand("display ont info {$slot} {$port}"),
                'zte' => $this->execOltCommand("show onu unquiet interface gpon-olt_{$slot}/{$port}"),
                'fiberhome' => $this->execOltCommand("show ont list slot {$slot} port {$port}"),
                'cdata' => $this->execOltCommand('show ont info all'),
                default => throw new Exception('Unsupported brand'),
            };

            $onus = [];

            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if ($line === '' || preg_match('/^[-=]+$/', $line) || stripos($line, 'onu id') !== false) {
                    continue;
                }

                // C-DATA format: F/S P ONT SN Control-flag Run-state
                if ($this->brand === 'cdata') {
                    if (preg_match('/(\d+)\/(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)/', $line, $m)) {
                        $lineSlot = (int) $m[2];
                        $linePort = (int) $m[3];
                        if ($lineSlot == $slot && $linePort == $port) {
                            $onus[] = [
                                'onu_id' => "{$slot}/{$port}/{$m[4]}",
                                'sn' => $m[5],
                                'status' => $m[7],
                            ];
                        }
                    }

                    continue;
                }

                if (preg_match('/^\s*(?:\d+[\s\/]\d+[\s\/]\d+\s+)?(\d+)\s+(\S+)(?:\s+(\S+))?(?:\s+(\S+))?/', $line, $m)) {
                    $onuId = $m[1];
                    $sn = $m[2];
                    $status = $m[4] ?? $m[3] ?? 'unknown';

                    $onus[] = [
                        'onu_id' => "{$slot}/{$port}/{$onuId}",
                        'sn' => $sn,
                        'status' => $status,
                    ];
                }
            }

            return $onus;
        } catch (Exception $e) {
            Log::error("MikroTik proxy getOnuList({$slot}/{$port}): {$e->getMessage()}");

            return [];
        }
    }

    private function chineseGetOnuList(int $slot, int $port): array
    {
        $output = $this->probeChineseStatusCommand();
        $onus = array_values(array_filter(ChineseOltParser::parseOnus($output), function ($onu) use ($slot, $port) {
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
    }

    private function probeChineseStatusCommand(): string
    {
        foreach ($this->chineseStatusCommands() as $command) {
            try {
                $output = $this->execPrivileged($command);
                $onus = ChineseOltParser::parseOnus($output);

                if ($onus !== []) {
                    $this->logCli('STATUS_COMMAND_SELECTED', $output, [
                        'command' => $command,
                        'parsed_onus' => count($onus),
                    ]);

                    return $output;
                }
            } catch (\Throwable) {
                // try next candidate
            }
        }

        foreach ($this->chineseStatusCommands() as $command) {
            try {
                return $this->execPrivileged($command);
            } catch (\Throwable) {
                // keep looking
            }
        }

        throw new Exception('Tidak ada command ONU yang berhasil dieksekusi');
    }

    /**
     * Read ONU counts for multiple ports in one shot (real-time).
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

        // Non-Chinese brands: loop the per-port list (same as direct drivers).
        if (! $this->isChineseBrand()) {
            $summary = ['total_onus' => 0, 'online_onus' => 0, 'offline_onus' => 0, 'onus' => []];

            foreach ($ports as $p) {
                foreach ($this->getOnuList($p['slot'], $p['port']) as $onu) {
                    $status = ($onu['status'] ?? '') === 'online' ? 'online' : 'offline';
                    $summary['total_onus']++;
                    $summary[$status === 'online' ? 'online_onus' : 'offline_onus']++;
                    $summary['onus'][] = ['onu_id' => $onu['onu_id'] ?? '-', 'status' => $status];
                }
            }

            return $summary;
        }

        try {
            // Prefer per-PON totals when the device supports it (matches OLT GUI counts).
            foreach ($this->chineseTotalsCommands() as $command) {
                try {
                    $output = $this->execPrivileged($command);
                    $totals = ChineseOltParser::parsePonTotals($output);

                    if ($totals === []) {
                        continue;
                    }

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
                } catch (\Throwable) {
                    // try next candidate
                }
            }

            // Fallback: parse the full ONU listing filtered to configured ports.
            $output = $this->probeChineseStatusCommand();
            $onus = array_values(array_filter(ChineseOltParser::parseOnus($output), function ($onu) use ($ports) {
                foreach ($ports as $p) {
                    if ($onu['slot'] === 0 && $onu['port'] === 0) {
                        return true;
                    }
                    if ($onu['slot'] === $p['slot'] && $onu['port'] === $p['port']) {
                        return true;
                    }
                }

                return false;
            }));

            $online = 0;
            $flat = [];
            foreach ($onus as $onu) {
                $status = $onu['status'] === 'online' ? 'online' : 'offline';
                if ($status === 'online') {
                    $online++;
                }
                $flat[] = ['onu_id' => $onu['onu_id'], 'status' => $status];
            }

            return [
                'total_onus' => count($flat),
                'online_onus' => $online,
                'offline_onus' => max(0, count($flat) - $online),
                'onus' => $flat,
            ];
        } catch (Exception $e) {
            Log::error("MikroTik proxy getOnuSummaryAll({$this->brand}): {$e->getMessage()}");

            return ['total_onus' => 0, 'online_onus' => 0, 'offline_onus' => 0, 'onus' => []];
        }
    }

    public function getOnuDetail(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 0;

            $output = match ($this->brand) {
                'huawei' => $this->execOltCommand("display ont info {$slot} {$port} {$idx}"),
                'zte' => $this->execOltCommand("show onu detail gpon-olt_{$slot}/{$port} onu {$idx}"),
                'fiberhome' => $this->execOltCommand("show ont info slot {$slot} port {$port} ont {$idx}"),
                'cdata' => $this->execOltCommand("show ont info {$slot}/{$port} {$idx}"),
                'vsol', 'hioso', 'hsgq', 'global' => $this->execPrivileged("show onu running config {$slot}/{$port}"),
                default => throw new Exception('Unsupported brand'),
            };

            return ['raw' => $output, 'onu_id' => $onuId];
        } catch (Exception $e) {
            Log::error("MikroTik proxy getOnuDetail({$onuId}): {$e->getMessage()}");

            return [];
        }
    }

    public function provisionOnu(array $data): array
    {
        try {
            $slot = $data['slot'];
            $port = $data['port'];
            $onuId = $data['onu_id'];
            $sn = $data['serial_number'] ?? '';
            $vlan = $data['vlan'] ?? 10;

            match ($this->brand) {
                'huawei' => $this->execPrivileged(
                    "interface gpon {$slot}/{$port}\nont add {$onuId} {$sn}\nont port native-vlan {$slot}/{$port} {$onuId} eth 1 vlan {$vlan}"
                ),
                'zte' => $this->execPrivileged(
                    "interface gpon-olt_{$slot}/{$port}\nonu {$onuId} type ont sn {$sn}"
                ),
                'fiberhome' => $this->execOltCommand(
                    "ont add slot {$slot} port {$port} sn {$sn}"
                ),
                'cdata' => $this->execPrivileged(
                    "interface gpon {$slot}/0\nont add {$onuId} sn-auth {$sn} ont-lineprofile-id 1 ont-srvprofile-id 1\nont port native-vlan {$slot}/{$port} {$onuId} eth 1 vlan {$vlan}"
                ),
                'vsol', 'hioso', 'hsgq', 'global' => $this->execPrivileged(
                    "interface gpon {$slot}/{$port}\nonu {$onuId} add sn-auth ".strtoupper($sn)
                ),
                default => throw new Exception('Unsupported brand'),
            };

            return ['success' => true, 'message' => "ONU {$onuId} berhasil diprovision"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeOnu(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 0;

            match ($this->brand) {
                'huawei' => $this->execPrivileged(
                    "interface gpon {$slot}/{$port}\nont delete {$idx}"
                ),
                'zte' => $this->execPrivileged(
                    "interface gpon-olt_{$slot}/{$port}\nno onu {$idx}"
                ),
                'fiberhome' => $this->execOltCommand(
                    "ont delete slot {$slot} port {$port} ont {$idx}"
                ),
                'cdata' => $this->execPrivileged(
                    "interface gpon {$slot}/0\nno ont add {$idx}"
                ),
                'vsol', 'hioso', 'hsgq', 'global' => $this->execPrivileged(
                    "interface gpon {$slot}/{$port}\nno onu {$idx}"
                ),
                default => throw new Exception('Unsupported brand'),
            };

            return ['success' => true, 'message' => "ONU {$onuId} berhasil dihapus"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function rebootOnu(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 0;

            match ($this->brand) {
                'huawei' => $this->execPrivileged(
                    "interface gpon {$slot}/{$port}\nont reset {$idx}"
                ),
                'zte' => $this->execPrivileged(
                    "interface gpon-olt_{$slot}/{$port}\nonu reset {$idx}"
                ),
                'fiberhome' => $this->execOltCommand(
                    "ont reset slot {$slot} port {$port} ont {$idx}"
                ),
                'cdata' => $this->execPrivileged(
                    "interface gpon {$slot}/0\nont reset {$idx}"
                ),
                'vsol', 'hioso', 'hsgq', 'global' => $this->execPrivileged(
                    "interface gpon {$slot}/{$port}\nonu {$idx} reboot"
                ),
                default => throw new Exception('Unsupported brand'),
            };

            return ['success' => true, 'message' => "ONU {$onuId} berhasil direboot"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPortStatus(int $slot, int $port): array
    {
        try {
            $output = match ($this->brand) {
                'huawei' => $this->execOltCommand("display port state gpon {$slot}/{$port}"),
                'zte' => $this->execOltCommand("show interface gpon-olt_{$slot}/{$port}"),
                'fiberhome' => $this->execOltCommand("show port info slot {$slot} port {$port}"),
                'cdata' => $this->execOltCommand("show port state gpon {$slot}/{$port}"),
                'vsol', 'hioso', 'hsgq', 'global' => $this->execPrivileged("show interface gpon {$slot}/{$port}"),
                default => throw new Exception('Unsupported brand'),
            };

            return ['raw' => $output, 'slot' => $slot, 'port' => $port];
        } catch (Exception $e) {
            Log::error("MikroTik proxy getPortStatus({$slot}/{$port}): {$e->getMessage()}");

            return [];
        }
    }

    public function getOpticalPower(string $onuId): array
    {
        $startTime = microtime(true);

        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 0;

            if ($this->brand === 'cdata') {
                // C-DATA: interface gpon uses F/S pair (slot/0), port is a command parameter
                $output = $this->execPrivileged(
                    "interface gpon {$slot}/0\nshow ont optical-info {$port} all\nexit"
                );
            } elseif ($this->isChineseBrand()) {
                $output = $this->execPrivileged('show onu opm-diag all');
            } else {
                $command = match ($this->brand) {
                    'huawei' => "display ont optical-info {$slot} {$port} {$idx}",
                    'zte' => "show onu optical-info {$slot} {$port} {$idx}",
                    'fiberhome' => "show ont optic slot {$slot} port {$port} ont {$idx}",
                    default => throw new Exception('Unsupported brand'),
                };

                $output = $this->execOltCommand($command);
            }

            $elapsed = round((microtime(true) - $startTime) * 1000, 1);

            $parsed = $this->parseOpticalOutput($output, $onuId, $idx);

            $this->logCli('PROXY_GET_OPTICAL_POWER', $output, [
                'onu_id' => $onuId,
                'slot' => $slot,
                'port' => $port,
                'ont_index' => $idx,
                'rx_power' => $parsed['rx_power'],
                'tx_power' => $parsed['tx_power'],
                'elapsed_ms' => $elapsed,
            ]);

            return [
                'onu_id' => $onuId,
                'rx_power' => $parsed['rx_power'],
                'tx_power' => $parsed['tx_power'],
            ];
        } catch (Exception $e) {
            $elapsed = round((microtime(true) - $startTime) * 1000, 1);
            Log::error("MikroTik proxy getOpticalPower({$onuId}) after {$elapsed}ms: {$e->getMessage()}");
            $this->logCli('PROXY_GET_OPTICAL_POWER_ERROR', $e->getMessage(), [
                'onu_id' => $onuId,
                'elapsed_ms' => $elapsed,
            ]);

            return ['onu_id' => $onuId, 'rx_power' => null, 'tx_power' => null];
        }
    }

    private function parseOpticalOutput(string $output, string $onuId, int $idx): array
    {
        $rx = null;
        $tx = null;

        // Chinese brand bulk OPM table: "EPON0/1:1  ...  -19.5  2.1"
        if ($this->isChineseBrand()) {
            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if (preg_match('/^[A-Za-z]*0*'.$idx.'\b/i', $line) === 1) {
                    if (preg_match_all('/-?\d+\.?\d*/', $line, $vals)) {
                        $nums = array_map('floatval', $vals[0]);
                        $neg = array_values(array_filter($nums, fn ($v) => $v < 0));
                        $pos = array_values(array_filter($nums, fn ($v) => $v >= 0));

                        if ($neg !== []) {
                            $rx = max($neg);
                        }
                        if ($pos !== []) {
                            $tx = min($pos);
                        }
                    }

                    return ['rx_power' => $rx, 'tx_power' => $tx];
                }
            }

            return ['rx_power' => $rx, 'tx_power' => $tx];
        }

        // If C-DATA bulk output, parse table and find specific ONT
        if ($this->brand === 'cdata') {
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || preg_match('/^[-=]+$/', $line)) {
                    continue;
                }

                // Match table row: ONT_ID SN Rx Tx ...
                if (preg_match('/^\s*'.$idx.'\s+(\S+)\s+(-?\d+\.?\d+)\s+(-?\d+\.?\d+)/', $line, $m)) {
                    $val1 = (float) $m[2];
                    $val2 = (float) $m[3];
                    if ($val1 < 0 && $val2 > 0) {
                        $rx = $val1;
                        $tx = $val2;
                    } elseif ($val1 > 0 && $val2 < 0) {
                        $rx = $val2;
                        $tx = $val1;
                    }

                    return ['rx_power' => $rx, 'tx_power' => $tx];
                }

                // Fallback: match any line with Rx/Tx dBm
                if (preg_match('/Rx\s*(?:Optical\s*)?[Pp]ower\s*[:=\-]?\s*(-?\d+\.?\d*)\s*dBm/i', $line, $rxM)) {
                    $rx = (float) $rxM[1];
                }
                if (preg_match('/Tx\s*(?:Optical\s*)?[Pp]ower\s*[:=\-]?\s*(-?\d+\.?\d*)\s*dBm/i', $line, $txM)) {
                    $tx = (float) $txM[1];
                }

                if ($rx !== null && $tx !== null) {
                    return ['rx_power' => $rx, 'tx_power' => $tx];
                }
            }

            return ['rx_power' => $rx, 'tx_power' => $tx];
        }

        // Non-C-DATA: generic parser
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $original = $line;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // "Rx Power : -xx.x dBm"
            if ($rx === null && preg_match('/Rx\s+(?:Optical\s+)?[Pp]ower\s*[:=\-]\s*(-?\d+\.?\d*)\s*dBm/i', $line, $m)) {
                $rx = (float) $m[1];

                continue;
            }

            // "Tx Power : xx.x dBm"
            if ($tx === null && preg_match('/Tx\s+(?:Optical\s+)?[Pp]ower\s*[:=\-]\s*(-?\d+\.?\d*)\s*dBm/i', $line, $m)) {
                $tx = (float) $m[1];

                continue;
            }

            // "rx power -xx.x"
            if ($rx === null && preg_match('/\brx\s+power\b\s+(-?\d+\.?\d*)/i', $line, $m)) {
                $rx = (float) $m[1];

                continue;
            }

            // "tx power xx.x"
            if ($tx === null && preg_match('/\btx\s+power\b\s+(-?\d+\.?\d*)/i', $line, $m)) {
                $tx = (float) $m[1];

                continue;
            }

            // "rx := -xx.x" or "rx: -xx.x"
            if ($rx === null && preg_match('/\brx\b\s*[:=]\s*(-?\d+\.?\d*)\s*dBm/i', $line, $m)) {
                $rx = (float) $m[1];

                continue;
            }

            // "tx := xx.x" or "tx: xx.x"
            if ($tx === null && preg_match('/\btx\b\s*[:=]\s*(-?\d+\.?\d*)\s*dBm/i', $line, $m)) {
                $tx = (float) $m[1];

                continue;
            }

            // generic rx anywhere
            if ($rx === null && preg_match('/\brx\b\s*(?:optical\s*power)?\s*[:=\-]?\s*(-?\d+\.?\d*)/i', $original, $m)) {
                $val = (float) $m[1];
                if ($val < 0) {
                    $rx = $val;

                    continue;
                }
            }

            // generic tx anywhere
            if ($tx === null && preg_match('/\btx\b\s*(?:optical\s*power)?\s*[:=\-]?\s*(-?\d+\.?\d*)/i', $original, $m)) {
                $val = (float) $m[1];
                if ($val >= 0) {
                    $tx = $val;

                    continue;
                }
            }
        }

        return ['rx_power' => $rx, 'tx_power' => $tx];
    }

    /**
     * Get ONT distances for C-DATA brand via "show ont basic-info".
     *
     * @return array<string, int|null> ontId => distance in meters
     */
    public function getOnuDistances(int $slot, int $port): array
    {
        if ($this->brand !== 'cdata') {
            return [];
        }

        try {
            $output = $this->execPrivileged("show ont basic-info {$slot}/{$port} all");
            $cleanOutput = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
            $cleanOutput = str_replace(["\r\n", "\r"], "\n", $cleanOutput);

            $this->logCli('GET_ONU_DISTANCES', $cleanOutput, [
                'command' => "show ont basic-info {$slot}/{$port} all",
                'slot' => $slot,
                'port' => $port,
            ]);

            return $this->parseBasicInfoDistanceOutput($cleanOutput);
        } catch (Exception $e) {
            Log::error("MikroTik proxy getOnuDistances cdata({$slot}/{$port}): {$e->getMessage()}");

            return [];
        }
    }

    private function parseBasicInfoDistanceOutput(string $output): array
    {
        $distances = [];
        $lines = explode("\n", $output);

        $headerCols = [];
        $isHeaderParsed = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^[-=]+$/', $line)) {
                continue;
            }

            if (! $isHeaderParsed && preg_match('/distance/i', $line)) {
                $headers = preg_split('/\s{2,}/', trim($line));
                if ($headers === false || count($headers) < 2) {
                    $headers = preg_split('/\s+/', trim($line));
                }
                foreach ($headers as $i => $name) {
                    $name = strtolower(trim($name));
                    if (preg_match('/ont.*id|id$/i', $name)) {
                        $headerCols['ont_id'] = $i;
                    } elseif (preg_match('/distance/i', $name)) {
                        $headerCols['distance'] = $i;
                    }
                }
                $isHeaderParsed = true;

                continue;
            }

            if ($headerCols !== [] && isset($headerCols['ont_id'], $headerCols['distance'])) {
                $cols = preg_split('/\s+/', trim($line));
                if ($cols !== false && count($cols) > max($headerCols['ont_id'], $headerCols['distance'])) {
                    $ontId = (int) $cols[$headerCols['ont_id']];
                    if ($ontId >= 1 && $ontId <= 128) {
                        $distRaw = str_replace(['km', 'KM', 'm', 'M'], '', $cols[$headerCols['distance']]);
                        $distVal = (int) $distRaw;
                        if ($distVal > 0 && $distVal < 100) {
                            $distVal = $distVal * 1000;
                        }
                        $distances[$ontId] = $distVal > 0 ? $distVal : null;

                        continue;
                    }
                }
            }

            if (preg_match('/^\s*(\d{1,2})\s+\S+.*?(\d{3,5})\s*(?:m|KM)?/i', $line, $m)) {
                $ontId = (int) $m[1];
                if ($ontId >= 1 && $ontId <= 128) {
                    $distVal = (int) $m[2];
                    if ($distVal > 0 && $distVal < 100) {
                        $distVal = $distVal * 1000;
                    }
                    $distances[$ontId] = ($distVal > 0 && $distVal <= 40000) ? $distVal : null;
                }
            }

            if (preg_match('/ONT\s+(\d{1,2})\b/i', $line, $idMatch)) {
                $ontId = (int) $idMatch[1];
                if ($ontId >= 1 && $ontId <= 128 && preg_match('/[Dd]istance\s*[:=]?\s*(\d+)', $line, $distMatch)) {
                    $distVal = (int) $distMatch[1];
                    if ($distVal > 0 && $distVal < 100) {
                        $distVal = $distVal * 1000;
                    }
                    $distances[$ontId] = ($distVal > 0 && $distVal <= 40000) ? $distVal : null;
                }
            }
        }

        return $distances;
    }

    private function logCli(string $action, string $output, array $context = []): void
    {
        try {
            $logDir = storage_path('logs');
            if (! is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $logFile = $logDir.'/olt.log';
            $timestamp = now()->format('Y-m-d H:i:s.u');

            $entry = array_merge([
                'timestamp' => $timestamp,
                'action' => $action,
                'connector' => 'mikrotik_proxy',
                'brand' => $this->brand,
            ], $context, [
                'raw_output' => $output,
            ]);

            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            Log::warning("MikroTik proxy CLI logging failed: {$e->getMessage()}");
        }
    }
}
