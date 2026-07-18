<?php

namespace App\Http\Controllers;

use App\Models\MikrotikRouter;
use App\Models\Onu;
use App\Services\Mikrotik\Internet\InternetServiceManager;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $routerId = $request->input('router_id');
        $router = $routerId
            ? MikrotikRouter::withoutGlobalScopes()->find($routerId)
            : MikrotikRouter::withoutGlobalScopes()->where('is_active', true)->first();
        $routers = MikrotikRouter::withoutGlobalScopes()->where('is_active', true)->orderBy('name')->get();

        $onuPpps = Onu::with('customer')
            ->whereHas('customer', fn ($q) => $q->where('type', 'ppp'))
            ->where('status', 'online')
            ->orderBy('last_seen_at', 'desc')
            ->get();

        $onuHotspots = Onu::with('customer')
            ->whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))
            ->where('status', 'online')
            ->orderBy('last_seen_at', 'desc')
            ->get();

        $onuStats = [
            'ppp' => [
                'total' => Onu::whereHas('customer', fn ($q) => $q->where('type', 'ppp'))->count(),
                'online' => $onuPpps->count(),
                'offline' => Onu::whereHas('customer', fn ($q) => $q->where('type', 'ppp'))->where('status', 'offline')->count(),
            ],
            'hotspot' => [
                'total' => Onu::whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))->count(),
                'online' => $onuHotspots->count(),
                'offline' => Onu::whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))->where('status', 'offline')->count(),
            ],
        ];

        $pppActive = [];
        $hotspotActive = [];
        if ($router) {
            try {
                $pppActive = InternetServiceManager::list($router, 'ppp_active')['items'] ?? [];
            } catch (\Exception $e) {
            }
            try {
                $hotspotActive = InternetServiceManager::list($router, 'hotspot_active')['items'] ?? [];
            } catch (\Exception $e) {
            }
        }

        return view('monitoring.index', compact(
            'router', 'routers', 'routerId',
            'onuPpps', 'onuHotspots', 'onuStats',
            'pppActive', 'hotspotActive'
        ));
    }

    public function liveData(Request $request): JsonResponse
    {
        $routerId = $request->input('router_id');
        $router = $routerId
            ? MikrotikRouter::withoutGlobalScopes()->find($routerId)
            : MikrotikRouter::withoutGlobalScopes()->where('is_active', true)->first();

        if (! $router) {
            return response()->json(['error' => 'No active router'], 400);
        }

        $pppActive = [];
        $hotspotActive = [];

        try {
            $result = InternetServiceManager::list($router, 'ppp_active');
            $pppActive = $result['items'] ?? [];
        } catch (\Exception $e) {
        }

        try {
            $result = InternetServiceManager::list($router, 'hotspot_active');
            $hotspotActive = $result['items'] ?? [];
        } catch (\Exception $e) {
        }

        $totalRx = 0;
        $totalTx = 0;
        $rxRate = 0;
        $txRate = 0;
        $wanName = 'WAN-ISP';

        try {
            $service = RouterConnectionService::forRouter($router->id);
            if ($service) {
                $ifaces = $service->run(fn (RouterCommandService $cmd) => $cmd->getInterfaces());
                if ($ifaces->isSuccess()) {
                    $list = $ifaces->toArray() ?? [];
                    $match = null;
                    foreach ($list as $i) {
                        if (($i['name'] ?? '') === $wanName) {
                            $match = $i;
                            break;
                        }
                    }
                    if (! $match) {
                        $wanName = $this->resolveWanInterface($service);
                        if ($wanName) {
                            foreach ($list as $i) {
                                if (($i['name'] ?? '') === $wanName) {
                                    $match = $i;
                                    break;
                                }
                            }
                        }
                    }

                    if ($match) {
                        $totalRx = (int) ($match['rx-byte'] ?? 0);
                        $totalTx = (int) ($match['tx-byte'] ?? 0);

                        // Dua snapshot untuk rate presisi (cocok angka MikroTik)
                        usleep(1000000);
                        $ifaces2 = $service->run(fn (RouterCommandService $cmd) => $cmd->getInterfaces());
                        if ($ifaces2->isSuccess()) {
                            $list2 = $ifaces2->toArray() ?? [];
                            $m2 = null;
                            foreach ($list2 as $i) {
                                if (($i['name'] ?? '') === $wanName) {
                                    $m2 = $i;
                                    break;
                                }
                            }
                            if ($m2) {
                                $rx2 = (int) ($m2['rx-byte'] ?? 0);
                                $tx2 = (int) ($m2['tx-byte'] ?? 0);
                                $drx = $rx2 - $totalRx;
                                $dtx = $tx2 - $totalTx;
                                if ($drx < 0) {
                                    $drx = 0;
                                }
                                if ($dtx < 0) {
                                    $dtx = 0;
                                }
                                $rxRate = $drx / 1.0;
                                $txRate = $dtx / 1.0;
                                $totalRx = $rx2;
                                $totalTx = $tx2;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }

        $pppOnline = Onu::with('customer', 'oltPort.olt')
            ->whereHas('customer', fn ($q) => $q->where('type', 'ppp'))
            ->where('status', 'online')
            ->orderBy('last_seen_at', 'desc')
            ->get()
            ->map(fn ($o) => [
                'customer' => $o->customer->name ?? '-',
                'customer_code' => $o->customer->customer_code ?? '-',
                'serial' => $o->serial_number ?? '-',
                'olt' => $o->oltPort?->olt?->name ?? '-',
                'rx_power' => $o->rx_power,
                'last_seen' => $o->last_seen_at?->diffForHumans() ?? '-',
            ]);

        $hotspotOnline = Onu::with('customer', 'oltPort.olt')
            ->whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))
            ->where('status', 'online')
            ->orderBy('last_seen_at', 'desc')
            ->get()
            ->map(fn ($o) => [
                'customer' => $o->customer->name ?? '-',
                'customer_code' => $o->customer->customer_code ?? '-',
                'serial' => $o->serial_number ?? '-',
                'olt' => $o->oltPort?->olt?->name ?? '-',
                'rx_power' => $o->rx_power,
                'last_seen' => $o->last_seen_at?->diffForHumans() ?? '-',
            ]);

        return response()->json([
            'total_rx' => $totalRx,
            'total_tx' => $totalTx,
            'rx_rate' => $rxRate,
            'tx_rate' => $txRate,
            'wan_name' => $wanName ?? null,
            'ppp_active_count' => count($pppActive),
            'hotspot_active_count' => count($hotspotActive),
            'ppp_active' => $pppActive,
            'hotspot_active' => $hotspotActive,
            'onu_ppp' => $pppOnline,
            'onu_ppp_count' => $pppOnline->count(),
            'onu_hotspot' => $hotspotOnline,
            'onu_hotspot_count' => $hotspotOnline->count(),
        ]);
    }

    /**
     * Cari nama interface WAN jika "wan-isp" tidak ada.
     * Prioritas: comment/name mengandung "wan"/"isp", lalu tipe pppoe-out, lalu ether yang running.
     */
    private function resolveWanInterface(RouterConnectionService $service): ?string
    {
        $result = $service->run(fn (RouterCommandService $cmd) => $cmd->getInterfaces());
        if (! $result->isSuccess()) {
            return null;
        }

        $interfaces = $result->toArray() ?? [];
        $running = fn ($i) => ($i['running'] ?? '') === 'true' || ($i['running'] ?? '') === true;
        $isWan = fn ($i) => stripos($i['name'] ?? '', 'wan') !== false
            || stripos($i['name'] ?? '', 'isp') !== false
            || stripos($i['comment'] ?? '', 'wan') !== false
            || stripos($i['comment'] ?? '', 'isp') !== false;

        foreach ($interfaces as $i) {
            if ($isWan($i)) {
                return $i['name'] ?? null;
            }
        }
        foreach ($interfaces as $i) {
            if (($i['type'] ?? '') === 'pppoe-out' && $running($i)) {
                return $i['name'] ?? null;
            }
        }
        foreach ($interfaces as $i) {
            if (($i['type'] ?? '') === 'ether' && $running($i)) {
                return $i['name'] ?? null;
            }
        }

        return null;
    }
}
