<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\SmartQos\SmartQosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QosHealthController extends Controller
{
    public function index(Request $request)
    {
        $selectedRouterId = $request->integer('router_id');
        $data = $this->fetchHealthData($selectedRouterId);

        return view('qos.health', $data);
    }

    public function jsonHealth(Request $request)
    {
        $selectedRouterId = $request->integer('router_id');
        $data = $this->fetchHealthData($selectedRouterId);

        return response()->json($data);
    }

    public function syncAll(Request $request)
    {
        $routers = SmartQosService::getActivePppoeRouters();
        $totalProvisioned = 0;
        $totalSkippedNoQos = 0;
        $totalSkippedNoIp = 0;
        $totalFailed = 0;

        foreach ($routers as $router) {
            $result = SmartQosService::syncAllQueues($router);
            $totalProvisioned += $result['provisioned'];
            $totalSkippedNoQos += $result['skipped_no_qos'];
            $totalSkippedNoIp += $result['skipped_no_ip'];
            $totalFailed += $result['failed'];
        }

        ActivityLog::log('SmartQos Sync', "Sinkronisasi QoS: {$totalProvisioned} provisioned, {$totalSkippedNoIp} skip (no IP), {$totalSkippedNoQos} skip (no QoS config), {$totalFailed} gagal");

        $msg = "Sinkronisasi selesai. {$totalProvisioned} queue diproses.";
        if ($totalSkippedNoIp > 0) {
            $msg .= " {$totalSkippedNoIp} pelanggan offline/tidak ada IP.";
        }
        if ($totalFailed > 0) {
            $msg .= " {$totalFailed} gagal.";
        }

        $data = $this->fetchHealthData();
        $data['optimizeResult'] = $msg;

        return view('qos.health', $data);
    }

    public function optimizeNow()
    {
        $routers = SmartQosService::getActivePppoeRouters();
        $totalProvisioned = 0;
        $totalOptimized = 0;
        $warnings = [];

        foreach ($routers as $router) {
            $routerName = $router->display_identity ?? $router->name;

            try {
                $syncResult = SmartQosService::syncAllQueues($router);
                $totalProvisioned += $syncResult['provisioned'];
            } catch (\Throwable $e) {
                Log::warning("SmartQos: Sync failed on {$routerName}: ".$e->getMessage());
                $warnings[] = "{$routerName}: sync gagal — ".$e->getMessage();
            }

            try {
                $cpuResult = SmartQosService::optimizeCpuQueues($router);
                $totalOptimized += $cpuResult['optimized'];
            } catch (\Throwable $e) {
                Log::warning("SmartQos: CPU optimize failed on {$routerName}: ".$e->getMessage());
                $warnings[] = "{$routerName}: optimize gagal — ".$e->getMessage();
            }

            try {
                $stats = SmartQosService::getHealthStats($router);
                $latency = $stats['latency_ms'] ?? 0;
                $grade = $stats['grade'] ?? 'N/A';
                if ($latency > 100) {
                    $warnings[] = "{$routerName}: latency {$latency}ms (Grade {$grade})";
                }
            } catch (\Throwable $e) {
                Log::warning("SmartQos: Health check failed on {$routerName}: ".$e->getMessage());
                $warnings[] = "{$routerName}: health check gagal — ".$e->getMessage();
            }
        }

        ActivityLog::log('SmartQos Optimize', "Optimized: {$totalProvisioned} provisioned, {$totalOptimized} CPU adjusted");

        $msg = "Optimasi selesai. {$totalProvisioned} queue disinkron, {$totalOptimized} di-adjust ke CAKE.";
        if (! empty($warnings)) {
            $msg .= ' Warning: '.implode('; ', $warnings);
        }

        $data = $this->fetchHealthData();
        $data['optimizeResult'] = $msg;

        return view('qos.health', $data);
    }

    private function fetchHealthData(?int $selectedRouterId = null): array
    {
        $routers = SmartQosService::getActivePppoeRouters();
        $routerStats = [];

        Log::debug("SmartQos: fetchHealthData — found {$routers->count()} routers");

        foreach ($routers as $router) {
            if ($selectedRouterId && $router->id !== $selectedRouterId) {
                continue;
            }

            try {
                $stats = SmartQosService::getHealthStats($router);
                $routerStats[] = $stats;
            } catch (\Throwable $e) {
                Log::warning("SmartQos: Gagal health stats {$router->name}: ".get_class($e).': '.$e->getMessage());
                $routerStats[] = [
                    'router_id' => $router->id,
                    'router_name' => $router->display_identity ?? $router->name,
                    'latency_ms' => 0,
                    'grade' => 'N/A',
                    'cake_active' => false,
                    'cake_type' => null,
                    'queue_types' => [],
                    'summary' => [
                        'total_simple_queues' => 0,
                        'smartqos_queues' => 0,
                        'existing_queues' => 0,
                        'cake_queues' => 0,
                        'pfifo_queues' => 0,
                        'total_trees' => 0,
                        'cake_trees' => 0,
                        'pfifo_trees' => 0,
                        'ppp_active' => 0,
                        'ppp_total' => 0,
                    ],
                    'smartqos_queues' => [],
                    'existing_queues' => [],
                    'cake_queues' => [],
                    'queue_trees' => [],
                    'interfaces' => [],
                    'error' => get_class($e).': '.$e->getMessage(),
                ];
            }
        }

        $summaries = array_map(fn ($s) => $s['summary'] ?? [], $routerStats);
        $totalQueues = $summaries ? array_sum(array_column($summaries, 'total_simple_queues')) : 0;
        $totalSmartQos = $summaries ? array_sum(array_column($summaries, 'smartqos_queues')) : 0;
        $avgLatency = 0;
        $validLatencies = array_filter(array_column($routerStats, 'latency_ms'), fn ($l) => $l > 0);
        if ($validLatencies) {
            $avgLatency = round(array_sum($validLatencies) / count($validLatencies), 1);
        }
        $overallGrade = SmartQosService::bufferbloatGrade($avgLatency);

        return compact(
            'routers',
            'routerStats',
            'selectedRouterId',
            'totalQueues',
            'totalSmartQos',
            'avgLatency',
            'overallGrade'
        );
    }
}
