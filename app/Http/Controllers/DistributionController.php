<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Odc;
use App\Models\OdpPoint;
use App\Models\OdpRoute;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DistributionController extends Controller
{
    public function index()
    {
        $odps = OdpPoint::with('customers', 'route')->get();
        $routes = OdpRoute::with('odc', 'points')->get();
        $odcs = Odc::withCount('routes')->orderBy('name')->get();

        $totalPorts = $odps->sum('port_capacity');
        $usedPorts = $odps->sum(fn ($o) => $o->customers->count());
        $availablePorts = $totalPorts - $usedPorts;
        $totalOdps = $odps->count();
        $fullOdps = $odps->filter(fn ($o) => $o->customers->count() >= $o->port_capacity)->count();

        $chartLabels = $odps->pluck('name');
        $chartUsed = $odps->map(fn ($o) => $o->customers->count());
        $chartCapacity = $odps->pluck('port_capacity');

        return view('distribution.index', compact(
            'odps', 'routes', 'odcs',
            'totalPorts', 'usedPorts', 'availablePorts', 'totalOdps', 'fullOdps',
            'chartLabels', 'chartUsed', 'chartCapacity'
        ));
    }

    public function storeOdc(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:odcs,name',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:active,maintenance,inactive',
            'capacity' => 'required|integer|min:0|max:1024',
            'notes' => 'nullable|string|max:1000',
        ]);

        $odc = Odc::create($validated);

        ActivityLog::log('Tambah ODC', 'Menambahkan ODC: '.$odc->name);

        return back()->with('success', 'ODC berhasil ditambahkan.');
    }

    public function updateOdc(Request $request, Odc $odc)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('odcs', 'name')->ignore($odc)],
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:active,maintenance,inactive',
            'capacity' => 'required|integer|min:0|max:1024',
            'notes' => 'nullable|string|max:1000',
        ]);

        $odc->update($validated);

        ActivityLog::log('Ubah ODC', 'Mengubah ODC: '.$odc->name);

        return back()->with('success', 'ODC berhasil diperbarui.');
    }

    public function destroyOdc(Odc $odc)
    {
        if ($odc->routes()->exists()) {
            ActivityLog::log('Gagal Hapus ODC', 'ODC '.$odc->name.' masih memiliki route ODP');

            return back()->with('error', 'ODC tidak bisa dihapus karena masih memiliki route ODP.');
        }

        $name = $odc->name;
        $odc->delete();

        ActivityLog::log('Hapus ODC', 'Menghapus ODC: '.$name);

        return back()->with('success', 'ODC berhasil dihapus.');
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
