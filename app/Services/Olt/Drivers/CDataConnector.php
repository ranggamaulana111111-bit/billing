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
            $this->ssh->read($this->prompt, SSH2::READ_REGEX);
            $this->enterPrivilegedMode();

            return true;
        } catch (Exception $e) {
            Log::error("C-Data SSH connect failed: {$e->getMessage()}");

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
            $info = $this->sendCommand('show version');

            return [
                'success' => true,
                'message' => 'Terhubung ke C-Data OLT',
                'data' => [
                    'version' => $this->parseLine($info, 'Firmware version'),
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

    public function getOnuList(int $slot, int $port): array
    {
        try {
            $output = $this->sendCommand("show ont info slot {$slot} port {$port}");
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
            Log::error("C-Data getOnuList failed: {$e->getMessage()}");

            return [];
        }
    }

    public function getOnuDetail(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $output = $this->sendCommand("show ont info slot {$parts[0]} port {$parts[1]} ont {$parts[2]}");

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
            $lineProfileId = $data['line_profile_id'] ?? 1;
            $srvProfileId = $data['srv_profile_id'] ?? 1;

            $this->sendCommand("interface gpon {$slot}/{$port}");
            $this->sendCommand("ont add {$onuId} sn-auth {$sn} ont-lineprofile-id {$lineProfileId} ont-srvprofile-id {$srvProfileId}");
            $this->sendCommand("ont port native-vlan {$slot}/{$port} {$onuId} eth 1 vlan {$vlan}");

            return ['success' => true, 'message' => "ONU {$onuId} berhasil diprovision"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeOnu(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $this->sendCommand("interface gpon {$parts[0]}/{$parts[1]}");
            $this->sendCommand("no ont add {$parts[2]}");

            return ['success' => true, 'message' => "ONU {$onuId} berhasil dihapus"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function rebootOnu(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $this->sendCommand("interface gpon {$parts[0]}/{$parts[1]}");
            $this->sendCommand("ont reset {$parts[2]}");

            return ['success' => true, 'message' => "ONU {$onuId} berhasil direboot"];
        } catch (Exception $e) {
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

    public function getOpticalPower(string $onuId): array
    {
        try {
            $parts = explode('/', $onuId);
            $output = $this->sendCommand("show ont optical-info slot {$parts[0]} port {$parts[1]} ont {$parts[2]}");
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

    private function sendCommand(string $command): string
    {
        if (! $this->ssh) {
            throw new Exception('SSH not connected');
        }

        $this->ssh->write($command."\n");

        return $this->ssh->read($this->prompt, SSH2::READ_REGEX);
    }

    private function enterPrivilegedMode(): void
    {
        $this->ssh->write("enable\n");
        $this->ssh->read($this->prompt, SSH2::READ_REGEX);
        $this->ssh->write("config\n");
        $this->ssh->read($this->prompt, SSH2::READ_REGEX);
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
