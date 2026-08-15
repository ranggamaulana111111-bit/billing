<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\MikrotikRouter;
use App\Models\NetworkMetric;
use App\Models\Olt;
use App\Models\Onu;

class DashboardController extends Controller
{
    public function index()
    {
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $routerOnline = $routers->where('status', 'online')->count();
        $routerOffline = $routers->where('status', '!=', 'online')->count();

        $olts = Olt::orderBy('name')->get();
        $oltOnline = $olts->where('connection_status', 'online')->count();
        $oltOffline = $olts->where('connection_status', 'offline')->count();
        $oltOther = max($olts->count() - $oltOnline - $oltOffline, 0);

        $onuTotal = Onu::fromOlt()->count();
        $onuOnline = Onu::fromOlt()->where('status', 'online')->count();
        $onuOffline = max($onuTotal - $onuOnline, 0);

        $customerPpp = Customer::where('type', 'ppp')->count();
        $customerHotspot = Customer::where('type', 'hotspot')->count();

        $incidentActive = Incident::active()->count();
        $incidentBreached = Incident::where('sla_status', 'breached')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();
        $recentIncidents = Incident::with('assignee')
            ->orderByDesc('detected_at')
            ->take(8)
            ->get();

        $pppOnline = $routers->sum(fn ($r) => (int) ($r->user_stats['pppoe_online'] ?? 0));
        $hotspotOnline = $routers->sum(fn ($r) => (int) ($r->user_stats['hotspot_online'] ?? 0));

        $metrics = collect();
        foreach ($routers as $router) {
            $m = NetworkMetric::forRouter($router->id)->latest('collected_at')->first();
            if (! $m) {
                continue;
            }

            $metrics->push((object) [
                'router' => $router->name,
                'cpu_load' => $m->cpu_load,
                'memory_usage_pct' => $m->memory_usage_pct,
                'bandwidth_download' => $m->bandwidth_download,
                'bandwidth_upload' => $m->bandwidth_upload,
                'latency_idle' => $m->latency_idle,
                'packet_loss' => $m->packet_loss,
                'collected_at' => $m->collected_at,
            ]);
        }

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
