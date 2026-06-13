<?php

namespace App\Services\Olt\Drivers;

use App\Services\Olt\Contracts\OltConnector;
use Exception;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class HuaweiConnector implements OltConnector
{
    private ?SSH2 $ssh = null;

    private string $host;

    private int $port;

    public function connect(string $host, int $port, string $username, string $password): bool
    {
        $this->host = $host;
        $this->port = $port;

        try {
            $this->ssh = new SSH2($host, $port, 10);
            if (! $this->ssh->login($username, $password)) {
                throw new Exception('SSH login failed');
            }
            $this->ssh->setTimeout(10);
            $this->enterEnableMode();

            return true;
        } catch (Exception $e) {
            Log::error("Huawei SSH connect failed: {$e->getMessage()}");

            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->ssh) {
            $this->ssh->disconnect();
            $this->ssh = null;
        }
    }

    public function testConnection(): array
    {
        try {
            $info = $this->execCommand('display version');
            $uptime = $this->execCommand('display system uptime');

            return [
                'success' => true,
                'message' => 'Terhubung ke Huawei OLT',
                'data' => [
                    'version' => $this->parseLine($info, 'Huawei'),
                    'uptime' => $this->parseLine($uptime, 'uptime'),
                ],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getSystemInfo(): array
    {
        try {
            $version = $this->execCommand('display version');
            $device = $this->execCommand('display device');
            $temperature = $this->execCommand('display device temperature');

            return [
                'version' => $this->parseLine($version, 'VRP'),
                'device' => $this->parseLine($device, 'Name'),
                'temperature' => $this->parseLine($temperature, 'Current'),
            ];
        } catch (Exception $e) {
            Log::error("Huawei getSystemInfo failed: {$e->getMessage()}");

            return [];
        }
    }

    public function getOnuList(int $slot, int $port): array
    {
        try {
            $output = $this->execCommand("display ont info {$slot} {$port}");
            $onus = [];

            foreach (explode("\n", $output) as $line) {
                if (preg_match('/^\s*(\d+)\s+(\S+)\s+(\S+)\s+(\S+)/', $line, $m)) {
                    $onus[] = [
                        'onu_id' => "{$slot}/{$port}/{$m[1]}",
                        'sn' => $m[2],
                        'status' => $m[4],
                    ];
                }
            }

            return $onus;
        } catch (Exception $e) {
            Log::error("Huawei getOnuList failed: {$e->getMessage()}");

            return [];
        }
    }

    public function getOnuDetail(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $cmd = "display ont info {$parts[0]} {$parts[1]} {$parts[2]}";
            $output = $this->execCommand($cmd);

            return [
                'raw' => $output,
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

            $cmds = [
                "interface gpon {$slot}/{$port}",
                "ont add {$onuId} {$sn}",
                "ont port native-vlan {$slot}/{$port} {$onuId} eth 1 vlan {$vlan}",
            ];

            foreach ($cmds as $cmd) {
                $this->execCommand($cmd);
            }

            return ['success' => true, 'message' => "ONU {$onuId} berhasil diprovision"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeOnu(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $this->execCommand("interface gpon {$parts[0]}/{$parts[1]}");
            $this->execCommand("ont delete {$parts[2]}");

            return ['success' => true, 'message' => "ONU {$onuId} berhasil dihapus"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function rebootOnu(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $this->execCommand("interface gpon {$parts[0]}/{$parts[1]}");
            $this->execCommand("ont reset {$parts[2]}");

            return ['success' => true, 'message' => "ONU {$onuId} berhasil direboot"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPortStatus(int $slot, int $port): array
    {
        try {
            $output = $this->execCommand("display port state gpon {$slot}/{$port}");

            return ['raw' => $output, 'slot' => $slot, 'port' => $port];
        } catch (Exception $e) {
            return [];
        }
    }

    public function getOpticalPower(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $output = $this->execCommand("display ont optical-info {$parts[0]} {$parts[1]} {$parts[2]}");
            $rx = null;
            $tx = null;

            foreach (explode("\n", $output) as $line) {
                if (preg_match('/Rx power\s*:\s*([-\d.]+)/i', $line, $m)) {
                    $rx = (float) $m[1];
                }
                if (preg_match('/Tx power\s*:\s*([-\d.]+)/i', $line, $m)) {
                    $tx = (float) $m[1];
                }
            }

            return [
                'onu_id' => $onuId,
                'rx_power' => $rx,
                'tx_power' => $tx,
            ];
        } catch (Exception $e) {
            return ['onu_id' => $onuId, 'rx_power' => null, 'tx_power' => null];
        }
    }

    private function execCommand(string $command): string
    {
        if (! $this->ssh) {
            throw new Exception('SSH not connected');
        }

        $output = $this->ssh->exec($command."\n");
        if ($output === false) {
            throw new Exception("Failed to execute command: {$command}");
        }

        return $output;
    }

    private function enterEnableMode(): void
    {
        $this->execCommand('system-view');
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
