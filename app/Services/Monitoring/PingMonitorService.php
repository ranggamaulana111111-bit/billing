<?php

namespace App\Services\Monitoring;

use App\Models\PingResult;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;

class PingMonitorService
{
    public function getDefaultTargets(): array
    {
        $setting = function (string $key, string $default) {
            try {
                $val = Setting::get($key);

                return $val ?: $default;
            } catch (\Throwable) {
                return $default;
            }
        };

        return [
            ['host' => '8.8.8.8', 'label' => 'Google DNS'],
            ['host' => '1.1.1.1', 'label' => 'Cloudflare DNS'],
            ['host' => $setting('mikrotik_host', '192.168.1.1'), 'label' => 'Gateway MikroTik'],
            ['host' => $setting('olt_ip_address', '172.10.10.2'), 'label' => 'OLT C-Data'],
        ];
    }

    public function ping(string $host, int $count = 10, int $timeout = 5): array
    {
        $escapedHost = escapeshellarg($host);
        $countArg = abs($count);
        $timeoutArg = abs($timeout);

        $os = strtolower(PHP_OS_FAMILY);
        if ($os === 'windows') {
            $cmd = "ping -n {$countArg} -w {$timeoutArg}000 {$escapedHost}";
        } else {
            $cmd = "ping -c {$countArg} -W {$timeoutArg} {$escapedHost} 2>&1";
        }

        $startTime = microtime(true);
        $output = shell_exec($cmd);
        $elapsed = round((microtime(true) - $startTime) * 1000);

        $result = [
            'host' => $host,
            'latency_ms' => null,
            'jitter_ms' => null,
            'packet_loss_percent' => 100.0,
            'response_time_ms' => $elapsed,
            'status' => 'offline',
            'raw' => $output ?? '',
        ];

        if ($output === null) {
            return $result;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*(?:packet\s*)?loss/i', $output, $lossMatch)) {
            $result['packet_loss_percent'] = (float) $lossMatch[1];
        }

        if (preg_match_all('/time[=<](\d+(?:\.\d+)?)\s*ms/i', $output, $times)) {
            $rtts = array_map('floatval', $times[1]);
            $result['latency_ms'] = round(array_sum($rtts) / count($rtts), 2);

            if (count($rtts) > 1) {
                $jitterSum = 0;
                for ($i = 1; $i < count($rtts); $i++) {
                    $jitterSum += abs($rtts[$i] - $rtts[$i - 1]);
                }
                $result['jitter_ms'] = round($jitterSum / (count($rtts) - 1), 2);
            } else {
                $result['jitter_ms'] = 0.0;
            }
        }

        if (preg_match('/(?:rtt|round-trip)\s*(?:min|avg|max)\s*[/=]\s*(\d+(?:\.\d+)?)\s*/(\d+(?:\.\d+)?)\s*/(\d+(?:\.\d+)?)/i', $output, $avgMatch)) {
            $result['latency_ms'] = (float) $avgMatch[2];
        }

        $result['status'] = match (true) {
            $result['packet_loss_percent'] == 0 && $result['latency_ms'] !== null && $result['latency_ms'] < 100 => 'online',
            $result['packet_loss_percent'] < 50 && $result['latency_ms'] !== null => 'warning',
            default => 'offline',
        };

        return $result;
    }

    public function pingAllTargets(?array $targets = null, ?int $onuId = null): array
    {
        $targets = $targets ?? $this->getDefaultTargets();
        $results = [];
        $tenantId = Auth::user()->tenant_id;

        foreach ($targets as $target) {
            $pingResult = $this->ping($target['host']);

            PingResult::create([
                'tenant_id' => $tenantId,
                'target_host' => $target['host'],
                'target_label' => $target['label'],
                'latency_ms' => $pingResult['latency_ms'],
                'jitter_ms' => $pingResult['jitter_ms'],
                'packet_loss_percent' => $pingResult['packet_loss_percent'],
                'response_time_ms' => $pingResult['response_time_ms'],
                'status' => $pingResult['status'],
                'onu_id' => $onuId,
            ]);

            $results[] = array_merge($pingResult, ['label' => $target['label']]);
        }

        return $results;
    }

    public function getHistory(string $targetHost, int $limit = 60): array
    {
        return PingResult::where('tenant_id', Auth::user()->tenant_id)
            ->where('target_host', $targetHost)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
