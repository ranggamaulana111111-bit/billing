<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\OdpPoint;
use App\Models\OdpPort;
use App\Models\OdpRoute;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DistributionController extends Controller
{
    public function index()
    {
        $odps = OdpPoint::with('customers', 'route')->get();
        $routes = OdpRoute::with('odc', 'points')->get();
        $odcs = Odc::withCount(['routes', 'odps'])->orderBy('nama_odc')->get();

        $newOdps = Odp::with('ports', 'odc')->get();
        $newOdpsJson = $newOdps->map(fn ($o) => [
            'id' => $o->id,
            'odc_id' => $o->odc_id,
            'name' => $o->nama_odp,
            'latitude' => $o->latitude,
            'longitude' => $o->longitude,
            'port_capacity' => (int) $o->kapasitas_port,
            'used' => $o->usedPortsCount(),
            'address' => $o->odc?->nama_odc,
            'kondisi_jalur' => $o->kondisi_jalur,
            'customers' => $o->customers()->pluck('name')->toArray(),
            'onu_total' => (int) $o->customers()->whereHas('onus')->count(),
            'onu_online' => (int) $o->customers()->whereHas('onus', fn ($q) => $q->where('status', 'online'))->count(),
        ]);

        $totalPorts = $newOdps->sum('kapasitas_port');
        $usedPorts = $newOdps->sum(fn ($o) => $o->usedPortsCount());
        $availablePorts = $totalPorts - $usedPorts;
        $totalOdps = $newOdps->count();
        $fullOdps = $newOdps->filter(fn ($o) => $o->availablePortsCount() === 0)->count();
        $downOdps = $newOdps->filter(fn ($o) => $o->kondisi_jalur === 'DOWN_LINK_FAILURE')->count();

        $chartLabels = $newOdps->pluck('nama_odp');
        $chartUsed = $newOdps->map(fn ($o) => $o->usedPortsCount());
        $chartCapacity = $newOdps->pluck('kapasitas_port');

        return view('distribution.index', compact(
            'odps', 'routes', 'odcs', 'newOdps', 'newOdpsJson',
            'totalPorts', 'usedPorts', 'availablePorts', 'totalOdps', 'fullOdps', 'downOdps',
            'chartLabels', 'chartUsed', 'chartCapacity'
        ));
    }

    public function storeOdc(Request $request)
    {
        $validated = $request->validate([
            'nama_odc' => 'required|string|max:255|unique:odcs,nama_odc',
            'koordinat' => 'nullable|string|max:255',
            'kapasitas_port' => 'required|integer|in:4,8,16',
        ]);

        $odc = Odc::create($validated);

        for ($i = 1; $i <= $odc->kapasitas_port; $i++) {
            $odc->ports()->create(['port_number' => $i, 'port_type' => 'outlet', 'status' => 'available']);
        }

        ActivityLog::log('Tambah ODC', 'Menambahkan ODC: '.$odc->nama_odc);

        return back()->with('success', 'ODC berhasil ditambahkan.');
    }

    public function updateOdc(Request $request, Odc $odc)
    {
        $validated = $request->validate([
            'nama_odc' => ['required', 'string', 'max:255', Rule::unique('odcs', 'nama_odc')->ignore($odc)],
            'koordinat' => 'nullable|string|max:255',
            'kapasitas_port' => 'required|integer|in:4,8,16',
        ]);

        $odc->update($validated);

        ActivityLog::log('Ubah ODC', 'Mengubah ODC: '.$odc->nama_odc);

        return back()->with('success', 'ODC berhasil diperbarui.');
    }

    public function destroyOdc(Odc $odc)
    {
        $usedCount = $odc->odps()->count();
        if ($usedCount > 0) {
            ActivityLog::log('Gagal Hapus ODC', 'ODC '.$odc->nama_odc.' masih memiliki '.$usedCount.' ODP');

            return back()->with('error', 'ODC tidak bisa dihapus karena masih memiliki ODP.');
        }

        $name = $odc->nama_odc;
        $odc->delete();

        ActivityLog::log('Hapus ODC', 'Menghapus ODC: '.$name);

        return back()->with('success', 'ODC berhasil dihapus.');
    }

    public function storeOdp(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'odc_id' => 'nullable|exists:odcs,id',
            'nama_odp' => 'required|string|max:255|unique:odps,nama_odp',
            'koordinat' => 'nullable|string|max:255',
            'kapasitas_port' => 'required|integer|in:8,16',
            'kabel_tube_color' => 'required|string',
            'kabel_core_number' => 'required|integer|min:1',
        ]);

        $validated['tenant_id'] = auth()->user()?->tenant_id;

        $odp = Odp::create($validated);

        for ($i = 1; $i <= $odp->kapasitas_port; $i++) {
            OdpPort::create(['odp_id' => $odp->id, 'port_number' => $i, 'status' => 'available']);
        }

        ActivityLog::log('Tambah ODP', 'Menambahkan ODP: '.$odp->nama_odp);

        return back()->with('success', 'ODP berhasil ditambahkan.');
    }

    public function storeRoute(Request $request)
    {
        $validated = $request->validate([
            'odc_id' => 'nullable|exists:odcs,id',
            'name' => 'required|string|max:255|unique:odp_routes,name',
            'description' => 'nullable|string|max:255',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'coordinates' => 'nullable|string',
        ]);

        $validated['coordinates'] = $this->parseCoordinates($validated['coordinates'] ?? null);

        $route = OdpRoute::create($validated);

        ActivityLog::log('Tambah Route ODP', 'Menambahkan route ODP: '.$route->name);

        return back()->with('success', 'Route ODP berhasil ditambahkan.');
    }

    public function updateRoute(Request $request, OdpRoute $odpRoute)
    {
        $validated = $request->validate([
            'odc_id' => 'nullable|exists:odcs,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('odp_routes', 'name')->ignore($odpRoute)],
            'description' => 'nullable|string|max:255',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'coordinates' => 'nullable|string',
        ]);

        $validated['coordinates'] = $this->parseCoordinates($validated['coordinates'] ?? null);
        $odpRoute->update($validated);

        ActivityLog::log('Ubah Route ODP', 'Mengubah route ODP: '.$odpRoute->name);

        return back()->with('success', 'Route ODP berhasil diperbarui.');
    }

    public function destroyRoute(OdpRoute $odpRoute)
    {
        if ($odpRoute->points()->exists()) {
            ActivityLog::log('Gagal Hapus Route ODP', 'Route '.$odpRoute->name.' masih memiliki titik ODP');

            return back()->with('error', 'Route tidak bisa dihapus karena masih memiliki titik ODP.');
        }

        $name = $odpRoute->name;
        $odpRoute->delete();

        ActivityLog::log('Hapus Route ODP', 'Menghapus route ODP: '.$name);

        return back()->with('success', 'Route ODP berhasil dihapus.');
    }

    public function storePoint(Request $request)
    {
        $validated = $request->validate([
            'odp_route_id' => 'required|exists:odp_routes,id',
            'name' => 'required|string|max:255|unique:odp_points,name',
            'address' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'status' => 'required|in:active,maintenance,inactive',
            'port_capacity' => 'required|integer|min:1|max:1024',
            'port_used' => 'nullable|integer|min:0|max:1024',
        ]);

        $validated['port_used'] = $validated['port_used'] ?? 0;

        $point = OdpPoint::create($validated);

        ActivityLog::log('Tambah Titik ODP', 'Menambahkan titik ODP: '.$point->name);

        return back()->with('success', 'Titik ODP berhasil ditambahkan.');
    }

    public function updatePoint(Request $request, OdpPoint $odpPoint)
    {
        $validated = $request->validate([
            'odp_route_id' => 'required|exists:odp_routes,id',
            'name' => ['required', 'string', 'max:255', Rule::unique('odp_points', 'name')->ignore($odpPoint)],
            'address' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'status' => 'required|in:active,maintenance,inactive',
            'port_capacity' => 'required|integer|min:1|max:1024',
            'port_used' => 'nullable|integer|min:0|max:1024',
        ]);

        $usedByCustomers = $odpPoint->customers()->count();
        if ((int) $validated['port_capacity'] < $usedByCustomers) {
            return back()->with('error', 'Kapasitas port tidak boleh lebih kecil dari jumlah pelanggan yang memakai ODP ini.');
        }

        $validated['port_used'] = $validated['port_used'] ?? 0;
        $odpPoint->update($validated);

        ActivityLog::log('Ubah Titik ODP', 'Mengubah titik ODP: '.$odpPoint->name);

        return back()->with('success', 'Titik ODP berhasil diperbarui.');
    }

    public function destroyPoint(OdpPoint $odpPoint)
    {
        if ($odpPoint->customers()->exists()) {
            ActivityLog::log('Gagal Hapus Titik ODP', 'Titik '.$odpPoint->name.' masih dipakai '.$odpPoint->customers()->count().' pelanggan');

            return back()->with('error', 'Titik ODP tidak bisa dihapus karena masih dipakai pelanggan.');
        }

        $name = $odpPoint->name;
        $odpPoint->delete();

        ActivityLog::log('Hapus Titik ODP', 'Menghapus titik ODP: '.$name);

        return back()->with('success', 'Titik ODP berhasil dihapus.');
    }

    private function parseCoordinates(?string $coordinates): array
    {
        if (! $coordinates) {
            return [];
        }

        $decoded = json_decode($coordinates, true);

        return is_array($decoded) ? $decoded : [];
    }
}
