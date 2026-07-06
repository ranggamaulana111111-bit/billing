<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MikrotikRouter;
use App\Models\VoucherProfile;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class VoucherProfileController extends Controller
{
    public function index()
    {
        $routers = MikrotikRouter::where('is_active', true)->get();
        $mikrotikProfiles = collect();
        $error = null;

        $localMap = [];
        foreach (VoucherProfile::all() as $lp) {
            $key = $lp->mikrotik_profile ?: $lp->name;
            $localMap[$key] = $lp;
        }

        foreach ($routers as $router) {
            try {
                $mikrotik = new MikrotikService($router);
                $list = $mikrotik->getHotspotProfiles();
                foreach ($list as $p) {
                    $name = $p['name'] ?? '';
                    $local = $localMap[$name] ?? null;
                    $mikrotikProfiles->push([
                        'id' => $p['.id'] ?? '',
                        'name' => $name,
                        'speed' => $p['rate-limit'] ?? null,
                        'shared_users' => $p['shared-users'] ?? 1,
                        'address_pool' => $p['address-pool'] ?? null,
                        'parent_queue' => $p['parent-queue'] ?? ($local->parent_queue ?? null),
                        'lock_user' => ($p['add-mac-cookie'] ?? '') === 'yes' || ($local->lock_user ?? false),
                        'router' => $router->name,
                        'price' => $local->price ?? 0,
                        'selling_price' => $local->selling_price ?? null,
                        'local_id' => $local->id ?? null,
                    ]);
                }
            } catch (\Exception $e) {
                // skip unreachable router
            }
        }

        if ($mikrotikProfiles->isEmpty()) {
            try {
                $mikrotik = new MikrotikService;
                if ($mikrotik->isConfigured()) {
                    $list = $mikrotik->getHotspotProfiles();
                    foreach ($list as $p) {
                        $name = $p['name'] ?? '';
                        $local = $localMap[$name] ?? null;
                        $mikrotikProfiles->push([
                            'id' => $p['.id'] ?? '',
                            'name' => $name,
                            'speed' => $p['rate-limit'] ?? null,
                            'shared_users' => $p['shared-users'] ?? 1,
                        'address_pool' => $p['address-pool'] ?? null,
                        'parent_queue' => $p['parent-queue'] ?? ($local->parent_queue ?? null),
                        'lock_user' => ($p['add-mac-cookie'] ?? '') === 'yes' || ($local->lock_user ?? false),
                            'router' => 'Default',
                            'price' => $local->price ?? 0,
                            'selling_price' => $local->selling_price ?? null,
                            'local_id' => $local->id ?? null,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $error = 'Gagal terhubung ke MikroTik: ' . $e->getMessage();
            }
        }

        if ($mikrotikProfiles->isEmpty() && ! $error) {
            $error = 'Tidak ada profile MikroTik yang tersedia.';
        }

        return view('voucher-profiles.index', compact('mikrotikProfiles', 'routers', 'error'));
    }

    private function buildMikrotikParams(Request $request): array
    {
        $params = [];
        if ($request->filled('speed')) {
            $params['rate-limit'] = $request->speed;
        }
        if ($request->filled('shared_users')) {
            $params['shared-users'] = (int) $request->shared_users;
        }
        if ($request->filled('address_pool')) {
            $params['address-pool'] = $request->address_pool;
        }
        if ($request->filled('parent_queue')) {
            $params['parent-queue'] = $request->parent_queue;
        }
        $params['add-mac-cookie'] = $request->boolean('lock_user') ? 'yes' : 'no';

        return $params;
    }

    private function saveLocalRecord(string $name, array $data): VoucherProfile
    {
        $local = VoucherProfile::where('mikrotik_profile', $name)->first();
        if ($local) {
            $local->update($data);
            return $local;
        }

        return VoucherProfile::create(array_merge([
            'name' => $name,
            'mikrotik_profile' => $name,
            'price' => 0,
            'shared_users' => 1,
            'is_active' => true,
        ], $data));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'speed' => 'nullable|string|max:50',
            'shared_users' => 'nullable|integer|min:1|max:100',
            'address_pool' => 'nullable|string|max:255',
            'parent_queue' => 'nullable|string|max:255',
            'lock_user' => 'nullable|boolean',
            'price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
        ]);

        $params = $this->buildMikrotikParams($request);

        $routers = MikrotikRouter::where('is_active', true)->whereIn('type', ['general'])->get();
        $result = null;

        foreach ($routers as $router) {
            try {
                $mikrotik = new MikrotikService($router);
                $result = $mikrotik->addHotspotProfile($request->name, $params);
                if ($result['success']) {
                    break;
                }
            } catch (\Exception $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        if (! $result || ! $result['success']) {
            try {
                $mikrotik = new MikrotikService;
                if ($mikrotik->isConfigured()) {
                    $result = $mikrotik->addHotspotProfile($request->name, $params);
                }
            } catch (\Exception $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        if (! $result || ! ($result['success'] ?? false)) {
            return back()->with('error', 'Gagal membuat profile: ' . ($result['message'] ?? 'Tidak ada router terhubung'));
        }

        $this->saveLocalRecord($request->name, [
            'speed' => $request->speed,
            'shared_users' => $request->shared_users ?: 1,
            'address_pool' => $request->address_pool,
            'parent_queue' => $request->parent_queue,
            'lock_user' => $request->boolean('lock_user'),
            'price' => $request->price ?: 0,
            'selling_price' => $request->selling_price,
        ]);

        ActivityLog::log('Buat Profile MikroTik', 'Membuat hotspot profile: ' . $request->name);

        return back()->with('success', 'Profile "' . $request->name . '" berhasil dibuat di MikroTik.');
    }

    public function destroyMikrotik(Request $request, string $profileId)
    {
        $routers = MikrotikRouter::where('is_active', true)->whereIn('type', ['general'])->get();
        $result = null;

        foreach ($routers as $router) {
            try {
                $mikrotik = new MikrotikService($router);
                $result = $mikrotik->removeHotspotProfile($profileId);
                if ($result['success']) {
                    break;
                }
            } catch (\Exception $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        if (! $result || ! $result['success']) {
            try {
                $mikrotik = new MikrotikService;
                if ($mikrotik->isConfigured()) {
                    $result = $mikrotik->removeHotspotProfile($profileId);
                }
            } catch (\Exception $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        if ($result && ($result['success'] ?? false)) {
            ActivityLog::log('Hapus Profile MikroTik', 'Menghapus hotspot profile ID: ' . $profileId);
            return back()->with('success', 'Profile berhasil dihapus dari MikroTik.');
        }

        return back()->with('error', 'Gagal menghapus profile: ' . ($result['message'] ?? 'Tidak ada router terhubung'));
    }

    public function updateMikrotik(Request $request, string $profileId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'speed' => 'nullable|string|max:50',
            'shared_users' => 'nullable|integer|min:1|max:100',
            'address_pool' => 'nullable|string|max:255',
            'parent_queue' => 'nullable|string|max:255',
            'lock_user' => 'nullable|boolean',
            'price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
        ]);

        $params = $this->buildMikrotikParams($request);
        $params['name'] = $request->name;

        $routers = MikrotikRouter::where('is_active', true)->whereIn('type', ['general'])->get();
        $result = null;

        foreach ($routers as $router) {
            try {
                $mikrotik = new MikrotikService($router);
                $result = $mikrotik->updateHotspotProfile($profileId, $params);
                if ($result['success']) {
                    break;
                }
            } catch (\Exception $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        if (! $result || ! $result['success']) {
            try {
                $mikrotik = new MikrotikService;
                if ($mikrotik->isConfigured()) {
                    $result = $mikrotik->updateHotspotProfile($profileId, $params);
                }
            } catch (\Exception $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        if (! $result || ! ($result['success'] ?? false)) {
            return back()->with('error', 'Gagal mengupdate profile: ' . ($result['message'] ?? 'Tidak ada router terhubung'));
        }

        $this->saveLocalRecord($request->name, [
            'speed' => $request->speed,
            'shared_users' => $request->shared_users ?: 1,
            'address_pool' => $request->address_pool,
            'parent_queue' => $request->parent_queue,
            'lock_user' => $request->boolean('lock_user'),
            'price' => $request->price ?: 0,
            'selling_price' => $request->selling_price,
        ]);

        ActivityLog::log('Update Profile MikroTik', 'Mengupdate hotspot profile: ' . $request->name);

        return back()->with('success', 'Profile "' . $request->name . '" berhasil diperbarui.');
    }

    public function syncMikrotik()
    {
        $routers = MikrotikRouter::where('is_active', true)->whereIn('type', ['general'])->get();

        $count = 0;
        $processProfiles = function (MikrotikService $mikrotik, string $label) use (&$count) {
            try {
                $profiles = $mikrotik->getHotspotProfiles();
            } catch (\Exception $e) {
                return;
            }

            foreach ($profiles as $profile) {
                $name = $profile['name'] ?? null;
                if (! $name) {
                    continue;
                }

                $existing = VoucherProfile::where('mikrotik_profile', $name)->first();

                $data = [
                    'speed' => $profile['rate-limit'] ?? null,
                    'shared_users' => (int) ($profile['shared-users'] ?? 1),
                    'address_pool' => $profile['address-pool'] ?? null,
                    'expired_mode' => $profile['on-expire'] ?? null,
                    'parent_queue' => $profile['parent-queue'] ?? null,
                    'lock_user' => ($profile['add-mac-cookie'] ?? '') === 'yes',
                ];

                if ($existing) {
                    $existing->update($data);
                } else {
                    VoucherProfile::create(array_merge($data, [
                        'name' => $name,
                        'mikrotik_profile' => $name,
                        'price' => 0,
                        'is_active' => true,
                    ]));
                }
                $count++;
            }
        };

        foreach ($routers as $router) {
            $processProfiles(new MikrotikService($router), $router->name);
        }

        if ($count === 0) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $processProfiles($mikrotik, 'Default');
            }
        }

        if ($count === 0) {
            return back()->with('error', 'Tidak ada profile MikroTik yang tersedia untuk disinkronasi.');
        }

        $msg = "Sinkronasi selesai. {$count} profile tersimpan.";
        ActivityLog::log('Sync Profile Voucher dari MikroTik', $msg);

        return back()->with('success', $msg);
    }
}
