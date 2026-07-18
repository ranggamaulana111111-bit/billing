<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MikrotikRouter;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MikrotikDeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = MikrotikRouter::query();

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('type')) {
            $query->byType($request->input('type'));
        }

        $routers = $query->orderBy('name')->paginate(20)->withQueryString();

        $stats = [
            'total' => MikrotikRouter::count(),
            'online' => MikrotikRouter::where('status', 'online')->count(),
            'offline' => MikrotikRouter::where('status', 'offline')->count(),
            'unknown' => MikrotikRouter::where('status', 'unknown')->count(),
        ];

        return view('noc.mikrotik-devices.index', compact('routers', 'stats'));
    }

    public function create()
    {
        return view('noc.mikrotik-devices.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'identity' => 'nullable|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'api_ssl_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'hotspot_server' => 'nullable|string|max:255',
            'type' => 'required|in:pppoe,bandwidth,general',
            'connection_type' => 'required|in:rest_api,api_ssl,ssh',
            'site' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'management_vlan' => 'nullable|integer|min:1|max:4094',
            'management_interface' => 'nullable|string|max:255',
            'timeout' => 'nullable|integer|min:1|max:120',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['password'] = $validated['password'] ?? '';
        $validated['status'] = 'unknown';

        if (blank($validated['hotspot_server'] ?? null)) {
            $validated['hotspot_server'] = 'all';
        }

        $router = MikrotikRouter::create($validated);

        ActivityLog::log('Tambah Router NOC', 'Menambahkan router: '.$router->name);

        return redirect()->route('noc.mikrotik-devices.show', $router)
            ->with('success', 'Router berhasil ditambahkan.');
    }

    public function show(MikrotikRouter $mikrotikDevice)
    {
        return view('noc.mikrotik-devices.show', ['router' => $mikrotikDevice]);
    }

    public function edit(MikrotikRouter $mikrotikDevice)
    {
        return view('noc.mikrotik-devices.edit', ['router' => $mikrotikDevice]);
    }

    public function update(Request $request, MikrotikRouter $mikrotikDevice)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'identity' => 'nullable|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'api_ssl_port' => 'nullable|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'hotspot_server' => 'nullable|string|max:255',
            'type' => 'required|in:pppoe,bandwidth,general',
            'connection_type' => 'required|in:rest_api,api_ssl,ssh',
            'site' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'management_vlan' => 'nullable|integer|min:1|max:4094',
            'management_interface' => 'nullable|string|max:255',
            'timeout' => 'nullable|integer|min:1|max:120',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $validated['is_active'] = $request->has('is_active');

        if (blank($validated['password'])) {
            unset($validated['password']);
        }

        if (blank($validated['hotspot_server'] ?? null)) {
            $validated['hotspot_server'] = 'all';
        }

        $mikrotikDevice->update($validated);

        ActivityLog::log('Ubah Router NOC', 'Mengubah router: '.$mikrotikDevice->name);

        return redirect()->route('noc.mikrotik-devices.show', $mikrotikDevice)
            ->with('success', 'Router berhasil diperbarui.');
    }

    public function destroy(MikrotikRouter $mikrotikDevice)
    {
        if ($mikrotikDevice->vouchers()->exists()) {
            return back()->with('error', 'Router tidak bisa dihapus karena masih memiliki voucher.');
        }

        $name = $mikrotikDevice->name;
        $mikrotikDevice->delete();

        ActivityLog::log('Hapus Router NOC', 'Menghapus router: '.$name);

        return redirect()->route('noc.mikrotik-devices.index')
            ->with('success', 'Router berhasil dihapus.');
    }

    public function testConnection(MikrotikRouter $mikrotikDevice)
    {
        $service = new MikrotikService($mikrotikDevice);
        $result = $service->testConnection();

        // Update status based on result
        if ($result['success']) {
            $mikrotikDevice->update([
                'status' => 'online',
                'last_seen' => now(),
                'last_connected' => now(),
            ]);

            // Try to fetch identity and system info
            try {
                $identity = $service->getSystemIdentity();
                if (! empty($identity['name'])) {
                    $mikrotikDevice->update(['identity' => $identity['name']]);
                }

                $resource = $service->getSystemResource();
                if (! empty($resource)) {
                    $mikrotikDevice->update([
                        'routeros_version' => $resource['version'] ?? null,
                        'model' => $resource['board-name'] ?? null,
                        'architecture' => $resource['architecture-name'] ?? null,
                    ]);
                }
            } catch (\Exception $e) {
                // Info fetch failed — connection itself works, so just log it
                Log::info('Device info fetch failed after successful connection: '.$e->getMessage());
            }
        } else {
            $mikrotikDevice->update(['status' => 'offline']);
        }

        // Determine specific error type for better UX
        $message = $result['message'];
        $errorType = null;
        if (! $result['success']) {
            $errorType = $this->classifyConnectionError($message);
            $message = $this->humanizeError($errorType, $message);
        }

        ActivityLog::log('Test Koneksi NOC', 'Test koneksi: '.$mikrotikDevice->name.' ('.$mikrotikDevice->host.') — '.($result['success'] ? 'OK' : 'FAIL'));

        return back()->with($result['success'] ? 'success' : 'error', $message);
    }

    public function toggleStatus(MikrotikRouter $mikrotikDevice)
    {
        $mikrotikDevice->update([
            'is_active' => ! $mikrotikDevice->is_active,
        ]);

        $label = $mikrotikDevice->is_active ? 'diaktifkan' : 'dinonaktifkan';
        ActivityLog::log('Toggle Status NOC', 'Router '.$mikrotikDevice->name.' '.$label);

        return back()->with('success', "Router berhasil {$label}.");
    }

    private function classifyConnectionError(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'curl error 6') || str_contains($lower, 'could not resolve host')) {
            return 'dns_error';
        }

        if (str_contains($lower, 'curl error 7') || str_contains($lower, 'connection refused')) {
            return 'connection_refused';
        }

        if (str_contains($lower, 'curl error 28') || str_contains($lower, 'timed out')) {
            return 'timeout';
        }

        if (str_contains($lower, 'ssl') || str_contains($lower, 'certificate') || str_contains($lower, 'curl error 60')) {
            return 'ssl_error';
        }

        if (str_contains($lower, '401') || str_contains($lower, 'unauthorized') || str_contains($lower, 'login failed')) {
            return 'auth_failed';
        }

        if (str_contains($lower, 'api disabled') || str_contains($lower, 'api not enabled')) {
            return 'api_disabled';
        }

        return 'unknown';
    }

    private function humanizeError(string $errorType, string $original): string
    {
        return match ($errorType) {
            'dns_error' => 'DNS tidak dapat meresolve host. Pastikan hostname/IP benar.',
            'connection_refused' => 'Koneksi ditolak. Pastikan REST API aktif di port yang benar.',
            'timeout' => 'Koneksi timeout. Periksa jaringan atau firewall.',
            'ssl_error' => 'Error SSL/TLS. Coba gunakan HTTP atau periksa sertifikat.',
            'auth_failed' => 'Autentikasi gagal. Periksa username/password.',
            'api_disabled' => 'REST API belum diaktifkan di MikroTik RouterOS.',
            default => $original,
        };
    }
}
