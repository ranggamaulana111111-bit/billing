<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class MikrotikDashboardController extends Controller
{
    public function index(Request $request)
    {
        $routers = MikrotikRouter::query();

        if ($request->filled('search')) {
            $routers->search($request->input('search'));
        }

        if ($request->filled('status')) {
            $routers->byStatus($request->input('status'));
        }

        if ($request->filled('site')) {
            $routers->where('site', $request->input('site'));
        }

        if ($request->filled('model')) {
            $routers->where('model', $request->input('model'));
        }

        if ($request->filled('version')) {
            $routers->where('routeros_version', $request->input('version'));
        }

        if ($request->filled('tag')) {
            $routers->byTags([$request->input('tag')]);
        }

        $routers = $routers->orderBy('name')->get();

        $routerData = $this->fetchAllRouterData($routers);

        $stats = $this->computeStats($routerData);

        $filterOptions = $this->getFilterOptions();

        return view('noc.mikrotik.dashboard', compact('routerData', 'stats', 'filterOptions'));
    }

    public function detail(MikrotikRouter $mikrotikDevice)
    {
        $data = $this->fetchSingleRouterData($mikrotikDevice);

        return view('noc.mikrotik.detail', ['router' => $mikrotikDevice, 'data' => $data]);
    }

    public function liveApi(Request $request)
    {
        $query = MikrotikRouter::query();

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }
        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }
        if ($request->filled('site')) {
            $query->where('site', $request->input('site'));
        }
        if ($request->filled('model')) {
            $query->where('model', $request->input('model'));
        }
        if ($request->filled('version')) {
            $query->where('routeros_version', $request->input('version'));
        }
        if ($request->filled('tag')) {
            $query->byTags([$request->input('tag')]);
        }

        $routers = $query->orderBy('name')->get();
        $routerData = $this->fetchAllRouterData($routers);
        $stats = $this->computeStats($routerData);

        return response()->json(['routers' => $routerData, 'stats' => $stats]);
    }

    public function liveDetailApi(MikrotikRouter $mikrotikDevice)
    {
        $data = $this->fetchSingleRouterData($mikrotikDevice);

        return response()->json($data);
    }

    private function fetchAllRouterData($routers): array
    {
        $results = [];

        foreach ($routers as $router) {
            $results[] = $this->fetchSingleRouterData($router);
        }

        return $results;
    }

    private function fetchSingleRouterData(MikrotikRouter $router): array
    {
        $base = [
            'id' => $router->id,
            'name' => $router->name,
            'identity' => $router->identity,
            'host' => $router->host,
            'port' => $router->port,
            'site' => $router->site,
            'location' => $router->location,
            'model' => $router->model,
            'routeros_version' => $router->routeros_version,
            'architecture' => $router->architecture,
            'serial_number' => $router->serial_number,
            'is_active' => $router->is_active,
            'status' => $router->status,
            'last_seen' => $router->last_seen?->toISOString(),
            'type' => $router->type,
            'tags' => $router->tags ?? [],
            'online' => false,
            'cpu_load' => null,
            'free_memory' => null,
            'total_memory' => null,
            'memory_pct' => null,
            'free_hdd' => null,
            'total_hdd' => null,
            'hdd_pct' => null,
            'uptime' => null,
            'uptime_seconds' => 0,
            'board_name' => null,
            'version' => null,
            'interfaces_total' => 0,
            'interfaces_up' => 0,
            'interfaces_down' => 0,
            'total_rx' => 0,
            'total_tx' => 0,
            'ppp_active' => 0,
            'hotspot_active' => 0,
            'vpn_active' => 0,
            'latency' => null,
            'interfaces' => [],
            'error' => null,
        ];

        if (! $router->is_active) {
            return $base;
        }

        try {
            $service = new MikrotikService($router);

            $resource = $service->getSystemResource();
            if (! empty($resource)) {
                $base['online'] = true;
                $base['status'] = 'online';
                $base['cpu_load'] = (int) ($resource['cpu-load'] ?? 0);
                $base['free_memory'] = (int) ($resource['free-memory'] ?? 0);
                $base['total_memory'] = (int) ($resource['total-memory'] ?? 0);
                $base['memory_pct'] = $base['total_memory'] > 0
                    ? round(($base['total_memory'] - $base['free_memory']) / $base['total_memory'] * 100, 1)
                    : 0;
                $base['free_hdd'] = (int) ($resource['free-hdd-space'] ?? 0);
                $base['total_hdd'] = (int) ($resource['total-hdd-space'] ?? 0);
                $base['hdd_pct'] = $base['total_hdd'] > 0
                    ? round(($base['total_hdd'] - $base['free_hdd']) / $base['total_hdd'] * 100, 1)
                    : 0;
                $base['uptime'] = $resource['uptime'] ?? null;
                $base['uptime_seconds'] = $this->parseUptime($resource['uptime'] ?? '0s');
                $base['board_name'] = $resource['board-name'] ?? null;
                $base['version'] = $resource['version'] ?? null;

                if (empty($base['identity'])) {
                    $identity = $service->getSystemIdentity();
                    $base['identity'] = $identity['name'] ?? $router->name;
                }

                if (empty($base['model'])) {
                    $base['model'] = $resource['board-name'] ?? null;
                }
                if (empty($base['routeros_version'])) {
                    $base['routeros_version'] = $resource['version'] ?? null;
                }
            }

            $interfaces = $service->getInterfaces();
            $base['interfaces'] = $interfaces;
            $base['interfaces_total'] = count($interfaces);
            foreach ($interfaces as $iface) {
                $running = isset($iface['running']) && $iface['running'] === 'true';
                if ($running) {
                    $base['interfaces_up']++;
                    $base['total_rx'] += (int) ($iface['rx-byte'] ?? 0);
                    $base['total_tx'] += (int) ($iface['tx-byte'] ?? 0);
                } else {
                    $base['interfaces_down']++;
                }
            }

            try {
                $ppp = $service->getPppActive();
                $base['ppp_active'] = count($ppp);
            } catch (\Exception $e) {
                // PPP not available
            }

            try {
                $hotspot = $service->getActiveHotspotSessions();
                $base['hotspot_active'] = count($hotspot);
            } catch (\Exception $e) {
                // Hotspot not available
            }

            $base['latency'] = $service->getLatency();

            $router->update([
                'status' => 'online',
                'last_seen' => now(),
            ]);

        } catch (\Exception $e) {
            $base['error'] = $e->getMessage();

            if ($router->status !== 'offline') {
                $router->update(['status' => 'offline']);
            }
        }

        return $base;
    }

    private function computeStats(array $routerData): array
    {
        $online = collect($routerData)->where('online', true);
        $offline = collect($routerData)->where('online', false);

        $highestCpu = $online->sortByDesc('cpu_load')->first();
        $highestMemory = $online->sortByDesc('memory_pct')->first();
        $highestStorage = $online->sortByDesc('hdd_pct')->first();
        $highestTraffic = $online->sortByDesc(fn ($r) => $r['total_rx'] + $r['total_tx'])->first();

        $lastOffline = collect($routerData)
            ->where('online', false)
            ->where('last_seen', '!=', null)
            ->sortByDesc('last_seen')
            ->first();

        $lastOnline = $online
            ->sortByDesc('last_seen')
            ->first();

        $totalPpp = $online->sum('ppp_active');
        $totalHotspot = $online->sum('hotspot_active');
        $totalVpn = $online->sum('vpn_active');
        $totalInterfacesUp = $online->sum('interfaces_up');
        $totalInterfacesDown = $online->sum('interfaces_down');
        $totalRx = $online->sum('total_rx');
        $totalTx = $online->sum('total_tx');

        return [
            'total' => count($routerData),
            'online' => $online->count(),
            'offline' => $offline->count(),
            'highest_cpu' => $highestCpu,
            'highest_memory' => $highestMemory,
            'highest_storage' => $highestStorage,
            'highest_traffic' => $highestTraffic,
            'last_offline' => $lastOffline,
            'last_online' => $lastOnline,
            'total_ppp' => $totalPpp,
            'total_hotspot' => $totalHotspot,
            'total_vpn' => $totalVpn,
            'total_interfaces_up' => $totalInterfacesUp,
            'total_interfaces_down' => $totalInterfacesDown,
            'total_rx' => $totalRx,
            'total_tx' => $totalTx,
            'avg_cpu' => $online->count() > 0 ? round($online->avg('cpu_load'), 1) : 0,
            'avg_memory' => $online->count() > 0 ? round($online->avg('memory_pct'), 1) : 0,
        ];
    }

    private function getFilterOptions(): array
    {
        return [
            'sites' => MikrotikRouter::whereNotNull('site')->where('site', '!=', '')->distinct()->pluck('site')->sort()->values(),
            'tags' => MikrotikRouter::whereNotNull('tags')->get()->pluck('tags')->flatten()->unique()->sort()->values(),
            'models' => MikrotikRouter::whereNotNull('model')->where('model', '!=', '')->distinct()->pluck('model')->sort()->values(),
            'versions' => MikrotikRouter::whereNotNull('routeros_version')->where('routeros_version', '!=', '')->distinct()->pluck('routeros_version')->sort()->values(),
        ];
    }

    private function parseUptime(string $uptime): int
    {
        $seconds = 0;
        if (preg_match('/(\d+)w/', $uptime, $m)) {
            $seconds += $m[1] * 604800;
        }
        if (preg_match('/(\d+)d/', $uptime, $m)) {
            $seconds += $m[1] * 86400;
        }
        if (preg_match('/(\d+)h/', $uptime, $m)) {
            $seconds += $m[1] * 3600;
        }
        if (preg_match('/(\d+)m/', $uptime, $m)) {
            $seconds += $m[1] * 60;
        }
        if (preg_match('/(\d+)s/', $uptime, $m)) {
            $seconds += $m[1];
        }

        return $seconds;
    }
}
