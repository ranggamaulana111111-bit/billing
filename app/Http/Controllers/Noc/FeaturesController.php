<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Device;
use App\Models\MikrotikRouter;
use App\Models\NetworkMetric;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\Onu;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use App\Modules\GenieACS\Contracts\IGenieACSClient;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Monitoring\PingMonitorService;
use App\Services\Olt\Factory\OltConnectorFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\Pool as HttpPool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class FeaturesController extends Controller
{
    public function map(): View
    {
        $user = auth()->user();
        $role = $user->role;
        $perms = (array) ($user->permissions ?? []);
        $hasPanelFtth = in_array($role, ['admin', 'superadmin', 'teknisi', 'noc']) || ! empty($perms['panel_ftth']);
        abort_unless($hasPanelFtth, 403);

        $routers = MikrotikRouter::where('is_active', true)->get();

        $onuOnline = Onu::fromOlt()->where('status', 'online')->count();
        $onuOffline = Onu::fromOlt()->where('status', 'offline')->count();

        $pppoeOnline = $routers->sum(fn ($r) => (int) ($r->user_stats['pppoe_online'] ?? 0));
        $pppoeOffline = $routers->sum(fn ($r) => (int) ($r->user_stats['pppoe_offline'] ?? 0));

        return view('noc.features.map', compact('pppoeOnline', 'pppoeOffline', 'onuOnline', 'onuOffline'));
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $like = "%{$q}%";
        $results = collect();

        Olt::where(fn ($b) => $b->where('name', 'like', $like)->orWhere('location', 'like', $like))
            ->limit(5)->get()
            ->each(function ($m) use (&$results) {
                $label = trim(($m->name ?? '').($m->location ? ' â€” '.$m->location : ''));
                $results->push([
                    'type' => 'OLT',
                    'label' => $label ?: (string) $m->ip_address,
                    'lat' => $m->latitude ? (float) $m->latitude : null,
                    'lon' => $m->longitude ? (float) $m->longitude : null,
                ]);
            });

        Odc::where('nama_odc', 'like', $like)->limit(5)->get()
            ->each(function ($m) use (&$results) {
                $results->push([
                    'type' => 'ODC',
                    'label' => (string) $m->nama_odc,
                    'lat' => $m->latitude,
                    'lon' => $m->longitude,
                ]);
            });

        Odp::where('nama_odp', 'like', $like)->limit(5)->get()
            ->each(function ($m) use (&$results) {
                $results->push([
                    'type' => 'ODP',
                    'label' => (string) $m->nama_odp,
                    'lat' => $m->latitude,
                    'lon' => $m->longitude,
                ]);
            });

        Customer::where(fn ($b) => $b->where('name', 'like', $like)
            ->orWhere('customer_code', 'like', $like)
            ->orWhere('location', 'like', $like))
            ->with('odp')->limit(5)->get()
            ->each(function ($m) use (&$results) {
                $lat = $m->odp ? $m->odp->latitude : null;
                $lon = $m->odp ? $m->odp->longitude : null;
                $label = trim($m->customer_code.' - '.$m->name.($m->location ? ' â€” '.$m->location : ''));
                $results->push([
                    'type' => 'Customer',
                    'label' => $label ?: (string) $m->customer_code,
                    'lat' => $lat,
                    'lon' => $lon,
                ]);
            });

        MikrotikRouter::where(fn ($b) => $b->where('name', 'like', $like)->orWhere('location', 'like', $like))
            ->limit(5)->get()
            ->each(function ($m) use (&$results) {
                $label = trim(($m->name ?? '').($m->location ? ' â€” '.$m->location : ''));
                $results->push([
                    'type' => 'Router',
                    'label' => $label ?: (string) $m->host,
                    'lat' => $m->latitude ? (float) $m->latitude : null,
                    'lon' => $m->longitude ? (float) $m->longitude : null,
                ]);
            });

        return response()->json($results->take(8)->values()->all());
    }

    /* â”€â”€ Sync Mikrotik (modal pada peta FTTH) â”€â”€ */

    public function mikrotikList(): JsonResponse
    {
        return response()->json(['routers' => $this->allRouters()]);
    }

    /** Cache koneksi SSH per-OLT untuk polling trafik PON (hindari reconnect tiap 2-3 detik) */
    private static array $ponTrafficConns = [];

    /**
     * Trafik live interface PON pada OLT pertama (byte kumulatif).
     * Rate dihitung sisi klien dari selisih antar poll.
     */
    public function oltPonTraffic(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pon' => ['nullable', 'integer', 'min:1', 'max:64'],
        ]);
        $pon = (int) ($data['pon'] ?? 1);

        $olt = Olt::orderBy('id')->first();
        if (! $olt) {
            return response()->json(['ok' => false, 'error' => 'Belum ada OLT tersimpan'], 404);
        }
        if (strtolower((string) $olt->brand) !== 'cdata') {
            return response()->json(['ok' => false, 'error' => 'Trafik PON hanya didukung OLT C-Data'], 422);
        }

        /* Riwayat sampel di Cache: respons instan, poll SSH hanya bila data tua */
        $histKey = "olt_pon_hist_{$olt->id}_{$pon}";
        $lockKey = "olt_pon_hist_lock_{$olt->id}_{$pon}";
        $hist = Cache::get($histKey, []);
        $lastT = $hist ? (int) end($hist)['t'] : 0;

        if ((time() - $lastT) >= 3 && ! Cache::has($lockKey)) {
            Cache::put($lockKey, 1, 30);

            try {
                $this->pollPonSample($olt, $pon, $histKey);
            } catch (\Throwable $e) {
                // kirim riwayat yang ada saja
            } finally {
                Cache::forget($lockKey);
            }

            $hist = Cache::get($histKey, []);
        }

        return response()->json([
            'ok' => true,
            'olt_name' => $olt->name,
            'pon' => $pon,
            'history' => array_values(array_slice($hist, -40)),
        ]);
    }

    /**
     * Satu poll live counter trafik PON via driver C-Data + simpan riwayat.
     * Koneksi driver dipertahankan di static pool antar-request (sama seperti sebelumnya).
     */
    private function pollPonSample(Olt $olt, int $pon, string $histKey): void
    {
        $key = (string) $olt->id;
        $driver = self::$ponTrafficConns[$key] ?? null;

        if (! $driver) {
            $driver = OltConnectorFactory::make(strtolower((string) $olt->brand), $olt);
            $connected = false;
            try {
                $connected = $driver->connect(
                    (string) $olt->ip_address,
                    (int) $olt->ssh_port,
                    (string) $olt->username,
                    (string) $olt->password,
                );
            } catch (\Throwable $e) {
                $connected = false;
            }
            if (! $connected) {
                return;
            }
            self::$ponTrafficConns[$key] = $driver;
        }

        try {
            $t = method_exists($driver, 'getPonTraffic') ? $driver->getPonTraffic($pon) : [];
        } catch (\Throwable $e) {
            $t = [];
        }

        if (empty($t) || ! isset($t['rx_bytes'], $t['tx_bytes'])) {
            /* Koneksi mungkin stale: buang agar poll berikutnya reconnect */
            try {
                $driver->disconnect();
            } catch (\Throwable $e) {
                // ignore
            }
            unset(self::$ponTrafficConns[$key]);

            return;
        }

        $hist = Cache::get($histKey, []);
        $hist[] = [
            't' => time(),
            'in' => (int) $t['rx_bytes'],
            'out' => (int) $t['tx_bytes'],
        ];
        Cache::put($histKey, array_values(array_slice($hist, -40)), 600);
    }

    /**
     * Trafik live interface WAN (uplink ISP) router Mikrotik aktif pertama.
     * Deteksi WAN: member interface list yang mengandung "wan",
     * fallback pola nama wan/isp/pppoe-out/ether1/sfp.
     *
     * Riwayat sampel counter disimpan di Cache sehingga respons SELALU instan:
     * grafik langsung penuh saat card dibuka / halaman di-refresh, dan sampel
     * baru hanya diambil live bila data terakhir sudah >2 detik.
     */
    public function mikrotikWanTraffic(): JsonResponse
    {
        $router = MikrotikRouter::where('is_active', true)->orderBy('id')->first();

        if (! $router) {
            return response()->json(['ok' => false, 'error' => 'Tidak ada router Mikrotik aktif'], 404);
        }

        $histKey = "mt_wan_hist_{$router->id}";
        $lockKey = "mt_wan_hist_lock_{$router->id}";
        $hist = Cache::get($histKey, []);
        $lastT = $hist ? (int) end($hist)['t'] : 0;

        /* Ambil satu sampel live bila data terakhir sudah tua & tidak sedang dipoll */
        if ((time() - $lastT) >= 2 && ! Cache::has($lockKey)) {
            Cache::put($lockKey, 1, 20);

            try {
                $this->pollWanSample($router, $histKey);
            } catch (\Throwable $e) {
                // sampel gagal — kirim riwayat yang ada saja
            } finally {
                Cache::forget($lockKey);
            }

            $hist = Cache::get($histKey, []);
        }

        return response()->json([
            'ok' => true,
            'router_id' => $router->id,
            'router_name' => $router->name,
            'wan' => Cache::get("mt_wan_if_{$router->id}"),
            'history' => array_values(array_slice($hist, -40)),
        ]);
    }

    /**
     * Trafik agregat TOWER HOTSPOT: total bandwidth interface MikroTik yang
     * melayani hotspot (bukan per-user). Dipakai card live trafik ONU hotspot
     * yang tidak memiliki sesi login aktif — tetap menampilkan trafik tower.
     */
    public function hotspotTowerTraffic(Request $request): JsonResponse
    {
        $router = MikrotikRouter::where('is_active', true)->orderBy('id')->first();
        if (! $router) {
            return response()->json(['ok' => false, 'error' => 'Tidak ada router aktif'], 404);
        }

        /* Trafik & user aktif hotspot bersifat AGREGAT per server hotspot
           (bukan per-ONU tower): bila beberapa tower berbagi 1 server hotspot
           (setup "share 1 server"), MikroTik tidak bisa membedakan tower mana
           client terhubung. Maka kita kembalikan nama server sebagai konteks
           agar UI menampilkan data sebagai "global", bukan per-tower. */
        $hsCfg = Cache::remember("hs_tower_if_{$router->id}", 300, function () use ($router) {
            $out = ['iface' => null, 'server' => null];
            try {
                $svc = new RouterCommandService($router);
                $hs = $svc->rawGet('/ip/hotspot');
                if ($hs->isSuccess() && is_array($hs->toArray())) {
                    foreach ($hs->toArray() as $s) {
                        $if = (string) ($s['interface'] ?? '');
                        $sv = (string) ($s['name'] ?? '');
                        if ($if !== '' && $out['iface'] === null) {
                            $out['iface'] = $if;
                            $out['server'] = $sv !== '' ? $sv : null;
                        }
                    }
                }
                if ($out['iface'] === null) {
                    $ifs = $svc->getInterfaces();
                    if ($ifs->isSuccess()) {
                        $names = collect($ifs->toArray())
                            ->map(fn ($i) => (string) ($i['name'] ?? ''))
                            ->filter(fn ($n) => $n !== '');
                        foreach (['hotspot', 'wlan', 'bridge', 'ether'] as $kw) {
                            $hit = $names->first(fn ($n) => stripos($n, $kw) !== false);
                            if ($hit) {
                                $out['iface'] = $hit;
                                break;
                            }
                        }
                    }
                }
            } catch (\Throwable) {
            }

            return $out;
        });

        $iface = $hsCfg['iface'];
        $hsServer = $hsCfg['server'];

        if (! $iface) {
            $iface = Cache::get("mt_wan_if_{$router->id}");
        }

        /* Jumlah client hotspot yang sedang aktif (login di /ip/hotspot/active).
           Trafik card tower adalah AGREGAT interface — maka "user aktif" yang
           ditampilkan adalah SELURUH sesi hotspot yang login di tower tersebut,
           bukan hanya sesi yang kebetulan terpetakan ke satu pelanggan (filter
           per-customer sebelumnya selalu menghasilkan 0 karena MAC sesi adalah
           perangkat end-user, bukan ONU tower). */
        $activeCacheKey = "hs_active_{$router->id}";

        /* Cache ringan hasil poll router (10s) agar card tidak memanggil
           MikroTik tiap tick — penyebab kemunculan user aktif sangat lambat. */
        $rows = Cache::remember($activeCacheKey, 10, function () use ($router) {
            try {
                $svc = new RouterCommandService($router);
                $hs = $svc->getHotspotActive();
                if ($hs->isSuccess() && is_array($hs->getData())) {
                    return $hs->getData();
                }
            } catch (\Throwable) {
            }

            return [];
        });

        $clients = count($rows);

        $histKey = "hs_tower_hist_{$router->id}";
        $lockKey = "hs_tower_lock_{$router->id}";
        $hist = Cache::get($histKey, []);
        $lastT = $hist ? (int) end($hist)['t'] : 0;

        if ($iface && (time() - $lastT) >= 2 && ! Cache::has($lockKey)) {
            Cache::put($lockKey, 1, 20);
            try {
                $svc = new RouterCommandService($router);
                $res = $svc->getInterfaceByName($iface);
                if ($res->isSuccess()) {
                    $row = $res->toArray();
                    $row = is_array($row) ? (isset($row[0]) && is_array($row[0]) ? $row[0] : $row) : [];
                    if (isset($row['rx-byte'], $row['tx-byte'])) {
                        $hist[] = ['t' => time(), 'in' => (float) $row['rx-byte'], 'out' => (float) $row['tx-byte']];
                        Cache::put($histKey, array_values(array_slice($hist, -40)), 600);
                    }
                }
            } catch (\Throwable) {
            } finally {
                Cache::forget($lockKey);
            }
            $hist = Cache::get($histKey, []);
        }

        $down = null;
        $up = null;
        if (count($hist) >= 2) {
            $a = $hist[count($hist) - 2];
            $b = $hist[count($hist) - 1];
            $dt = $b['t'] - $a['t'];
            if ($dt > 0) {
                $down = max(0, (($b['in'] - $a['in']) / $dt) * 8);
                $up = max(0, (($b['out'] - $a['out']) / $dt) * 8);
            }
        }

        /* Beberapa tower hotspot berbagi 1 server hotspot yang sama → data
           trafik & user aktif bersifat AGREGAT/GLOBAL (bukan per-tower). */
        $hotspotOnuCount = Cache::remember('hs_onu_count', 60, function () {
            return Onu::whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))->count();
        });

        return response()->json([
            'ok' => true,
            'online' => $iface !== null,
            'interface' => $iface,
            'server' => $hsServer,
            'aggregate' => true,
            'shared' => $hotspotOnuCount > 1,
            'clients' => $clients,
            'down' => $down,
            'up' => $up,
            'history' => array_values(array_slice($hist, -40)),
        ]);
    }

    /**
     * Satu poll live counter interface WAN + simpan sebagai titik riwayat.
     */
    private function pollWanSample(MikrotikRouter $router, string $histKey): void
    {
        $svc = new RouterCommandService($router);
        $cacheKey = "mt_wan_if_{$router->id}";

        $wan = Cache::get($cacheKey);

        if (! $wan) {
            $members = $svc->rawGet('/interface/list/member');
            if ($members->isSuccess() && is_array($members->toArray())) {
                foreach ($members->toArray() as $m) {
                    $list = mb_strtolower((string) ($m['list'] ?? ''));
                    $ifname = (string) ($m['interface'] ?? '');
                    if ($ifname !== '' && str_contains($list, 'wan')) {
                        $wan = $ifname;
                        break;
                    }
                }
            }
            if (! $wan) {
                $ifs = $svc->getInterfaces();
                if ($ifs->isSuccess()) {
                    $names = collect($ifs->toArray())
                        ->map(fn ($i) => (string) ($i['name'] ?? ''))
                        ->filter(fn ($n) => $n !== '');
                    foreach (['^wan', '^isp', '^pppoe-out', '^ether1', '^sfp'] as $pat) {
                        $hit = $names->first(fn ($n) => preg_match('/'.$pat.'/i', $n));
                        if ($hit) {
                            $wan = $hit;
                            break;
                        }
                    }
                }
            }
            if (! $wan) {
                return;
            }
            Cache::put($cacheKey, $wan, 300);
        }

        $res = $svc->getInterfaceByName($wan);
        if (! $res->isSuccess()) {
            Cache::forget($cacheKey);

            return;
        }

        $row = $res->toArray();
        $row = is_array($row) ? (isset($row[0]) && is_array($row[0]) ? $row[0] : $row) : [];

        if (! isset($row['rx-byte'], $row['tx-byte'])) {
            return;
        }

        $hist = Cache::get($histKey, []);
        $hist[] = [
            't' => time(),
            'in' => (float) $row['rx-byte'],
            'out' => (float) $row['tx-byte'],
        ];
        Cache::put($histKey, array_values(array_slice($hist, -40)), 600);
    }

    public function mikrotikSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ip' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $ip = trim($data['ip']);
        $port = (int) $data['port'];

        $router = MikrotikRouter::withTrashed()
            ->where('local_ip', $ip)->orWhere('host', $ip)
            ->first();

        if ($router) {
            if ($router->trashed()) {
                $router->restore();
            }
        } else {
            $router = new MikrotikRouter;
            $router->name = 'Mikrotik '.$ip;
            $router->host = $ip;
            $router->connection_mode = 'local_ip';
            $router->connection_type = 'rest_api';
            $router->is_active = true;
            $router->timeout = 10;
        }

        $router->local_ip = $ip;
        $router->local_port = $port;
        $router->username = $data['username'];
        $router->password = $data['password'];
        $router->save();

        return response()->json([
            'ok' => true,
            'router' => $this->routerPayload($router),
            'routers' => $this->allRouters(),
        ]);
    }

    public function mikrotikConnect(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);
        $router = MikrotikRouter::find($data['id']);

        if (! $router) {
            return response()->json(['ok' => false, 'error' => 'Router tidak ditemukan'], 404);
        }

        return response()->json($this->connectRouter($router));
    }

    public function mikrotikSyncAll(Request $request): JsonResponse
    {
        /* Auto-sync saat buka peta boleh pakai data <60s agar instan;
           tombol Sync manual mengirim force=1 untuk sinkronisasi penuh */
        $force = $request->boolean('force');
        $routers = MikrotikRouter::orderBy('id')->get();
        $ok = 0;

        foreach ($routers as $router) {
            $fresh = $router->status === 'online'
                && $router->user_stats_updated_at
                && $router->user_stats_updated_at->gt(now()->subSeconds(60));

            if (! $force && $fresh) {
                $ok++;

                continue;
            }

            if ($this->connectRouter($router)['ok']) {
                $ok++;
            }
        }

        $this->flushMapMarkersCache();

        return response()->json([
            'ok' => $ok,
            'failed' => $routers->count() - $ok,
            'total' => $routers->count(),
            'pppoe_online' => $routers->sum(fn ($r) => (int) ($r->user_stats['pppoe_online'] ?? 0)),
            'pppoe_offline' => $routers->sum(fn ($r) => (int) ($r->user_stats['pppoe_offline'] ?? 0)),
        ]);
    }

    /**
     * Ambil daftar PPPoE client aktif dari semua router Mikrotik (untuk card Queue).
     */
    public function mikrotikPppoe(): JsonResponse
    {
        $routers = MikrotikRouter::where('is_active', true)->orderBy('id')->get();
        $clients = [];
        $routerSummaries = [];

        $customers = Customer::whereNotNull('pppoe_username')
            ->with(['onus' => fn ($q) => $q->with('oltPort.olt'), 'odp'])
            ->get();
        $customerMap = $customers->mapWithKeys(fn ($c) => [mb_strtolower((string) $c->pppoe_username) => $c])->all();
        $customerNames = $customers->mapWithKeys(fn ($c) => [mb_strtolower((string) $c->pppoe_username) => (string) $c->name])->all();

        foreach ($routers as $router) {
            try {
                $cmd = new RouterCommandService($router);
                $active = $cmd->getPppActive();
                $secrets = $cmd->getPppSecrets();

                $activeOk = $active->isSuccess() && is_array($active->getData());
                $status = $activeOk ? 'online' : 'offline';

                $secretMap = [];
                if ($secrets->isSuccess() && is_array($secrets->getData())) {
                    foreach ($secrets->getData() as $s) {
                        if (isset($s['name'])) {
                            $secretMap[$s['name']] = $s;
                        }
                    }
                }

                $routerClients = [];
                if ($activeOk) {
                    foreach ($active->getData() as $s) {
                        $name = (string) ($s['name'] ?? '');
                        $sec = $secretMap[$name] ?? [];
                        $cust = $customerMap[mb_strtolower($name)] ?? null;
                        $onu = $cust?->onus?->first();
                        $routerClients[] = [
                            'router_id' => $router->id,
                            'router_name' => $router->name,
                            'name' => $name,
                            'customer_name' => $cust?->name ?? $customerNames[mb_strtolower($name)] ?? null,
                            'service' => $s['service'] ?? null,
                            'address' => $s['address'] ?? null,
                            'caller_id' => $s['caller-id'] ?? null,
                            'uptime' => $s['uptime'] ?? null,
                            'session_id' => $s['.id'] ?? null,
                            'profile' => $sec['profile'] ?? ($s['profile'] ?? null),
                            'comment' => $sec['comment'] ?? null,
                            'bytes_in' => isset($s['bytes-in']) ? (int) $s['bytes-in'] : null,
                            'bytes_out' => isset($s['bytes-out']) ? (int) $s['bytes-out'] : null,
                            'serial_number' => $onu?->serial_number ?? $cust?->serial_number ?? null,
                            'rx_power' => $onu?->rx_power,
                            'olt' => $onu?->oltPort?->olt?->name ?? null,
                            'odp' => $cust?->odp?->nama_odp ?? null,
                        ];
                    }
                }

                $routerSummaries[] = [
                    'id' => $router->id,
                    'name' => $router->name,
                    'ip' => $router->local_ip ?: $router->host,
                    'status' => $status,
                    'total' => count($routerClients),
                ];

                foreach ($routerClients as $client) {
                    $clients[] = $client;
                }
            } catch (\Throwable $e) {
                $routerSummaries[] = [
                    'id' => $router->id,
                    'name' => $router->name,
                    'ip' => $router->local_ip ?: $router->host,
                    'status' => 'offline',
                    'total' => 0,
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'routers' => $routerSummaries,
            'clients' => $clients,
            'total' => count($clients),
        ]);
    }

    /**
     * Cari satu sesi PPP aktif di MikroTik berdasarkan username (dengan/tanpa
     * realm). Dipakai oleh card live trafik ONU yang belum ter-link ke
     * pelanggan di DB: walau tidak ada Customer, sesi tetap ditampilkan LIVE.
     */
    public function pppoeSession(Request $request): JsonResponse
    {
        $user = trim((string) $request->query('user', ''));
        if ($user === '') {
            return response()->json(['ok' => true, 'found' => false]);
        }

        /* Lookup dari indeks sesi ber-cache — bukan poll REST per router */
        $idx = $this->activeSessionsIndex();
        $key = self::normPppoeUser($user);
        $s = ($key !== '' && isset($idx['ppp'][$key])) ? $idx['ppp'][$key] : null;

        if (! $s) {
            return response()->json(['ok' => true, 'found' => false]);
        }

        return response()->json([
            'ok' => true,
            'found' => true,
            'session' => [
                'name' => $s['name'],
                'address' => $s['ip'],
                'service' => 'pppoe',
                'uptime' => $s['uptime'],
                'bytes_in' => (int) $s['bytes_in'],
                'bytes_out' => (int) $s['bytes_out'],
            ],
            /* Riwayat counter sesi: chart terisi instan tanpa
               menunggu dua poll berturut-turut di browser */
            'history' => $this->appendSessionHistory((string) $s['name'], (int) $s['bytes_in'], (int) $s['bytes_out'], $idx['built_at'] ?? null),
        ]);
    }

    /**
     * Simpan satu sampel counter sesi PPP (per username, dinormalisasi) ke
     * cache riwayat — dipakai bersama pppoeSession() dan customerDetail()
     * agar chart trafik ONU pelanggan terisi instan dari history server.
     *
     * @return array<int, array{t: int, in: int, out: int}>
     */
    private function appendSessionHistory(string $sessionName, int $bytesIn, int $bytesOut, ?int $readAt = null): array
    {
        $readAt = $readAt ?? time();
        $key = 'ppp_sess_hist_'.md5(mb_strtolower(trim($sessionName)));
        $lockKey = $key.'_lock';
        $hist = Cache::get($key, []);
        $lastT = $hist ? (int) end($hist)['t'] : 0;

        /* Gunakan waktu pembacaan counter dari router (bukan waktu request)
           agar Δbytes dan Δt konsisten — index sesi di-cache 5s, sehingga
           sampel hanya bertambah tiap kali counter benar-benar berubah,
           dan laju yang dihitung menyamai rate di MikroTik (bukan 0/spike). */
        if ($readAt > $lastT && ! Cache::has($lockKey)) {
            Cache::put($lockKey, 1, 20);
            try {
                $hist[] = ['t' => $readAt, 'in' => $bytesIn, 'out' => $bytesOut];
                Cache::put($key, array_values(array_slice($hist, -40)), 600);
            } finally {
                Cache::forget($lockKey);
            }
            $hist = Cache::get($key, []);
        }

        return array_values(array_slice($hist, -40));
    }

    /**
     * Daftar ONU Hotspot (pelanggan bertype hotspot) untuk card "Daftar Hotspot".
     */
    public function hotspotList(): JsonResponse
    {
        $onus = Onu::with(['customer.odp', 'oltPort.olt'])
            ->whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))
            ->orderByDesc('last_seen_at')
            ->get();

        // Build IP map from MikroTik hotspot active users (cached 60s)
        $ipMap = Cache::remember('hotspot_active_ip_map', 60, function () {
            $map = [];
            $routers = MikrotikRouter::where('is_active', true)->get();
            foreach ($routers as $router) {
                try {
                    $cmd = new RouterCommandService($router);
                    $active = $cmd->getHotspotActive();
                    if ($active->isSuccess() && is_array($active->getData())) {
                        foreach ($active->getData() as $hs) {
                            $ip = $hs['address'] ?? null;
                            if (! $ip) {
                                continue;
                            }
                            // Match by user (username = customer_code)
                            $user = mb_strtolower((string) ($hs['user'] ?? ''));
                            if ($user) {
                                $map['u:'.$user] = $ip;
                            }
                            // Match by mac-address
                            $mac = str_replace(['-', ' '], ':', mb_strtolower((string) ($hs['mac-address'] ?? '')));
                            if ($mac) {
                                $map['m:'.$mac] = $ip;
                            }
                        }
                    }
                } catch (\Throwable) {
                }
            }

            return $map;
        });

        $clients = [];
        $devIpIdx = $this->deviceIpIndex();
        foreach ($onus as $onu) {
            $cust = $onu->customer;
            $clients[] = [
                'id' => $onu->id,
                'name' => $cust?->name ?? ($onu->serial_number ?? 'ONU-'.$onu->id),
                'customer_code' => $cust?->customer_code,
                'serial_number' => $onu->serial_number,
                'caller_id' => $onu->caller_id,
                'vendor' => $onu->vendor,
                'model' => $onu->model,
                'mac_address' => $onu->mac_address,
                'ip_address' => $this->hotspotIpFor($ipMap, $onu->mac_address, $cust)
                    ?? $this->storedIpFor($devIpIdx, $cust),
                'status' => $onu->status,
                'rx_power' => $onu->rx_power,
                'olt' => $onu->oltPort?->olt?->name,
                'odp' => $cust?->odp?->nama_odp,
                'last_seen' => $onu->last_seen_at ? $onu->last_seen_at->toDateTimeString() : null,
            ];
        }

        return response()->json(['ok' => true, 'clients' => $clients, 'total' => count($clients)]);
    }

    public function mikrotikDelete(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);
        $router = MikrotikRouter::find($data['id']);

        if (! $router) {
            return response()->json(['ok' => false, 'error' => 'Router tidak ditemukan'], 404);
        }

        $router->delete();

        return response()->json(['ok' => true, 'routers' => $this->allRouters()]);
    }

    /* â”€â”€ Sync OLT (modal pada peta FTTH) â”€â”€ */

    public function oltList(): JsonResponse
    {
        return response()->json(['olts' => $this->allOlts()]);
    }

    /**
     * Status live OLT untuk kartu peta: online (DB), IP, dan bandwidth agregat
     * terakhir dari kolektor jaringan sebagai proksi trafik PON 1.
     */
    public function oltLive(int $id): JsonResponse
    {
        $device = Device::find($id);
        if (! $device || strtolower((string) $device->type) !== 'olt') {
            return response()->json(['ok' => false, 'error' => 'OLT device not found'], 404);
        }

        $olt = $this->resolveOltModelForDevice($device);
        $online = $olt !== null && $olt->connection_status !== 'offline';
        $mikrotikUp = false;

        $router = MikrotikRouter::where('is_active', true)->orderBy('id')->first();
        $cmd = $router ? new RouterCommandService($router) : null;

        $bwDown = null;
        $bwUp = null;
        $collectedAt = null;

        /* Live: agregat rate semua interface PPPoE (≈ trafik PON 1).
           Counter dibaca ulang tiap ~3 detik (di-lock) dan laju dihitung dari
           selisih counter dengan timestamp bacaan sebenarnya — sehingga nilainya
           menyamai rate MikroTik (tidak "stuck" 10 detik lalu loncat). */
        $rateKey = 'olt_pon_rate';
        $prevKey = 'olt_pon_rate_prev';
        $lockKey = 'olt_pon_rate_lock';
        $rate = Cache::get($rateKey);
        $lastT = is_array($rate) ? (int) ($rate['t'] ?? 0) : 0;

        if ((time() - $lastT) >= 3 && ! Cache::has($lockKey)) {
            Cache::put($lockKey, 1, 20);
            try {
                if ($cmd) {
                    $res = $cmd->getInterfaces();
                    if ($res->isSuccess() && is_array($res->getData())) {
                        $mikrotikUp = true;
                        $inBytes = 0;
                        $outBytes = 0;
                        foreach ($res->getData() as $if) {
                            $name = mb_strtolower((string) ($if['name'] ?? ''));
                            if (! str_starts_with($name, '<pppoe-')) {
                                continue;
                            }
                            $inBytes += (int) ($if['rx-byte'] ?? 0);
                            $outBytes += (int) ($if['tx-byte'] ?? 0);
                        }

                        $now = microtime(true);
                        $prev = Cache::get($prevKey);
                        Cache::put($prevKey, ['t' => $now, 'in' => $inBytes, 'out' => $outBytes], 120);

                        if ($prev && ($now - $prev['t']) > 0) {
                            $dt = $now - $prev['t'];
                            $rate = [
                                't' => time(),
                                'rx' => max(0, (($inBytes - $prev['in']) * 8) / $dt),
                                'tx' => max(0, (($outBytes - $prev['out']) * 8) / $dt),
                            ];
                            Cache::put($rateKey, $rate, 60);
                        }
                    }
                }
            } catch (\Throwable) {
            } finally {
                Cache::forget($lockKey);
            }
            $rate = Cache::get($rateKey);
        }

        /* Status fisik OLT REAL: MikroTik melakukan ICMP ping ke IP management
           OLT. Reply => OLT fisik hidup (ONLINE), timeout => OLT fisik mati
           (OFFLINE). Ini status sebenarnya — menggantikan 'offline' dari poll
           SSH yang tak relevan karena IP management OLT tak reachable dari
           server aplikasi. */
        $oltIp = $olt?->ip_address ?? $device->ip_address;
        if ($cmd && $oltIp) {
            $pingStatus = $this->pingOltStatus($cmd, $oltIp, "olt_ping_live_{$device->id}");
            if ($pingStatus !== null) {
                $online = $pingStatus === 'online';
                if ($olt && $olt->connection_status !== $pingStatus) {
                    $olt->update(['connection_status' => $pingStatus, 'last_polled_at' => now()]);
                }
            }
        }

        /* Fallback: metrik kolektor bila live tidak tersedia */
        if (($rate === null || ($rate['rx'] ?? null) === null) && $online) {
            $metric = NetworkMetric::orderByDesc('collected_at')->first();
            if ($metric && $metric->collected_at && $metric->collected_at->gt(now()->subMinutes(15))) {
                $rate = [
                    'rx' => ((float) $metric->bandwidth_download) * 1e6,
                    'tx' => ((float) $metric->bandwidth_upload) * 1e6,
                ];
                $collectedAt = $metric->collected_at->toIso8601String();
            }
        }

        $bwDown = ($rate['tx'] ?? null);
        $bwUp = ($rate['rx'] ?? null);

        /* Riwayat sampel rate di server: chart ONU card terisi instan dari
           history, tak perlu menunggu beberapa tick di browser */
        $histKey = "olt_live_hist_{$device->id}";
        if ($bwDown !== null || $bwUp !== null) {
            $hist = Cache::get($histKey, []);
            $last = $hist ? end($hist) : null;
            $nowT = time();
            /* Rate di-cache 10 s — jangan duplikasi sampel identik */
            $dup = $last
                && abs((float) ($last['down'] ?? -1) - (float) $bwDown) < 1
                && abs((float) ($last['up'] ?? -1) - (float) $bwUp) < 1
                && ($nowT - (int) $last['t']) < 15;
            if (! $dup) {
                $hist[] = ['t' => $nowT, 'down' => (float) $bwDown, 'up' => (float) $bwUp];
                Cache::put($histKey, array_values(array_slice($hist, -40)), 600);
            }
        }

        return response()->json([
            'ok' => true,
            'online' => $online,
            'ip' => $olt?->ip_address ?? $device->ip_address,
            'name' => $olt?->name ?? $device->name,
            'bw_down' => $bwDown !== null ? (float) $bwDown : null,
            'bw_up' => $bwUp !== null ? (float) $bwUp : null,
            'collected_at' => $collectedAt,
            'history' => array_values(array_slice(Cache::get($histKey, []), -40)),
        ]);
    }

    /**
     * Cocokkan Device type=olt dengan model Olt:
     * 1. atribut induk "OLT — NAMA" -> Olt.name persis
     * 2. nama ternormalisasi (tanpa non-alfanumerik)
     */
    private function resolveOltModelForDevice(Device $device): ?Olt
    {
        $attrs = is_array($device->attributes) ? $device->attributes : [];
        $induk = trim((string) ($attrs['induk'] ?? ''));
        if ($induk !== '') {
            $part = str_contains($induk, '—') ? trim(explode('—', $induk, 2)[1]) : $induk;
            if ($part !== '') {
                $olt = Olt::where('name', $part)->first();
                if ($olt) {
                    return $olt;
                }
            }
        }

        $norm = static fn (string $s): string => preg_replace('/[^a-z0-9]/i', '', mb_strtolower($s));
        foreach (Olt::all() as $o) {
            if ($norm((string) $o->name) === $norm((string) $device->name)) {
                return $o;
            }
        }

        return null;
    }

    /**
     * Refresh status fisik OLT secara REAL untuk semua OLT (model Olt maupun
     * Device type=olt yang ditambah manual) dengan melakukan ICMP ping dari
     * MikroTik ke IP management OLT. Hasil ditulis ke Olt.connection_status /
     * Device.status agar konsisten di seluruh peta.
     */
    private function refreshAllOltRealStatus(array $allDevices): void
    {
        $router = MikrotikRouter::where('is_active', true)->orderBy('id')->first();
        if (! $router) {
            return;
        }

        $cmd = new RouterCommandService($router);

        /* OLT terdaftar (model Olt) */
        foreach (Olt::all() as $olt) {
            $ip = trim((string) ($olt->ip_address ?? ''));
            if ($ip === '') {
                continue;
            }
            $status = $this->pingOltStatus($cmd, $ip, "olt_real_{$olt->id}");
            if ($status !== null && $olt->connection_status !== $status) {
                $olt->update(['connection_status' => $status, 'last_polled_at' => now()]);
            }
        }

        /* OLT yang ditambah manual di peta (tanpa model Olt terpadan) */
        foreach ($allDevices as $d) {
            if (strtolower((string) $d->type) !== 'olt') {
                continue;
            }
            if ($this->resolveOltModelForDevice($d)) {
                continue;
            }
            $ip = trim((string) ($d->ip_address ?? ''));
            if ($ip === '') {
                continue;
            }
            $status = $this->pingOltStatus($cmd, $ip, "olt_dev_real_{$d->id}");
            if ($status !== null && (string) ($d->status ?? '') !== $status) {
                $d->update(['status' => $status]);
            }
        }
    }

    /**
     * Ping IP dari MikroTik dan kembalikan 'online'/'offline' (null bila tak bisa
     * ditentukan). Hasil di-cache 30s agar tak membebani MikroTik tiap request.
     */
    private function pingOltStatus(RouterCommandService $cmd, string $ip, string $cacheKey): ?string
    {
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $status = null;
        try {
            $ping = $cmd->pingHost($ip, 3, '1s');
            if ($ping->isSuccess()) {
                $status = ! empty($ping->getData()['reachable']) ? 'online' : 'offline';
            }
        } catch (\Throwable) {
            $status = null;
        }

        if ($status !== null) {
            Cache::put($cacheKey, $status, 30);
        }

        return $status;
    }

    public function oltSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ip' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'in:huawei,zte,fiberhome,cdata,vsol,hioso,hsgq,global'],
        ]);

        $olt = Olt::where('ip_address', $data['ip'])->first();

        if (! $olt) {
            $olt = new Olt;
            $olt->name = 'OLT '.$data['ip'];
            $olt->status = 'active';
        }

        $olt->ip_address = $data['ip'];
        $olt->ssh_port = (int) $data['port'];
        $olt->username = $data['username'];
        $olt->password = $data['password'];
        $olt->brand = $data['brand'];
        $olt->save();

        return response()->json([
            'ok' => true,
            'olt' => $this->oltPayload($olt),
            'olts' => $this->allOlts(),
        ]);
    }

    public function oltConnect(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);
        $olt = Olt::find($data['id']);

        if (! $olt) {
            return response()->json(['ok' => false, 'error' => 'OLT tidak ditemukan'], 404);
        }

        return response()->json($this->connectOlt($olt));
    }

    public function oltSyncAll(Request $request): JsonResponse
    {
        /* Sama seperti MikroTik: auto-sync pakai cache <60s, manual force=1 */
        $force = $request->boolean('force');
        $olts = Olt::orderBy('id')->get();
        $ok = 0;

        foreach ($olts as $olt) {
            $fresh = $olt->connection_status === 'online'
                && $olt->last_polled_at
                && $olt->last_polled_at->gt(now()->subSeconds(60));

            if (! $force && $fresh) {
                $ok++;

                continue;
            }

            if (($this->connectOlt($olt))['ok']) {
                $ok++;
            }
        }

        $onuOnline = Onu::fromOlt()->where('status', 'online')->count();
        $onuOffline = Onu::fromOlt()->where('status', 'offline')->count();

        $this->flushMapMarkersCache();

        return response()->json([
            'ok' => $ok,
            'failed' => $olts->count() - $ok,
            'total' => $olts->count(),
            'onu_online' => $onuOnline,
            'onu_offline' => $onuOffline,
        ]);
    }

    public function oltDelete(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);
        $olt = Olt::find($data['id']);

        if (! $olt) {
            return response()->json(['ok' => false, 'error' => 'OLT tidak ditemukan'], 404);
        }

        $olt->delete();

        return response()->json(['ok' => true, 'olts' => $this->allOlts()]);
    }

    /* â”€â”€ Sync GenieACS (modal pada peta FTTH) â”€â”€ */

    public function genieacsConfig(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'base_url' => Setting::get('genieacs_base_url') ?: (string) config('genieacs.base_url', ''),
        ]);
    }

    public function genieacsSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:255'],
        ]);

        $url = trim((string) ($data['url'] ?? ''));

        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            return response()->json(['ok' => false, 'error' => 'URL harus diawali http:// atau https://'], 422);
        }

        Setting::set('genieacs_base_url', $url !== '' ? $url : null);

        return response()->json([
            'ok' => true,
            'base_url' => $url !== '' ? $url : null,
            'message' => $url !== '' ? 'Config GenieACS tersimpan' : 'Config GenieACS dihapus',
        ]);
    }

    /* â”€â”€ Notifikasi (WhatsApp & Telegram) â€” modal pada peta FTTH â”€â”€ */

    public function notifConfig(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'wa' => [
                'enabled' => Setting::get('wa_enabled', 'false'),
                'api_url' => Setting::get('wa_api_url', ''),
                'api_key' => Setting::get('wa_api_key', ''),
                'sender' => Setting::get('wa_sender', ''),
                'recipient' => Setting::get('wa_recipient', ''),
            ],
            'telegram' => [
                'enabled' => Setting::get('telegram_enabled', 'false'),
                'bot_token' => Setting::get('telegram_bot_token', ''),
                'chat_id' => Setting::get('telegram_chat_id', ''),
            ],
        ]);
    }

    public function notifSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'wa' => ['nullable', 'array'],
            'wa.enabled' => ['nullable', 'in:true,false'],
            'wa.api_url' => ['nullable', 'string', 'max:255'],
            'wa.api_key' => ['nullable', 'string', 'max:255'],
            'wa.sender' => ['nullable', 'string', 'max:64'],
            'wa.recipient' => ['nullable', 'string', 'max:64'],
            'telegram' => ['nullable', 'array'],
            'telegram.enabled' => ['nullable', 'in:true,false'],
            'telegram.bot_token' => ['nullable', 'string', 'max:255'],
            'telegram.chat_id' => ['nullable', 'string', 'max:64'],
        ]);

        if (! empty($data['wa'])) {
            Setting::set('wa_enabled', (string) ($data['wa']['enabled'] ?? 'false'));
            Setting::set('wa_api_url', $data['wa']['api_url'] ?? null);
            Setting::set('wa_api_key', $data['wa']['api_key'] ?? null);
            Setting::set('wa_sender', $data['wa']['sender'] ?? null);
            Setting::set('wa_recipient', $data['wa']['recipient'] ?? null);
        }

        if (! empty($data['telegram'])) {
            Setting::set('telegram_enabled', (string) ($data['telegram']['enabled'] ?? 'false'));
            Setting::set('telegram_bot_token', $data['telegram']['bot_token'] ?? null);
            Setting::set('telegram_chat_id', $data['telegram']['chat_id'] ?? null);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Pengaturan notifikasi tersimpan',
        ]);
    }

    /* ── Manajemen User (modal pada peta FTTH) ── */

    private function allUsers()
    {
        return User::orderBy('created_at', 'desc')
            ->get(['id', 'name', 'username', 'email', 'role', 'permissions'])
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'email' => $u->email,
                    'role' => $u->role,
                    'permissions' => $u->permissions ?? [],
                ];
            })
            ->all();
    }

    public function usersConfig(): JsonResponse
    {
        abort_unless(in_array(auth()->user()->role, ['noc', 'superadmin']), 403, 'Hanya NOC yang boleh mengatur hak akses.');

        return response()->json(['users' => $this->allUsers()]);
    }

    public function usersSave(Request $request): JsonResponse
    {
        abort_unless(in_array(auth()->user()->role, ['noc', 'superadmin']), 403, 'Hanya NOC yang boleh mengatur hak akses.');

        $permKeys = ['edit_map', 'sync_mikrotik', 'sync_olt', 'sync_genieacs', 'ganti_wifi', 'import_export', 'panel_ftth'];

        $rules = [
            'username' => ['required', 'string', 'max:60'],
            'role' => ['required', 'in:admin,teknisi,noc,sales'],
            'permissions' => ['array'],
        ];

        $id = $request->input('id');
        if ($id) {
            $rules['username'][2] = 'unique:users,username,'.$id;
            $rules['password'] = ['nullable', 'string', 'min:8'];
        } else {
            $rules['username'][2] = 'unique:users';
            $rules['password'] = ['required', 'string', 'min:8'];
        }

        $data = $request->validate($rules);

        $permissions = [];
        $inputPerms = (array) ($request->input('permissions', []));
        foreach ($permKeys as $key) {
            $permissions[$key] = ! empty($inputPerms[$key]);
        }

        if ($id) {
            $user = User::find($id);
            if (! $user) {
                return response()->json(['ok' => false, 'error' => 'User tidak ditemukan'], 404);
            }
            $user->name = $data['username'];
            $user->username = $data['username'];
            $user->role = $data['role'];
            $user->permissions = $permissions;
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
                $user->password_plain = $data['password'];
            }
            $user->save();
            ActivityLog::log('Ubah User', 'User '.$user->username.' diperbarui');
        } else {
            $user = User::create([
                'name' => $data['username'],
                'username' => $data['username'],
                'email' => null,
                'password' => Hash::make($data['password']),
                'password_plain' => $data['password'],
                'role' => $data['role'],
                'permissions' => $permissions,
            ]);
            ActivityLog::log('Tambah User', 'User '.$user->username.' ditambahkan sebagai '.$user->role);
        }

        return response()->json(['ok' => true, 'users' => $this->allUsers()]);
    }

    public function usersDelete(Request $request): JsonResponse
    {
        abort_unless(in_array(auth()->user()->role, ['noc', 'superadmin']), 403, 'Hanya NOC yang boleh mengatur hak akses.');

        $data = $request->validate(['id' => ['required', 'integer']]);

        if ((int) $data['id'] === (int) Auth::id()) {
            return response()->json(['ok' => false, 'error' => 'Tidak dapat menghapus akun sendiri'], 422);
        }

        $user = User::find($data['id']);
        if (! $user) {
            return response()->json(['ok' => false, 'error' => 'User tidak ditemukan'], 404);
        }

        $user->delete();
        ActivityLog::log('Hapus User', 'User '.$user->username.' dihapus');

        return response()->json(['ok' => true, 'users' => $this->allUsers()]);
    }

    public function genieacsSync(): JsonResponse
    {
        try {
            $devices = app(IGenieACSClient::class)->devices([], [
                '_id', '_deviceId', '_lastInform', '_lastBoot', '_tags',
                'InternetGatewayDevice.DeviceInfo.Manufacturer',
                'InternetGatewayDevice.DeviceInfo.ProductClass',
                'InternetGatewayDevice.DeviceInfo.HardwareVersion',
                'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
                'InternetGatewayDevice.DeviceInfo.SerialNumber',
                'InternetGatewayDevice.ManagementServer.ConnectionRequestURL',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('genieacsSync gagal: '.$e->getMessage());

            return response()->json([
                'ok' => false,
                'error' => 'Gagal ambil data dari GenieACS: '.$e->getMessage(),
            ], 502);
        }

        $now = time();
        $online = 0;
        $offline = 0;
        $updated = 0;
        $total = is_array($devices) ? count($devices) : 0;

        $onusBySerial = Onu::fromOlt()->get()->keyBy(fn ($o) => strtolower(trim((string) $o->serial_number)));

        foreach ((array) $devices as $dev) {
            $devId = $dev['_id'] ?? null;
            $serial = $dev['_deviceId'] ?? ($dev['InternetGatewayDevice.DeviceInfo.SerialNumber'] ?? null);
            $lastInform = $dev['_lastInform'] ?? null;
            $isOnline = $lastInform !== null && ($now - strtotime((string) $lastInform)) < 600;

            if ($isOnline) {
                $online++;
            } else {
                $offline++;
            }

            if (! $serial) {
                continue;
            }

            $onu = $onusBySerial[strtolower(trim((string) $serial))] ?? null;
            if (! $onu) {
                continue;
            }

            $onu->update([
                'acs_device_id' => $devId,
                'acs_status' => $isOnline ? 'online' : 'offline',
                'acs_last_inform' => $lastInform ? Carbon::parse($lastInform) : null,
                'acs_manufacturer' => $dev['InternetGatewayDevice.DeviceInfo.Manufacturer'] ?? null,
                'acs_product_class' => $dev['InternetGatewayDevice.DeviceInfo.ProductClass'] ?? null,
                'acs_hardware_version' => $dev['InternetGatewayDevice.DeviceInfo.HardwareVersion'] ?? null,
                'acs_software_version' => $dev['InternetGatewayDevice.DeviceInfo.SoftwareVersion'] ?? null,
                'acs_connection_request_url' => $dev['InternetGatewayDevice.ManagementServer.ConnectionRequestURL'] ?? null,
            ]);
            $updated++;
        }

        return response()->json([
            'ok' => true,
            'message' => "Sync GenieACS selesai: {$total} device ({$online} online, {$offline} offline), {$updated} ONU tersambung",
            'total' => $total,
            'online' => $online,
            'offline' => $offline,
            'updated' => $updated,
        ]);
    }

    /* â”€â”€ Backup & Restore (card pada peta FTTH) â”€â”€ */

    public function backupConfig(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'backup_email' => Setting::get('backup_email') ?: '',
            'backup_time' => Setting::get('backup_time') ?: '',
            'smtp_host' => Setting::get('smtp_host') ?: '',
            'smtp_port' => Setting::get('smtp_port') ?: '',
            'smtp_username' => Setting::get('smtp_username') ?: '',
            'smtp_has_password' => filled(Setting::get('smtp_password')),
            'smtp_from' => Setting::get('smtp_from') ?: '',
        ]);
    }

    public function backupSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'time' => ['nullable', 'string', 'max:10'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from' => ['nullable', 'email', 'max:255'],
        ]);

        Setting::set('backup_email', filled($data['email'] ?? null) ? trim($data['email']) : null);
        Setting::set('backup_time', filled($data['time'] ?? null) ? trim($data['time']) : null);

        /* SMTP runtime (tanpa .env): kosong = pakai konfigurasi .env */
        Setting::set('smtp_host', filled($data['smtp_host'] ?? null) ? trim($data['smtp_host']) : null);
        Setting::set('smtp_port', filled($data['smtp_port'] ?? null) ? (int) $data['smtp_port'] : null);
        Setting::set('smtp_username', filled($data['smtp_username'] ?? null) ? trim($data['smtp_username']) : null);
        if (array_key_exists('smtp_password', $data) && filled($data['smtp_password'])) {
            Setting::set('smtp_password', trim($data['smtp_password']));
        }
        Setting::set('smtp_from', filled($data['smtp_from'] ?? null) ? trim($data['smtp_from']) : null);

        return response()->json(['ok' => true, 'message' => 'Konfigurasi Auto Backup tersimpan']);
    }

    /* Override konfigurasi mailer smtp secara runtime dari tabel settings,
       sehingga pengiriman backup tidak bergantung pada .env.
       Port 465 = implicit TLS (smtps), selain itu STARTTLS default. */
    private function applyMailSettingsFromDb(): void
    {
        $host = trim((string) (Setting::get('smtp_host') ?? ''));
        $user = trim((string) (Setting::get('smtp_username') ?? ''));
        $pass = (string) (Setting::get('smtp_password') ?? '');

        if ($host === '' || $user === '' || $pass === '') {
            return;
        }

        $port = (int) (Setting::get('smtp_port') ?: 587);
        config([
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $user,
            'mail.mailers.smtp.password' => $pass,
            'mail.mailers.smtp.scheme' => $port === 465 ? 'smtps' : null,
        ]);

        $from = trim((string) (Setting::get('smtp_from') ?? '')) ?: $user;
        config(['mail.from.address' => $from, 'mail.from.name' => config('app.name')]);
    }

    public function backupSendNow(Request $request): JsonResponse
    {
        $email = trim((string) ($request->input('email') ?: Setting::get('backup_email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'error' => 'Email penerima tidak valid'], 422);
        }

        /* SMTP dari card Backup (settings) bila terisi; fallback ke .env */
        $this->applyMailSettingsFromDb();

        $payload = $this->buildFullBackup();
        $filename = 'alkonek-backup-'.now()->format('Ymd-His').'.json';

        try {
            Mail::raw(
                'Lampiran backup otomatis '.config('app.name').' dibuat pada '.now()->format('d M Y H:i:s').'.',
                function ($m) use ($email, $payload, $filename) {
                    $m->to($email)
                        ->subject('[Backup '.config('app.name').'] '.now()->format('Y-m-d H:i'))
                        ->attachData(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), $filename);
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('backupSendNow gagal: '.$e->getMessage());

            return response()->json([
                'ok' => false,
                'error' => 'Gagal mengirim email: '.$e->getMessage(),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Backup terkirim ke '.$email,
            'filename' => $filename,
        ]);
    }

    public function backupRestore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'kind' => ['required', 'in:database,routers'],
        ]);

        $raw = $request->file('file')->get();

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $content = json_decode($raw, true);

        if (! is_array($content)) {
            return response()->json(['ok' => false, 'error' => 'File JSON tidak valid'], 422);
        }

        return $data['kind'] === 'routers'
            ? $this->restoreRouters($content)
            : $this->restoreDatabase($content);
    }

    public function excelExport(): HttpResponse
    {
        $customers = Customer::orderBy('customer_code')->get();

        $header = [
            'customer_code', 'name', 'type', 'phone', 'email', 'nik', 'location',
            'package', 'pppoe_username', 'pppoe_password', 'serial_number',
            'mac_address', 'original_ppp_profile', 'odp', 'odc', 'due_date', 'status',
        ];

        $lines = [implode(',', $header)];

        foreach ($customers as $c) {
            $lines[] = implode(',', array_map([$this, 'csvField'], [
                $c->customer_code, $c->name, $c->type, $c->phone, $c->email, $c->nik,
                $c->location, $c->package?->name, $c->pppoe_username, $c->pppoe_password,
                $c->serial_number, $c->mac_address, $c->original_ppp_profile,
                $c->odp?->nama_odp, $c->odp?->odc?->nama_odc,
                $c->due_date ? (string) $c->due_date : '',
                $c->status,
            ]));
        }

        return Response::make("\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="alkonek-pelanggan-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    public function excelImport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $raw = file_get_contents($request->file('file')->getRealPath());

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $rows = array_values(array_filter(
            array_map('str_getcsv', preg_split('/\r\n|\n|\r/', $raw)),
            fn ($r) => is_array($r) && count($r) > 0 && trim((string) $r[0]) !== '',
        ));

        if (! $rows) {
            return response()->json(['ok' => false, 'error' => 'File kosong'], 422);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows));
        $idx = array_flip($header);

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || count($row) === 0) {
                continue;
            }

            $rec = [];
            foreach ($idx as $key => $i) {
                $rec[$key] = trim((string) ($row[$i] ?? ''));
            }

            $code = $rec['customer_code'] ?? '';
            if ($code === '') {
                $skipped++;

                continue;
            }

            $customer = Customer::where('customer_code', $code)->first();

            if (! $customer) {
                $customer = new Customer;
                $customer->customer_code = $code;
                $customer->status = 'active';
            }

            foreach ([
                'name', 'type', 'phone', 'email', 'nik', 'location',
                'pppoe_username', 'pppoe_password', 'serial_number', 'mac_address',
                'original_ppp_profile', 'status',
            ] as $field) {
                if (isset($rec[$field]) && $rec[$field] !== '') {
                    $customer->{$field} = $rec[$field];
                }
            }

            if (isset($rec['due_date']) && $rec['due_date'] !== '') {
                $customer->due_date = $rec['due_date'];
            }

            if (! empty($rec['package'])) {
                $pkg = Package::where('name', $rec['package'])->first();
                if ($pkg) {
                    $customer->package_id = $pkg->id;
                }
            }

            if (! empty($rec['odp'])) {
                $odp = Odp::where('nama_odp', $rec['odp'])->first();
                if ($odp) {
                    $customer->odp_id = $odp->id;
                }
            }

            $customer->save();
            $imported++;
        }

        return response()->json([
            'ok' => true,
            'message' => $imported.' pelanggan diimpor'.($skipped ? " ({$skipped} dilewati tanpa kode)" : ''),
        ]);
    }

    public function kmzExport(): HttpResponse
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<kml xmlns="http://www.opengis.net/kml/2.2">', '<Document><name>ALKONEK FTTH Network</name>'];

        $add = function (string $type, string $name, float $lat, float $lon, string $desc = '') use (&$lines) {
            $color = match ($type) {
                'OLT' => 'FF3A3AF2',
                'ODC' => 'FF23A6F5',
                'ODP' => 'FFD9424A',
                default => 'FF80E64A',
            };
            $nameE = htmlspecialchars($name, ENT_XML1, 'UTF-8');
            $descE = htmlspecialchars($desc, ENT_XML1, 'UTF-8');
            $lines[] = '<Placemark>';
            $lines[] = '  <name>'.$type.' - '.$nameE.'</name>';
            $lines[] = '  <description>'.$descE.'</description>';
            $lines[] = '  <Style><IconStyle><color>'.$color.'</color><scale>0.9</scale></IconStyle></Style>';
            $lines[] = '  <Point><coordinates>'.sprintf('%.7f', $lon).','.sprintf('%.7f', $lat).',0</coordinates></Point>';
            $lines[] = '</Placemark>';
        };

        foreach (Olt::orderBy('name')->get() as $m) {
            if ($m->latitude === null || $m->longitude === null) {
                continue;
            }
            $add('OLT', (string) $m->name, (float) $m->latitude, (float) $m->longitude, (string) ($m->location ?? ''));
        }

        foreach (MikrotikRouter::orderBy('name')->get() as $m) {
            if ($m->latitude === null || $m->longitude === null) {
                continue;
            }
            $add('Router', (string) $m->name, (float) $m->latitude, (float) $m->longitude, (string) ($m->location ?? ''));
        }

        foreach (Odc::orderBy('nama_odc')->get() as $m) {
            if ($m->latitude === null || $m->longitude === null) {
                continue;
            }
            $add('ODC', (string) $m->nama_odc, $m->latitude, $m->longitude, 'ODC');
        }

        foreach (Odp::orderBy('nama_odp')->get() as $m) {
            if ($m->latitude === null || $m->longitude === null) {
                continue;
            }
            $add('ODP', (string) $m->nama_odp, $m->latitude, $m->longitude, 'ODP');
        }

        $lines[] = '</Document></kml>';

        return Response::make(implode("\n", $lines), 200, [
            'Content-Type' => 'application/vnd.google-earth.kml+xml',
            'Content-Disposition' => 'attachment; filename="alkonek-ftth-'.now()->format('Ymd-His').'.kml"',
        ]);
    }

    public function kmzImport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'kmz' || $ext === 'zip') {
            if (! class_exists('ZipArchive')) {
                return response()->json(['ok' => false, 'error' => 'Ekstensi Zip (php_zip) tidak aktif di server â€” gunakan file .kml'], 422);
            }

            $zip = new \ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                return response()->json(['ok' => false, 'error' => 'File KMZ tidak valid'], 422);
            }

            $found = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_ends_with(strtolower((string) $name), '.kml')) {
                    $content = $zip->getFromIndex($i);
                    $found = true;
                    break;
                }
            }
            $zip->close();

            if (! $found) {
                return response()->json(['ok' => false, 'error' => 'Tidak ada file .kml di dalam KMZ'], 422);
            }
        }

        $xml = @simplexml_load_string($content);
        if ($xml === false) {
            return response()->json(['ok' => false, 'error' => 'File KML tidak valid'], 422);
        }

        $xml->registerXPathNamespace('k', 'http://www.opengis.net/kml/2.2');

        $placemarks = $xml->xpath('//k:Placemark');
        if (empty($placemarks)) {
            $placemarks = $xml->xpath('//Placemark');
        }

        $updated = 0;

        foreach ($placemarks as $pm) {
            $pm->registerXPathNamespace('k', 'http://www.opengis.net/kml/2.2');

            $name = trim((string) ($pm->name ?? ''));

            $coordNodes = $pm->xpath('.//k:coordinates');
            if (empty($coordNodes)) {
                $coordNodes = $pm->xpath('.//coordinates');
            }

            if ($name === '' || empty($coordNodes)) {
                continue;
            }

            $parts = preg_split('/[,\s]+/', trim((string) $coordNodes[0]));
            if (count($parts) < 2) {
                continue;
            }

            $lon = (float) $parts[0];
            $lat = (float) $parts[1];

            if (abs($lat) > 90 || abs($lon) > 180) {
                continue;
            }

            if ($this->applyCoordinate($name, $lat, $lon)) {
                $updated++;
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $updated.' titik koordinat diperbarui dari file KML',
        ]);
    }

    /* â”€â”€ Map markers & Tambah Perangkat (card pada peta FTTH) â”€â”€ */

    public function mapMarkers(): JsonResponse
    {
        /* Payload marker di-cache singkat (45s): auto-refresh tiap 10s + load
           halaman tak perlu membangun ulang ribuan resolusi status berulang.
           Flush otomatis saat device/sync mengubah data (flushMapMarkersCache). */
        $markers = Cache::remember($this->mapMarkersCacheKey(), now()->addSeconds(45), function () {
            return $this->buildMapMarkers();
        });

        return response()->json(['ok' => true, 'markers' => $markers]);
    }

    private function mapMarkersCacheKey(): string
    {
        $tenantId = optional(Auth::user())->tenant_id ?? 0;

        return "ftth_map_markers_v1_t{$tenantId}";
    }

    private function flushMapMarkersCache(): void
    {
        Cache::forget($this->mapMarkersCacheKey());
    }

    private function buildMapMarkers(): array
    {
        $markers = [];
        $allDevices = Device::orderBy('type')->orderBy('name')->get();

        /* Build lookup maps for status resolution */
        $allDevicesByName = [];
        foreach ($allDevices as $d) {
            $allDevicesByName[$d->name] = $d;
        }

        /* Refresh status fisik OLT secara REAL: MikroTik (satu‑satunya perangkat
           yang terhubung ke OLT) melakukan ICMP ping ke IP management OLT.
           Reply => ONLINE (fisik hidup), timeout => OFFLINE (fisik mati).
           Hasil di-cache 30s dan ditulis ke Olt.connection_status / Device.status
           agar konsisten dengan kartu peta & card Sync OLT. */
        $this->refreshAllOltRealStatus($allDevices);

        $oltStatusMap = [];
        foreach (Olt::all() as $olt) {
            $oltStatusMap[$olt->name] = $olt->connection_status;
        }

        $allDevices->each(function ($m) use (&$markers, $allDevicesByName, $oltStatusMap) {
            if ($m->latitude === null || $m->longitude === null) {
                return;
            }
            $attrs = is_array($m->attributes) ? $m->attributes : [];

            /* Resolve status: walk up parent chain to OLT */
            $resolvedStatus = self::resolveDeviceStatus($m, $allDevicesByName, $oltStatusMap);
            $onuCust = $this->resolveOnuCustomer($m);

            /* OLT tanpa IP: ambil dari model Olt yang cocok */
            $ipAddress = $m->ip_address;
            if (strtoupper($m->type) === 'OLT' && empty($ipAddress)) {
                $ipAddress = $this->resolveOltModelForDevice($m)?->ip_address;
            }

            $markers[] = [
                'id' => $m->id,
                'source' => 'device',
                'type' => strtoupper($m->type),
                'label' => $m->name,
                'lat' => (float) $m->latitude,
                'lon' => (float) $m->longitude,
                'location' => $m->location ?? '',
                'status' => $resolvedStatus,
                'device_status' => $m->status,
                'detail' => trim(($m->brand ?? '').($m->model ? ' · '.$m->model : '')),
                'parent' => isset($attrs['induk']) ? (string) $attrs['induk'] : null,
                'attributes' => $attrs,
                'capacity' => $m->capacity,
                'ip_address' => $ipAddress,
                'brand' => $m->brand,
                'model' => $m->model,
                'notes' => $m->notes,
                'customer_id' => $onuCust[0],
                'onu_type' => $onuCust[1],
            ];
        });

        return $markers;
    }

    public function deviceList(): JsonResponse
    {
        $devices = Device::whereNotIn('type', ['router', 'olt'])
            ->orderBy('type')->orderBy('name')->get()
            ->map(fn ($d) => $this->devicePayload($d))
            ->values()
            ->all();

        $routers = MikrotikRouter::where('status', 'online')->orderBy('id')->get()
            ->map(fn ($r) => $this->routerPayload($r))
            ->values()
            ->all();

        /* OLT di card Perangkat: tampilkan nama OLT + type dari Device peta
           (mis. OLT-UTAMA = C-DATA FD1601S). Data sync bisa menimpa model
           dengan deskripsi hardware, jadi utamakan brand/model milik Device. */
        $normDevs = [];
        foreach (Device::where('type', 'olt')->get() as $od) {
            $normDevs[self::normDeviceKey((string) $od->name)] = $od;
        }

        $olts = Olt::where('connection_status', 'online')->orderBy('id')->get()
            ->map(function ($o) use ($normDevs) {
                $p = $this->oltPayload($o);
                $dev = $normDevs[self::normDeviceKey((string) $o->name)]
                    ?? collect($normDevs)->first(function ($d) use ($o) {
                        $a = self::normDeviceKey((string) $o->name);
                        $b = self::normDeviceKey((string) $d->name);

                        return $a !== '' && $b !== '' && (str_contains($b, $a) || str_contains($a, $b));
                    });
                if ($dev) {
                    $p['brand'] = $dev->brand ?: ($p['brand'] ?? null);
                    $p['model'] = $dev->model ?: ($p['model'] ?? null);
                }

                return $p;
            })
            ->values()
            ->all();

        /* Status ONU/pelanggan di card Perangkat konsisten dengan peta:
           resolve lewat rantai induk sampai OLT (kolom status sering kosong) */
        $allDevs = Device::get();
        $allDevsById = $allDevs->keyBy('id');
        $allDevsByName = [];
        foreach ($allDevs as $ad) {
            $allDevsByName[$ad->name] = $ad;
        }
        $oltStatusMapDev = [];
        foreach (Olt::all() as $oo) {
            $oltStatusMapDev[$oo->name] = $oo->connection_status;
        }
        /* Index device ONU per pppoe_user & nama ternormalisasi: pelanggan
           (PPPoE maupun hotspot) yang ONU-nya sudah ada di peta mengikuti
           status map — 'active' hanya untuk yang belum ditambahkan ke peta */
        $onuByPppoe = [];
        $onuByNormName = [];
        foreach ($allDevs as $ad) {
            if (strtolower((string) $ad->type) !== 'onu') {
                continue;
            }
            $oa = is_array($ad->attributes) ? $ad->attributes : [];
            $pu = isset($oa['pppoe_user']) ? self::normPppoeUser((string) $oa['pppoe_user']) : '';
            if ($pu !== '') {
                $onuByPppoe[$pu] = $ad;
            }
            $key = self::normDeviceKey((string) $ad->name);
            if ($key !== '') {
                $onuByNormName[$key] = $ad;
            }
        }

        $customers = Customer::with(['odp'])
            ->whereIn('type', ['ppp', 'hotspot'])
            ->orderBy('name')
            ->get()
            ->map(function ($c) use ($onuByPppoe, $onuByNormName, $allDevsByName, $oltStatusMapDev) {
                /* ONLINE/OFFLINE hanya untuk pelanggan yang BENAR-BENAR sudah
                   ditambahkan ke titik lokasi peta (tertaut ODP/port). Yang
                   belum ditautkan tetap pakai status billing active/nonactive. */
                $dev = null;
                if (($c->odp_id || $c->odp_port_id)) {
                    if ($c->pppoe_username && isset($onuByPppoe[self::normPppoeUser((string) $c->pppoe_username)])) {
                        $dev = $onuByPppoe[self::normPppoeUser((string) $c->pppoe_username)];
                    }
                    if (! $dev) {
                        /* Cocokkan juga per nama (mis. ONU hotspot "Icang Cell") */
                        $n = self::normDeviceKey((string) $c->name);
                        if ($n !== '' && isset($onuByNormName[$n])) {
                            $dev = $onuByNormName[$n];
                        }
                    }
                }

                return [
                    'id' => $c->id,
                    'kind' => 'customer',
                    'customer_type' => $c->type,
                    'customer_code' => $c->customer_code,
                    'name' => $c->name,
                    'pppoe_username' => $c->pppoe_username,
                    'status' => $dev ? self::resolveDeviceStatus($dev, $allDevsByName, $oltStatusMapDev) : $c->status,
                    'location' => $c->location,
                    'lat' => $c->odp?->latitude,
                    'lon' => $c->odp?->longitude,
                ];
            })
            ->values()
            ->all();

        $onuDevices = [];
        foreach ($devices as $d) {
            if ($d['type'] !== 'onu') {
                continue;
            }
            $attrs = is_array($d['attributes']) ? $d['attributes'] : [];
            $pppoe = isset($attrs['pppoe_user']) ? (string) $attrs['pppoe_user'] : '';
            if ($pppoe === '') {
                continue;
            }
            $devModel = $allDevsById->get($d['id']);
            $onuDevices[] = [
                'id' => $d['id'],
                'kind' => 'device',
                'customer_code' => null,
                'name' => $d['name'],
                'pppoe_username' => $pppoe,
                'status' => $devModel ? self::resolveDeviceStatus($devModel, $allDevsByName, $oltStatusMapDev) : ($d['status'] ?? null),
                'location' => $d['location'],
                'lat' => $d['latitude'],
                'lon' => $d['longitude'],
            ];
        }

        $customers = array_merge($customers, $onuDevices);

        $onuCount = 0;
        $onuHotspotCount = 0;
        foreach ($customers as $c) {
            if (($c['customer_type'] ?? '') === 'hotspot') {
                $onuHotspotCount++;
            } else {
                $onuCount++;
            }
        }
        $counts = [
            'router' => count($routers),
            'olt' => count($olts),
            'otb' => 0, 'odc' => 0, 'odp' => 0, 'htb' => 0,
            'onu' => $onuCount,
            'onu_hotspot' => $onuHotspotCount,
        ];
        foreach ($devices as $d) {
            if ($d['type'] === 'onu') {
                continue;
            }
            if (isset($counts[$d['type']])) {
                $counts[$d['type']]++;
            }
        }

        return response()->json([
            'ok' => true,
            'devices' => $devices,
            'routers' => $routers,
            'olts' => $olts,
            'customers' => $customers,
            'counts' => $counts,
        ]);
    }

    /**
     * Parse capacity string like "1/8", "3/32", or plain "8".
     * Returns ['used' => int, 'total' => int].
     */
    private static function parseCapacity(?string $capacity): array
    {
        if (! $capacity || trim($capacity) === '') {
            return ['used' => 0, 'total' => 0];
        }
        $capacity = trim($capacity);
        if (preg_match('#^(\d+)\s*/\s*(\d+)$#', $capacity, $m)) {
            return ['used' => (int) $m[1], 'total' => (int) $m[2]];
        }
        if (is_numeric($capacity)) {
            return ['used' => 0, 'total' => (int) $capacity];
        }

        return ['used' => 0, 'total' => 0];
    }

    /**
     * Walk up the parent chain to find the OLT ancestor's online status.
     * Returns 'online', 'offline', or the device's own status if no OLT found.
     */
    private static function resolveDeviceStatus(Device $device, array $allDevicesByName, array $oltStatusMap): string
    {
        $current = $device;
        $visited = [];

        for ($i = 0; $i < 20; $i++) {
            $attrs = is_array($current->attributes) ? $current->attributes : [];
            $induk = $attrs['induk'] ?? '';
            if ($induk === '' || in_array($induk, $visited, true)) {
                break;
            }
            $visited[] = $induk;

            /* Parse "TYPE — Name" from induk */
            $parts = preg_split('/\s+[-–—]\s+/u', $induk, 2);
            if (count($parts) !== 2) {
                break;
            }
            $parentType = strtolower(trim($parts[0]));
            $parentName = trim($parts[1]);

            if ($parentType === 'olt') {
                /* Check olts table first, then devices table.
                   oltStatusMap diisi dari Olt.connection_status yang SUDAH
                   direfresh secara REAL (ICMP ping dari MikroTik ke IP OLT)
                   di buildMapMarkers — sehingga 'offline' di sini berarti OLT
                   fisik benar-benar mati / tidak reply ping. */
                if (isset($oltStatusMap[$parentName])) {
                    return $oltStatusMap[$parentName] === 'offline' ? 'offline' : 'online';
                }
                if (isset($allDevicesByName[$parentName]) && $allDevicesByName[$parentName]->type === 'olt') {
                    return $allDevicesByName[$parentName]->status === 'offline' ? 'offline' : 'online';
                }

                /* Not found → OLT assumed online (if not explicitly tracked) */
                return 'online';
            }

            /* Walk up to parent device */
            if (isset($allDevicesByName[$parentName])) {
                $current = $allDevicesByName[$parentName];
            } else {
                break;
            }
        }

        /* Perangkat tanpa parent OLT terpoll (ditambah manual di peta, mis. OLT/OTB/ODC
           yang belum punya backend Olt) dianggap ONLINE secara default, bukan offline.
           Untuk tipe OLT, cek model Olt bila ada (status sudah direfresh REAL via ping). */
        if (strtolower($device->type) === 'olt') {
            $oltModel = Olt::where('name', $device->name)->first();
            if ($oltModel) {
                return $oltModel->connection_status === 'offline' ? 'offline' : 'online';
            }
        }

        return $device->status ?? 'online';
    }

    public function odcStats(int $id): JsonResponse
    {
        $device = Device::find($id);
        if (! $device || $device->type !== 'odc') {
            return response()->json(['ok' => false, 'error' => 'ODC not found'], 404);
        }

        $attrs = is_array($device->attributes) ? $device->attributes : [];
        $odcId = $attrs['odc_id'] ?? null;

        $portUsed = 0;
        $portTotal = 0;
        $onuTotal = 0;
        $uptime = null;
        $odpNames = [];

        /* Parse capacity string like "1/8" or "3/32" or plain "8" */
        $parsedCap = self::parseCapacity($device->capacity);
        $portTotal = $parsedCap['total'];

        /* Load ALL devices once */
        $allDevices = Device::select('id', 'type', 'name', 'capacity', 'status', 'attributes')->get();

        /* Normalize: lowercase, strip non-alphanumeric for fuzzy name matching */
        $norm = function (string $s): string {
            return preg_replace('/[^a-z0-9]/i', '', strtolower($s));
        };
        $devNorm = $norm($device->name);

        /* -- Find matching Odc model -- */
        $odc = null;
        if ($odcId) {
            $odc = Odc::find($odcId);
        }
        if (! $odc) {
            $odc = Odc::where('nama_odc', $device->name)->first();
        }
        /* Normalized match: strip all non-alphanumeric and compare */
        if (! $odc) {
            foreach (Odc::all() as $candidate) {
                if ($norm($candidate->nama_odc) === $devNorm) {
                    $odc = $candidate;
                    break;
                }
            }
        }
        /* Segment match: device name may include site prefix (e.g. "ODC-ALK-UTAMA" vs "ODC UTAMA")
           Split on non-alnum, check if model segments are a subset of device segments */
        if (! $odc) {
            $devParts = array_map('strtolower', array_filter(preg_split('/[^a-z0-9]/i', $device->name)));
            foreach (Odc::all() as $candidate) {
                $candParts = array_map('strtolower', array_filter(preg_split('/[^a-z0-9]/i', $candidate->nama_odc)));
                if (count($candParts) > 0 && count($candParts) <= count($devParts)) {
                    $diff = array_diff($candParts, $devParts);
                    if (empty($diff)) {
                        $odc = $candidate;
                        break;
                    }
                }
            }
        }
        /* Last resort: match by child ODP device names -> ODP model names -> ODC parent */
        if (! $odc) {
            $childOdpDeviceNames = [];
            foreach ($allDevices as $dev) {
                $da = is_array($dev->attributes) ? $dev->attributes : [];
                if (($da['induk'] ?? '') === 'ODC — '.$device->name && $dev->type === 'odp') {
                    $childOdpDeviceNames[] = $dev->name;
                }
            }
            if (! empty($childOdpDeviceNames)) {
                $allOdpModelsCheck = Odp::with('odc')->get();
                foreach ($allOdpModelsCheck as $odpModel) {
                    foreach ($childOdpDeviceNames as $cdName) {
                        if ($norm($odpModel->nama_odp) === $norm($cdName)) {
                            $odc = $odpModel->odc;
                            break 2;
                        }
                    }
                }
            }
        }

        /* -- Build device tree for ODP/ONU data -- */
        $childrenByParent = [];
        foreach ($allDevices as $dev) {
            $devAttrs = is_array($dev->attributes) ? $dev->attributes : [];
            $induk = $devAttrs['induk'] ?? '';
            if ($induk !== '') {
                $childrenByParent[$induk][] = $dev;
            }
        }
        $parentLabel = 'ODC — '.$device->name;
        $directChildren = $childrenByParent[$parentLabel] ?? [];
        $odpChildNames = [];
        foreach ($directChildren as $dc) {
            if ($dc->type === 'odp') {
                $odpChildNames[] = $dc->name;
            }
        }

        /* -- Normalize-match device ODP names -> ODP models -- */
        $allOdpModels = Odp::with('odc')->get();
        $matchedOdps = [];
        foreach ($allOdpModels as $odpModel) {
            foreach ($odpChildNames as $cdName) {
                if ($norm($odpModel->nama_odp) === $norm($cdName)) {
                    $matchedOdps[] = $odpModel;
                    break;
                }
            }
        }

        if ($odc) {
            $portTotal = $odc->kapasitas_port;

            /* Port used: count direct ODP children connected to this ODC */
            $effectiveOdps = ! empty($matchedOdps) ? $matchedOdps : $odc->odps()->get();
            $portUsed = count($odpChildNames);
            /* Also count from odc_ports if synced and higher */
            $odcPortUsed = $odc->ports()->where('status', 'used')->count();
            if ($odcPortUsed > $portUsed) {
                $portUsed = $odcPortUsed;
            }

            /* Count ONUs by traversing device tree downward from this ODC */
            $em = "\xE2\x80\x94";
            $visited = [];
            $queue = [];
            foreach ($directChildren as $dc) {
                $queue[] = $dc;
            }
            while (! empty($queue)) {
                $current = array_shift($queue);
                if (in_array($current->name, $visited, true)) {
                    continue;
                }
                $visited[] = $current->name;
                if ($current->type === 'onu') {
                    $onuTotal++;
                }
                $typePrefix = strtoupper($current->type).' '.$em.' '.$current->name;
                $subDevices = $childrenByParent[$typePrefix] ?? [];
                foreach ($subDevices as $sub) {
                    if (! in_array($sub->name, $visited, true)) {
                        $queue[] = $sub;
                    }
                }
            }
            /* Fallback: if still 0, count from ODP ports */
            if ($onuTotal === 0) {
                foreach ($effectiveOdps as $matchedOdp) {
                    $onuTotal += $matchedOdp->ports()->where('status', 'used')->count();
                }
            }

            /* Build ODP list with real port data */
            foreach ($effectiveOdps as $matchedOdp) {
                $odpPortUsed = $matchedOdp->ports()->where('status', 'used')->count();
                $odpPortTotal = $matchedOdp->kapasitas_port;
                $odpNames[] = [
                    'name' => $matchedOdp->nama_odp,
                    'onu_count' => $odpPortUsed,
                    'port_used' => $odpPortUsed,
                    'port_total' => $odpPortTotal,
                ];
            }
        } else {
            /* Pure fallback: device tree only */
            $portUsed = count($odpChildNames);

            $visited = [];
            $queue = [];
            foreach ($directChildren as $dc) {
                $queue[] = ['name' => $dc->name, 'type' => $dc->type];
            }
            while (! empty($queue)) {
                $current = array_shift($queue);
                $currentName = $current['name'];
                $currentType = $current['type'];
                if (in_array($currentName, $visited, true)) {
                    continue;
                }
                $visited[] = $currentName;
                $typePrefix = strtoupper($currentType).' — '.$currentName;
                $subDevices = $childrenByParent[$typePrefix] ?? [];
                foreach ($subDevices as $sub) {
                    if ($sub->type === 'onu') {
                        $onuTotal++;
                    } elseif (! in_array($sub->name, $visited, true)) {
                        $queue[] = ['name' => $sub->name, 'type' => $sub->type];
                    }
                }
            }

            foreach ($directChildren as $child) {
                if ($child->type !== 'odp') {
                    continue;
                }
                $odpModel = null;
                foreach ($allOdpModels as $candidate) {
                    if ($norm($candidate->nama_odp) === $norm($child->name)) {
                        $odpModel = $candidate;
                        break;
                    }
                }
                if ($odpModel) {
                    $odpOnuCount = $odpModel->ports()->where('status', 'used')->count();
                    $odpNames[] = [
                        'name' => $odpModel->nama_odp,
                        'onu_count' => $odpOnuCount,
                        'port_used' => $odpOnuCount,
                        'port_total' => $odpModel->kapasitas_port,
                    ];
                } else {
                    $odpOnuCount = 0;
                    $odpPrefix = 'ODP — '.$child->name;
                    foreach ($childrenByParent[$odpPrefix] ?? [] as $sub) {
                        if ($sub->type === 'onu') {
                            $odpOnuCount++;
                        }
                    }
                    $odpNames[] = [
                        'name' => $child->name,
                        'onu_count' => $odpOnuCount,
                        'port_used' => $odpOnuCount,
                        'port_total' => 0,
                    ];
                }
            }
        }

        if ($device->created_at) {
            $now = now();
            $diff = $device->created_at->diff($now);
            $uptime = [
                'days' => $diff->days,
                'hours' => $diff->h,
                'minutes' => $diff->i,
                'formatted' => $diff->days > 0
                    ? "{$diff->days} hari {$diff->h} jam"
                    : "{$diff->h} jam {$diff->i} menit",
            ];
        }

        /* Resolve ODC status from OLT ancestor chain */
        $allDevicesByName = [];
        foreach ($allDevices as $d) {
            $allDevicesByName[$d->name] = $d;
        }
        $oltStatusMap = [];
        foreach (Olt::all() as $olt) {
            $oltStatusMap[$olt->name] = $olt->connection_status;
        }
        $resolvedStatus = self::resolveDeviceStatus($device, $allDevicesByName, $oltStatusMap);

        return response()->json([
            'ok' => true,
            'status' => $resolvedStatus,
            'port_used' => $portUsed,
            'port_total' => $portTotal,
            'sisa' => max(0, $portTotal - $portUsed),
            'onu_total' => $onuTotal,
            'odp_count' => count($odpNames),
            'odps' => $odpNames,
            'uptime' => $uptime,
        ]);
    }

    public function odpStats(int $id): JsonResponse
    {
        $device = Device::find($id);
        if (! $device || $device->type !== 'odp') {
            return response()->json(['ok' => false, 'error' => 'ODP not found'], 404);
        }

        $attrs = is_array($device->attributes) ? $device->attributes : [];
        $odpId = $attrs['odp_id'] ?? null;

        $norm = function (string $s): string {
            return preg_replace('/[^a-z0-9]/i', '', strtolower($s));
        };
        $devNorm = $norm($device->name);

        /* -- Find matching Odp model -- */
        $odp = null;
        if ($odpId) {
            $odp = Odp::find($odpId);
        }
        if (! $odp) {
            $odp = Odp::where('nama_odp', $device->name)->first();
        }
        if (! $odp) {
            foreach (Odp::all() as $candidate) {
                if ($norm($candidate->nama_odp) === $devNorm) {
                    $odp = $candidate;
                    break;
                }
            }
        }
        if (! $odp) {
            $devParts = array_map('strtolower', array_filter(preg_split('/[^a-z0-9]/i', $device->name)));
            foreach (Odp::all() as $candidate) {
                $candParts = array_map('strtolower', array_filter(preg_split('/[^a-z0-9]/i', $candidate->nama_odp)));
                if (count($candParts) > 0 && count($candParts) <= count($devParts)) {
                    $diff = array_diff($candParts, $devParts);
                    if (empty($diff)) {
                        $odp = $candidate;
                        break;
                    }
                }
            }
        }

        /* Port totals */
        $parsedCap = self::parseCapacity($device->capacity);
        $portTotal = $parsedCap['total'];
        $portUsed = 0;
        $onuTotal = 0;
        $uptime = null;

        /* Load ALL devices once & build parent -> children tree */
        $allDevices = Device::select('id', 'type', 'name', 'capacity', 'status', 'attributes')->get();
        $childrenByParent = [];
        foreach ($allDevices as $dev) {
            $devAttrs = is_array($dev->attributes) ? $dev->attributes : [];
            $induk = $devAttrs['induk'] ?? '';
            if ($induk !== '') {
                $childrenByParent[$induk][] = $dev;
            }
        }

        /* Count child ONU devices directly under this ODP */
        $odpPrefix = 'ODP — '.$device->name;
        foreach ($childrenByParent[$odpPrefix] ?? [] as $sub) {
            if ($sub->type === 'onu') {
                $onuTotal++;
            }
        }

        if ($odp) {
            $portTotal = $odp->kapasitas_port;

            /* Port terpakai = port yang benar-benar ditempati:
               pelanggan (customers.odp_port_id), ONU terdaftar (onus.odp_port_id),
               atau penanda status 'used' pada odp_ports */
            $ports = $odp->ports()->get(['id', 'port_number', 'status']);
            $portIds = $ports->pluck('id');
            $usedNums = collect();

            $custPortIds = Customer::whereIn('odp_port_id', $portIds)->pluck('odp_port_id');
            $usedNums = $usedNums->merge($ports->whereIn('id', $custPortIds)->pluck('port_number'));

            $onuPortIds = Onu::whereIn('odp_port_id', $portIds)->pluck('odp_port_id');
            $usedNums = $usedNums->merge($ports->whereIn('id', $onuPortIds)->pluck('port_number'));

            $usedNums = $usedNums->merge($ports->where('status', 'used')->pluck('port_number'));

            $portUsed = $usedNums->filter()->unique()->count();

            /* Setiap ONU yang tergantung di ODP ini melalui peta juga menempati satu port */
            $portUsed = max($portUsed, min($onuTotal, $portTotal));

            /* ONU terhubung = sumber nyata: ONU pada port, pelanggan di ODP ini, atau child device */
            $onuFromPorts = Onu::whereIn('odp_port_id', $portIds)->count();
            $onuFromCustomers = Customer::where(function ($q) use ($odp, $portIds) {
                $q->whereIn('odp_port_id', $portIds)->orWhere('odp_id', $odp->id);
            })->count();
            $onuTotal = max($onuTotal, $onuFromPorts, $onuFromCustomers);
        } else {
            $portUsed = $onuTotal;
        }

        if ($device->created_at) {
            $now = now();
            $diff = $device->created_at->diff($now);
            $uptime = [
                'days' => $diff->days,
                'hours' => $diff->h,
                'minutes' => $diff->i,
                'formatted' => $diff->days > 0
                    ? "{$diff->days} hari {$diff->h} jam"
                    : "{$diff->h} jam {$diff->i} menit",
            ];
        }

        /* Resolve ODP status from OLT ancestor chain */
        $allDevicesByName = [];
        foreach ($allDevices as $d) {
            $allDevicesByName[$d->name] = $d;
        }
        $oltStatusMap = [];
        foreach (Olt::all() as $olt) {
            $oltStatusMap[$olt->name] = $olt->connection_status;
        }
        $resolvedStatus = self::resolveDeviceStatus($device, $allDevicesByName, $oltStatusMap);

        return response()->json([
            'ok' => true,
            'status' => $resolvedStatus,
            'port_used' => $portUsed,
            'port_total' => $portTotal,
            'sisa' => max(0, $portTotal - $portUsed),
            'onu_total' => $onuTotal,
            'uptime' => $uptime,
        ]);
    }

    public function deviceDeleteAll(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'string']]);
        $type = strtolower($data['type']);

        if (! in_array($type, ['router', 'olt', 'otb', 'odc', 'odp', 'htb'], true)) {
            return response()->json(['ok' => false, 'error' => 'Tipe perangkat tidak valid'], 422);
        }

        if ($type === 'router') {
            $deleted = MikrotikRouter::where('status', 'online')->count();
            MikrotikRouter::where('status', 'online')->delete();
            $this->flushMapMarkersCache();

            return response()->json(['ok' => true, 'message' => "{$deleted} router dihapus", 'deleted' => $deleted]);
        }

        if ($type === 'olt') {
            $deleted = Olt::where('connection_status', 'online')->count();
            Olt::where('connection_status', 'online')->delete();
            $this->flushMapMarkersCache();

            return response()->json(['ok' => true, 'message' => "{$deleted} OLT dihapus", 'deleted' => $deleted]);
        }

        $deleted = Device::where('type', $type)->count();
        Device::where('type', $type)->delete();
        $this->flushMapMarkersCache();

        return response()->json(['ok' => true, 'message' => "{$deleted} {$type} dihapus", 'deleted' => $deleted]);
    }

    public function customerDelete(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $customer = Customer::find($data['id']);

        if (! $customer) {
            return response()->json(['ok' => false, 'error' => 'Pelanggan tidak ditemukan'], 404);
        }

        if ($customer->odp_port_id) {
            $customer->odpPort?->update(['status' => 'available']);
        }

        $name = $customer->name;
        $customer->delete();

        return response()->json(['ok' => true, 'message' => 'Pelanggan '.$name.' dihapus']);
    }

    public function customerDeleteAll(): JsonResponse
    {
        $query = Customer::where('type', 'ppp')->whereNotNull('pppoe_username');

        $deleted = 0;
        $query->get()->each(function (Customer $c) use (&$deleted): void {
            if ($c->odp_port_id) {
                $c->odpPort?->update(['status' => 'available']);
            }
            $c->delete();
            $deleted++;
        });

        return response()->json(['ok' => true, 'message' => "{$deleted} pelanggan dihapus", 'deleted' => $deleted]);
    }

    public function deviceParents(): JsonResponse
    {
        $parents = [];
        $push = function (string $type, string $name) use (&$parents): void {
            $parents[] = ['type' => $type, 'name' => $name];
        };

        Device::orderBy('type')->orderBy('name')
            ->whereIn('type', ['olt', 'odc', 'odp', 'otb', 'closure'])
            ->get(['type', 'name'])
            ->each(fn ($m) => $push(strtoupper($m->type), $m->name));

        return response()->json(['ok' => true, 'parents' => $parents]);
    }

    public function deviceSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'type' => ['required', 'string', 'in:onu,odp,htb,closure,odc,otb,olt,custom'],
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:online,offline'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'mac_address' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $extra = $request->input('attributes', []);
        $attributes = is_array($extra) ? array_filter($extra, fn ($v) => $v !== null && $v !== '') : [];

        $device = isset($data['id']) ? Device::find($data['id']) : null;

        if (! $device) {
            $device = new Device;
        }

        $device->type = $data['type'];

        if ($data['type'] === 'onu') {
            $device->name = trim($data['name'] ?? '') !== ''
                ? trim($data['name'])
                : (($data['serial_number'] ?? null)
                    ? $data['serial_number']
                    : 'ONU-'.strtoupper(uniqid()));
        } else {
            $device->name = trim($data['name'] ?? '');
        }

        foreach ([
            'status', 'serial_number', 'mac_address', 'brand', 'model', 'ip_address',
            'capacity', 'location', 'latitude', 'longitude', 'notes',
        ] as $field) {
            $device->{$field} = array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== ''
                ? $data[$field]
                : null;
        }

        $device->attributes = $attributes ?: null;
        $device->save();
        $this->flushMapMarkersCache();

        return response()->json([
            'ok' => true,
            'message' => 'Perangkat '.strtoupper($device->type).' "'.$device->name.'" disimpan',
            'device' => $this->devicePayload($device->fresh()),
        ]);
    }

    public function deviceCableSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'cable_path' => ['nullable', 'array'],
            'cable_path.*.0' => ['numeric'],
            'cable_path.*.1' => ['numeric'],
            'cable_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cable_meteor_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cable_width' => ['nullable', 'numeric', 'between:1,20'],
            'cable_curve' => ['nullable', 'boolean'],
            'cable_anim' => ['nullable', 'string', 'in:none,dash,glow-fast,glow-slow'],
            'clear' => ['nullable', 'boolean'],
        ]);

        $device = Device::find($data['id']);

        if (! $device) {
            return response()->json(['ok' => false, 'error' => 'Perangkat tidak ditemukan'], 404);
        }

        $attrs = is_array($device->attributes) ? $device->attributes : [];

        if (! empty($data['clear'])) {
            unset($attrs['cable_path']);
        } elseif (isset($data['cable_path']) && is_array($data['cable_path'])) {
            $path = [];
            foreach ($data['cable_path'] as $pt) {
                if (! is_array($pt) || count($pt) < 2) {
                    continue;
                }
                $lat = isset($pt[0]) ? (float) $pt[0] : null;
                $lng = isset($pt[1]) ? (float) $pt[1] : null;
                if (is_numeric($lat) && is_numeric($lng)) {
                    $path[] = [round($lat, 6), round($lng, 6)];
                }
            }
            if (count($path) >= 2) {
                $attrs['cable_path'] = $path;
            } else {
                unset($attrs['cable_path']);
            }
        }

        if (isset($data['cable_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $data['cable_color'])) {
            $attrs['cable_color'] = $data['cable_color'];
        } else {
            unset($attrs['cable_color']);
        }

        if (isset($data['cable_meteor_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $data['cable_meteor_color'])) {
            $attrs['cable_meteor_color'] = $data['cable_meteor_color'];
        } else {
            unset($attrs['cable_meteor_color']);
        }

        if (isset($data['cable_width']) && is_numeric($data['cable_width'])) {
            $w = (float) $data['cable_width'];
            if ($w >= 1 && $w <= 20) {
                $attrs['cable_width'] = $w;
            } else {
                unset($attrs['cable_width']);
            }
        } else {
            unset($attrs['cable_width']);
        }

        if (isset($data['cable_curve'])) {
            $attrs['cable_curve'] = ! empty($data['cable_curve']);
        } else {
            unset($attrs['cable_curve']);
        }

        if (array_key_exists('cable_anim', $data)) {
            if ($data['cable_anim'] !== null && in_array($data['cable_anim'], ['none', 'dash', 'glow-fast', 'glow-slow'], true)) {
                $attrs['cable_anim'] = $data['cable_anim'];
            } else {
                unset($attrs['cable_anim']);
            }
        }

        $device->attributes = $attrs ?: null;
        $device->save();
        $this->flushMapMarkersCache();

        return response()->json([
            'ok' => true,
            'message' => 'Jalur kabel disimpan',
            'device' => $this->devicePayload($device->fresh()),
        ]);
    }

    public function deviceStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'status' => ['required', 'string', 'in:online,offline'],
        ]);

        $device = Device::find($data['id']);

        if (! $device) {
            return response()->json(['ok' => false, 'error' => 'Perangkat tidak ditemukan'], 404);
        }

        $device->status = $data['status'];
        $device->save();
        $this->flushMapMarkersCache();

        return response()->json([
            'ok' => true,
            'message' => 'Status '.$device->name.': '.strtoupper($device->status),
            'device' => $this->devicePayload($device->fresh()),
        ]);
    }

    public function deviceDelete(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);
        $device = Device::find($data['id']);

        if (! $device) {
            return response()->json(['ok' => false, 'error' => 'Perangkat tidak ditemukan'], 404);
        }

        $device->delete();
        $this->flushMapMarkersCache();

        return response()->json(['ok' => true, 'message' => 'Perangkat dihapus']);
    }

    /* ── Card ONU pelanggan (klik marker pelanggan pada peta FTTH) ── */

    public function customerDetail(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $customer = Customer::with([
            'odp', 'package', 'odpPort',
            'onus' => fn ($q) => $q->fromOlt(),
        ])->find($data['id']);

        if (! $customer) {
            return response()->json(['ok' => false, 'error' => 'Pelanggan tidak ditemukan'], 404);
        }

        $onu = $customer->onus->first();
        $onuOltPort = null;
        if ($onu) {
            $onu->load(['oltPort.olt']);
            $onuOltPort = $onu->oltPort;
        }

        /* Pelanggan hotspot menyimpan username hotspot di kolom name (pppoe_username kosong) */
        $sessionUser = $customer->pppoe_username;
        if (($customer->type === 'hotspot' || $customer->type === 'hotspot_voucher') && empty($sessionUser)) {
            $sessionUser = $customer->name;
        }

        /* Coba beberapa kandidat username (name, pppoe_username, customer_code)
           karena username sesi hotspot di MikroTik bisa berupa customer_code. */
        $session = null;
        $candidates = array_unique(array_filter([
            $sessionUser,
            $customer->customer_code,
            $customer->pppoe_username,
            $customer->name,
        ]));
        foreach ($candidates as $cand) {
            $session = $this->findActiveSession($cand);
            if ($session) {
                break;
            }
        }

        /* Riwayat counter sesi (dipakai chart Live Traffic card ONU) —
           kunci sama dengan pppoeSession() agar history menyatu */
        $sessHistory = [];
        if ($session && $session['name'] && $session['bytes_in'] !== null && $session['bytes_out'] !== null) {
            $builtAt = ($this->activeSessionsIndex())['built_at'] ?? null;
            $sessHistory = $this->appendSessionHistory(
                (string) $session['name'],
                (int) $session['bytes_in'],
                (int) $session['bytes_out'],
                $builtAt,
            );
        }

        $lat = $customer->odp && $customer->odp->latitude !== null ? (float) $customer->odp->latitude : null;
        $lon = $customer->odp && $customer->odp->longitude !== null ? (float) $customer->odp->longitude : null;

        return response()->json([
            'ok' => true,
            'customer' => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'type' => $customer->type,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'location' => $customer->location,
                'status' => $customer->status,
                'due_date' => $customer->due_date ? (string) $customer->due_date : null,
                'package' => $customer->package?->name,
                'odp' => $customer->odp?->nama_odp,
                'odp_port' => $customer->odpPort?->port_number,
                'pppoe_username' => $customer->pppoe_username,
                'serial_number' => $customer->serial_number,
                'mac_address' => $customer->mac_address,
                'lat' => $lat,
                'lon' => $lon,
            ],
            'onu' => $onu ? [
                'id' => $onu->id,
                'onu_id' => $onu->onu_id,
                'serial_number' => $onu->serial_number,
                'mac_address' => $onu->mac_address,
                'status' => $onu->status,
                'rx_power' => $onu->rx_power,
                'tx_power' => $onu->tx_power,
                'distance' => $onu->distance,
                'uptime' => $onu->uptime,
                'slot' => $onuOltPort?->slot_number ?? $onu->slot_number,
                'port' => $onuOltPort?->port_number ?? $onu->port_number,
                'olt_name' => $onuOltPort?->olt?->name,
                'olt_brand' => $onuOltPort?->olt?->brand,
                'acs_device_id' => $onu->acs_device_id,
                'acs_status' => $onu->acs_status,
                'acs_last_inform' => $onu->acs_last_inform ? $onu->acs_last_inform->toIso8601String() : null,
                'acs_ip' => $onu->acs_ip,
                'acs_manufacturer' => $onu->acs_manufacturer,
                'acs_product_class' => $onu->acs_product_class,
                'acs_software_version' => $onu->acs_software_version,
            ] : null,
            'session' => $session,
            'session_history' => $sessHistory,
            'maps' => ($lat !== null && $lon !== null) ? 'https://www.google.com/maps?q='.$lat.','.$lon : null,
            'wa' => $this->waLink($customer->phone),
            'edit' => '/customer/'.$customer->customer_code.'/edit',
        ]);
    }

    /**
     * Cari IP sesi hotspot aktif untuk pelanggan: cocokkan MAC dulu, lalu
     * beberapa kandidat username (name, pppoe_username, customer_code).
     * Username hotspot di MikroTik umumnya = nama pelanggan.
     *
     * @param  array<string, string>  $hsIpMap  peta 'u:<user>' / 'm:<mac>' => ip
     */
    private function hotspotIpFor(array $hsIpMap, ?string $mac, ?Customer $c): ?string
    {
        $mac = str_replace(['-', ' '], ':', mb_strtolower((string) $mac));
        if ($mac !== '' && isset($hsIpMap['m:'.$mac])) {
            return $hsIpMap['m:'.$mac];
        }

        if (! $c) {
            return null;
        }

        foreach ([$c->name, $c->pppoe_username, $c->customer_code] as $u) {
            $u = mb_strtolower(trim((string) $u));
            if ($u !== '' && isset($hsIpMap['u:'.$u])) {
                return $hsIpMap['u:'.$u];
            }
        }

        return null;
    }

    /**
     * Indeks IP tersimpan pada Device peta (kolom ip_address) untuk ONU —
     * fallback bila tidak ada sesi PPPoE/hotspot aktif di MikroTik.
     * Kunci: nama device, serial, dan atribut pppoe_user/hotspot_user.
     *
     * @return array<string, string>
     */
    private function deviceIpIndex(): array
    {
        $idx = [];
        foreach (Device::where('type', 'onu')->get() as $d) {
            $ip = trim((string) $d->ip_address);
            if ($ip === '') {
                continue;
            }
            $attrs = is_array($d->attributes) ? $d->attributes : [];
            $keys = array_filter([
                mb_strtolower(trim((string) $d->name)),
                mb_strtolower(trim((string) $d->serial_number)),
                isset($attrs['pppoe_user']) ? mb_strtolower(trim((string) $attrs['pppoe_user'])) : '',
                isset($attrs['hotspot_user']) ? mb_strtolower(trim((string) $attrs['hotspot_user'])) : '',
            ]);
            foreach ($keys as $k) {
                $idx[$k] ??= $ip;
            }
        }

        return $idx;
    }

    private function storedIpFor(array $devIpIdx, ?Customer $c): ?string
    {
        if (! $c) {
            return null;
        }
        foreach ([$c->name, $c->serial_number, $c->pppoe_username] as $k) {
            $k = mb_strtolower(trim((string) $k));
            if ($k !== '' && isset($devIpIdx[$k])) {
                return $devIpIdx[$k];
            }
        }

        return null;
    }

    /**
     * Tabel ONU: agregasi pelanggan PPPoE + status OLT/ODP/HTB.
     */
    public function onuTable(): JsonResponse
    {
        $rows = $this->buildOnuTableRows();

        return response()->json(['ok' => true, 'rows' => $rows, 'total' => count($rows)]);
    }

    public function onuTablePrint(Request $request): HttpResponse
    {
        $rows = $this->buildOnuTableRows((string) $request->query('q', ''));
        $typeFilter = $request->query('type');
        if ($typeFilter === 'ppp') {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['type_onu'] ?? '') === 'PPPoE'));
        } elseif ($typeFilter === 'hotspot') {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['type_onu'] ?? '') === 'Hotspot'));
        }

        $settings = [
            'company_name' => Setting::get('company_name') ?: 'ALKONEKbill',
            'company_address' => Setting::get('company_address') ?: '',
            'company_phone' => Setting::get('company_phone') ?: '',
            'company_logo' => Setting::get('company_logo') ?: '',
        ];

        $pdf = Pdf::loadView('noc.features.onu-table-pdf', compact('rows', 'settings', 'typeFilter'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('tabel-onu'.($typeFilter ? '-'.$typeFilter : '').'.pdf');
    }

    public function onuTableExport(Request $request): HttpResponse
    {
        $rows = $this->buildOnuTableRows((string) $request->query('q', ''));
        $typeFilter = $request->query('type');
        if ($typeFilter === 'ppp') {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['type_onu'] ?? '') === 'PPPoE'));
        } elseif ($typeFilter === 'hotspot') {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['type_onu'] ?? '') === 'Hotspot'));
        }

        $lines = ["\xEF\xBB\xBF", implode(',', ['No', 'Nama', 'Type', 'Akun PPPoE', 'IP Address', 'Koordinat', 'HTB', 'ODP', 'OLT'])."\r\n"];

        foreach ($rows as $i => $r) {
            $lines[] = implode(',', [
                $i + 1,
                $this->csvField($r['nama']),
                $this->csvField($r['type_onu']),
                $this->csvField($r['pppoe_username']),
                $this->csvField($r['ip_address']),
                $this->csvField($r['koordinat']),
                $this->csvField($r['htb']),
                $this->csvField($r['odp']),
                $this->csvField($r['olt']),
            ])."\r\n";
        }

        return Response::make(implode('', $lines), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tabel-onu-'.date('Ymd-His').'.csv"',
        ]);
    }

    private function buildOnuTableRows(?string $q = null): array
    {
        $needle = mb_strtolower(trim((string) $q));

        $rows = Cache::remember('onu_table_rows', 60, function () {

            $customers = Customer::with([
                'odp',
                'odpPort.odp',
                'onus' => fn ($q2) => $q2->fromOlt(),
                'onus.oltPort.olt',
                'onus.odpPort.odp',
                'mikrotikOnus.oltPort.olt',
                'mikrotikOnus.odpPort.odp',
            ])->whereIn('type', ['ppp', 'hotspot'])
                ->where(fn ($q3) => $q3->whereNotNull('pppoe_username')->orWhere('type', 'hotspot'))
                ->orderBy('name')
                ->get();

            $deviceByPppoe = [];
            $deviceRows = [];
            foreach (Device::whereIn('type', ['onu', 'htb'])->orderBy('name')->get() as $d) {
                $attrs = is_array($d->attributes) ? $d->attributes : [];
                $pppoe = isset($attrs['pppoe_user']) ? (string) $attrs['pppoe_user'] : '';
                $isHotspot = ! empty($attrs['hotspot']);
                if ($pppoe === '' && ! $isHotspot) {
                    continue;
                }
                if ($pppoe !== '' && ! isset($deviceByPppoe[$pppoe])) {
                    $deviceByPppoe[$pppoe] = $d;
                }
                $deviceRows[] = [$d, $pppoe, $attrs];
            }

            /* Gabung PPPoE + hotspot active dari MikroTik dalam satu loop (cached 60 s) */
            $mikrotikData = Cache::remember('mikrotik_active_map', 60, function () {
                $pppoe = [];
                $hotspot = [];
                $routers = MikrotikRouter::where('is_active', true)->orderBy('id')->get();
                foreach ($routers as $router) {
                    try {
                        $svc = new RouterCommandService($router);
                        $pppResult = $svc->getPppActive();
                        if ($pppResult->isSuccess() && is_array($pppResult->getData())) {
                            foreach ($pppResult->getData() as $s) {
                                $name = (string) ($s['name'] ?? '');
                                if ($name !== '' && ! isset($pppoe[$name])) {
                                    $pppoe[$name] = $s['address'] ?? null;
                                }
                            }
                        }
                        $hsResult = $svc->getHotspotActive();
                        if ($hsResult->isSuccess() && is_array($hsResult->getData())) {
                            foreach ($hsResult->getData() as $hs) {
                                $ip = $hs['address'] ?? null;
                                if (! $ip) {
                                    continue;
                                }
                                $user = mb_strtolower((string) ($hs['user'] ?? ''));
                                if ($user) {
                                    $hotspot['u:'.$user] = $ip;
                                }
                                $mac = str_replace(['-', ' '], ':', mb_strtolower((string) ($hs['mac-address'] ?? '')));
                                if ($mac) {
                                    $hotspot['m:'.$mac] = $ip;
                                }
                            }
                        }
                    } catch (\Throwable) {
                        // router offline / tunnel mati
                    }
                }

                return ['pppoe' => $pppoe, 'hotspot' => $hotspot];
            });
            $ipMap = $mikrotikData['pppoe'];
            $hsIpMap = $mikrotikData['hotspot'];
            $devIpIdx = $this->deviceIpIndex();

            /* Index perangkat berdasarkan type:nama untuk traversal rantai topologi
               (ONU -> ODP -> OLT) melalui atribut `induk`.
               Kunci dinormalisasi (hanya A-Z0-9) agar "ODP-ALK-MLN04"
               cocok dengan perangkat "ODP-ALK-MLN/04". */
            $deviceIndex = [];
            foreach (Device::orderBy('name')->get() as $dv) {
                $deviceIndex[strtoupper($dv->type).':'.self::normDeviceKey((string) $dv->name)] = $dv;
            }

            /* Kolom OLT pada Tabel ONU menampilkan tipe perangkat (brand + model),
               cth: C-DATA FD1601S — prioritas Device peta (type=olt) sama seperti
               card Perangkat, fallback ke tabel olts. Nama dipakai bila tipe kosong. */
            $oltTypeIdx = [];
            foreach (Device::where('type', 'olt')->get() as $od) {
                $t = trim(trim((string) $od->brand).' '.trim((string) $od->model));
                if ($t !== '') {
                    $oltTypeIdx[self::normDeviceKey((string) $od->name)] = $t;
                }
            }
            foreach (Olt::all() as $oo) {
                $t = trim(trim((string) $oo->brand).' '.trim((string) $oo->model));
                $k = self::normDeviceKey((string) $oo->name);
                if ($t !== '' && ! isset($oltTypeIdx[$k])) {
                    $oltTypeIdx[$k] = $t;
                }
            }
            $oltTypeFor = function (?string $name) use ($oltTypeIdx): ?string {
                if (! $name) {
                    return null;
                }
                $k = self::normDeviceKey($name);
                if (isset($oltTypeIdx[$k])) {
                    return $oltTypeIdx[$k];
                }
                foreach ($oltTypeIdx as $nk => $t) {
                    if ($nk !== '' && (str_contains($nk, $k) || str_contains($k, $nk))) {
                        return $t;
                    }
                }

                return null;
            };

            $billingPppoe = [];
            $rows = [];
            foreach ($customers as $c) {
                if ($c->pppoe_username) {
                    $billingPppoe[$c->pppoe_username] = true;
                }

                $onu = $c->onus->first();
                $mikrotikOnu = $onu ? null : $c->mikrotikOnus->first();
                $resolveOnu = $onu ?? $mikrotikOnu;
                $device = $c->pppoe_username ? ($deviceByPppoe[$c->pppoe_username] ?? null) : null;
                $topo = $this->resolveDeviceTopology($device, $deviceIndex);

                $typeOnu = $c->type === 'hotspot' ? 'Hotspot' : 'PPPoE';

                /* IP address: PPPoE dari session aktif, hotspot dari hotspot active,
                   lalu fallback ke IP tersimpan pada Device peta */
                if ($typeOnu === 'Hotspot') {
                    $ip = $this->hotspotIpFor($hsIpMap, $onu?->mac_address ?? $c->mac_address, $c)
                        ?? $this->storedIpFor($devIpIdx, $c);
                } else {
                    $ip = $ipMap[$c->pppoe_username] ?? null;
                }

                /* --- ODP resolution --- */
                $odpName = $c->odp?->nama_odp
                    ?? $c->odpPort?->odp?->nama_odp
                    ?? $resolveOnu?->odpPort?->odp?->nama_odp
                    ?? $topo['odp'];

                /* --- OLT resolution: utamakan rantai topologi perangkat --- */
                $oltName = $topo['olt'];

                /* Fallback: walk up Device induk chain from ODP → ODC → OLT */
                if (! $oltName && $odpName) {
                    $curType = 'ODP';
                    $curName = $odpName;
                    while ($curName !== '' && ! $oltName) {
                        $curDev = $deviceIndex[strtoupper($curType).':'.self::normDeviceKey($curName)] ?? null;
                        if (! $curDev) {
                            break;
                        }
                        $ca = is_array($curDev->attributes) ? $curDev->attributes : [];
                        $ci = isset($ca['induk']) ? (string) $ca['induk'] : '';
                        if ($ci === '') {
                            break;
                        }
                        $cp = preg_split('/\s+[-–—]\s+/u', $ci, 2);
                        $ctype = isset($cp[0]) ? strtoupper(trim($cp[0])) : '';
                        $cname = isset($cp[1]) ? trim($cp[1]) : '';
                        if ($ctype === 'OLT' && $cname !== '') {
                            $oltName = $cname;
                            break;
                        }
                        $curType = $ctype;
                        $curName = $cname;
                    }
                }

                /* Terakhir: nama OLT dari relasi onus -> olt_port -> olt */
                if (! $oltName) {
                    $oltName = $resolveOnu?->oltPort?->olt?->name;
                }

                /* --- Koordinat: ODP relasi -> ODP via port ONU -> perangkat pelanggan --- */
                $koordinat = $c->odp?->koordinat
                    ?? $c->odpPort?->odp?->koordinat
                    ?? $resolveOnu?->odpPort?->odp?->koordinat;
                if ($koordinat === null && $device && $device->latitude !== null && $device->longitude !== null) {
                    $koordinat = trim((string) $device->latitude).', '.trim((string) $device->longitude);
                }
                if ($koordinat === null && $odpName) {
                    $odpDev = $deviceIndex['ODP:'.self::normDeviceKey($odpName)] ?? null;
                    if ($odpDev && $odpDev->latitude !== null && $odpDev->longitude !== null) {
                        $koordinat = trim((string) $odpDev->latitude).', '.trim((string) $odpDev->longitude);
                    }
                }

                $rows[] = [
                    'id' => $c->id,
                    'customer_code' => $c->customer_code,
                    'nama' => $c->name,
                    'type_onu' => $typeOnu,
                    'pppoe_username' => $typeOnu === 'Hotspot' ? '-' : ($c->pppoe_username ?? '-'),
                    'ip_address' => $ip,
                    'koordinat' => $koordinat,
                    'htb' => ($device && $device->type === 'htb') ? $device->name : '-',
                    'odp' => $odpName,
                    'olt' => $oltTypeFor($oltName) ?? $oltName,
                ];
            }

            $emitted = [];
            foreach ($deviceRows as [$d, $pppoe, $attrs]) {
                if (isset($billingPppoe[$pppoe]) || ($pppoe !== '' && isset($emitted[$pppoe]))) {
                    continue;
                }
                if ($pppoe !== '') {
                    $emitted[$pppoe] = true;
                }

                $isHotspot = ! empty($attrs['hotspot']);
                $typeOnu = $isHotspot ? 'Hotspot' : 'PPPoE';
                $koordinat = $d->latitude !== null && $d->longitude !== null
                    ? trim((string) $d->latitude).', '.trim((string) $d->longitude)
                    : null;

                $topo = $this->resolveDeviceTopology($d, $deviceIndex);

                $ip = $pppoe !== '' ? ($ipMap[$pppoe] ?? null) : null;

                $rows[] = [
                    'id' => $d->id,
                    'customer_code' => null,
                    'nama' => $d->name,
                    'type_onu' => $typeOnu,
                    'pppoe_username' => $pppoe,
                    'ip_address' => $ip,
                    'koordinat' => $koordinat,
                    'htb' => $d->type === 'htb' ? $d->name : '-',
                    'odp' => $topo['odp'],
                    'olt' => $oltTypeFor($topo['olt']) ?? $topo['olt'],
                ];
            }

            return $rows;

        }); // end Cache::remember

        if ($needle !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => str_contains(
                mb_strtolower(implode(' ', array_filter([
                    $r['nama'],
                    $r['type_onu'],
                    $r['pppoe_username'],
                    $r['ip_address'],
                    $r['koordinat'],
                    $r['htb'],
                    $r['odp'],
                    $r['olt'],
                ]))),
                $needle
            )));
        }

        return $rows;
    }

    /**
     * Normalisasi nama perangkat untuk kunci index topologi:
     * buang semua karakter non-alphanumeric lalu uppercase,
     * sehingga "ODP-ALK-MLN/04" == "ODP-ALK-MLN04" == "odp alk mln 04".
     */
    private static function normDeviceKey(string $s): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim($s)));
    }

    /**
     * Normalisasi username PPPoE/Hotspot untuk pencocokan antar sumber.
     * MikroTik sering mengembalikan username lengkap dengan realm
     * (mis. "nanangluki@alkonek.ppp") padahal di DB hanya tersimpan
     * "nanangluki". Buang suffix "@domain" agar keduanya cocok.
     */
    private static function normPppoeUser(string $s): string
    {
        $s = trim((string) $s);
        if ($s === '') {
            return '';
        }
        $at = strpos($s, '@');
        if ($at !== false) {
            $s = substr($s, 0, $at);
        }

        return mb_strtolower($s);
    }

    /**
     * Normalisasi MAC address ke bentuk lowercase dipisah titik dua,
     * untuk pencocokan sesi hotspot aktif <-> ONU/customer.
     */
    private static function normMac(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        $s = preg_replace('/[^a-f0-9]/i', '', mb_strtolower((string) $s));

        return strlen($s) === 12 ? implode(':', str_split($s, 2)) : '';
    }

    /**
     * Resolve ODP & OLT untuk sebuah perangkat dengan menelusuri rantai
     * topologi lewat atribut `induk` (mis. ONU -> "ODP — nama" -> "OLT — nama").
     */
    private function resolveDeviceTopology(?Device $device, array $deviceIndex): array
    {
        $odp = null;
        $olt = null;

        if ($device) {
            $attrs = is_array($device->attributes) ? $device->attributes : [];
            $induk = isset($attrs['induk']) ? (string) $attrs['induk'] : '';

            if ($induk !== '') {
                $parts = preg_split('/\s+[-–—]\s+/u', $induk, 2);
                $ptype = isset($parts[0]) ? strtoupper(trim($parts[0])) : '';
                $pname = isset($parts[1]) ? trim($parts[1]) : '';

                if ($ptype === 'ODP' && $pname !== '') {
                    $odp = $pname;
                    $curType = 'ODP';
                    $curName = $pname;
                    while ($curName !== '' && ! $olt) {
                        $dev = $deviceIndex[strtoupper($curType).':'.self::normDeviceKey($curName)] ?? null;
                        if (! $dev) {
                            break;
                        }
                        $da = is_array($dev->attributes) ? $dev->attributes : [];
                        $dinduk = isset($da['induk']) ? (string) $da['induk'] : '';
                        if ($dinduk === '') {
                            break;
                        }
                        $dp = preg_split('/\s+[-–—]\s+/u', $dinduk, 2);
                        $dtype = isset($dp[0]) ? strtoupper(trim($dp[0])) : '';
                        $dname = isset($dp[1]) ? trim($dp[1]) : '';
                        if ($dtype === 'OLT' && $dname !== '') {
                            $olt = $dname;
                            break;
                        }
                        $curType = $dtype;
                        $curName = $dname;
                    }
                } elseif ($ptype === 'OLT' && $pname !== '') {
                    $olt = $pname;
                }
            }
        }

        return ['odp' => $odp, 'olt' => $olt];
    }

    public function customerPing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'ip' => ['nullable', 'string', 'max:255'],
        ]);

        $ip = null;
        $label = null;
        if (! empty($data['id'])) {
            $customer = Customer::find($data['id']);

            if (! $customer) {
                return response()->json(['ok' => false, 'error' => 'Pelanggan tidak ditemukan'], 404);
            }

            $sessionUser = $customer->pppoe_username;
            if (($customer->type === 'hotspot' || $customer->type === 'hotspot_voucher') && empty($sessionUser)) {
                $sessionUser = $customer->name;
            }
            $session = $this->findActiveSession($sessionUser);
            $ip = $session['ip'] ?? null;
            $label = $customer->pppoe_username ?: $customer->customer_code;
        } elseif (! empty($data['ip'])) {
            $ip = $data['ip'];
            $label = $ip;
        }

        if (! $ip) {
            return response()->json([
                'ok' => false,
                'error' => 'Tidak ada IP untuk di-ping'.($label ? ' ('.$label.')' : '').' (client offline / tidak ada IP)',
            ], 422);
        }

        try {
            $result = app(PingMonitorService::class)->ping($ip, 3, 5);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Gagal menjalankan ping: '.$e->getMessage()], 500);
        }

        return response()->json([
            'ok' => true,
            'host' => $ip,
            'result' => $result,
        ]);
    }

    public function onuReboot(Request $request): JsonResponse
    {
        $data = $request->validate(['onu_id' => ['required', 'integer']]);

        $onu = Onu::with('oltPort.olt')->find($data['onu_id']);

        if (! $onu) {
            return response()->json(['ok' => false, 'error' => 'ONU tidak ditemukan'], 404);
        }

        $olt = $onu->oltPort?->olt;

        if (! $olt) {
            return response()->json(['ok' => false, 'error' => 'ONU tidak terhubung ke OLT manapun'], 422);
        }

        try {
            $connector = OltConnectorFactory::make($olt->brand, $olt);
            $connector->connect($olt->ip_address, $olt->ssh_port, $olt->username, $olt->password);
            $result = $connector->rebootOnu($onu->onu_id);
            $connector->disconnect();

            return response()->json([
                'ok' => (bool) ($result['success'] ?? false),
                'message' => $result['message'] ?? 'ONU '.$onu->onu_id.' di-reboot',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('onuReboot gagal: '.$e->getMessage());

            return response()->json(['ok' => false, 'error' => 'Reboot gagal: '.$e->getMessage()], 502);
        }
    }

    public function customerAcs(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $customer = Customer::with('onus')->find($data['id']);

        if (! $customer) {
            return response()->json(['ok' => false, 'error' => 'Pelanggan tidak ditemukan'], 404);
        }

        $onu = $customer->onus->first();

        if (! $onu || ! $onu->acs_device_id) {
            return response()->json(['ok' => false, 'error' => 'Belum ada perangkat ACS tersambung untuk pelanggan ini'], 422);
        }

        try {
            $dev = app(IGenieACSClient::class)->device($onu->acs_device_id, [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Mode',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassPhrase',
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.AssociatedDevice',
                'InternetGatewayDevice.LANDevice.1.Hosts.Host',
                'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress',
                'InternetGatewayDevice.DeviceInfo.Manufacturer',
                'InternetGatewayDevice.DeviceInfo.ProductClass',
                'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('customerAcs gagal: '.$e->getMessage());

            return response()->json(['ok' => false, 'error' => 'Gagal ambil data ACS: '.$e->getMessage()], 502);
        }

        $val = function (string $path) use ($dev) {
            $v = is_array($dev) ? ($dev[$path] ?? null) : null;

            return is_array($v) ? ($v['value'] ?? null) : $v;
        };

        $objCount = function (string $path) use ($dev) {
            $v = is_array($dev) ? ($dev[$path] ?? null) : null;
            if (! is_array($v)) {
                return 0;
            }
            $n = 0;
            foreach ($v as $k => $iv) {
                if ($k === '_id' || $k === '_lastInform' || $k === '_object') {
                    continue;
                }
                if (is_array($iv)) {
                    $n++;
                }
            }

            return $n;
        };

        return response()->json([
            'ok' => true,
            'device_id' => $onu->acs_device_id,
            'acs' => [
                'ssid' => $val('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID'),
                'wifi_enabled' => $val('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable'),
                'channel' => $val('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel'),
                'mode' => $val('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Mode'),
                'wifi_password' => $val('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassPhrase'),
                'wlan_clients' => $objCount('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.AssociatedDevice'),
                'lan_clients' => $objCount('InternetGatewayDevice.LANDevice.1.Hosts.Host'),
                'external_ip' => $val('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress'),
                'manufacturer' => $val('InternetGatewayDevice.DeviceInfo.Manufacturer'),
                'product_class' => $val('InternetGatewayDevice.DeviceInfo.ProductClass'),
                'software_version' => $val('InternetGatewayDevice.DeviceInfo.SoftwareVersion'),
            ],
        ]);
    }

    public function customerAcsSet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'ssid' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'max:64'],
        ]);

        $customer = Customer::with('onus')->find($data['id']);

        if (! $customer) {
            return response()->json(['ok' => false, 'error' => 'Pelanggan tidak ditemukan'], 404);
        }

        $onu = $customer->onus->first();

        if (! $onu || ! $onu->acs_device_id) {
            return response()->json(['ok' => false, 'error' => 'Belum ada perangkat ACS tersambung untuk pelanggan ini'], 422);
        }

        $params = [];
        if (($data['ssid'] ?? '') !== '') {
            $params[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $data['ssid']];
        }
        if (($data['password'] ?? '') !== '') {
            $params[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassPhrase', $data['password']];
        }

        if (empty($params)) {
            return response()->json(['ok' => false, 'error' => 'Tidak ada perubahan WiFi dikirim'], 422);
        }

        try {
            app(IGenieACSClient::class)->setParameterValues($onu->acs_device_id, $params);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('customerAcsSet gagal: '.$e->getMessage());

            return response()->json(['ok' => false, 'error' => 'Gagal set WiFi: '.$e->getMessage()], 502);
        }

        return response()->json(['ok' => true, 'message' => 'WiFi diperbarui, ONU akan menginformasi ulang dalam beberapa detik']);
    }

    public function customerDuplicate(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $src = Customer::find($data['id']);

        if (! $src) {
            return response()->json(['ok' => false, 'error' => 'Pelanggan tidak ditemukan'], 404);
        }

        $copy = new Customer;
        $copy->tenant_id = $src->tenant_id;
        $copy->type = $src->type;
        $copy->name = $src->name.' (Duplikat)';
        $copy->location = $src->location;
        $copy->phone = $src->phone;
        $copy->email = $src->email;
        $copy->nik = $src->nik;
        $copy->package_id = $src->package_id;
        $copy->odp_id = $src->odp_id;
        $copy->odp_port_id = null;
        $copy->original_ppp_profile = $src->original_ppp_profile;
        $copy->status = 'active';
        $copy->save();

        return response()->json([
            'ok' => true,
            'message' => 'Duplikat dibuat: '.$copy->customer_code,
            'customer' => [
                'id' => $copy->id,
                'customer_code' => $copy->customer_code,
                'name' => $copy->name,
            ],
            'edit' => '/customer/'.$copy->customer_code.'/edit',
        ]);
    }

    /**
     * Indeks sesi aktif (PPPoE + hotspot) semua router, cache 5 detik.
     * Satu kali fetch per jendela 5 s dipakai bersama oleh customerDetail(),
     * pppoeSession(), dsb. sehingga pencarian kandidat username menjadi
     * lookup array — bukan puluhan panggilan REST berurutan (sumber lambat
     * 15-20 detik saat membuka card ONU).
     *
     * @return array{ppp: array<string, array>, hs: array<string, array>, hs_ns: array<string, array>}
     */
    private function activeSessionsIndex(): array
    {
        return Cache::remember('mt_sessions_idx', 3, function (): array {
            $ppp = [];
            $hs = [];
            $hsNs = [];

            foreach (MikrotikRouter::where('is_active', true)->orderBy('id')->get() as $router) {
                try {
                    $cmd = new RouterCommandService($router);

                    /* Counter interface diambil SEKALI per router — fallback
                       byte utk sesi yang tidak menyertakan bytes-in/out */
                    $ifMap = [];
                    $ifs = $cmd->getInterfaces();
                    if ($ifs->isSuccess() && is_array($ifs->toArray())) {
                        foreach ($ifs->toArray() as $i) {
                            $nm = trim((string) ($i['name'] ?? ''), '<>');
                            if ($nm !== '') {
                                $ifMap[mb_strtolower($nm)] = $i;
                            }
                        }
                    }

                    /* PPPoE aktif: kunci = username ternormalisasi
                       (lowercase, tanpa suffix @realm) */
                    $act = $cmd->getPppActive();
                    if ($act->isSuccess() && is_array($act->getData())) {
                        foreach ($act->getData() as $s) {
                            $name = trim((string) ($s['name'] ?? ''));
                            if ($name === '') {
                                continue;
                            }
                            $bytesIn = isset($s['bytes-in']) ? (int) $s['bytes-in'] : null;
                            $bytesOut = isset($s['bytes-out']) ? (int) $s['bytes-out'] : null;

                            if ($bytesIn === null || $bytesOut === null) {
                                /* REST /ppp/active kerap tanpa bytes; ambil dari
                                   counter interface <pppoe-user@realm> */
                                $plain = mb_strtolower(explode('@', $name)[0]);
                                $if = $ifMap[mb_strtolower('pppoe-'.$name)]
                                    ?? $ifMap['pppoe-'.$plain]
                                    ?? null;
                                if ($if !== null) {
                                    $bytesIn = (int) ($if['rx-byte'] ?? 0);
                                    $bytesOut = (int) ($if['tx-byte'] ?? 0);
                                }
                            }

                            $ppp[self::normPppoeUser($name)] = [
                                'name' => $name,
                                'ip' => $s['address'] ?? null,
                                'caller_id' => $s['caller-id'] ?? null,
                                'uptime' => $s['uptime'] ?? null,
                                'bytes_in' => $bytesIn ?? 0,
                                'bytes_out' => $bytesOut ?? 0,
                                'router_name' => $router->name,
                            ];
                        }
                    }

                    /* Hotspot aktif: kunci = user lowercase + varian tanpa spasi
                       (MikroTik kerap mencetak ulang user dengan spasi dibuang) */
                    $hot = $cmd->getHotspotActive();
                    if ($hot->isSuccess() && is_array($hot->getData())) {
                        foreach ($hot->getData() as $s) {
                            $u = trim((string) ($s['user'] ?? ''));
                            if ($u === '') {
                                continue;
                            }
                            $sess = [
                                'name' => $u,
                                'ip' => $s['address'] ?? null,
                                'caller_id' => $s['mac-address'] ?? null,
                                'uptime' => $s['uptime'] ?? null,
                                'bytes_in' => isset($s['bytes-in']) ? (int) $s['bytes-in'] : null,
                                'bytes_out' => isset($s['bytes-out']) ? (int) $s['bytes-out'] : null,
                                'router_name' => $router->name,
                            ];
                            $k = mb_strtolower($u);
                            $hs[$k] = $sess;
                            $hsNs[preg_replace('/\s+/', '', $k)] = $sess;
                        }
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return ['ppp' => $ppp, 'hs' => $hs, 'hs_ns' => $hsNs, 'built_at' => time()];
        });
    }

    private function findActiveSession(?string $username): ?array
    {
        if (! $username) {
            return null;
        }

        $idx = $this->activeSessionsIndex();

        /* PPPoE: cocokkan username ternormalisasi
           (case-insensitive, suffix @realm dibuang) */
        $key = self::normPppoeUser($username);
        if ($key !== '' && isset($idx['ppp'][$key])) {
            return $idx['ppp'][$key];
        }

        /* Hotspot: trim + case-insensitive, plus varian tanpa spasi */
        $hKey = mb_strtolower(trim((string) $username));
        if ($hKey !== '' && isset($idx['hs'][$hKey])) {
            return $idx['hs'][$hKey];
        }
        $hKeyNs = preg_replace('/\s+/', '', $hKey);
        if ($hKeyNs !== '' && $hKeyNs !== $hKey && isset($idx['hs_ns'][$hKeyNs])) {
            return $idx['hs_ns'][$hKeyNs];
        }

        return null;
    }

    private function waLink(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits;
    }

    private ?array $onusBySerialCache = null;

    private ?array $onusByOnuIdCache = null;

    private ?array $customersByPppoeCache = null;

    private ?array $customersByNameCache = null;

    private ?array $customersBySerialCache = null;

    private array $customersByIdCache = [];

    private function onusBySerial(): array
    {
        if ($this->onusBySerialCache === null) {
            $this->onusBySerialCache = Onu::whereNotNull('customer_id')
                ->get()
                ->keyBy(fn ($o) => strtolower(trim((string) ($o->serial_number ?? ''))))
                ->all();
        }

        return $this->onusBySerialCache;
    }

    private function onusByOnuId(): array
    {
        if ($this->onusByOnuIdCache === null) {
            $this->onusByOnuIdCache = Onu::whereNotNull('customer_id')
                ->whereNotNull('onu_id')
                ->get()
                ->keyBy(fn ($o) => strtolower(trim((string) ($o->onu_id ?? ''))))
                ->all();
        }

        return $this->onusByOnuIdCache;
    }

    private function customersByPppoe(): array
    {
        if ($this->customersByPppoeCache === null) {
            $this->customersByPppoeCache = Customer::query()
                ->whereNotNull('pppoe_username')
                ->get()
                ->keyBy(fn ($c) => self::normPppoeUser((string) $c->pppoe_username))
                ->all();
        }

        return $this->customersByPppoeCache;
    }

    private function customersByName(): array
    {
        if ($this->customersByNameCache === null) {
            $this->customersByNameCache = Customer::query()
                ->select(['id', 'type', 'name'])
                ->get()
                ->keyBy(fn ($c) => strtolower(trim((string) $c->name)))
                ->all();
        }

        return $this->customersByNameCache;
    }

    private function customersBySerial(): array
    {
        if ($this->customersBySerialCache === null) {
            $this->customersBySerialCache = Customer::query()
                ->whereNotNull('serial_number')
                ->get()
                ->keyBy(fn ($c) => strtolower(trim((string) $c->serial_number)))
                ->all();
        }

        return $this->customersBySerialCache;
    }

    private function customerById(int $id): ?Customer
    {
        if (! array_key_exists($id, $this->customersByIdCache)) {
            $this->customersByIdCache[$id] = Customer::find($id);
        }

        return $this->customersByIdCache[$id];
    }

    private function resolveOnuCustomer(Device $d): array
    {
        if (strtolower($d->type) !== 'onu') {
            return [null, null];
        }
        $serial = $d->serial_number ? strtolower(trim((string) $d->serial_number)) : null;
        $attrs = is_array($d->attributes) ? $d->attributes : [];

        /* 1. Serial ONU -> pelanggan (kolom Customer.serial_number) */
        if ($serial) {
            $c = $this->customersBySerial()[$serial] ?? null;
            if ($c) {
                return [$c->id, $c->type];
            }
        }

        /* 2. Bridge via tabel onus: ONU fisik (serial) -> pelanggan terhubung */
        if ($serial) {
            $onu = $this->onusBySerial()[$serial] ?? null;
            if ($onu && $onu->customer_id && ($c = $this->customerById((int) $onu->customer_id))) {
                return [$c->id, $c->type];
            }
        }

        /* 3. onu_id (ID OLT) -> pelanggan via tabel onus */
        $onuId = ! empty($attrs['onu_id']) ? strtolower(trim((string) $attrs['onu_id'])) : null;
        if ($onuId) {
            $onu = $this->onusByOnuId()[$onuId] ?? null;
            if ($onu && $onu->customer_id && ($c = $this->customerById((int) $onu->customer_id))) {
                return [$c->id, $c->type];
            }
        }

        /* 4. Atribut pppoe/hotspot + nama device -> pelanggan */
        $cust = null;
        if (! empty($attrs['pppoe_user'])) {
            $cust = $this->customersByPppoe()[self::normPppoeUser((string) $attrs['pppoe_user'])] ?? null;
        }
        if (! $cust && ! empty($attrs['hotspot_user'])) {
            $cust = $this->customersByPppoe()[self::normPppoeUser((string) $attrs['hotspot_user'])] ?? null;
        }
        if (! $cust && $d->name) {
            $cust = $this->customersByName()[strtolower(trim((string) $d->name))] ?? null;
        }
        if (! $cust) {
            return [null, null];
        }

        return [$cust->id, $cust->type];
    }

    private function devicePayload(Device $d): array
    {
        $onu = $this->resolveOnuCustomer($d);

        return [
            'id' => $d->id,
            'type' => $d->type,
            'type_label' => strtoupper($d->type),
            'status' => $d->status,
            'name' => $d->name,
            'brand' => $d->brand,
            'model' => $d->model,
            'serial_number' => $d->serial_number,
            'mac_address' => $d->mac_address,
            'ip_address' => $d->ip_address,
            'capacity' => $d->capacity,
            'location' => $d->location,
            'latitude' => $d->latitude !== null ? (float) $d->latitude : null,
            'longitude' => $d->longitude !== null ? (float) $d->longitude : null,
            'attributes' => $d->attributes ?: (object) [],
            'notes' => $d->notes,
            'customer_id' => $onu[0],
            'onu_type' => $onu[1],
        ];
    }

    private function restoreRouters(array $content): JsonResponse
    {
        $rows = $content['routers'] ?? (isset($content[0]) ? $content : null);

        if (! is_array($rows)) {
            return response()->json(['ok' => false, 'error' => 'Tidak ditemukan data router (key "routers")'], 422);
        }

        $restored = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $host = trim((string) ($row['host'] ?? $row['local_ip'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($host === '' && $name === '') {
                continue;
            }

            $router = MikrotikRouter::withTrashed()
                ->where(function ($q) use ($host, $name) {
                    if ($host !== '') {
                        $q->orWhere('host', $host);
                    }
                    if ($name !== '') {
                        $q->orWhere('name', $name);
                    }
                })
                ->first();

            if (! $router) {
                $router = new MikrotikRouter;
                $router->name = $name ?: 'Mikrotik '.$host;
                $router->host = $host ?: $name;
                $router->is_active = true;
                $router->username = $row['username'] ?? '';
                $router->password = $row['password'] ?? '';
            } elseif ($router->trashed()) {
                $router->restore();
            }

            foreach ([
                'local_ip', 'local_port', 'port', 'ssh_port', 'username', 'password',
                'connection_mode', 'connection_type', 'timeout', 'location',
                'latitude', 'longitude', 'is_active',
            ] as $field) {
                if (array_key_exists($field, $row)) {
                    $router->{$field} = $row[$field];
                }
            }

            $router->save();
            $restored++;
        }

        return response()->json(['ok' => true, 'message' => $restored.' router dipulihkan']);
    }

    private function restoreDatabase(array $content): JsonResponse
    {
        $rows = array_merge(
            is_array($content['pppoe'] ?? null) ? $content['pppoe'] : [],
            is_array($content['hotspot'] ?? null) ? $content['hotspot'] : [],
        );

        if (empty($rows) && isset($content[0])) {
            $rows = $content;
        }

        if (empty($rows)) {
            return response()->json(['ok' => false, 'error' => 'Tidak ditemukan data pelanggan (key "pppoe"/"hotspot")'], 422);
        }

        $restored = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = trim((string) ($row['customer_code'] ?? ''));

            if ($code === '') {
                $skipped++;

                continue;
            }

            $customer = Customer::where('customer_code', $code)->first();

            if (! $customer) {
                $customer = new Customer;
                $customer->customer_code = $code;
                $customer->status = 'active';
            }

            foreach ([
                'name', 'type', 'phone', 'email', 'nik', 'location',
                'pppoe_username', 'pppoe_password', 'serial_number', 'mac_address',
                'original_ppp_profile', 'status',
            ] as $field) {
                if (array_key_exists($field, $row)) {
                    $customer->{$field} = $row[$field];
                }
            }

            if (! empty($row['due_date'])) {
                $customer->due_date = $row['due_date'];
            }

            if (! empty($row['package'])) {
                $pkg = Package::where('name', $row['package'])->first();
                if ($pkg) {
                    $customer->package_id = $pkg->id;
                }
            }

            if (! empty($row['odp'])) {
                $odp = Odp::where('nama_odp', $row['odp'])->first();
                if ($odp) {
                    $customer->odp_id = $odp->id;
                }
            }

            $customer->save();
            $restored++;
        }

        return response()->json([
            'ok' => true,
            'message' => $restored.' pelanggan dipulihkan'.($skipped ? " ({$skipped} dilewati tanpa kode)" : ''),
        ]);
    }

    private function applyCoordinate(string $name, float $lat, float $lon): bool
    {
        if ($olt = Olt::where('name', $name)->first()) {
            $olt->update(['latitude' => $lat, 'longitude' => $lon]);

            return true;
        }

        if ($router = MikrotikRouter::where('name', $name)->first()) {
            $router->update(['latitude' => $lat, 'longitude' => $lon]);

            return true;
        }

        $koordinat = sprintf('%.7f', $lat).','.sprintf('%.7f', $lon);

        if ($odc = Odc::where('nama_odc', $name)->first()) {
            $odc->update(['koordinat' => $koordinat]);

            return true;
        }

        if ($odp = Odp::where('nama_odp', $name)->first()) {
            $odp->update(['koordinat' => $koordinat]);

            return true;
        }

        return false;
    }

    private function buildFullBackup(): array
    {
        $customers = Customer::with(['package', 'odp'])->orderBy('customer_code')->get();

        $pppoe = [];
        $hotspot = [];

        foreach ($customers as $c) {
            $row = [
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'type' => $c->type,
                'phone' => $c->phone,
                'email' => $c->email,
                'nik' => $c->nik,
                'location' => $c->location,
                'package' => $c->package?->name,
                'pppoe_username' => $c->pppoe_username,
                'pppoe_password' => $c->pppoe_password,
                'serial_number' => $c->serial_number,
                'mac_address' => $c->mac_address,
                'original_ppp_profile' => $c->original_ppp_profile,
                'odp' => $c->odp?->nama_odp,
                'odc' => $c->odp?->odc?->nama_odc,
                'due_date' => $c->due_date ? (string) $c->due_date : '',
                'status' => $c->status,
            ];

            if ($c->type === 'hotspot') {
                $hotspot[] = $row;
            } else {
                $pppoe[] = $row;
            }
        }

        $routers = MikrotikRouter::orderBy('id')->get()->map(fn ($r) => [
            'name' => $r->name,
            'host' => $r->host,
            'local_ip' => $r->local_ip,
            'local_port' => $r->local_port,
            'port' => $r->port,
            'username' => $r->username,
            'connection_mode' => $r->connection_mode,
            'connection_type' => $r->connection_type,
            'timeout' => $r->timeout,
            'is_active' => $r->is_active,
            'location' => $r->location,
            'latitude' => $r->latitude !== null ? (float) $r->latitude : null,
            'longitude' => $r->longitude !== null ? (float) $r->longitude : null,
        ])->values()->all();

        $olts = Olt::orderBy('id')->get()->map(fn ($o) => [
            'name' => $o->name,
            'brand' => $o->brand,
            'model' => $o->model,
            'ip_address' => $o->ip_address,
            'ssh_port' => $o->ssh_port,
            'location' => $o->location,
            'latitude' => $o->latitude !== null ? (float) $o->latitude : null,
            'longitude' => $o->longitude !== null ? (float) $o->longitude : null,
        ])->values()->all();

        $odcs = Odc::orderBy('id')->get()->map(fn ($o) => [
            'nama_odc' => $o->nama_odc,
            'koordinat' => $o->koordinat,
            'kapasitas_port' => $o->kapasitas_port,
        ])->values()->all();

        $odps = Odp::orderBy('id')->get()->map(fn ($o) => [
            'nama_odp' => $o->nama_odp,
            'koordinat' => $o->koordinat,
            'kapasitas_port' => $o->kapasitas_port,
            'kondisi_jalur' => $o->kondisi_jalur,
        ])->values()->all();

        /* Full backup: dump seluruh tabel aplikasi billing (struktur kolom + baris),
           kecuali tabel infrastruktur/telemetri yang tidak bernilai restore. */
        $excludeTables = [
            'migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches',
            'failed_jobs', 'password_reset_tokens', 'personal_access_tokens',
            'ping_results', 'onu_monitoring_history', 'network_metrics',
            'network_metrics_aggregated', 'interface_change_logs',
            'mikrotik_interface_metadata', 'routeros_sync_logs', 'noc_automation_job_logs',
        ];
        $database = [];

        /* Scope hanya ke database aplikasi ini (SHOW TABLES), bukan seluruh
           server MySQL yang mungkin memuat database lain. */
        $tables = [];
        try {
            foreach (DB::select('SHOW TABLES') as $t) {
                $tables[] = array_values((array) $t)[0];
            }
        } catch (\Throwable) {
            $tables = Schema::getTableListing();
        }

        foreach ($tables as $table) {
            if (in_array($table, $excludeTables, true)) {
                continue;
            }
            $database[$table] = [
                'columns' => Schema::getColumnListing($table),
                'rows' => DB::table($table)->get()->map(fn ($r) => (array) $r)->values()->all(),
            ];
        }
        ksort($database);

        return [
            'app' => config('app.name'),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'counts' => [
                'pppoe' => count($pppoe),
                'hotspot' => count($hotspot),
                'routers' => count($routers),
                'olts' => count($olts),
                'odcs' => count($odcs),
                'odps' => count($odps),
            ],
            'pppoe' => $pppoe,
            'hotspot' => $hotspot,
            'routers' => $routers,
            'olts' => $olts,
            'odcs' => $odcs,
            'odps' => $odps,
            'database' => [
                'tables' => count($database),
                'rows' => array_sum(array_map(fn ($t) => count($t['rows']), $database)),
                'data' => $database,
            ],
        ];
    }

    private function csvField($value): string
    {
        $value = (string) ($value ?? '');

        if (preg_match('/[",\r\n]/', $value)) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    private function routerPayload(MikrotikRouter $router): array
    {
        return [
            'id' => $router->id,
            'name' => $router->name,
            'ip' => $router->local_ip ?: $router->host,
            'port' => $router->local_port ?: $router->port,
            'username' => $router->username,
            'routeros_version' => $router->routeros_version,
            'model' => $router->model,
            'status' => $router->status,
            'last_connected' => $router->last_connected?->toDateTimeString(),
        ];
    }

    private function allRouters(): array
    {
        return MikrotikRouter::orderBy('id')->get()
            ->map(fn ($r) => $this->routerPayload($r))
            ->values()
            ->all();
    }

    private function connectRouter(MikrotikRouter $router): array
    {
        $ip = $router->local_ip ?: $router->host;
        $port = (int) ($router->local_port ?: 80);
        $base = "http://{$ip}:{$port}";
        $timeout = (int) ($router->timeout ?? 10);

        try {
            /* system/resource + ppp/active + ppp/secret dikirim paralel.
               PENTING: nama respons wajib lewat $pool->as('...') — kunci
               array pada hasil closure TIDAK dipakai Pool sebagai key */
            $res = Http::pool(function (HttpPool $pool) use ($base, $router, $timeout) {
                $pool->as('resource')->withBasicAuth($router->username, $router->password)
                    ->withoutVerifying()->timeout($timeout)->get("{$base}/rest/system/resource");
                $pool->as('active')->withBasicAuth($router->username, $router->password)
                    ->withoutVerifying()->timeout($timeout)->get("{$base}/rest/ppp/active");
                $pool->as('secret')->withBasicAuth($router->username, $router->password)
                    ->withoutVerifying()->timeout($timeout)->get("{$base}/rest/ppp/secret");

                return [];
            });

            /** @var \Illuminate\Http\Client\Response|null $resource */
            $resource = $res['resource'] ?? null;

            if ($resource instanceof \Illuminate\Http\Client\Response && $resource->successful()) {
                $data = $resource->json();

                $pppoeOnline = null;
                $pppoeOffline = null;
                $pppoeUsers = null;

                try {
                    $active = $res['active'] ?? null;
                    $secret = $res['secret'] ?? null;

                    $activeArr = ($active instanceof \Illuminate\Http\Client\Response && $active->successful() && is_array($active->json())) ? $active->json() : [];
                    $secretArr = ($secret instanceof \Illuminate\Http\Client\Response && $secret->successful() && is_array($secret->json())) ? $secret->json() : [];

                    $pppoeOnline = count($activeArr);
                    $pppoeUsers = count($secretArr);
                    $pppoeOffline = max($pppoeUsers - $pppoeOnline, 0);
                } catch (\Exception $e) {
                    // biarkan null bila fetch statistik gagal
                }

                $prev = $router->user_stats ?: [];
                $prevOnline = isset($prev['pppoe_online']) ? (int) $prev['pppoe_online'] : null;
                $prevOffline = isset($prev['pppoe_offline']) ? (int) $prev['pppoe_offline'] : null;

                $router->update([
                    'routeros_version' => $data['version'] ?? null,
                    'model' => $data['board-name'] ?? null,
                    'architecture' => $data['architecture-name'] ?? $data['cpu'] ?? null,
                    'status' => 'online',
                    'last_seen' => now(),
                    'last_connected' => now(),
                    'user_stats' => [
                        'pppoe_online' => $pppoeOnline ?? 0,
                        'pppoe_offline' => $pppoeOffline ?? 0,
                        'hotspot_online' => $prev['hotspot_online'] ?? 0,
                        'hotspot_offline' => $prev['hotspot_offline'] ?? 0,
                    ],
                    'user_stats_updated_at' => now(),
                ]);

                return [
                    'ok' => true,
                    'routeros_version' => $data['version'] ?? null,
                    'model' => $data['board-name'] ?? null,
                    'pppoe_users' => $pppoeUsers,
                    'pppoe_online' => $pppoeOnline,
                    'pppoe_offline' => $pppoeOffline,
                    'prev_pppoe_online' => $prevOnline,
                    'prev_pppoe_offline' => $prevOffline,
                ];
            }

            $router->update(['status' => 'offline', 'last_seen' => now()]);

            $status = ($resource instanceof \Illuminate\Http\Client\Response) ? $resource->status() : 0;

            return ['ok' => false, 'error' => 'Koneksi gagal (HTTP '.$status.') ke '.$ip.':'.$port];
        } catch (\Exception $e) {
            $router->update(['status' => 'offline', 'last_seen' => now()]);

            return ['ok' => false, 'error' => $this->friendlyCurlError($e, $ip, $port)];
        }
    }

    private function oltPayload(Olt $olt): array
    {
        return [
            'id' => $olt->id,
            'name' => $olt->name,
            'brand' => $olt->brand,
            'ip' => $olt->ip_address,
            'port' => $olt->ssh_port,
            'username' => $olt->username,
            'model' => $olt->model,
            'status' => $olt->connection_status,
            'last_polled_at' => $olt->last_polled_at?->toDateTimeString(),
        ];
    }

    private function allOlts(): array
    {
        return Olt::orderBy('id')->get()
            ->map(fn ($o) => $this->oltPayload($o))
            ->values()
            ->all();
    }

    private function connectOlt(Olt $olt): array
    {
        try {
            $driver = OltConnectorFactory::makeRaw($olt->brand);

            $connected = $driver->connect(
                $olt->ip_address,
                (int) $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            if (! $connected) {
                $olt->update(['connection_status' => 'offline', 'last_polled_at' => now()]);

                return ['ok' => false, 'error' => 'Gagal konek ke '.$olt->ip_address.':'.$olt->ssh_port.' (SSH login gagal)'];
            }

            $info = $driver->testConnection();

            try {
                $sys = $driver->getSystemInfo();
            } catch (\Throwable $e) {
                $sys = [];
            }

            if (! ($info['success'] ?? false)) {
                try {
                    $driver->disconnect();
                } catch (\Throwable $e) {
                    // abaikan error saat disconnect
                }
                $olt->update(['connection_status' => 'offline', 'last_polled_at' => now()]);

                return ['ok' => false, 'error' => $info['message'] ?? 'Tes koneksi gagal'];
            }

            $onuTotal = 0;
            $onuOnline = 0;
            $onuOffline = 0;
            $scanOk = false;

            foreach ($olt->ports as $port) {
                try {
                    $onus = $driver->getOnuList((int) $port->slot_number, (int) $port->port_number);
                    foreach ($onus as $onu) {
                        $scanOk = true;
                        $onuTotal++;
                        if (($onu['status'] ?? null) === 'online') {
                            $onuOnline++;
                        } else {
                            $onuOffline++;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning(
                        "connectOlt getOnuList gagal port {$port->slot_number}/{$port->port_number}: {$e->getMessage()}"
                    );
                }
            }

            try {
                $driver->disconnect();
            } catch (\Throwable $e) {
                // abaikan error saat disconnect
            }

            // Safety net: bila scan perangkat tidak menghasilkan ONU (port belum terdaftar / brand mismatch),
            // fallback ke angka dari DB agar tidak menampilkan "0 ONU" yang menyesatkan.
            if (! $scanOk) {
                $onuQuery = Onu::fromOlt()->whereHas('oltPort', fn ($q) => $q->where('olt_id', $olt->id));
                $onuTotal = (clone $onuQuery)->count();
                $onuOnline = (clone $onuQuery)->where('status', 'online')->count();
                $onuOffline = (clone $onuQuery)->where('status', 'offline')->count();
            }

            $data = $info['data'] ?? [];
            $version = $this->cleanFirmwareValue($data['version'] ?? $data['raw'] ?? ($sys['version'] ?? null));
            $model = $this->cleanFirmwareValue($data['device'] ?? ($sys['device'] ?? null));

            $olt->update([
                'connection_status' => 'online',
                'model' => $model ?: $olt->model,
                'last_polled_at' => now(),
            ]);

            return [
                'ok' => true,
                'message' => $info['message'] ?? 'Terhubung ke OLT',
                'version' => $version,
                'model' => $model,
                'onu_total' => $onuTotal,
                'onu_online' => $onuOnline,
                'onu_offline' => $onuOffline,
            ];
        } catch (\Exception $e) {
            $olt->update(['connection_status' => 'offline', 'last_polled_at' => now()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function cleanFirmwareValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);

        $parts = explode(':', $value, 2);

        if (count($parts) === 2) {
            $value = trim($parts[1]);
        }

        return $value ?: null;
    }

    private function friendlyCurlError(\Exception $e, string $ip, int $port): string
    {
        $msg = $e->getMessage();
        $ipPort = $ip.':'.$port;

        return match (true) {
            str_contains($msg, 'error 7') => 'Koneksi ditolak (refused) ke '.$ipPort,
            str_contains($msg, 'error 6') => 'Host tidak ditemukan: '.$ipPort,
            str_contains($msg, 'error 28') => 'Timeout koneksi ke '.$ipPort,
            str_contains($msg, 'error 56') => 'Gagal menerima data dari '.$ipPort,
            default => 'Gagal konek ke '.$ipPort.' ('.(strlen($msg) > 80 ? substr($msg, 0, 80).'...' : $msg).')',
        };
    }
}
