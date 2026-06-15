<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MikrotikRouter;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class MikrotikRouterController extends Controller
{
    public function index()
    {
        $routers = MikrotikRouter::orderBy('name')->get();

        return view('mikrotik-routers.index', compact('routers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'hotspot_server' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['password'] = $validated['password'] ?? '';

        MikrotikRouter::create($validated);

        ActivityLog::log('Tambah Router', 'Menambahkan router: '.$validated['name']);

        return back()->with('success', 'Router berhasil ditambahkan.');
    }

    public function update(Request $request, MikrotikRouter $mikrotikRouter)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'hotspot_server' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        if (blank($validated['password'])) {
            unset($validated['password']);
        }

        $mikrotikRouter->update($validated);

        ActivityLog::log('Ubah Router', 'Mengubah router: '.$validated['name']);

        return back()->with('success', 'Router berhasil diperbarui.');
    }

    public function destroy(MikrotikRouter $mikrotikRouter)
    {
        if ($mikrotikRouter->vouchers()->exists()) {
            return back()->with('error', 'Router tidak bisa dihapus karena masih memiliki voucher.');
        }

        $name = $mikrotikRouter->name;
        $mikrotikRouter->delete();

        ActivityLog::log('Hapus Router', 'Menghapus router: '.$name);

        return back()->with('success', 'Router berhasil dihapus.');
    }

    public function test(MikrotikRouter $mikrotikRouter)
    {
        $service = new MikrotikService($mikrotikRouter);

        $result = $service->testConnection();

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }
}
