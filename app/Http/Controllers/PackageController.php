<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Package;
use App\Services\Billing\BillingService;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index()
    {
        $query = Package::withCount('customers')->orderBy('price');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('speed', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('mikrotik_profile', 'like', "%{$search}%");
            });
        }

        if (request()->filled('status')) {
            $query->where('is_active', request('status') === 'active');
        }

        $packages = $query->paginate(15)->withQueryString();

        $mikrotikProfiles = [];
        $mikrotik = new MikrotikService;
        if ($mikrotik->isConfigured()) {
            $mikrotikProfiles = $mikrotik->getPppProfiles();
        }

        return view('packages.index', compact('packages', 'mikrotikProfiles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'speed' => 'required|numeric|min:1|max:10000',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'nullable|in:daily,weekly,monthly,yearly',
            'mikrotik_profile' => 'nullable|string|max:255',
            'mikrotik_profile_manual' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if (($validated['mikrotik_profile'] ?? '') === '__custom__') {
            $validated['mikrotik_profile'] = $validated['mikrotik_profile_manual'] ?? null;
        }
        unset($validated['mikrotik_profile_manual']);

        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $validated['billing_cycle'] = $validated['billing_cycle'] ?? 'monthly';

        Package::create($validated);

        $mikrotikMessage = '';
        if (! empty($validated['mikrotik_profile'])) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $result = $mikrotik->addPppProfile($validated['mikrotik_profile']);
                $mikrotikMessage = $result['success'] ? '' : ' (Profil MikroTik gagal: '.$result['message'].')';
            }
        }

        ActivityLog::log('Tambah Paket', 'Menambahkan paket: '.$validated['name']);

        return redirect()->route('packages.index')->with('success', 'Paket berhasil ditambahkan.'.$mikrotikMessage);
    }

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'speed' => 'required|numeric|min:1|max:10000',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'nullable|in:daily,weekly,monthly,yearly',
            'mikrotik_profile' => 'nullable|string|max:255',
            'mikrotik_profile_manual' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if (($validated['mikrotik_profile'] ?? '') === '__custom__') {
            $validated['mikrotik_profile'] = $validated['mikrotik_profile_manual'] ?? null;
        }
        unset($validated['mikrotik_profile_manual']);

        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : $package->is_active;
        $validated['billing_cycle'] = $validated['billing_cycle'] ?? 'monthly';

        $oldProfile = $package->mikrotik_profile;
        $package->update($validated);

        $mikrotikMessage = '';
        $mikrotik = new MikrotikService;
        if ($mikrotik->isConfigured()) {
            $newProfile = $validated['mikrotik_profile'] ?? null;

            if ($oldProfile && $oldProfile !== $newProfile) {
                $profiles = $mikrotik->getPppProfiles();
                $match = collect($profiles)->firstWhere('name', $oldProfile);
                if ($match && isset($match['.id'])) {
                    $result = $mikrotik->removePppProfile($match['.id']);
                    $mikrotikMessage .= $result['success'] ? '' : ' (Hapus profil lama gagal: '.$result['message'].')';
                }
            }

            if ($newProfile && $newProfile !== $oldProfile) {
                $result = $mikrotik->addPppProfile($newProfile);
                $mikrotikMessage .= $result['success'] ? '' : ' (Tambah profil baru gagal: '.$result['message'].')';
            }
        }

        ActivityLog::log('Ubah Paket', 'Mengubah paket: '.$package->name);

        return redirect()->route('packages.index')->with('success', 'Paket berhasil diperbarui.'.$mikrotikMessage);
    }

    public function destroy(Package $package)
    {
        if ($package->customers()->exists()) {
            ActivityLog::log('Gagal Hapus Paket', 'Paket '.$package->name.' masih dipakai '.$package->customers()->count().' pelanggan');

            return redirect()->route('packages.index')->with('error', 'Paket tidak bisa dihapus karena masih dipakai pelanggan. Nonaktifkan paket jika tidak ingin dipakai lagi.');
        }

        $name = $package->name;
        $profileName = $package->mikrotik_profile;
        $package->delete();

        $mikrotikMessage = '';
        if ($profileName) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $profiles = $mikrotik->getPppProfiles();
                $match = collect($profiles)->firstWhere('name', $profileName);
                if ($match && isset($match['.id'])) {
                    $result = $mikrotik->removePppProfile($match['.id']);
                    $mikrotikMessage = $result['success'] ? '' : ' (Hapus profil MikroTik gagal: '.$result['message'].')';
                }
            }
        }

        ActivityLog::log('Hapus Paket', 'Menghapus paket: '.$name);

        return redirect()->route('packages.index')->with('success', 'Paket '.$name.' berhasil dihapus.'.$mikrotikMessage);
    }

    public function massBill(BillingService $billing)
    {
        $generated = $billing->generateMonthlyInvoices();

        ActivityLog::log('Tagih Massal', "Generate {$generated} tagihan untuk semua pelanggan aktif");

        return back()->with('success', "Berhasil membuat {$generated} tagihan.");
    }
}
