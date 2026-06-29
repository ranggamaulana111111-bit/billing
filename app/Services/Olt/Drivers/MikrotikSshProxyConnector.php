<?php

namespace App\Services\Olt\Drivers;

use App\Models\Setting;
use App\Services\Olt\Contracts\OltConnector;
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
            }

            return $this->connected;
        } catch (Exception $e) {
            Log::error("MikroTik proxy: gagal konek ke MikroTik: {$e->getMessage()}");

            return false;
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

        $url = "{$this->scheme}://{$this->mikrotikHost}:{$this->mikrotikPort}/rest/tool/ssh";

        try {
            $response = Http::withBasicAuth($this->mikrotikUser, $this->mikrotikPass)
                ->withoutVerifying()
                ->timeout(30)
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

            return $this->parseApiResponse($response->json(), $response->body());
        } catch (Exception $e) {
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
            'cdata' => $this->execOltCommand("enable\nconfig\n{$command}"),
            default => $this->execOltCommand($command),
        };
    }

    public function testConnection(): array
    {
        try {
            $info = match ($this->brand) {
                'huawei' => $this->execOltCommand('display version'),
                'zte' => $this->execOltCommand('show system information'),
                'fiberhome' => $this->execOltCommand('show system-info'),
                'cdata' => $this->execOltCommand('show version'),
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
            $output = match ($this->brand) {
                'huawei' => $this->execOltCommand("display ont info {$slot} {$port}"),
                'zte' => $this->execOltCommand("show onu unquiet interface gpon-olt_{$slot}/{$port}"),
                'fiberhome' => $this->execOltCommand("show ont list slot {$slot} port {$port}"),
                'cdata' => $this->execOltCommand("show ont info slot {$slot} port {$port}"),
                default => throw new Exception('Unsupported brand'),
            };

            $onus = [];

            foreach (explode("\n", $output) as $line) {
                $line = trim($line);
                if ($line === '' || preg_match('/^[-=]+$/', $line) || stripos($line, 'onu id') !== false || stripos($line, 'sn') !== false) {
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
                'cdata' => $this->execOltCommand("show ont info slot {$slot} port {$port} ont {$idx}"),
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
                    "interface gpon {$slot}/{$port}\nont add {$onuId} sn-auth {$sn} ont-lineprofile-id 1 ont-srvprofile-id 1\nont port native-vlan {$slot}/{$port} {$onuId} eth 1 vlan {$vlan}"
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
                    "interface gpon {$slot}/{$port}\nno ont add {$idx}"
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
                    "interface gpon {$slot}/{$port}\nont reset {$idx}"
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
        try {
            $parts = explode('/', $onuId);
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 0;

            $output = match ($this->brand) {
                'huawei' => $this->execOltCommand("display ont optical-info {$slot} {$port} {$idx}"),
                'zte' => $this->execOltCommand("show onu optical-info {$slot} {$port} {$idx}"),
                'fiberhome' => $this->execOltCommand("show ont optic slot {$slot} port {$port} ont {$idx}"),
                'cdata' => $this->execOltCommand("show ont optical-info slot {$slot} port {$port} ont {$idx}"),
                default => throw new Exception('Unsupported brand'),
            };

            $rx = null;
            $tx = null;

            foreach (explode("\n", $output) as $line) {
                if (preg_match('/[Rr]x\s*.*?([-\d.]+)/', $line, $m)) {
                    $rx = (float) $m[1];
                }
                if (preg_match('/[Tt]x\s*.*?([-\d.]+)/', $line, $m)) {
                    $tx = (float) $m[1];
                }
            }

            return [
                'onu_id' => $onuId,
                'rx_power' => $rx,
                'tx_power' => $tx,
            ];
        } catch (Exception $e) {
            Log::error("MikroTik proxy getOpticalPower({$onuId}): {$e->getMessage()}");

            return ['onu_id' => $onuId, 'rx_power' => null, 'tx_power' => null];
        }
    }
}
