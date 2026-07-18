<?php

namespace App\Http\Controllers;

use App\Models\Onu;
use App\Models\OnuMonitoringHistory;
use App\Services\Monitoring\DiagnosisService;
use App\Services\Monitoring\FiberTopologyService;
use App\Services\Monitoring\HealthScoreService;
use App\Services\Monitoring\PingMonitorService;
use App\Services\Monitoring\SpeedTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnuHealthController extends Controller
{
    public function __construct(
        private readonly HealthScoreService $healthScoreService = new HealthScoreService,
        private readonly DiagnosisService $diagnosisService = new DiagnosisService,
        private readonly PingMonitorService $pingMonitorService = new PingMonitorService,
        private readonly SpeedTestService $speedTestService = new SpeedTestService,
        private readonly FiberTopologyService $fiberTopologyService = new FiberTopologyService,
    ) {}

    public function dashboard()
    {
        $onuList = Onu::with(['oltPort.olt', 'customer'])
            ->get()
            ->map(function ($onu) {
                $health = $this->healthScoreService->calculate([
                    'rx_power' => $onu->rx_power,
                    'tx_power' => $onu->tx_power,
                    'status' => $onu->status,
                ]);

                return [
                    'onu' => $onu,
                    'health' => $health,
                    'status_badge' => $this->healthScoreService->getStatusBadge($onu->status ?? 'unknown'),
                ];
            });

        $totalOnu = $onuList->count();
        $onlineCount = $onuList->where('onu.status', 'online')->count();
        $offlineCount = $onuList->where('onu.status', 'offline')->count();
        $warningCount = $onuList->whereIn('onu.status', ['los', 'dying-gasp'])->count();
        $avgScore = $totalOnu > 0 ? round($onuList->avg('health.score'), 1) : 0;

        $statusDistribution = $onuList->groupBy('onu.status')
            ->map(fn ($items) => $items->count())
            ->toArray();

        $scoreDistribution = [
            'excellent' => $onuList->where('health.grade', 'Excellent')->count(),
            'good' => $onuList->where('health.grade', 'Good')->count(),
            'warning' => $onuList->where('health.grade', 'Warning')->count(),
            'critical' => $onuList->where('health.grade', 'Critical')->count(),
        ];

        $sortedOnus = $onuList->sortBy('health.score')->values()->all();

        return view('olt.health.dashboard', compact(
            'sortedOnus',
            'totalOnu',
            'onlineCount',
            'offlineCount',
            'warningCount',
            'avgScore',
            'statusDistribution',
            'scoreDistribution',
        ));
    }

    public function detail(Onu $onu)
    {
        $onu->load(['oltPort.olt', 'customer', 'odpPort.odp']);

        $health = $this->healthScoreService->calculate([
            'rx_power' => $onu->rx_power,
            'tx_power' => $onu->tx_power,
            'status' => $onu->status,
        ]);

        $diagnosis = $this->diagnosisService->diagnose([
            'rx_power' => $onu->rx_power,
            'tx_power' => $onu->tx_power,
            'status' => $onu->status,
            'temperature' => null,
            'voltage' => null,
            'bias_current' => null,
            'los_detected' => $onu->status === 'los',
            'dying_gasp_detected' => $onu->status === 'dying-gasp',
            'auth_failed' => $onu->status === 'auth-failed',
        ]);

        $speedEstimate = $this->speedTestService->getEstimateFromOptical([
            'rx_power' => $onu->rx_power,
            'status' => $onu->status,
        ]);

        $history = OnuMonitoringHistory::where('onu_id', $onu->id)
            ->orderByDesc('created_at')
            ->limit(48)
            ->get();

        $statusBadge = $this->healthScoreService->getStatusBadge($onu->status ?? 'unknown');

        $distanceKm = null;
        if ($onu->distance) {
            $distanceKm = round($onu->distance / 1000, 2);
        }

        return view('olt.health.detail', compact(
            'onu',
            'health',
            'diagnosis',
            'speedEstimate',
            'history',
            'statusBadge',
            'distanceKm',
        ));
    }

    public function topology()
    {
        $topology = $this->fiberTopologyService->getTopologyData();

        return view('olt.health.topology', ['topology' => $topology]);
    }

    public function pingMonitor()
    {
        $targets = $this->pingMonitorService->getDefaultTargets();

        return view('olt.health.ping', ['targets' => $targets]);
    }

    public function pingExecute(Request $request): JsonResponse
    {
        $results = $this->pingMonitorService->pingAllTargets();

        return response()->json(['success' => true, 'data' => $results]);
    }

    public function speedTest(Request $request)
    {
        $onlineOnus = Onu::where('status', 'online')
            ->with('customer')
            ->get();

        $onuList = $onlineOnus->map(fn ($onu) => [
            'id' => $onu->id,
            'onu_id' => $onu->onu_id,
            'customer_name' => $onu->customer->name ?? 'Unlinked',
            'customer_code' => $onu->customer->customer_code ?? '',
            'rx_power' => $onu->rx_power,
            'tx_power' => $onu->tx_power,
            'status' => $onu->status,
        ])->values();

        if ($request->expectsJson()) {
            $onuId = $request->input('onu_id');
            $onu = $onuId ? Onu::with(['oltPort.olt', 'customer'])->find($onuId) : null;

            if (! $onu) {
                return response()->json(['success' => false, 'message' => 'ONU tidak ditemukan']);
            }

            $estimate = $this->speedTestService->getEstimateFromOptical([
                'rx_power' => $onu->rx_power,
                'status' => $onu->status,
            ]);

            return response()->json([
                'success' => true,
                'onu' => [
                    'id' => $onu->id,
                    'onu_id' => $onu->onu_id,
                    'customer_name' => $onu->customer->name ?? 'Unlinked',
                    'rx_power' => $onu->rx_power,
                    'tx_power' => $onu->tx_power,
                ],
                'estimate' => $estimate,
            ]);
        }

        return view('olt.health.speedtest', ['onus' => $onuList, 'onu' => null, 'estimate' => null]);
    }

    public function diagnosis(Onu $onu)
    {
        $onu->load(['oltPort.olt', 'customer']);

        $diagnoses = $this->diagnosisService->diagnose([
            'rx_power' => $onu->rx_power,
            'tx_power' => $onu->tx_power,
            'status' => $onu->status,
            'temperature' => null,
            'voltage' => null,
            'bias_current' => null,
            'los_detected' => $onu->status === 'los',
            'dying_gasp_detected' => $onu->status === 'dying-gasp',
            'auth_failed' => $onu->status === 'auth-failed',
        ]);

        $health = $this->healthScoreService->calculate([
            'rx_power' => $onu->rx_power,
            'tx_power' => $onu->tx_power,
            'status' => $onu->status,
        ]);

        $statusBadge = $this->healthScoreService->getStatusBadge($onu->status ?? 'unknown');

        return view('olt.health.diagnosis', compact('onu', 'diagnoses', 'health', 'statusBadge'));
    }

    public function liveDashboardData(): JsonResponse
    {
        $onuList = Onu::with(['oltPort.olt', 'customer'])
            ->get()
            ->map(function ($onu) {
                $health = $this->healthScoreService->calculate([
                    'rx_power' => $onu->rx_power,
                    'tx_power' => $onu->tx_power,
                    'status' => $onu->status,
                ]);

                return [
                    'id' => $onu->id,
                    'onu_id' => $onu->onu_id,
                    'customer_name' => $onu->customer->name ?? 'Unlinked',
                    'status' => $onu->status,
                    'rx_power' => $onu->rx_power,
                    'tx_power' => $onu->tx_power,
                    'health_score' => $health['score'],
                    'health_grade' => $health['grade'],
                ];
            });

        return response()->json(['success' => true, 'data' => $onuList]);
    }

    public function recordSnapshot(Onu $onu): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        OnuMonitoringHistory::create([
            'tenant_id' => $tenantId,
            'onu_id' => $onu->id,
            'rx_power' => $onu->rx_power,
            'tx_power' => $onu->tx_power,
            'status' => $onu->status,
            'los_detected' => $onu->status === 'los',
            'dying_gasp_detected' => $onu->status === 'dying-gasp',
            'uptime' => $onu->uptime,
        ]);

        return response()->json(['success' => true]);
    }
}
