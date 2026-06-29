<?php

namespace App\Services\Olt\Drivers;

use App\Services\Olt\Contracts\OltConnector;
use Exception;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class FiberHomeConnector implements OltConnector
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

            return true;
        } catch (Exception $e) {
            Log::error("FiberHome SSH connect failed: {$e->getMessage()}");

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
            $info = $this->execCommand('show system-info');

            return [
                'success' => true,
                'message' => 'Terhubung ke FiberHome OLT',
                'data' => ['raw' => $this->parseLine($info, 'System')],
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getSystemInfo(): array
    {
        try {
            $info = $this->execCommand('show system-info');

            return ['raw' => $info];
        } catch (Exception $e) {
            Log::error("FiberHome getSystemInfo failed: {$e->getMessage()}");

            return [];
        }
    }

    public function getOnuList(int $slot, int $port): array
    {
        try {
            $output = $this->execCommand("show ont list slot {$slot} port {$port}");
            $onus = [];

            foreach (explode("\n", $output) as $line) {
                if (preg_match('/^\s*(\d+)\s+(\S+)\s+(\S+)/', $line, $m)) {
                    $onus[] = [
                        'onu_id' => "{$slot}/{$port}/{$m[1]}",
                        'sn' => $m[2],
                        'status' => $m[3],
                    ];
                }
            }

            return $onus;
        } catch (Exception $e) {
            Log::error("FiberHome getOnuList failed: {$e->getMessage()}");

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
            $output = $this->execCommand("show ont info slot {$slot} port {$port} ont {$idx}");

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
            $sn = $data['serial_number'] ?? '';

            $this->execCommand("ont add slot {$slot} port {$port} sn {$sn}");

            return ['success' => true, 'message' => "ONU dengan SN {$sn} berhasil diprovision"];
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
            $this->execCommand("ont delete slot {$slot} port {$port} ont {$idx}");

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
            $this->execCommand("ont reset slot {$slot} port {$port} ont {$idx}");

            return ['success' => true, 'message' => "ONU {$onuId} berhasil direboot"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPortStatus(int $slot, int $port): array
    {
        try {
            $output = $this->execCommand("show port info slot {$slot} port {$port}");

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
            $output = $this->execCommand("show ont optic slot {$slot} port {$port} ont {$idx}");
            $rx = null;
            $tx = null;

            foreach (explode("\n", $output) as $line) {
                if (preg_match('/rx.*?([-\d.]+)/i', $line, $m)) {
                    $rx = (float) $m[1];
                }
                if (preg_match('/tx.*?([-\d.]+)/i', $line, $m)) {
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
