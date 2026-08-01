<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MikrotikRouter;
use App\Models\Olt;
use App\Services\MikrotikService;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index()
    {
        $routers = MikrotikRouter::orderBy('name')->get();
        $olts = Olt::orderBy('name')->get();

        return view('settings.integrations', compact('routers', 'olts'));
    }

    public function storeMikrotik(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
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

        ActivityLog::log('Test Router (Integrasi)', 'Test koneksi router: '.$mikrotikRouter->name.' ('.$mikrotikRouter->host.')');

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function storeOlt(Request $request)
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

                return back()->with('error',
                    "Port {$olt->ssh_port} di {$olt->ip_address} tidak reachable (timeout {$ping}ms). Cek routing/firewall antara server dan OLT."
                );
            }
            fclose($sock);
        }

        if (empty($olt->password)) {
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

                return back()->with('error', "SSH login ditolak oleh {$olt->ip_address}{$via}. Cek username/password OLT.");
            }

            $result = $connector->testConnection();
            $connector->disconnect();

            if ($result['success']) {
                ActivityLog::log('Test koneksi OLT (Integrasi)', "OLT: {$olt->name} ({$olt->ip_address})");

                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Exception $e) {
            return back()->with('error', 'Koneksi SSH gagal: '.$e->getMessage());
        }
    }
}
