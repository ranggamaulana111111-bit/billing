<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Device;
use App\Models\MikrotikRouter;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\Onu;
use App\Models\Package;
use App\Models\Setting;
use App\Modules\GenieACS\Contracts\IGenieACSClient;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Monitoring\PingMonitorService;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class FeaturesController extends Controller
{
    public function map(): View
    {
        abort_unless(auth()->user()->role === 'noc', 403);

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
                $label = trim(($m->name ?? '').($m->location ? ' — '.$m->location : ''));
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
                $label = trim($m->customer_code.' - '.$m->name.($m->location ? ' — '.$m->location : ''));
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
                $label = trim(($m->name ?? '').($m->location ? ' — '.$m->location : ''));
                $results->push([
                    'type' => 'Router',
                    'label' => $label ?: (string) $m->host,
                    'lat' => $m->latitude ? (float) $m->latitude : null,
                    'lon' => $m->longitude ? (float) $m->longitude : null,
                ]);
            });

        return response()->json($results->take(8)->values()->all());
    }

    /* ── Sync Mikrotik (modal pada peta FTTH) ── */

    public function mikrotikList(): JsonResponse
    {
        return response()->json(['routers' => $this->allRouters()]);
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

    public function mikrotikSyncAll(): JsonResponse
    {
        $routers = MikrotikRouter::orderBy('id')->get();
        $ok = 0;

        foreach ($routers as $router) {
            if ($this->connectRouter($router)['ok']) {
                $ok++;
            }
        }

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
                        $routerClients[] = [
                            'router_id' => $router->id,
                            'router_name' => $router->name,
                            'name' => $name,
                            'service' => $s['service'] ?? null,
                            'address' => $s['address'] ?? null,
                            'caller_id' => $s['caller-id'] ?? null,
                            'uptime' => $s['uptime'] ?? null,
                            'session_id' => $s['.id'] ?? null,
                            'profile' => $sec['profile'] ?? ($s['profile'] ?? null),
                            'comment' => $sec['comment'] ?? null,
                            'bytes_in' => isset($s['bytes-in']) ? (int) $s['bytes-in'] : null,
                            'bytes_out' => isset($s['bytes-out']) ? (int) $s['bytes-out'] : null,
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

    /* ── Sync OLT (modal pada peta FTTH) ── */

    public function oltList(): JsonResponse
    {
        return response()->json(['olts' => $this->allOlts()]);
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

    public function oltSyncAll(): JsonResponse
    {
        $olts = Olt::orderBy('id')->get();
        $ok = 0;

        foreach ($olts as $olt) {
            if (($this->connectOlt($olt))['ok']) {
                $ok++;
            }
        }

        $onuOnline = Onu::fromOlt()->where('status', 'online')->count();
        $onuOffline = Onu::fromOlt()->where('status', 'offline')->count();

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

    /* ── Sync GenieACS (modal pada peta FTTH) ── */

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

    /* ── Notifikasi (WhatsApp & Telegram) — modal pada peta FTTH ── */

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

    /* ── Backup & Restore (card pada peta FTTH) ── */

    public function backupConfig(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'backup_email' => Setting::get('backup_email') ?: '',
            'backup_time' => Setting::get('backup_time') ?: '',
        ]);
    }

    public function backupSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'time' => ['nullable', 'string', 'max:10'],
        ]);

        Setting::set('backup_email', filled($data['email'] ?? null) ? trim($data['email']) : null);
        Setting::set('backup_time', filled($data['time'] ?? null) ? trim($data['time']) : null);

        return response()->json(['ok' => true, 'message' => 'Konfigurasi Auto Backup tersimpan']);
    }

    public function backupSendNow(Request $request): JsonResponse
    {
        $email = trim((string) ($request->input('email') ?: Setting::get('backup_email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'error' => 'Email penerima tidak valid'], 422);
        }

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

    public function excelExport(): \Illuminate\Http\Response
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

    public function kmzExport(): \Illuminate\Http\Response
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
                return response()->json(['ok' => false, 'error' => 'Ekstensi Zip (php_zip) tidak aktif di server — gunakan file .kml'], 422);
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

    /* ── Map markers & Tambah Perangkat (card pada peta FTTH) ── */

    public function mapMarkers(): JsonResponse
    {
        $markers = [];

        Olt::orderBy('name')->get()->each(function ($m) use (&$markers) {
            if ($m->latitude === null || $m->longitude === null) {
                return;
            }
            $markers[] = [
                'id' => $m->id,
                'source' => 'olt',
                'type' => 'OLT',
                'label' => $m->name,
                'lat' => (float) $m->latitude,
                'lon' => (float) $m->longitude,
                'location' => $m->location ?? '',
                'status' => $m->status === 'active' ? 'online' : ($m->status ? 'offline' : null),
                'detail' => trim(($m->brand ?? '').' · '.($m->ip_address ?? '')),
            ];
        });

        MikrotikRouter::orderBy('name')->get()->each(function ($m) use (&$markers) {
            if ($m->latitude === null || $m->longitude === null) {
                return;
            }
            $status = $m->status;
            if (! $status && $m->last_seen) {
                $status = $m->last_seen->diffInMinutes(now()) <= 5 ? 'online' : 'offline';
            }
            $markers[] = [
                'id' => $m->id,
                'source' => 'router',
                'type' => 'Router',
                'label' => $m->name,
                'lat' => (float) $m->latitude,
                'lon' => (float) $m->longitude,
                'location' => $m->location ?? '',
                'status' => $status ? strtolower($status) : null,
                'detail' => trim(($m->model ?? '').' · '.($m->host ?? '')),
            ];
        });

        Odc::orderBy('nama_odc')->get()->each(function ($m) use (&$markers) {
            if ($m->latitude === null || $m->longitude === null) {
                return;
            }
            $markers[] = [
                'id' => $m->id,
                'source' => 'odc',
                'type' => 'ODC',
                'label' => $m->nama_odc,
                'lat' => (float) $m->latitude,
                'lon' => (float) $m->longitude,
                'location' => 'Kapasitas: '.($m->kapasitas_port ?? '-'),
                'detail' => 'ODC',
            ];
        });

        Device::orderBy('type')->orderBy('name')->get()->each(function ($m) use (&$markers) {
            if ($m->latitude === null || $m->longitude === null) {
                return;
            }
            $attrs = is_array($m->attributes) ? $m->attributes : [];
            $markers[] = [
                'id' => $m->id,
                'source' => 'device',
                'type' => strtoupper($m->type),
                'label' => $m->name,
                'lat' => (float) $m->latitude,
                'lon' => (float) $m->longitude,
                'location' => $m->location ?? '',
                'status' => $m->status ? strtolower($m->status) : null,
                'detail' => trim(($m->brand ?? '').($m->model ? ' · '.$m->model : '')),
                'parent' => isset($attrs['induk']) ? (string) $attrs['induk'] : null,
                'attributes' => $attrs,
                'capacity' => $m->capacity,
                'ip_address' => $m->ip_address,
                'brand' => $m->brand,
                'model' => $m->model,
                'notes' => $m->notes,
            ];
        });

        Customer::with([
            'odp',
            'onus' => fn ($q) => $q->fromOlt(),
        ])->orderBy('customer_code')->get()->each(function ($m) use (&$markers) {
            if (! $m->odp || $m->odp->latitude === null || $m->odp->longitude === null) {
                return;
            }
            $firstOnu = $m->onus->first();
            $markers[] = [
                'id' => $m->id,
                'source' => 'customer',
                'type' => 'Customer',
                'label' => $m->customer_code.' - '.$m->name,
                'name' => $m->name,
                'pppoe_username' => $m->pppoe_username,
                'lat' => (float) $m->odp->latitude,
                'lon' => (float) $m->odp->longitude,
                'location' => $m->location ?? '',
                'detail' => $m->type ?? '',
                'parent' => 'ODP — '.$m->odp->nama_odp,
                'phone' => $m->phone,
                'billing' => $m->status,
                'onu_status' => $firstOnu?->status,
                'has_acs' => ! empty($firstOnu?->acs_device_id),
            ];
        });

        return response()->json(['ok' => true, 'markers' => $markers]);
    }

    public function deviceList(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'devices' => Device::orderBy('type')->orderBy('name')->get()
                ->map(fn ($d) => $this->devicePayload($d))
                ->values()
                ->all(),
        ]);
    }

    public function deviceParents(): JsonResponse
    {
        $parents = [];
        $push = function (string $type, string $name) use (&$parents): void {
            $parents[] = ['type' => $type, 'name' => $name];
        };

        Device::orderBy('type')->orderBy('name')->get(['type', 'name'])
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

        return response()->json([
            'ok' => true,
            'message' => 'Perangkat '.strtoupper($device->type).' "'.$device->name.'" disimpan',
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

        return response()->json([
            'ok' => true,
            'message' => 'Status '.$device->name.' → '.strtoupper($device->status),
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

        $session = $this->findActiveSession($customer->pppoe_username);

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
            'maps' => ($lat !== null && $lon !== null) ? 'https://www.google.com/maps?q='.$lat.','.$lon : null,
            'wa' => $this->waLink($customer->phone),
            'edit' => '/customer/'.$customer->customer_code.'/edit',
        ]);
    }

    public function customerPing(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $customer = Customer::find($data['id']);

        if (! $customer) {
            return response()->json(['ok' => false, 'error' => 'Pelanggan tidak ditemukan'], 404);
        }

        $session = $this->findActiveSession($customer->pppoe_username);
        $ip = $session['ip'] ?? null;

        if (! $ip) {
            return response()->json([
                'ok' => false,
                'error' => 'Tidak ada IP aktif untuk '.($customer->pppoe_username ?: $customer->customer_code).' (client offline / tidak ada sesi PPPoE)',
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

        return response()->json([
            'ok' => true,
            'device_id' => $onu->acs_device_id,
            'acs' => [
                'ssid' => $val('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID'),
                'wifi_enabled' => $val('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable'),
                'channel' => $val('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel'),
                'mode' => $val('InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Mode'),
                'external_ip' => $val('InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress'),
                'manufacturer' => $val('InternetGatewayDevice.DeviceInfo.Manufacturer'),
                'product_class' => $val('InternetGatewayDevice.DeviceInfo.ProductClass'),
                'software_version' => $val('InternetGatewayDevice.DeviceInfo.SoftwareVersion'),
            ],
        ]);
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

    private function findActiveSession(?string $username): ?array
    {
        if (! $username) {
            return null;
        }

        foreach (MikrotikRouter::where('is_active', true)->orderBy('id')->get() as $router) {
            try {
                $cmd = new RouterCommandService($router);
                $active = $cmd->getPppActive();

                if (! $active->isSuccess() || ! is_array($active->getData())) {
                    continue;
                }

                foreach ($active->getData() as $s) {
                    if ((string) ($s['name'] ?? '') === $username) {
                        return [
                            'ip' => $s['address'] ?? null,
                            'caller_id' => $s['caller-id'] ?? null,
                            'uptime' => $s['uptime'] ?? null,
                            'bytes_in' => isset($s['bytes-in']) ? (int) $s['bytes-in'] : null,
                            'bytes_out' => isset($s['bytes-out']) ? (int) $s['bytes-out'] : null,
                            'router_name' => $router->name,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
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

    private function devicePayload(Device $d): array
    {
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

        try {
            $res = Http::withBasicAuth($router->username, $router->password)
                ->withoutVerifying()
                ->timeout($router->timeout ?? 10)
                ->get("http://{$ip}:{$port}/rest/system/resource");

            if ($res->successful()) {
                $data = $res->json();

                $pppoeOnline = null;
                $pppoeOffline = null;
                $pppoeUsers = null;

                try {
                    $active = Http::withBasicAuth($router->username, $router->password)
                        ->withoutVerifying()
                        ->timeout($router->timeout ?? 10)
                        ->get("http://{$ip}:{$port}/rest/ppp/active");

                    $secret = Http::withBasicAuth($router->username, $router->password)
                        ->withoutVerifying()
                        ->timeout($router->timeout ?? 10)
                        ->get("http://{$ip}:{$port}/rest/ppp/secret");

                    $activeArr = $active->successful() && is_array($active->json()) ? $active->json() : [];
                    $secretArr = $secret->successful() && is_array($secret->json()) ? $secret->json() : [];

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

            return ['ok' => false, 'error' => 'Koneksi gagal (HTTP '.$res->status().') ke '.$ip.':'.$port];
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
