<?php

namespace App\Services\Olt\Drivers;

use App\Services\Olt\Contracts\OltConnector;
use Exception;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class CDataConnector implements OltConnector
{
    private ?SSH2 $ssh = null;

    private string $host;

    private int $port;

    private string $prompt = '/[$#>]/';

    private bool $inConfigMode = false;

    private bool $inGponInterface = false;

    /** opticalCache[slot][port][ontId] = ['rx_power' => float, 'tx_power' => float, 'distance' => int|null] */
    private array $opticalCache = [];

    /** distanceCache[slot][port][ontId] = int (meters) */
    private array $distanceCache = [];

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

            $this->logCli('CONNECT', 'SSH connected and entered enable mode', [
                'host' => $host,
                'port' => $port,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("C-Data SSH connect failed: {$e->getMessage()}");
            $this->logCli('CONNECT_ERROR', $e->getMessage(), ['host' => $host, 'port' => $port]);

            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->ssh) {
            try {
                if ($this->inGponInterface) {
                    $this->exitGponInterface();
                }
                if ($this->inConfigMode) {
                    $this->exitConfigMode();
                }
            } catch (\Throwable $e) {
                // ignore cleanup errors
            }
            $this->logCli('DISCONNECT', 'SSH session closed');
            $this->ssh->disconnect();
            $this->ssh = null;
            $this->inConfigMode = false;
            $this->inGponInterface = false;
            $this->opticalCache = [];
            $this->distanceCache = [];
        }
    }

    public function testConnection(): array
    {
        try {
            $version = $this->sendCommand('show version');

            return [
                'success' => true,
                'message' => 'Terhubung ke C-Data OLT',
                'data' => [
                    'version' => $this->parseLine($version, 'Firmware version'),
                ],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getSystemInfo(): array
    {
        try {
            $version = $this->sendCommand('show version');
            $device = $this->sendCommand('show device');

            return [
                'version' => $this->parseLine($version, 'Firmware version'),
                'device' => $this->parseLine($device, 'Device type'),
            ];
        } catch (Exception $e) {
            Log::error("C-Data getSystemInfo failed: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Get ONU list using "show ont info all".
     *
     * Output format: F/S P ONT SN Control-flag Run-state
     * Example: 0/0 1 1 ABCD1234 active online
     */
    public function getOnuList(int $slot, int $port): array
    {
        try {
            $output = $this->sendCommand('show ont info all');
            $cleanOutput = $this->cleanOutput($output);

            $this->logCli('GET_ONU_LIST', $cleanOutput, [
                'command' => 'show ont info all',
                'slot' => $slot,
                'port' => $port,
            ]);

            $onus = [];
            $lines = explode("\n", $cleanOutput);

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                // Skip separator lines
                if (preg_match('/^[-=]+$/', $line)) {
                    continue;
                }

                // Skip header lines
                if (preg_match('/^\s*F\/S\s+P\b/i', $line)) {
                    continue;
                }
                if (preg_match('/^\s*ONT\s+ID\b/i', $line)) {
                    continue;
                }

                // Match: F/S P ONT SN Control-flag Run-state
                // Example: 0/0 1 1 ABCD1234 active online
                if (preg_match('/(\d+)\/(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)/', $line, $m)) {
                    $lineFrame = (int) $m[1];
                    $lineSlot = (int) $m[2];
                    $linePort = (int) $m[3];
                    $ontId = (int) $m[4];
                    $sn = $m[5];
                    $runState = $m[7];

                    // Filter: requested slot matches F/S slot, requested port matches P
                    if ($lineSlot == $slot && $linePort == $port) {
                        $onus[] = [
                            'onu_id' => "{$slot}/{$port}/{$ontId}",
                            'sn' => $sn,
                            'status' => $this->mapCDataStatus($runState),
                        ];
                    }

                    continue;
                }

                // Fallback: match simpler format "P ONT SN ..." (no F/S prefix)
                if (preg_match('/^\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/', $line, $m)) {
                    $linePort = (int) $m[1];
                    $ontId = (int) $m[2];
                    $sn = $m[3];
                    $runState = $m[6];

                    if ($linePort == $port) {
                        $onus[] = [
                            'onu_id' => "{$slot}/{$port}/{$ontId}",
                            'sn' => $sn,
                            'status' => $this->mapCDataStatus($runState),
                        ];
                    }
                }
            }

            if (empty($onus)) {
                Log::warning("C-Data getOnuList({$slot}/{$port}) — 0 ONU parsed. Raw:\n".substr($cleanOutput, 0, 1000));
            }

            return $onus;
        } catch (Exception $e) {
            Log::error("C-Data getOnuList failed: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Get ONU detail using "show ont info {F/S} {P} {ONT}".
     *
     * Example: show ont info 0/0 1 1
     */
    public function getOnuDetail(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 0;

            $output = $this->sendCommand("show ont info {$slot}/{$port} {$idx}");
            $cleanOutput = $this->cleanOutput($output);

            return [
                'raw' => $cleanOutput,
                'onu_id' => $onuId,
            ];
        } catch (Exception $e) {
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
            $lineProfileId = $data['line_profile_id'] ?? 1;
            $srvProfileId = $data['srv_profile_id'] ?? 1;

            $this->enterConfigMode();
            $this->enterGponInterface($slot, $port);
            $this->sendCommand("ont add {$onuId} sn-auth {$sn} ont-lineprofile-id {$lineProfileId} ont-srvprofile-id {$srvProfileId}");
            $this->sendCommand("ont port native-vlan {$slot}/{$port} {$onuId} eth 1 vlan {$vlan}");
            $this->exitGponInterface();
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
            $idx = $parts[2] ?? 0;

            $this->enterConfigMode();
            $this->enterGponInterface($slot, $port);
            $this->sendCommand("no ont add {$idx}");
            $this->exitGponInterface();
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
            $idx = $parts[2] ?? 0;

            $this->enterConfigMode();
            $this->enterGponInterface($slot, $port);
            $this->sendCommand("ont reset {$idx}");
            $this->exitGponInterface();
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
            $output = $this->sendCommand("show port state gpon {$slot}/{$port}");

            return ['raw' => $output, 'slot' => $slot, 'port' => $port];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get optical power via config-gpon bulk query.
     *
     * Enters config → interface gpon {slot}/0 → show ont optical-info {port} all
     * C-Data uses F/S pair for interface (slot/0), port is a command parameter.
     * Caches results per slot/port so subsequent ONT queries are instant.
     */
    public function getOpticalPower(string $onuId): array
    {
        $startTime = microtime(true);

        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 0;

            // Return from cache if available
            if (isset($this->opticalCache[$slot][$port][$idx])) {
                $cached = $this->opticalCache[$slot][$port][$idx];

                $this->logCli('GET_OPTICAL_POWER', '(cached)', [
                    'onu_id' => $onuId,
                    'rx_power' => $cached['rx_power'],
                    'tx_power' => $cached['tx_power'],
                    'elapsed_ms' => 0,
                ]);

                return [
                    'onu_id' => $onuId,
                    'rx_power' => $cached['rx_power'],
                    'tx_power' => $cached['tx_power'],
                ];
            }

            // If we already fetched bulk for this port, ONT not found
            if (isset($this->opticalCache[$slot][$port])) {
                $this->logCli('GET_OPTICAL_POWER', '(bulk cached, ONT not found)', [
                    'onu_id' => $onuId,
                    'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 1),
                ]);

                return ['onu_id' => $onuId, 'rx_power' => null, 'tx_power' => null];
            }

            // Fetch bulk optical data for this port
            $command = "show ont optical-info {$port} all";

            $this->enterConfigMode();
            $this->enterGponInterface($slot, $port);
            $output = $this->sendCommand($command);
            $this->exitGponInterface();
            $this->exitConfigMode();

            $cleanOutput = $this->cleanOutput($output);
            $this->parseBulkOpticalOutput($cleanOutput, $slot, $port);

            // Also fetch distances (separate command, config mode)
            $distances = $this->getOnuDistances($slot, $port);
            if ($distances !== [] && isset($this->opticalCache[$slot][$port])) {
                foreach ($this->opticalCache[$slot][$port] as $ontId => &$entry) {
                    $entry['distance'] = $distances[$ontId] ?? null;
                }
                unset($entry);
            }

            $elapsed = round((microtime(true) - $startTime) * 1000, 1);

            $cached = $this->opticalCache[$slot][$port][$idx] ?? null;
            $rxPower = $cached['rx_power'] ?? null;
            $txPower = $cached['tx_power'] ?? null;

            $this->logCli('GET_OPTICAL_POWER', $cleanOutput, [
                'command' => "config → gpon {$slot}/0 → {$command}",
                'onu_id' => $onuId,
                'slot' => $slot,
                'port' => $port,
                'ont_index' => $idx,
                'rx_power' => $rxPower,
                'tx_power' => $txPower,
                'elapsed_ms' => $elapsed,
                'parse_method' => $cached ? 'bulk_parse' : 'not_found',
            ]);

            if ($rxPower === null || $txPower === null) {
                Log::warning("C-Data optical parse incomplete for {$onuId}. ".
                    "RX={$rxPower}, TX={$txPower}. ".
                    'Raw (first 500): '.substr($cleanOutput, 0, 500));
            }

            return [
                'onu_id' => $onuId,
                'rx_power' => $rxPower,
                'tx_power' => $txPower,
            ];
        } catch (Exception $e) {
            $elapsed = round((microtime(true) - $startTime) * 1000, 1);
            Log::error("C-Data getOpticalPower({$onuId}) exception after {$elapsed}ms: {$e->getMessage()}");
            $this->logCli('GET_OPTICAL_POWER_ERROR', $e->getMessage(), [
                'onu_id' => $onuId,
                'elapsed_ms' => $elapsed,
            ]);

            $this->emergencyCleanup();

            return ['onu_id' => $onuId, 'rx_power' => null, 'tx_power' => null];
        }
    }

    /**
     * Get ONT distances via "show ont basic-info {F/S} {port} all".
     *
     * Runs from config mode (not interface mode). Returns distance in meters
     * for every ONT on the specified PON port. Cached per session per slot/port.
     *
     * @return array<string, int|null> ontId => distance in meters (or null)
     */
    public function getOnuDistances(int $slot, int $port): array
    {
        if (isset($this->distanceCache[$slot][$port])) {
            return $this->distanceCache[$slot][$port];
        }

        try {
            $this->ensureConfigMode();
            $output = $this->sendCommand("show ont basic-info {$slot}/{$port} all");
            $cleanOutput = $this->cleanOutput($output);

            $this->logCli('GET_ONU_DISTANCES', $cleanOutput, [
                'command' => "show ont basic-info {$slot}/{$port} all",
                'slot' => $slot,
                'port' => $port,
            ]);

            $this->distanceCache[$slot][$port] = $this->parseBasicInfoOutput($cleanOutput);

            return $this->distanceCache[$slot][$port];
        } catch (Exception $e) {
            Log::error("C-Data getOnuDistances({$slot}/{$port}) failed: {$e->getMessage()}");
            $this->logCli('GET_ONU_DISTANCES_ERROR', $e->getMessage(), [
                'slot' => $slot,
                'port' => $port,
            ]);

            $this->distanceCache[$slot][$port] = [];

            return [];
        }
    }

    /**
     * Parse "show ont basic-info" output to extract distance per ONT.
     *
     * The output is a tabular format with columns like:
     *   ONT-ID  SN  Model  Description  Distance(m)  Type  Status  Uptime
     * or key-value pairs per ONT block.
     *
     * We look for lines containing an integer ONT ID followed by a distance value.
     * Distance values are typically 0-20000 (meters) for GPON.
     */
    private function parseBasicInfoOutput(string $output): array
    {
        $distances = [];
        $lines = explode("\n", $output);

        $headerCols = [];
        $isHeaderParsed = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^[-=]+$/', $line)) {
                continue;
            }

            // Detect header row containing 'Distance' or 'DISTANCE'
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

            // Strategy 1: Use header column positions
            if ($headerCols !== [] && isset($headerCols['ont_id'], $headerCols['distance'])) {
                $cols = preg_split('/\s+/', trim($line));
                if ($cols !== false && count($cols) > max($headerCols['ont_id'], $headerCols['distance'])) {
                    $ontId = (int) $cols[$headerCols['ont_id']];
                    if ($ontId >= 1 && $ontId <= 128) {
                        $distRaw = str_replace(['km', 'KM', 'm', 'M'], '', $cols[$headerCols['distance']]);
                        $distVal = (int) $distRaw;
                        // If value looks like km (small number like 1-20), convert to meters
                        if ($distVal > 0 && $distVal < 100) {
                            $distVal = $distVal * 1000;
                        }
                        $distances[$ontId] = $distVal > 0 ? $distVal : null;

                        continue;
                    }
                }
            }

            // Strategy 2: Regex — "Distance" keyword near a numeric value
            // e.g. "1  ABCD1234  ...  1234  ..." or "ONT 1 Distance: 1234m"
            if (preg_match('/^\s*(\d{1,2})\s+\S+.*?(\d{3,5})\s*(?:m|KM)?/i', $line, $m)) {
                $ontId = (int) $m[1];
                if ($ontId >= 1 && $ontId <= 128) {
                    $distVal = (int) $m[2];
                    if ($distVal > 0 && $distVal < 100) {
                        $distVal = $distVal * 1000;
                    }
                    $distances[$ontId] = ($distVal > 0 && $distVal <= 40000) ? $distVal : null;

                    continue;
                }
            }

            // Strategy 3: Key-value "ONT <id> ... Distance: <val>"
            if (preg_match('/ONT\s+(\d{1,2})\b/i', $line, $idMatch)) {
                $ontId = (int) $idMatch[1];
                if ($ontId < 1 || $ontId > 128) {
                    continue;
                }
                if (preg_match('/[Dd]istance\s*[:=]?\s*(\d+)', $line, $distMatch)) {
                    $distVal = (int) $distMatch[1];
                    if ($distVal > 0 && $distVal < 100) {
                        $distVal = $distVal * 1000;
                    }
                    $distances[$ontId] = ($distVal > 0 && $distVal <= 40000) ? $distVal : null;
                }
            }
        }

        $count = count($distances);
        if ($count > 0) {
            Log::info("C-Data parsed {$count} ONT distances from basic-info");
        } else {
            Log::warning('C-Data parseBasicInfoOutput: 0 distances parsed. Raw (first 500): '.substr($output, 0, 500));
        }

        return $distances;
    }

    private function ensureConfigMode(): void
    {
        if (! $this->inConfigMode) {
            $this->enterConfigMode();
        }
    }

    // ── Command Helpers ──

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
        $this->ssh->write("enable\n");
        $this->ssh->read($this->prompt, SSH2::READ_REGEX);
        $this->inConfigMode = false;
        $this->inGponInterface = false;
    }

    private function enterConfigMode(): void
    {
        if ($this->inConfigMode) {
            return;
        }

        $this->ssh->write("config\n");
        $this->ssh->read($this->prompt, SSH2::READ_REGEX);
        $this->inConfigMode = true;
    }

    private function exitConfigMode(): void
    {
        if (! $this->inConfigMode) {
            return;
        }

        // Must exit GPON interface first if inside it
        if ($this->inGponInterface) {
            $this->exitGponInterface();
        }

        $this->ssh->write("exit\n");
        $this->ssh->read($this->prompt, SSH2::READ_REGEX);
        $this->inConfigMode = false;
    }

    private function enterGponInterface(int $slot, int $port): void
    {
        $this->sendCommand("interface gpon {$slot}/0");
        $this->inGponInterface = true;
    }

    private function exitGponInterface(): void
    {
        if (! $this->inGponInterface) {
            return;
        }

        $this->ssh->write("exit\n");
        $this->ssh->read($this->prompt, SSH2::READ_REGEX);
        $this->inGponInterface = false;
    }

    private function emergencyCleanup(): void
    {
        try {
            if ($this->inGponInterface) {
                $this->exitGponInterface();
            }
            if ($this->inConfigMode) {
                $this->exitConfigMode();
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    // ── Parsers ──

    /**
     * Parse bulk optical output from "show ont optical-info {port} all".
     *
     * Expected table format per ONT row (varies by firmware):
     *   ONT_ID SN Rx_Tx_Power... dBm values...
     *
     * Strategy: look for lines with an integer ONT ID followed by
     * numeric dBm values. First negative = Rx, first positive = Tx.
     */
    private function parseBulkOpticalOutput(string $output, int $slot, int $port): void
    {
        if (! isset($this->opticalCache[$slot][$port])) {
            $this->opticalCache[$slot][$port] = [];
        }

        $lines = explode("\n", $output);

        // Track column positions from header line
        $headerCols = [];
        $isHeaderParsed = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Skip separator lines
            if (preg_match('/^[-=]+$/', $line)) {
                continue;
            }

            // Try to detect header with column names
            if (! $isHeaderParsed && preg_match('/ONT/i', $line) && preg_match('/Rx|Power|Optical/i', $line)) {
                // Parse column positions from header
                $headerCols = $this->parseHeaderColumns($line);
                $isHeaderParsed = true;

                continue;
            }

            // Skip remaining header-like lines (no numeric data)
            if (preg_match('/F\/S/i', $line) && ! preg_match('/-?\d+\.?\d*/', $line)) {
                continue;
            }

            // Strategy 1: Use header column positions if available
            if ($headerCols !== []) {
                $parsed = $this->parseRowByColumns($line, $headerCols);
                if ($parsed !== null) {
                    $this->opticalCache[$slot][$port][$parsed['ont_id']] = [
                        'rx_power' => $parsed['rx_power'],
                        'tx_power' => $parsed['tx_power'],
                    ];

                    continue;
                }
            }

            // Strategy 2: Regex — match ONT ID followed by SN and dBm values
            // Format: ONT_ID SN Rx_dBm Tx_dBm ...
            if (preg_match('/^\s*(\d{1,2})\s+(\S+)\s+(-?\d+\.?\d+)\s+(-?\d+\.?\d+)/', $line, $m)) {
                $ontId = (int) $m[1];
                if ($ontId >= 1 && $ontId <= 128) {
                    $val1 = (float) $m[3];
                    $val2 = (float) $m[4];

                    $this->assignRxTx($slot, $port, $ontId, $val1, $val2);

                    continue;
                }
            }

            // Strategy 3: Regex — ONT ID followed by numeric values (no SN)
            if (preg_match('/^\s*(\d{1,2})\s+(-?\d+\.?\d+)\s+(-?\d+\.?\d+)/', $line, $m)) {
                $ontId = (int) $m[1];
                if ($ontId >= 1 && $ontId <= 128) {
                    $val1 = (float) $m[2];
                    $val2 = (float) $m[3];

                    $this->assignRxTx($slot, $port, $ontId, $val1, $val2);

                    continue;
                }
            }

            // Strategy 4: Key-value inline — "ONT 1 ... Rx Power: -19.47 dBm ... Tx Power: 1.97 dBm"
            if (preg_match('/ONT\s+(\d{1,2})\b/i', $line, $idMatch)) {
                $ontId = (int) $idMatch[1];
                if ($ontId < 1 || $ontId > 128) {
                    continue;
                }

                $rx = null;
                $tx = null;

                if (preg_match('/Rx\s*(?:Optical\s*)?[Pp]ower\s*[:=\-]?\s*(-?\d+\.?\d*)\s*dBm/i', $line, $rxM)) {
                    $rx = (float) $rxM[1];
                }
                if (preg_match('/Tx\s*(?:Optical\s*)?[Pp]ower\s*[:=\-]?\s*(-?\d+\.?\d*)\s*dBm/i', $line, $txM)) {
                    $tx = (float) $txM[1];
                }

                if ($rx !== null && $tx !== null) {
                    $this->opticalCache[$slot][$port][$ontId] = ['rx_power' => $rx, 'tx_power' => $tx];
                }
            }
        }

        $count = count($this->opticalCache[$slot][$port]);
        if ($count > 0) {
            Log::info("C-Data parsed {$count} ONT optical entries for gpon {$slot}/{$port}");
        } else {
            Log::warning("C-Data parseBulkOpticalOutput: 0 entries parsed for gpon {$slot}/{$port}. Raw (first 500): ".substr($output, 0, 500));
        }
    }

    private function parseHeaderColumns(string $headerLine): array
    {
        $cols = [];
        $headers = preg_split('/\s{2,}/', trim($headerLine));
        if ($headers === false || count($headers) < 2) {
            // Try single-space split and look for keywords
            $headers = preg_split('/\s+/', trim($headerLine));
        }

        foreach ($headers as $i => $name) {
            $name = strtolower(trim($name));
            if (str_contains($name, 'ont') && ! str_contains($name, 'olt') && ! str_contains($name, 'rx') && ! str_contains($name, 'tx')) {
                $cols['ont_id'] = $i;
            } elseif (str_contains($name, 'sn') && ! str_contains($name, 'son') && strlen($name) <= 4) {
                $cols['sn'] = $i;
            } elseif (preg_match('/olt.*rx|rx.*olt/i', $name)) {
                $cols['olt_rx'] = $i;
            } elseif (preg_match('/ont.*rx|rx.*ont|rx.*power/i', $name) && ! preg_match('/olt/i', $name)) {
                $cols['rx_power'] = $i;
            } elseif (preg_match('/ont.*tx|tx.*ont|tx.*power/i', $name) && ! preg_match('/olt/i', $name)) {
                $cols['tx_power'] = $i;
            } elseif ($name === 'rx') {
                $cols['rx_power'] = $i;
            } elseif ($name === 'tx') {
                $cols['tx_power'] = $i;
            }
        }

        return $cols;
    }

    private function parseRowByColumns(string $line, array $headerCols): ?array
    {
        $cols = preg_split('/\s+/', trim($line));
        if ($cols === false || count($cols) < 3) {
            return null;
        }

        $ontId = null;
        $rxPower = null;
        $txPower = null;

        if (isset($headerCols['ont_id']) && isset($cols[$headerCols['ont_id']])) {
            $val = (int) $cols[$headerCols['ont_id']];
            if ($val >= 1 && $val <= 128) {
                $ontId = $val;
            }
        }

        if ($ontId === null) {
            // Try first column as ONT ID
            $val = (int) $cols[0];
            if ($val >= 1 && $val <= 128) {
                $ontId = $val;
            }
        }

        if ($ontId === null) {
            return null;
        }

        if (isset($headerCols['rx_power']) && isset($cols[$headerCols['rx_power']])) {
            $rxPower = (float) str_replace('dBm', '', $cols[$headerCols['rx_power']]);
        }
        if (isset($headerCols['tx_power']) && isset($cols[$headerCols['tx_power']])) {
            $txPower = (float) str_replace('dBm', '', $cols[$headerCols['tx_power']]);
        }

        // Fallback: scan for negative (Rx) and positive (Tx) dBm values
        if ($rxPower === null || $txPower === null) {
            $negatives = [];
            $positives = [];
            for ($i = 1; $i < count($cols); $i++) {
                $val = $cols[$i];
                if (preg_match('/^-?\d+\.?\d*dBm$/i', $val)) {
                    $num = (float) str_replace('dBm', '', $val);
                    if ($num < 0) {
                        $negatives[] = $num;
                    } else {
                        $positives[] = $num;
                    }
                } elseif (preg_match('/^-?\d+\.?\d*$/', $val)) {
                    $num = (float) $val;
                    if ($num < -5 && $num > -40) {
                        $negatives[] = $num;
                    } elseif ($num >= 0 && $num < 10) {
                        $positives[] = $num;
                    }
                }
            }

            if ($rxPower === null && $negatives !== []) {
                $rxPower = $negatives[0];
            }
            if ($txPower === null && $positives !== []) {
                $txPower = $positives[0];
            }
        }

        if ($rxPower !== null && $txPower !== null) {
            return ['ont_id' => $ontId, 'rx_power' => $rxPower, 'tx_power' => $txPower];
        }

        return null;
    }

    private function assignRxTx(int $slot, int $port, int $ontId, float $val1, float $val2): void
    {
        if ($val1 < 0 && $val2 > 0) {
            $this->opticalCache[$slot][$port][$ontId] = ['rx_power' => $val1, 'tx_power' => $val2];
        } elseif ($val1 > 0 && $val2 < 0) {
            $this->opticalCache[$slot][$port][$ontId] = ['rx_power' => $val2, 'tx_power' => $val1];
        } elseif ($val1 < 0 && $val2 < 0) {
            // Both negative — first is likely Rx (higher = closer to OLT)
            $this->opticalCache[$slot][$port][$ontId] = ['rx_power' => $val1, 'tx_power' => abs($val2)];
        } else {
            $this->opticalCache[$slot][$port][$ontId] = ['rx_power' => $val1, 'tx_power' => $val2];
        }
    }

    private function mapCDataStatus(string $runState): string
    {
        $state = strtolower(trim($runState));

        return match (true) {
            str_contains($state, 'online') => 'online',
            str_contains($state, 'offline') => 'offline',
            str_contains($state, 'los') => 'LOS',
            str_contains($state, 'dying') => 'dying-gasp',
            default => $state,
        };
    }

    // ── Utilities ──

    private function cleanOutput(string $output): string
    {
        $output = preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
        $output = preg_replace('/\x1b\][^\x07\x1b]*(?:\x07|\x1b\\\\)/', '', $output);
        $output = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $output);
        $output = str_replace(["\r\n", "\r"], "\n", $output);
        $lines = explode("\n", $output);
        $cleaned = [];
        foreach ($lines as $line) {
            // Remove config-mode prompt lines
            if (preg_match('/^[\s]*\(config[^\)]*\)[\s#>]/i', $line)) {
                continue;
            }
            // Remove bare prompt lines
            if (preg_match('/^[\s]*\S+(?:\(config[^\)]*\))?[\s#>]\s*$/', $line)) {
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

            $logFile = $logDir.'/olt.log';
            $timestamp = now()->format('Y-m-d H:i:s.u');

            $entry = [
                'timestamp' => $timestamp,
                'action' => $action,
                'host' => $this->host ?? '-',
                'port' => $this->port ?? 0,
            ];

            if ($context) {
                foreach ($context as $key => $value) {
                    $entry[$key] = $value;
                }
            }

            $entry['raw_output'] = $output;

            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            Log::warning("C-Data CLI logging failed: {$e->getMessage()}");
        }
    }

    private function parseLine(string $output, string $keyword): string
    {
        foreach (explode("\n", $output) as $line) {
            if (str_contains($line, $keyword)) {
                return trim($line);
            }
        }

        return '';
    }
}
