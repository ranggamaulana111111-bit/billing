<?php

namespace App\Services;

use App\Models\MikrotikRouter;
use Exception;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class MikrotikSshService
{
    protected SSH2 $ssh;

    protected string $host;

    protected int $sshPort;

    public function __construct(MikrotikRouter $router)
    {
        $this->host = $router->host;
        $this->sshPort = $router->ssh_port ?: 22;
        $this->ssh = new SSH2($this->host, $this->sshPort, 30);

        if (! $this->ssh->login($router->username, $router->password)) {
            throw new Exception("SSH login failed to {$this->host}:{$this->sshPort}");
        }
    }

    public function testConnection(): array
    {
        try {
            $res = $this->exec('/system resource print');
            $pairs = $this->parseColonLines($res);
            $uptime = $pairs['uptime'] ?? 'unknown';

            return [
                'success' => true,
                'message' => "Terhubung via SSH ke {$this->host}:{$this->sshPort} (uptime: {$uptime})",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal: '.$e->getMessage(),
            ];
        }
    }

    public function getLatency(): ?float
    {
        $start = microtime(true);
        $this->exec('/system resource print');
        $elapsed = (microtime(true) - $start) * 1000;
        return round($elapsed, 1);
    }

    public function getSystemResource(): array
    {
        $out = $this->exec('/system resource print');
        $pairs = $this->parseColonLines($out);
        return [
            'uptime' => $pairs['uptime'] ?? null,
            'cpu-load' => $this->parsePercent($pairs['cpu-load'] ?? null),
            'free-memory' => $this->parseSizeInBytes($pairs['free-memory'] ?? null),
            'total-memory' => $this->parseSizeInBytes($pairs['total-memory'] ?? null),
            'free-hdd-space' => $this->parseSizeInBytes($pairs['free-hdd-space'] ?? null),
            'total-hdd-space' => $this->parseSizeInBytes($pairs['total-hdd-space'] ?? null),
            'version' => $pairs['version'] ?? null,
            'board-name' => $pairs['board-name'] ?? null,
        ];
    }

    public function getSystemIdentity(): array
    {
        $out = $this->exec('/system identity print');
        $pairs = $this->parseColonLines($out);
        return [
            'name' => $pairs['name'] ?? null,
        ];
    }

    public function getInterfaces(): array
    {
        $out = $this->exec('/interface print terse');
        return $this->parseTerseTable($out);
    }

    public function getInterfaceTraffic(string $interface): array
    {
        $out = $this->exec("/interface monitor-traffic {$interface} once");
        return $this->parseTersePairs($out);
    }

    public function getActiveHotspotSessions(): array
    {
        $out = $this->exec('/ip hotspot active print terse');
        return $this->parseTerseTable($out);
    }

    public function getPppActive(): array
    {
        $out = $this->exec('/ppp active print terse');
        return $this->parseTerseTable($out);
    }

    public function getHotspotUsers(): array
    {
        $out = $this->exec('/ip hotspot user print terse');
        return $this->parseTerseTable($out);
    }

    public function getSimpleQueues(): array
    {
        $out = $this->exec('/queue simple print terse');
        return $this->parseTerseTable($out);
    }

    public function getPppSecrets(): array
    {
        $out = $this->exec('/ppp secret print terse');
        return $this->parseTerseTable($out);
    }

    public function getHotspotServers(): array
    {
        $out = $this->exec('/ip hotspot print terse');
        return $this->parseTerseTable($out);
    }

    public function getHotspotProfiles(): array
    {
        $out = $this->exec('/ip hotspot user profile print terse');
        return $this->parseTerseTable($out);
    }

    public function getPppProfiles(): array
    {
        $out = $this->exec('/ppp profile print terse');
        return $this->parseTerseTable($out);
    }

    public function getSystemHealth(): array
    {
        try {
            $out = $this->exec('/system health print');
            return $this->parseTersePairs($out);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getLog(int $count = 50): array
    {
        $out = $this->exec("/log print terse .top={$count}");
        return $this->parseTerseTable($out);
    }

    // ── Helpers ──

    protected function exec(string $command): string
    {
        $out = $this->ssh->exec($command);
        if ($out === false) {
            throw new Exception("SSH command failed: {$command}");
        }
        return trim($out);
    }

    protected function parseTerseTable(string $output): array
    {
        $lines = explode("\n", trim($output));
        $rows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, 'Flags:')) {
                continue;
            }

            $row = $this->parseTerseLine($line);
            if (! empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function parseTerseLine(string $line): array
    {
        // Split respecting quoted values: "value with spaces"
        $parts = preg_split('/\s+/', $line);
        $row = [];
        $flags = '';

        foreach ($parts as $i => $part) {
            if ($i === 0) {
                // Index number
                $row['.id'] = '*'.$part;
                continue;
            }

            // Check if it's a flag (single uppercase letter like R, X, D, S)
            if (preg_match('/^[A-Z]$/', $part) && $i < 5) {
                $flags .= $part;
                continue;
            }

            // key=value pair
            if (str_contains($part, '=')) {
                $eqPos = strpos($part, '=');
                $key = substr($part, 0, $eqPos);
                $value = substr($part, $eqPos + 1);

                // Strip surrounding quotes
                $value = trim($value, '"');

                $row[$key] = $value;
            }
        }

        if ($flags) {
            $row['flags'] = $flags;
        }
        if (! empty($row)) {
            $row['disabled'] = str_contains($flags, 'X') ? 'true' : 'false';
            $row['running'] = str_contains($flags, 'R') ? 'true' : 'false';
        }

        return $row;
    }

    protected function parseTersePairs(string $output): array
    {
        $result = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, ':')) {
                $colonPos = strpos($line, ':');
                $key = trim(substr($line, 0, $colonPos));
                $value = trim(substr($line, $colonPos + 1));
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected function parsePercent(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        // Strip trailing %, return numeric value as string
        return preg_replace('/[^0-9.]/', '', $value);
    }

    protected function parseSizeInBytes(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^([0-9.]+)\s*(KiB|MiB|GiB|TiB|kB|MB|GB|TB)?$/i', $value, $m)) {
            $num = (float) $m[1];
            $unit = strtoupper($m[2] ?? '');

            return match ($unit) {
                'TIB', 'TB' => (int) ($num * 1099511627776),
                'GIB', 'GB' => (int) ($num * 1073741824),
                'MIB', 'MB' => (int) ($num * 1048576),
                'KIB', 'KB' => (int) ($num * 1024),
                default => (int) $num,
            };
        }

        return (int) $value;
    }

    protected function parseColonLines(string $output): array
    {
        // Parse MikroTik colon-delimited output:
        //   uptime: 2h2m58s
        //   version: 6.45.9 (long-term)
        //   name: "rb"
        $result = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));
            $value = trim($value, '"');
            $result[$key] = $value;
        }

        return $result;
    }

    protected function extractTerseValue(string $output, string $key): ?string
    {
        // Match key="value" or key=value
        if (preg_match('/'.preg_quote($key, '/').'="?([^"\n]*)"?/i', $output, $m)) {
            return $m[1];
        }
        return null;
    }
}
