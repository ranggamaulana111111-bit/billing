<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\MikrotikRouter;
use App\Models\NetworkMetric;
use App\Models\Olt;
use App\Models\Onu;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $tenantKey = (string) (Auth::user()?->tenant_id ?? 'global');

        $snapshot = Cache::remember('noc_dashboard_'.$tenantKey, 60, function () {
            $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
            $olts = Olt::orderBy('name')->get();

            $onu = Onu::fromOlt()
                ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as online', ['online'])
                ->first();

            $customerTypes = Customer::selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type');

            $metrics = collect();
            if ($routers->isNotEmpty()) {
                $baseline = NetworkMetric::query()
                    ->select('mikrotik_router_id', DB::raw('MAX(collected_at) as collected_at'))
                    ->whereIn('mikrotik_router_id', $routers->pluck('id')->all())
                    ->groupBy('mikrotik_router_id');

                $latestMetrics = NetworkMetric::query()
                    ->joinSub($baseline, 'latest', function ($join) {
                        $join->on('network_metrics.mikrotik_router_id', '=', 'latest.mikrotik_router_id')
                            ->on('network_metrics.collected_at', '=', 'latest.collected_at');
                    })
                    ->get();

                $routerById = $routers->keyBy('id');

                $metrics = $latestMetrics->map(fn ($m) => (object) [
                    'router' => ($routerById->get($m->mikrotik_router_id)?->name) ?? '#'.$m->mikrotik_router_id,
                    'cpu_load' => $m->cpu_load,
                    'memory_usage_pct' => $m->memory_usage_pct,
                    'bandwidth_download' => $m->bandwidth_download,
                    'bandwidth_upload' => $m->bandwidth_upload,
                    'latency_idle' => $m->latency_idle,
                    'packet_loss' => $m->packet_loss,
                    'collected_at' => $m->collected_at,
                ]);
            }

            return [
                'routers' => $routers,
                'olts' => $olts,
                'onu' => $onu,
                'customerTypes' => $customerTypes,
                'metrics' => $metrics,
            ];
        });

        $routers = $snapshot['routers'];
        $olts = $snapshot['olts'];
        $metrics = $snapshot['metrics'];

        $routerOnline = $routers->where('status', 'online')->count();
        $routerOffline = $routers->where('status', '!=', 'online')->count();

        $oltOnline = $olts->where('connection_status', 'online')->count();
        $oltOffline = $olts->where('connection_status', 'offline')->count();
        $oltOther = max($olts->count() - $oltOnline - $oltOffline, 0);

        $onuTotal = (int) ($snapshot['onu']->total ?? 0);
        $onuOnline = (int) ($snapshot['onu']->online ?? 0);
        $onuOffline = max($onuTotal - $onuOnline, 0);

        $customerPpp = (int) ($snapshot['customerTypes']['ppp'] ?? 0);
        $customerHotspot = (int) ($snapshot['customerTypes']['hotspot'] ?? 0);

        $incidentStats = Incident::query()
            ->whereIn('status', ['open', 'investigating'])
            ->selectRaw('count(*) as active, sum(case when sla_status = ? then 1 else 0 end) as breached', ['breached'])
            ->first();
        $incidentActive = (int) ($incidentStats->active ?? 0);
        $incidentBreached = (int) ($incidentStats->breached ?? 0);
        $recentIncidents = Incident::with('assignee')
            ->orderByDesc('detected_at')
            ->take(8)
            ->get();

        $pppOnline = $routers->sum(fn ($r) => (int) ($r->user_stats['pppoe_online'] ?? 0));
        $hotspotOnline = $routers->sum(fn ($r) => (int) ($r->user_stats['hotspot_online'] ?? 0));

        $totalBwDl = $metrics->sum('bandwidth_download');
        $totalBwUl = $metrics->sum('bandwidth_upload');

        return view('noc.dashboard', compact(
            'routers', 'routerOnline', 'routerOffline',
            'olts', 'oltOnline', 'oltOffline', 'oltOther',
            'onuTotal', 'onuOnline', 'onuOffline',
            'customerPpp', 'customerHotspot',
            'incidentActive', 'incidentBreached', 'recentIncidents',
            'pppOnline', 'hotspotOnline',
            'metrics', 'totalBwDl', 'totalBwUl'
        ));
    }
}
