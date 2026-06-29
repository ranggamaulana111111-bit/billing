<?php

namespace App\Services\Olt\Drivers;

use App\Services\Olt\Contracts\OltConnector;
use Exception;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class ZteConnector implements OltConnector
{
    private ?SSH2 $ssh = null;

    private string $host;

    private int $port;

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
            $this->enterPrivilegedMode();

            return true;
        } catch (Exception $e) {
            Log::error("ZTE SSH connect failed: {$e->getMessage()}");

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
            $info = $this->execCommand('show system information');
            $version = $this->execCommand('show version');

            return [
                'success' => true,
                'message' => 'Terhubung ke ZTE OLT',
                'data' => [
                    'version' => $this->parseLine($version, 'Version'),
                    'uptime' => $this->parseLine($info, 'uptime'),
                ],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getSystemInfo(): array
    {
        try {
            $info = $this->execCommand('show system information');

            return ['raw' => $info];
        } catch (Exception $e) {
            Log::error("ZTE getSystemInfo failed: {$e->getMessage()}");

            return [];
        }
    }

    public function getOnuList(int $slot, int $port): array
    {
        try {
            $output = $this->execCommand("show onu unquiet interface gpon-olt_{$slot}/{$port}");
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
            Log::error("ZTE getOnuList failed: {$e->getMessage()}");

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
            $output = $this->execCommand("show onu detail gpon-olt_{$slot}/{$port} onu {$idx}");

            return ['raw' => $output, 'onu_id' => $onuId];
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
            $onuType = $data['onu_type'] ?? 'ont';

            $cmds = [
                "interface gpon-olt_{$slot}/{$port}",
                "onu {$onuId} type {$onuType} sn {$sn}",
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
            $slot = $parts[0] ?? 0;
            $port = $parts[1] ?? 0;
            $idx = $parts[2] ?? 0;
            $this->execCommand("interface gpon-olt_{$slot}/{$port}");
            $this->execCommand("no onu {$idx}");

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
            $this->execCommand("interface gpon-olt_{$slot}/{$port}");
            $this->execCommand("onu reset {$idx}");

            return ['success' => true, 'message' => "ONU {$onuId} berhasil direboot"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPortStatus(int $slot, int $port): array
    {
        try {
            $output = $this->execCommand("show interface gpon-olt_{$slot}/{$port}");

            return ['raw' => $output, 'slot' => $slot, 'port' => $port];
        } catch (Exception $e) {
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
            $output = $this->execCommand("show onu optical-info {$slot} {$port} {$idx}");
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

    private function enterPrivilegedMode(): void
    {
        $this->execCommand('enable');
        $this->execCommand('configure terminal');
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
