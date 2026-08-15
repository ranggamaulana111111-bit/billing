<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MikrotikRouter;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\Setting;
use App\Services\MikrotikService;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class IntegrationController extends Controller
{
    public function index()
    {
        $routersTunnel = MikrotikRouter::where('connection_mode', 'tunnel')->orderBy('name')->get();
        $routersLocal = MikrotikRouter::where('connection_mode', 'local_ip')->orderBy('name')->get();
        $olts = Olt::orderBy('name')->with('ports')->get();

        $odcCount = Odc::count();
        $odpCount = Odp::count();

        return view('settings.integrations', compact('routersTunnel', 'routersLocal', 'olts', 'odcCount', 'odpCount'));
    }

    public function storeMikrotik(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'local_ip' => 'nullable|string|max:45',
            'local_port' => 'nullable|integer|min:1|max:65535',
            'connection_mode' => 'required|in:tunnel,local_ip',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'hotspot_server' => 'nullable|string|max:255',
            'type' => 'required|in:pppoe,bandwidth,general',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['password'] = $validated['password'] ?? '';

        if (blank($validated['hotspot_server'] ?? null)) {
            $validated['hotspot_server'] = 'all';
        }

        MikrotikRouter::create($validated);

        ActivityLog::log('Tambah Router (Integrasi)', 'Menambahkan router: '.$validated['name']);

        return back()->with('success', 'Router MikroTik berhasil ditambahkan.');
    }

    public function updateMikrotik(Request $request, MikrotikRouter $mikrotikRouter)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'local_ip' => 'nullable|string|max:45',
            'local_port' => 'nullable|integer|min:1|max:65535',
            'connection_mode' => 'required|in:tunnel,local_ip',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'hotspot_server' => 'nullable|string|max:255',
            'type' => 'required|in:pppoe,bandwidth,general',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
        ]);

        $validated['is_active'] = $request->has('is_active');

        if (blank($validated['password'])) {
            unset($validated['password']);
        }

        if (blank($validated['hotspot_server'] ?? null)) {
            $validated['hotspot_server'] = 'all';
        }

        $mikrotikRouter->update($validated);

        ActivityLog::log('Ubah Router (Integrasi)', 'Mengubah router: '.$validated['name']);

        return back()->with('success', 'Router MikroTik berhasil diperbarui.');
    }

    public function destroyMikrotik(MikrotikRouter $mikrotikRouter)
    {
        if ($mikrotikRouter->vouchers()->exists()) {
            return back()->with('error', 'Router tidak bisa dihapus karena masih memiliki voucher.');
        }

        $name = $mikrotikRouter->name;
        $mikrotikRouter->delete();

        ActivityLog::log('Hapus Router (Integrasi)', 'Menghapus router: '.$name);

        return back()->with('success', 'Router MikroTik berhasil dihapus.');
    }

    public function testMikrotik(MikrotikRouter $mikrotikRouter)
    {
        $service = new MikrotikService($mikrotikRouter);

        $result = $service->testConnection();

        if ($result['success']) {
            $stats = $service->getUserStats();
            $mikrotikRouter->update([
                'status' => 'online',
                'last_seen' => now(),
                'last_connected' => now(),
                'user_stats' => $stats,
                'user_stats_updated_at' => now(),
            ]);
        } else {
            $mikrotikRouter->update(['status' => 'offline']);
        }

        ActivityLog::log('Test Router (Integrasi)', 'Test koneksi router: '.$mikrotikRouter->name.' ('.$mikrotikRouter->host.')');

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * Lightweight real-time connection check for a single router (AJAX).
     */
    public function liveMikrotik(MikrotikRouter $mikrotikRouter)
    {
        $service = new MikrotikService($mikrotikRouter);
        $result = $service->testConnection();

        if ($result['success']) {
            $mikrotikRouter->update([
                'status' => 'online',
                'last_seen' => now(),
                'last_connected' => now(),
            ]);

            return response()->json([
                'router_id' => $mikrotikRouter->id,
                'success' => true,
                'status' => 'online',
                'last_connected' => $mikrotikRouter->fresh()->last_connected?->toIso8601String(),
            ]);
        }

        $mikrotikRouter->update(['status' => 'offline']);

        return response()->json([
            'router_id' => $mikrotikRouter->id,
            'success' => false,
            'status' => 'offline',
            'error' => $result['message'],
        ]);
    }

    public function storeOlt(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|in:huawei,zte,fiberhome,cdata,global,vsol,hsgq,hioso',
            'model' => 'nullable|string|max:255',
            'ip_address' => 'required|string|max:45',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'snmp_community' => 'nullable|string|max:255',
            'snmp_version' => 'nullable|in:v1,v2c,v3',
            'snmp_port' => 'required|integer|min:1|max:65535',
            'location' => 'nullable|string|max:255',
            'jump_host' => 'nullable|string|max:45',
            'jump_port' => 'required_with:jump_host|integer|min:1|max:65535',
            'jump_username' => 'nullable|string|max:255',
            'jump_password' => 'nullable|string|max:255',
            'status' => 'required|in:active,maintenance,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        $olt = Olt::create($validated);

        ActivityLog::log('Tambah OLT (Integrasi)', 'Menambahkan OLT: '.$olt->name);

        return back()->with('success', 'OLT berhasil ditambahkan.');
    }

    public function updateOlt(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|in:huawei,zte,fiberhome,cdata,global,vsol,hsgq,hioso',
            'model' => 'nullable|string|max:255',
            'ip_address' => 'required|string|max:45',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'snmp_community' => 'nullable|string|max:255',
            'snmp_version' => 'nullable|in:v1,v2c,v3',
            'snmp_port' => 'required|integer|min:1|max:65535',
            'location' => 'nullable|string|max:255',
            'jump_host' => 'nullable|string|max:45',
            'jump_port' => 'required_with:jump_host|integer|min:1|max:65535',
            'jump_username' => 'nullable|string|max:255',
            'jump_password' => 'nullable|string|max:255',
            'status' => 'required|in:active,maintenance,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (blank($validated['password'])) {
            unset($validated['password']);
        }

        if (blank($validated['jump_password'])) {
            unset($validated['jump_password']);
        }

        $olt->update($validated);

        ActivityLog::log('Ubah OLT (Integrasi)', 'Mengubah OLT: '.$validated['name']);

        return back()->with('success', 'OLT berhasil diperbarui.');
    }

    public function destroyOlt(Olt $olt)
    {
        $name = $olt->name;
        $olt->delete();

        ActivityLog::log('Hapus OLT (Integrasi)', 'Menghapus OLT: '.$name);

        return back()->with('success', 'OLT berhasil dihapus.');
    }

    public function testOlt(Olt $olt)
    {
        if (! $olt->usesMikrotikProxy()) {
            $start = microtime(true);
            $sock = @fsockopen($olt->ip_address, $olt->ssh_port, $errno, $errstr, 5);
            if (! $sock) {
                $ping = round((microtime(true) - $start) * 1000, 1);
                $olt->update(['connection_status' => 'offline']);

                return back()->with('error',
                    "Port {$olt->ssh_port} di {$olt->ip_address} tidak reachable (timeout {$ping}ms). Cek routing/firewall antara server dan OLT."
                );
            }
            fclose($sock);
        }

        if (empty($olt->password)) {
            $olt->update(['connection_status' => 'offline']);

            return back()->with('error', "Password OLT \"{$olt->name}\" belum diisi. Silakan edit OLT dan isi password terlebih dahulu.");
        }

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
                $olt->update(['connection_status' => 'offline']);

                return back()->with('error', "SSH login ditolak oleh {$olt->ip_address}{$via}. Cek username/password OLT.");
            }

            $result = $connector->testConnection();
            $connector->disconnect();

            if ($result['success']) {
                $olt->update(['connection_status' => 'online', 'last_polled_at' => now()]);
                ActivityLog::log('Test koneksi OLT (Integrasi)', "OLT: {$olt->name} ({$olt->ip_address})");

                return back()->with('success', $result['message']);
            }

            $olt->update(['connection_status' => 'offline']);

            return back()->with('error', $result['message']);
        } catch (\Exception $e) {
            $olt->update(['connection_status' => 'offline']);

            return back()->with('error', 'Koneksi SSH gagal: '.$e->getMessage());
        }
    }

    /**
     * Real-time ONU read for a single OLT (AJAX).
     *
     * Connects to the OLT on-demand and reads ONU counts straight from the
     * device — no reliance on the cached `onus` table.
     */
    public function liveOlt(Olt $olt)
    {
        $olt->load('ports');

        $ping = null;
        try {
            if ($olt->usesMikrotikProxy()) {
                $start = microtime(true);
                $mikrotikHost = Setting::get('mikrotik_host');
                $mikrotikUser = Setting::get('mikrotik_user');
                $mikrotikPass = Setting::get('mikrotik_password');
                $mikrotikPort = (int) Setting::get('mikrotik_port', '80');
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
            'slot' => $port->slot_number,
            'port' => $port->port_number,
            'type' => $port->port_type,
        ])->values()->all();

        $summary = ['total_onus' => 0, 'online_onus' => 0, 'offline_onus' => 0, 'onus' => []];
        $error = null;

        try {
            $connector = OltConnectorFactory::make($olt->brand, $olt);
            $connected = $connector->connect(
                $olt->ip_address,
                $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            if (! $connected) {
                throw new \Exception('SSH login gagal');
            }

            if (method_exists($connector, 'getOnuSummaryAll')) {
                $summary = $connector->getOnuSummaryAll($ports);
            } else {
                foreach ($ports as $p) {
                    foreach ($connector->getOnuList($p['slot'], $p['port']) as $onu) {
                        $status = ($onu['status'] ?? '') === 'online' ? 'online' : 'offline';
                        $summary['total_onus']++;
                        $summary[$status === 'online' ? 'online_onus' : 'offline_onus']++;
                        $summary['onus'][] = ['onu_id' => $onu['onu_id'] ?? '-', 'status' => $status];
                    }
                }
            }

            $connector->disconnect();
            $olt->update(['connection_status' => 'online', 'last_polled_at' => now()]);
        } catch (\Exception $e) {
            $error = $e->getMessage();
            $olt->update(['connection_status' => 'offline']);
        }

        return response()->json([
            'olt_id' => $olt->id,
            'ping' => $ping,
            'connection_status' => $olt->connection_status,
            'last_polled_at' => $olt->last_polled_at?->toIso8601String(),
            'total_onus' => $summary['total_onus'],
            'online_onus' => $summary['online_onus'],
            'offline_onus' => $summary['offline_onus'],
            'onus' => $summary['onus'],
            'error' => $error,
        ]);
    }
}
