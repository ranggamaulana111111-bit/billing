<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Olt;
use App\Models\OltPort;
use App\Models\Onu;
use App\Models\Setting;
use App\Services\MikrotikService;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class OltController extends Controller
{
    public function index()
    {
        $olts = Olt::orderBy('name')->get();
        $totalOnus = Onu::count();
        $onlineOnus = Onu::where('status', 'online')->count();
        $offlineOnus = Onu::where('status', 'offline')->count();

        return view('olt.index', compact('olts', 'totalOnus', 'onlineOnus', 'offlineOnus'));
    }

    public function create()
    {
        return view('olt.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|in:huawei,zte,fiberhome,cdata',
            'model' => 'nullable|string|max:255',
            'ip_address' => 'required|string|max:45',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'snmp_community' => 'nullable|string|max:255',
            'snmp_version' => 'nullable|in:v1,v2c,v3',
            'snmp_port' => 'required|integer|min:1|max:65535',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'jump_host' => 'nullable|string|max:45',
            'jump_port' => 'required_with:jump_host|integer|min:1|max:65535',
            'jump_username' => 'nullable|string|max:255',
            'jump_password' => 'nullable|string|max:255',
            'status' => 'required|in:active,maintenance,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        Olt::create($validated);

        return redirect()->route('olt.index')->with('success', 'OLT berhasil ditambahkan.');
    }

    public function show(Olt $olt)
    {
        $olt->load('ports.onus.customer');
        $totalPorts = $olt->ports->count();
        $totalOnus = Onu::whereIn('olt_port_id', $olt->ports->pluck('id'))->count();
        $onlineOnus = Onu::whereIn('olt_port_id', $olt->ports->pluck('id'))->where('status', 'online')->count();

        return view('olt.show', compact('olt', 'totalPorts', 'totalOnus', 'onlineOnus'));
    }

    public function edit(Olt $olt)
    {
        return view('olt.edit', compact('olt'));
    }

    public function update(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|in:huawei,zte,fiberhome,cdata',
            'model' => 'nullable|string|max:255',
            'ip_address' => 'required|string|max:45',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'snmp_community' => 'nullable|string|max:255',
            'snmp_version' => 'nullable|in:v1,v2c,v3',
            'snmp_port' => 'required|integer|min:1|max:65535',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'jump_host' => 'nullable|string|max:45',
            'jump_port' => 'required_with:jump_host|integer|min:1|max:65535',
            'jump_username' => 'nullable|string|max:255',
            'jump_password' => 'nullable|string|max:255',
            'status' => 'required|in:active,maintenance,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (! $request->filled('password')) {
            unset($validated['password']);
        }
        if (! $request->filled('jump_password')) {
            unset($validated['jump_password']);
        }

        $olt->update($validated);

        return redirect()->route('olt.index')->with('success', 'OLT berhasil diperbarui.');
    }

    public function destroy(Olt $olt)
    {
        $olt->delete();

        return redirect()->route('olt.index')->with('success', 'OLT berhasil dihapus.');
    }

    public function testConnection(Olt $olt)
    {
        if (! $olt->usesMikrotikProxy()) {
            // Step 1: check if port is reachable (skip for proxy)
            $start = microtime(true);
            $sock = @fsockopen($olt->ip_address, $olt->ssh_port, $errno, $errstr, 5);
            if (! $sock) {
                $ping = round((microtime(true) - $start) * 1000, 1);

                return back()->with('error',
                    "Port {$olt->ssh_port} di {$olt->ip_address} tidak reachable ".
                    "(timeout {$ping}ms). Cek routing/firewall antara server dan OLT."
                );
            }
            fclose($sock);
        }

        // Step 2: try SSH login (via proxy or direct)
        try {
            $connector = OltConnectorFactory::make($olt->brand, $olt);
            $connected = $connector->connect(
                $olt->ip_address,
                $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            if (! $connected) {
                $via = $olt->usesMikrotikProxy() ? ' via MikroTik proxy' : '';

                return back()->with('error', "SSH login ditolak oleh {$olt->ip_address}{$via}. Cek username/password OLT.");
            }

            $result = $connector->testConnection();
            $connector->disconnect();

            if ($result['success']) {
                ActivityLog::log('Test koneksi OLT', "OLT: {$olt->name} ({$olt->ip_address})");

                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Exception $e) {
            return back()->with('error', 'Koneksi SSH gagal: '.$e->getMessage());
        }
    }

    private function autoSyncPorts(Olt $olt): void
    {
        $existingCount = $olt->ports()->count();
        if ($existingCount > 0) {
            return;
        }

        // Auto-create default ports based on brand
        $defaults = match ($olt->brand) {
            'cdata', 'huawei', 'zte' => [
                ['slot' => 0, 'port' => 0, 'type' => 'gpon'],
                ['slot' => 0, 'port' => 1, 'type' => 'gpon'],
                ['slot' => 0, 'port' => 2, 'type' => 'gpon'],
                ['slot' => 0, 'port' => 3, 'type' => 'gpon'],
            ],
            'fiberhome' => [
                ['slot' => 0, 'port' => 0, 'type' => 'gpon'],
                ['slot' => 0, 'port' => 1, 'type' => 'gpon'],
                ['slot' => 0, 'port' => 2, 'type' => 'gpon'],
                ['slot' => 0, 'port' => 3, 'type' => 'gpon'],
            ],
            default => [
                ['slot' => 0, 'port' => 0, 'type' => 'gpon'],
            ],
        };

        foreach ($defaults as $p) {
            OltPort::firstOrCreate(
                ['olt_id' => $olt->id, 'slot_number' => $p['slot'], 'port_number' => $p['port']],
                ['port_type' => $p['type'], 'status' => 'active']
            );
        }
    }

    public function scanOnus(Olt $olt)
    {
        if (! $olt->usesMikrotikProxy()) {
            $sock = @fsockopen($olt->ip_address, $olt->ssh_port, $errno, $errstr, 5);
            if (! $sock) {
                return back()->with('error',
                    "Port {$olt->ssh_port} di {$olt->ip_address} tidak reachable. ".
                    'Cek routing/firewall antara server dan OLT.'
                );
            }
            fclose($sock);
        }

        $this->autoSyncPorts($olt);

        $connector = OltConnectorFactory::make($olt->brand, $olt);
        $connected = $connector->connect(
            $olt->ip_address,
            $olt->ssh_port,
            $olt->username,
            $olt->password
        );

        if (! $connected) {
            $via = $olt->usesMikrotikProxy() ? ' via MikroTik proxy' : '';

            return back()->with('error', "SSH login ditolak oleh {$olt->ip_address}{$via}. Cek username/password OLT.");
        }

        $ports = $olt->ports()->get();
        $totalFound = 0;
        $failedPorts = 0;

        foreach ($ports as $port) {
            try {
                $onus = $connector->getOnuList($port->slot_number, $port->port_number);

                foreach ($onus as $onuData) {
                    try {
                        $optical = $connector->getOpticalPower($onuData['onu_id']);
                    } catch (\Exception $e) {
                        Log::warning("Scan optical power gagal ONU {$onuData['onu_id']}: {$e->getMessage()}");
                        $optical = ['rx_power' => null, 'tx_power' => null];
                    }

                    Onu::updateOrCreate(
                        [
                            'olt_port_id' => $port->id,
                            'onu_id' => $onuData['onu_id'],
                        ],
                        [
                            'serial_number' => $onuData['sn'] ?? null,
                            'status' => $onuData['status'] ?? 'unknown',
                            'rx_power' => $optical['rx_power'] ?? null,
                            'tx_power' => $optical['tx_power'] ?? null,
                            'slot_number' => $port->slot_number,
                            'port_number' => $port->port_number,
                            'last_seen_at' => now(),
                        ]
                    );

                    $totalFound++;
                }
            } catch (\Exception $e) {
                $failedPorts++;
                Log::error("Scan port {$port->slot_number}/{$port->port_number} gagal: {$e->getMessage()}");
            }
        }

        $connector->disconnect();
        $olt->update(['last_polled_at' => now()]);

        $msg = "Scan selesai. {$totalFound} ONU ditemukan.";
        if ($failedPorts > 0) {
            $msg .= " {$failedPorts} port gagal (lihat log).";
        }

        ActivityLog::log('Scan ONU', "OLT: {$olt->name} — {$totalFound} ONU");

        return back()->with('success', $msg);
    }

    public function syncFromMikrotik(Olt $olt)
    {
        try {
            $mikrotik = new MikrotikService;
            if (! $mikrotik->isConfigured()) {
                return back()->with('error', 'MikroTik belum dikonfigurasi di Settings.');
            }

            $active = $mikrotik->getPppActive();
            $firstPort = $olt->ports()->first();

            if (! $firstPort) {
                return back()->with('error', 'OLT belum punya port. Sync ports atau scan dulu.');
            }

            $synced = 0;
            foreach ($active as $session) {
                $username = $session['name'] ?? '';
                if (! $username) {
                    continue;
                }

                $customer = Customer::where('pppoe_username', $username)->first();
                if (! $customer) {
                    continue;
                }

                $mac = $session['caller-id'] ?? '';

                Onu::updateOrCreate(
                    [
                        'olt_port_id' => $firstPort->id,
                        'customer_id' => $customer->id,
                    ],
                    [
                        'onu_id' => 'mikrotik-'.$customer->id,
                        'caller_id' => $mac ?: 'PPPoE-'.$username,
                        'status' => 'online',
                        'slot_number' => $firstPort->slot_number,
                        'port_number' => $firstPort->port_number,
                        'last_seen_at' => now(),
                    ]
                );

                $synced++;
            }

            $msg = "Sinkron dari MikroTik selesai. {$synced} ONU ditemukan.";
            ActivityLog::log('Sync MikroTik', "OLT: {$olt->name} — {$synced} ONU");

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            Log::error("Sync dari MikroTik gagal: {$e->getMessage()}");

            return back()->with('error', 'Gagal sync dari MikroTik: '.$e->getMessage());
        }
    }

    public function rebootOnu(Olt $olt, Onu $onu)
    {
        try {
            $connector = OltConnectorFactory::make($olt->brand, $olt);
            $connector->connect(
                $olt->ip_address,
                $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            $result = $connector->rebootOnu($onu->onu_id);
            $connector->disconnect();

            if ($result['success']) {
                ActivityLog::log('Reboot ONU', "ONU: {$onu->onu_id} — OLT: {$olt->name}");

                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Exception $e) {
            return back()->with('error', 'Reboot gagal: '.$e->getMessage());
        }
    }

    public function removeOnu(Olt $olt, Onu $onu)
    {
        try {
            $connector = OltConnectorFactory::make($olt->brand, $olt);
            $connector->connect(
                $olt->ip_address,
                $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            $result = $connector->removeOnu($onu->onu_id);
            $connector->disconnect();

            if ($result['success']) {
                ActivityLog::log('Hapus ONU', "ONU: {$onu->onu_id} — OLT: {$olt->name}");
                $onu->delete();

                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Exception $e) {
            return back()->with('error', 'Hapus ONU gagal: '.$e->getMessage());
        }
    }

    public function linkCustomer(Request $request, Onu $onu)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $onu->update(['customer_id' => $validated['customer_id']]);

        ActivityLog::log('Taut ONU', "ONU: {$onu->onu_id} → Customer ID: {$validated['customer_id']}");

        return back()->with('success', 'ONU berhasil ditautkan ke pelanggan.');
    }

    public function syncPorts(Olt $olt)
    {
        $request = request();
        $validated = $request->validate([
            'ports' => 'required|array',
            'ports.*.slot' => 'required|integer',
            'ports.*.port' => 'required|integer',
            'ports.*.type' => 'required|string',
        ]);

        foreach ($validated['ports'] as $portData) {
            OltPort::firstOrCreate(
                [
                    'olt_id' => $olt->id,
                    'slot_number' => $portData['slot'],
                    'port_number' => $portData['port'],
                ],
                [
                    'port_type' => $portData['type'] ?? 'gpon',
                    'status' => 'active',
                ]
            );
        }

        ActivityLog::log('Sinkron port', "OLT: {$olt->name} — ".count($validated['ports']).' port');

        return back()->with('success', 'Port berhasil disinkronkan.');
    }

    public function monitoring()
    {
        $olts = Olt::withCount('ports')->get();

        $customers = Customer::with(['onus' => function ($q) {
            $q->with('oltPort.olt')->latest('last_seen_at');
        }])->get();

        $customerSignals = $customers->map(function ($customer) {
            $onu = $customer->onus->first();

            return [
                'customer' => $customer,
                'onu' => $onu,
                'rx_power' => $onu?->rx_power,
                'tx_power' => $onu?->tx_power,
                'status' => $onu?->status ?? 'no_onu',
                'onu_id' => $onu?->onu_id,
                'serial' => $onu?->serial_number,
                'last_seen' => $onu?->last_seen_at,
                'olt' => $onu?->oltPort?->olt,
                'oltPort' => $onu?->oltPort,
            ];
        })->sortBy([
            fn ($a) => $a['rx_power'] ?? PHP_FLOAT_MAX,
        ])->values();

        $totalCustomers = $customers->count();
        $totalOnline = $customerSignals->filter(fn ($cs) => $cs['status'] === 'online')->count();
        $totalOffline = $customerSignals->filter(fn ($cs) => $cs['status'] === 'offline')->count();
        $totalWeak = $customerSignals->filter(
            fn ($cs) => $cs['rx_power'] !== null && $cs['rx_power'] < -27
        )->count();

        return view('olt.monitoring', compact(
            'olts', 'customerSignals',
            'totalCustomers', 'totalOnline', 'totalOffline', 'totalWeak'
        ));
    }

    // ── LIVE DATA API ──

    public function liveData(Olt $olt)
    {
        $olt->load('ports.onus.customer');

        // ping: direct SSH port check or MikroTik API latency for proxy
        $ping = null;
        try {
            if ($olt->usesMikrotikProxy()) {
                $start = microtime(true);
                $mikrotikHost = Setting::get('mikrotik_host');
                $mikrotikUser = Setting::get('mikrotik_user');
                $mikrotikPass = Setting::get('mikrotik_password');
                $mikrotikPort = (int) (Setting::get('mikrotik_port', '80'));
                $scheme = $mikrotikPort === 443 ? 'https' : 'http';

                Http::withBasicAuth($mikrotikUser, $mikrotikPass)
                    ->withoutVerifying()
                    ->timeout(3)
                    ->get("{$scheme}://{$mikrotikHost}:{$mikrotikPort}/rest/system/resource");

                $ping = round((microtime(true) - $start) * 1000, 1);
            } else {
                $start = microtime(true);
                $sock = @fsockopen($olt->ip_address, $olt->ssh_port, $errno, $errstr, 3);
                if ($sock) {
                    $ping = round((microtime(true) - $start) * 1000, 1);
                    fclose($sock);
                }
            }
        } catch (\Exception $e) {
            $ping = null;
        }

        $ports = $olt->ports->map(fn ($port) => [
            'id' => $port->id,
            'slot' => $port->slot_number,
            'port' => $port->port_number,
            'type' => $port->port_type,
            'status' => $port->status,
            'onus' => $port->onus->map(fn ($onu) => [
                'id' => $onu->id,
                'onu_id' => $onu->onu_id,
                'serial_number' => $onu->serial_number,
                'caller_id' => $onu->caller_id,
                'status' => $onu->status,
                'rx_power' => $onu->rx_power,
                'tx_power' => $onu->tx_power,
                'customer_name' => $onu->customer?->name,
                'customer_id' => $onu->customer_id,
                'last_seen_at' => $onu->last_seen_at?->diffForHumans(),
            ]),
        ]);

        $totalOnus = $olt->ports->sum(fn ($p) => $p->onus->count());
        $onlineOnus = $olt->ports->sum(fn ($p) => $p->onus->where('status', 'online')->count());
        $totalPorts = $olt->ports->count();

        return response()->json([
            'ping' => $ping,
            'total_onus' => $totalOnus,
            'online_onus' => $onlineOnus,
            'total_ports' => $totalPorts,
            'ports' => $ports,
        ]);
    }

    public static function isAdmin(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function map()
    {
        $olts = Olt::withCount('ports')->get();

        $oltData = $olts->map(function ($olt) {
            $portIds = $olt->ports->pluck('id');
            $totalOnus = Onu::whereIn('olt_port_id', $portIds)->count();
            $onlineOnus = Onu::whereIn('olt_port_id', $portIds)->where('status', 'online')->count();

            return [
                'id' => $olt->id,
                'name' => $olt->name,
                'brand' => $olt->brand,
                'ip_address' => $olt->ip_address,
                'location' => $olt->location,
                'latitude' => $olt->latitude,
                'longitude' => $olt->longitude,
                'status' => $olt->status,
                'last_polled_at' => $olt->last_polled_at?->diffForHumans(),
                'ports_count' => $olt->ports_count,
                'total_onus' => $totalOnus,
                'online_onus' => $onlineOnus,
            ];
        });

        return view('olt.map', compact('oltData'));
    }

    public function exportOlt()
    {
        $olts = Olt::withCount('ports')->get();

        $csv = "Nama,Brand,IP Address,Port SSH,Port SNMP,Lokasi,Status,Last Polled\n";
        foreach ($olts as $olt) {
            $csv .= "{$olt->name},{$olt->brand},{$olt->ip_address},{$olt->ssh_port},{$olt->snmp_port},".str_replace(',', ';', $olt->location ?? '').",{$olt->status},{$olt->last_polled_at}\n";
        }

        ActivityLog::log('Export OLT', count($olts).' OLT di-export');

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="olts-'.now()->format('Ymd').'.csv"',
        ]);
    }

    public function exportOnu(Request $request)
    {
        $query = Onu::with('oltPort.olt', 'customer');

        if ($oltId = $request->olt_id) {
            $query->whereHas('oltPort', fn ($q) => $q->where('olt_id', $oltId));
        }
        if ($status = $request->status) {
            $query->where('status', $status);
        }

        $onus = $query->get();

        $csv = "OLT,Port,ONU ID,Serial,Caller ID,Vendor,Status,Rx Power,Tx Power,Pelanggan,Last Seen\n";
        foreach ($onus as $onu) {
            $csv .= "{$onu->oltPort?->olt?->name},{$onu->oltPort?->port_name},{$onu->onu_id},{$onu->serial_number},{$onu->caller_id},{$onu->vendor},{$onu->status},{$onu->rx_power},{$onu->tx_power},".str_replace(',', ';', $onu->customer?->name ?? '').",{$onu->last_seen_at}\n";
        }

        ActivityLog::log('Export ONU', count($onus).' ONU di-export');

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="onus-'.now()->format('Ymd').'.csv"',
        ]);
    }

    public function searchOnu(Request $request)
    {
        $query = Onu::with('oltPort.olt', 'customer');

        if ($search = $request->q) {
            $query->where(function ($q) use ($search) {
                $q->where('onu_id', 'LIKE', "%{$search}%")
                    ->orWhere('serial_number', 'LIKE', "%{$search}%")
                    ->orWhere('caller_id', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'LIKE', "%{$search}%"));
            });
        }

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        if ($oltId = $request->olt_id) {
            $query->whereHas('oltPort', fn ($q) => $q->where('olt_id', $oltId));
        }

        $onus = $query->paginate(20)->withQueryString();
        $olts = Olt::pluck('name', 'id');

        return view('olt.search', compact('onus', 'olts'));
    }
}
