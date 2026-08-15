<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Onu;
use App\Services\Mikrotik\Internet\InternetServiceManager;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $routerId = $request->input('router_id');
        $router = $routerId
            ? MikrotikRouter::withoutGlobalScopes()->find($routerId)
            : MikrotikRouter::withoutGlobalScopes()->where('is_active', true)->first();
        $routers = MikrotikRouter::withoutGlobalScopes()->where('is_active', true)->orderBy('name')->get();

        $pppActive = [];
        $hotspotActive = [];
        $pppLiveOk = false;

        $onuPpps = $this->pickBestPerCustomer(
            Onu::with('customer', 'oltPort.olt')
                ->whereHas('customer', fn ($q) => $q->where('type', 'ppp'))
                ->where('status', 'online')
                ->get()
        );

        $onuHotspots = $this->pickBestPerCustomer(
            Onu::with('customer')
                ->whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))
                ->where('status', 'online')
                ->orderBy('last_seen_at', 'desc')
                ->get()
        );

        $totalPppCustomers = Customer::where('type', 'ppp')->count();
        $totalHotspotCustomers = Customer::where('type', 'hotspot')->count();

        $onuStats = [
            'ppp' => [
                'total' => $totalPppCustomers,
                'online' => $onuPpps->count(),
                'offline' => max(0, $totalPppCustomers - $onuPpps->count()),
            ],
            'hotspot' => [
                'total' => $totalHotspotCustomers,
                'online' => $onuHotspots->count(),
                'offline' => max(0, $totalHotspotCustomers - $onuHotspots->count()),
            ],
        ];

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
        $pppLiveOk = false;

        try {
            $result = InternetServiceManager::list($router, 'ppp_active');
            $pppActive = $result['items'] ?? [];
            $pppLiveOk = ($result['success'] ?? false) === true;
        } catch (\Exception $e) {
        }

        try {
            $result = InternetServiceManager::list($router, 'hotspot_active');
            $hotspotActive = $result['items'] ?? [];
        } catch (\Exception $e) {
        }

        $totalRx = 0;
        $totalTx = 0;
        $rxRate = null;
        $txRate = null;
        $wanName = 'WAN-ISP';

        try {
            $service = RouterConnectionService::forRouter($router->id);
            if ($service) {
                $ifaces = $service->run(fn (RouterCommandService $cmd) => $cmd->getInterfaces());
                if ($ifaces->isSuccess()) {
                    $list = $ifaces->toArray() ?? [];
                    $wanName = $this->resolveWanFromList($list) ?? 'WAN-ISP';
                    $match = collect($list)->first(fn ($i) => ($i['name'] ?? '') === $wanName);
                    if ($match) {
                        $totalRx = (int) ($match['rx-byte'] ?? 0);
                        $totalTx = (int) ($match['tx-byte'] ?? 0);
                    }
                }
            }
        } catch (\Exception $e) {
        }

        $pppOnline = $this->resolveActivePppOnus($pppActive);
        if (! $pppLiveOk) {
            $pppOnline = $this->pickBestPerCustomer(
                Onu::with('customer', 'oltPort.olt')
                    ->whereHas('customer', fn ($q) => $q->where('type', 'ppp'))
                    ->where('status', 'online')
                    ->get()
            );
        }

        $pppOnline = $pppOnline->map(fn ($o) => [
            'customer' => $o->customer->name ?? 'Unlinked: '.($o->serial_number ?: $o->onu_id),
            'customer_code' => $o->customer->customer_code ?? '',
            'serial' => $o->serial_number ?? '-',
            'olt' => $o->oltPort?->olt?->name ?? '-',
            'rx_power' => $o->rx_power,
            'last_seen' => $o->last_seen_at?->diffForHumans() ?? '-',
        ])->values();

        $hotspotOnline = $this->pickBestPerCustomer(
            Onu::with('customer', 'oltPort.olt')
                ->whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))
                ->where('status', 'online')
                ->orderBy('last_seen_at', 'desc')
                ->get()
        )
            ->map(fn ($o) => [
                'customer' => $o->customer->name ?? 'Unlinked: '.($o->serial_number ?: $o->onu_id),
                'customer_code' => $o->customer->customer_code ?? '',
                'serial' => $o->serial_number ?? '-',
                'olt' => $o->oltPort?->olt?->name ?? '-',
                'rx_power' => $o->rx_power,
                'last_seen' => $o->last_seen_at?->diffForHumans() ?? '-',
            ])
            ->values();

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
     * ONU PPP yang sedang aktif: cocokkan sesi PPP aktif dari MikroTik
     * (via pppoe_username pelanggan atau MAC caller-id ONU) lalu pilih
     * satu ONU terbaik per pelanggan agar redaman (rx_power) terbaca.
     */
    private function resolveActivePppOnus(array $pppActive): Collection
    {
        $sessions = collect($pppActive);
        $usernames = $sessions->pluck('name')->filter()->map(fn ($n) => mb_strtolower(trim((string) $n)))->values();
        $macs = $sessions->pluck('caller-id')->filter()->map(fn ($m) => $this->normMac($m))->values();

        if ($usernames->isEmpty() && $macs->isEmpty()) {
            return collect();
        }

        $onus = collect();

        if ($usernames->isNotEmpty()) {
            $customerIds = Customer::where('type', 'ppp')
                ->get(['id', 'pppoe_username'])
                ->filter(fn ($c) => filled($c->pppoe_username)
                    && $usernames->contains(mb_strtolower(trim($c->pppoe_username))))
                ->pluck('id');

            if ($customerIds->isNotEmpty()) {
                $onus = Onu::with('customer', 'oltPort.olt')
                    ->whereIn('customer_id', $customerIds)
                    ->get();
            }
        }

        if ($macs->isNotEmpty()) {
            $macOnus = Onu::with('customer', 'oltPort.olt')
                ->whereNotNull('caller_id')
                ->where('caller_id', '!=', 'PPPoE-Hotspot')
                ->get()
                ->filter(fn ($o) => filled($o->caller_id) && $macs->contains($this->normMac($o->caller_id)));

            $onus = $onus->merge($macOnus);
        }

        return $this->pickBestPerCustomer($onus);
    }

    /**
     * Normalisasi MAC ke huruf kecil tanpa pemisah.
     */
    private function normMac(?string $mac): string
    {
        return strtolower(preg_replace('/[^a-f0-9]/i', '', (string) $mac));
    }

    /**
     * Satu ONU terbaik per pelanggan: utamakan yang punya redaman (rx_power),
     * lalu yang punya serial, lalu yang terbaru dilihat.
     */
    private function pickBestPerCustomer(Collection $onus): Collection
    {
        return $onus->sortByDesc(fn ($o) => [
            $o->rx_power !== null ? 1 : 0,
            filled($o->serial_number) ? 1 : 0,
            $o->last_seen_at?->timestamp ?? 0,
        ])->unique(fn ($o) => $o->customer_id ?? 'onu_'.$o->id)->values();
    }

    /**
     * Cari nama interface WAN dari daftar interface yang sudah diambil.
     * Prioritas: "wan-isp" persis, lalu comment/name mengandung "wan"/"isp",
     * lalu tipe pppoe-out, lalu ether yang running.
     */
    private function resolveWanFromList(array $interfaces): ?string
    {
        $running = fn ($i) => ($i['running'] ?? '') === 'true' || ($i['running'] ?? '') === true;
        $isWan = fn ($i) => stripos($i['name'] ?? '', 'wan') !== false
            || stripos($i['name'] ?? '', 'isp') !== false
            || stripos($i['comment'] ?? '', 'wan') !== false
            || stripos($i['comment'] ?? '', 'isp') !== false;

        foreach ($interfaces as $i) {
            if (strcasecmp($i['name'] ?? '', 'WAN-ISP') === 0) {
                return $i['name'];
            }
        }
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
