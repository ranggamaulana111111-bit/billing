<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MikrotikRouter;
use App\Models\Setting;
use App\Models\Voucher;
use App\Models\VoucherProfile;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'vouchers');

        Voucher::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        // ── TAB 1: Voucher list ──
        $search = $request->get('search');
        $filterStatus = $request->get('status');

        $query = Voucher::latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('password', 'like', "%{$search}%");
            });
        }

        if ($filterStatus) {
            $query->where('status', $filterStatus);
        }

        $vouchers = $query->paginate(20);

        $stats = [
            'total' => Voucher::count(),
            'active' => Voucher::where('status', 'active')->count(),
            'used' => Voucher::where('status', 'used')->count(),
            'expired' => Voucher::where('status', 'expired')->count(),
        ];

        $activeRouters = MikrotikRouter::where('is_active', true)->get();
        $mikrotikConnected = $activeRouters->isNotEmpty() || (new MikrotikService)->isConfigured();

        // ── TAB 2: Report ──
        $reportQuery = Voucher::with('profile');

        if ($request->filled('report_profile_id')) {
            $reportQuery->where('voucher_profile_id', $request->report_profile_id);
        }

        if ($request->filled('report_status')) {
            $reportQuery->where('status', $request->report_status);
        }

        if ($request->filled('date_from')) {
            $reportQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $reportQuery->whereDate('created_at', '<=', $request->date_to);
        }

        $reportVouchers = $reportQuery->latest()->paginate(20, ['*'], 'report_page')->withQueryString();

        $reportStats = [
            'total' => Voucher::count(),
            'active' => Voucher::where('status', 'active')->count(),
            'used' => Voucher::where('status', 'used')->count(),
            'expired' => Voucher::where('status', 'expired')->count(),
            'revenue' => Voucher::where('status', 'used')->sum('price'),
        ];

        $reportProfiles = VoucherProfile::orderBy('name')->pluck('name', 'id');

        // ── TAB 3: Profiles ──
        $profiles = VoucherProfile::orderBy('name')->get();

        // ── TAB 4: Routers ──
        $routers = MikrotikRouter::orderBy('name')->get();

        // ── TAB 5: Templates ──
        $templates = \App\Models\VoucherTemplate::orderBy('name')->get();

        return view('vouchers.index', compact(
            'tab',
            'vouchers', 'stats', 'search', 'filterStatus', 'mikrotikConnected', 'activeRouters',
            'reportVouchers', 'reportStats', 'reportProfiles',
            'profiles',
            'routers',
            'templates',
        ));
    }

    public function create()
    {
        $profiles = \App\Models\VoucherProfile::where('is_active', true)->get();
        $routers = MikrotikRouter::where('is_active', true)->get();
        $templates = \App\Models\VoucherTemplate::where('is_active', true)->get();
        $mikrotikConnected = $routers->isNotEmpty() || (new MikrotikService)->isConfigured();

        return view('vouchers.create', compact('mikrotikConnected', 'profiles', 'routers', 'templates'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'duration' => 'required|integer|min:1|max:720',
            'duration_unit' => 'required|in:hours,days',
            'count' => 'required|integer|min:1|max:100',
            'profile_id' => 'nullable|exists:voucher_profiles,id',
            'router_id' => 'nullable|exists:mikrotik_routers,id',
            'template_id' => 'nullable|exists:voucher_templates,id',
            'prefix' => 'nullable|string|max:10|alpha_num',
        ]);

        $hours = $validated['duration_unit'] === 'days'
            ? $validated['duration'] * 24
            : $validated['duration'];

        $extra = [];
        if ($validated['profile_id']) {
            $profile = \App\Models\VoucherProfile::find($validated['profile_id']);
            if ($profile) {
                $extra = [
                    'voucher_profile_id' => $profile->id,
                    'price' => $profile->price,
                    'prefix' => $validated['prefix'] ?? '',
                    'speed' => $profile->speed,
                    'quota_limit' => $profile->quota_limit,
                    'validity_days' => $profile->validity_days,
                    'shared_users' => $profile->shared_users,
                ];
            }
        }
        if ($validated['router_id']) {
            $extra['router_id'] = $validated['router_id'];
        }
        if ($validated['template_id']) {
            $extra['voucher_template_id'] = $validated['template_id'];
        }

        $vouchers = Voucher::generate($hours, $validated['count'], $extra ?: null);

        $pushed = 0;
        $failed = 0;

        if ($validated['router_id']) {
            $router = MikrotikRouter::find($validated['router_id']);
            if ($router) {
                $mikrotik = new MikrotikService($router);
                foreach ($vouchers as $voucher) {
                    $result = $mikrotik->addHotspotUser(
                        $voucher->username,
                        $voucher->password,
                        null,
                        $hours
                    );
                    if ($result['success']) {
                        $pushed++;
                    } else {
                        $failed++;
                    }
                }
            }
        } else {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                foreach ($vouchers as $voucher) {
                    $result = $mikrotik->addHotspotUser(
                        $voucher->username,
                        $voucher->password,
                        null,
                        $hours
                    );
                    if ($result['success']) {
                        $pushed++;
                    } else {
                        $failed++;
                    }
                }
            }
        }

        $details = 'Membuat '.count($vouchers).' voucher WiFi ('.$hours.' jam)';
        if ($pushed > 0) {
            $details .= ". Push ke MikroTik: {$pushed} sukses";
        }
        if ($failed > 0) {
            $details .= ", {$failed} gagal";
        }

        ActivityLog::log('Generate Voucher', $details);

        return redirect()->route('vouchers.index')
            ->with('success', count($vouchers).' voucher berhasil dibuat.'
                .($pushed > 0 ? " {$pushed} sudah di-push ke MikroTik." : '')
                .($failed > 0 ? " {$failed} gagal push ke MikroTik." : ''))
            ->with('vouchers', $vouchers);
    }

    public function quickPrint(Request $request)
    {
        $validated = $request->validate([
            'duration' => 'required|integer|min:1|max:720',
            'duration_unit' => 'required|in:hours,days',
            'count' => 'required|integer|min:1|max:100',
            'profile_id' => 'nullable|exists:voucher_profiles,id',
            'router_id' => 'nullable|exists:mikrotik_routers,id',
            'template_id' => 'nullable|exists:voucher_templates,id',
            'prefix' => 'nullable|string|max:10|alpha_num',
        ]);

        $hours = $validated['duration_unit'] === 'days'
            ? $validated['duration'] * 24
            : $validated['duration'];

        $extra = [];
        if ($validated['profile_id']) {
            $profile = \App\Models\VoucherProfile::find($validated['profile_id']);
            if ($profile) {
                $extra = [
                    'voucher_profile_id' => $profile->id,
                    'price' => $profile->price,
                    'prefix' => $validated['prefix'] ?? '',
                    'speed' => $profile->speed,
                    'quota_limit' => $profile->quota_limit,
                    'validity_days' => $profile->validity_days,
                    'shared_users' => $profile->shared_users,
                ];
            }
        }
        if ($validated['router_id']) {
            $extra['router_id'] = $validated['router_id'];
        }
        if ($validated['template_id']) {
            $extra['voucher_template_id'] = $validated['template_id'];
        }

        $vouchers = Voucher::generate($hours, $validated['count'], $extra ?: null);

        if ($validated['router_id']) {
            $router = MikrotikRouter::find($validated['router_id']);
            if ($router) {
                $mikrotik = new MikrotikService($router);
                foreach ($vouchers as $voucher) {
                    $mikrotik->addHotspotUser($voucher->username, $voucher->password, null, $hours);
                }
            }
        } else {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                foreach ($vouchers as $voucher) {
                    $mikrotik->addHotspotUser($voucher->username, $voucher->password, null, $hours);
                }
            }
        }

        ActivityLog::log('Cetak Cepat Voucher', 'Membuat '.count($vouchers).' voucher WiFi ('.$hours.' jam) dari dashboard');

        $companyName = Setting::get('company_name', 'RabegNet');

        return view('vouchers.print-batch', compact('vouchers', 'companyName'));
    }

    public function print(Voucher $voucher)
    {
        $companyName = Setting::get('company_name', 'RabegNet');

        return view('vouchers.print', compact('voucher', 'companyName'));
    }

    public function printBatch(Request $request)
    {
        $ids = $request->input('ids', '');

        if (is_string($ids)) {
            $ids = $ids ? explode(',', $ids) : [];
        }

        $ids = array_filter(array_map('intval', $ids));

        if (empty($ids)) {
            return back()->with('error', 'Pilih voucher yang akan dicetak.');
        }

        $vouchers = Voucher::whereIn('id', $ids)->get();
        $companyName = Setting::get('company_name', 'RabegNet');

        return view('vouchers.print-batch', compact('vouchers', 'companyName'));
    }

    public function destroy(Voucher $voucher)
    {
        if ($voucher->router_id) {
            $router = MikrotikRouter::find($voucher->router_id);
            if ($router) {
                $mikrotik = new MikrotikService($router);
                $mikrotik->removeHotspotUser($voucher->username);
            }
        } else {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $mikrotik->removeHotspotUser($voucher->username);
            }
        }

        $voucher->delete();

        ActivityLog::log('Hapus Voucher', 'Voucher '.$voucher->username.' dihapus');

        return back()->with('success', 'Voucher dihapus.');
    }

    public function markUsed(Voucher $voucher)
    {
        $voucher->update([
            'status' => 'used',
            'used_at' => now(),
        ]);

        ActivityLog::log('Pakai Voucher', 'Voucher '.$voucher->username.' ditandai terpakai');

        return back()->with('success', 'Voucher '.$voucher->username.' ditandai terpakai.');
    }

    public function syncMikrotik()
    {
        $routers = MikrotikRouter::where('is_active', true)->get();

        if ($routers->isEmpty()) {
            $mikrotik = new MikrotikService;
            if (! $mikrotik->isConfigured()) {
                return back()->with('error', 'Tidak ada router aktif dan konfigurasi MikroTik belum lengkap.');
            }
            $this->doSync($mikrotik);

            return back()->with('success', 'Sinkronasi selesai.');
        }

        foreach ($routers as $router) {
            $mikrotik = new MikrotikService($router);
            $this->doSync($mikrotik);
        }

        return back()->with('success', 'Sinkronasi dengan semua router selesai.');
    }

    protected function doSync(MikrotikService $mikrotik): void
    {
        $synced = 0;
        $activeVouchers = Voucher::where('status', 'active')->get();

        foreach ($activeVouchers as $voucher) {
            $user = $mikrotik->getUserByUsername($voucher->username);

            if ($user) {
                $sessions = $mikrotik->getUserActiveSessions($voucher->username);
                if (! empty($sessions)) {
                    $voucher->update([
                        'status' => 'used',
                        'used_at' => now(),
                    ]);
                    $synced++;
                }
            } else {
                $mikrotik->addHotspotUser(
                    $voucher->username,
                    $voucher->password,
                    null,
                    $voucher->duration_hours
                );
                $synced++;
            }
        }

        $expiredVouchers = Voucher::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        ActivityLog::log('Sync MikroTik', "Sinkronasi: {$synced} voucher diselaraskan, {$expiredVouchers} kadaluarsa");
    }
}
