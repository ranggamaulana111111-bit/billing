<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MikrotikRouter;
use App\Models\Setting;
use App\Models\Voucher;
use App\Models\VoucherProfile;
use App\Models\VoucherTemplate;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'vouchers');

        Voucher::where('status', 'active')
            ->whereNotNull('expires_at')
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

        $vouchers = $query->paginate(100);

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

        // ── TAB 3: MikroTik Profiles ──
        $mikrotikProfiles = collect();
        $activeRoutersForProfiles = MikrotikRouter::where('is_active', true)->get();

        $localMap = [];
        foreach (VoucherProfile::all() as $lp) {
            $localMap[$lp->mikrotik_profile ?: $lp->name] = $lp;
        }

        foreach ($activeRoutersForProfiles as $router) {
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
                        'price' => $local->price ?? 0,
                        'selling_price' => $local->selling_price ?? null,
                        'router' => $router->name,
                    ]);
                }
            } catch (\Exception $e) {
                // skip unreachable
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
                            'expired_mode' => $p['on-expire'] ?? ($local->expired_mode ?? null),
                            'parent_queue' => $p['parent-queue'] ?? ($local->parent_queue ?? null),
                            'lock_user' => ($p['add-mac-cookie'] ?? '') === 'yes' || ($local->lock_user ?? false),
                            'price' => $local->price ?? 0,
                            'selling_price' => $local->selling_price ?? null,
                            'router' => 'Default',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // skip
            }
        }

        // ── TAB 4: Templates ──
        $templates = VoucherTemplate::orderBy('name')->get();

        return view('vouchers.index', compact(
            'tab',
            'vouchers', 'stats', 'search', 'filterStatus', 'mikrotikConnected', 'activeRouters',
            'reportVouchers', 'reportStats', 'reportProfiles',
            'mikrotikProfiles',
            'templates',
        ));
    }

    public function create()
    {
        $routers = MikrotikRouter::where('is_active', true)->get();
        $templates = VoucherTemplate::where('is_active', true)->get();
        $mikrotikConnected = $routers->isNotEmpty() || (new MikrotikService)->isConfigured();
        $lastVoucher = Voucher::latest()->first();
        $defaultNameLength = (int) (Setting::get('voucher_username_length') ?: 8);

        $mikrotikProfiles = collect();
        $hotspotServers = collect();

        $fetchFromRouter = function (MikrotikService $mikrotik, string $label, ?int $routerId) use (&$mikrotikProfiles, &$hotspotServers) {
            try {
                $list = $mikrotik->getHotspotProfiles();
                foreach ($list as $p) {
                    $mikrotikProfiles->push([
                        'name' => $p['name'] ?? '',
                        'speed' => $p['rate-limit'] ?? null,
                        'router' => $label,
                        'router_id' => $routerId,
                    ]);
                }
            } catch (\Exception $e) {
                // skip
            }
            try {
                $servers = $mikrotik->getHotspotServers();
                foreach ($servers as $s) {
                    $hotspotServers->push($s);
                }
            } catch (\Exception $e) {
                // skip
            }
        };

        foreach ($routers as $router) {
            $fetchFromRouter(new MikrotikService($router), $router->name, $router->id);
        }

        if ($mikrotikProfiles->isEmpty() && $hotspotServers->isEmpty()) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $fetchFromRouter($mikrotik, 'Default', null);
            }
        }

        return view('vouchers.create', compact(
            'mikrotikConnected', 'routers', 'templates',
            'mikrotikProfiles', 'hotspotServers', 'lastVoucher', 'defaultNameLength'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'duration' => 'required|integer|min:1|max:720',
            'duration_unit' => 'required|in:hours,days',
            'count' => 'required|integer|min:1|max:100',
            'mikrotik_profile_name' => 'nullable|string|max:255',
            'router_id' => 'nullable|exists:mikrotik_routers,id',
            'template_id' => 'nullable|exists:voucher_templates,id',
            'prefix' => 'nullable|string|max:10|alpha_num',
            'name_length' => 'nullable|integer|min:3|max:20',
            'character_type' => 'nullable|in:random,numeric',
            'password_same_as_username' => 'nullable|boolean',
            'hotspot_server' => 'nullable|string|max:255',
            'time_limit_wdhm' => 'nullable|string|max:50',
            'data_limit' => 'nullable|integer|min:0',
            'data_unit' => 'nullable|in:MB,GB',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['password_same_as_username'] = $request->has('password_same_as_username');

        $result = $this->generateAndPush($validated);
        $vouchers = $result['vouchers'];
        $pushed = $result['pushed'];
        $failed = $result['failed'];
        $hours = $result['hours'];

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
            'mikrotik_profile_name' => 'nullable|string|max:255',
            'router_id' => 'nullable|exists:mikrotik_routers,id',
            'template_id' => 'nullable|exists:voucher_templates,id',
            'prefix' => 'nullable|string|max:10|alpha_num',
            'name_length' => 'nullable|integer|min:3|max:20',
            'character_type' => 'nullable|in:random,numeric',
            'password_same_as_username' => 'nullable|boolean',
            'hotspot_server' => 'nullable|string|max:255',
            'time_limit_wdhm' => 'nullable|string|max:50',
            'data_limit' => 'nullable|integer|min:0',
            'data_unit' => 'nullable|in:MB,GB',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['password_same_as_username'] = $request->has('password_same_as_username');

        $result = $this->generateAndPush($validated);
        $vouchers = $result['vouchers'];
        $hours = $result['hours'];

        ActivityLog::log('Cetak Cepat Voucher', 'Membuat '.count($vouchers).' voucher WiFi ('.$hours.' jam) dari dashboard');

        $companyName = Setting::get('company_name', 'ALKONEK');

        return view('vouchers.print-batch', compact('vouchers', 'companyName'));
    }

    public function print(Voucher $voucher)
    {
        $companyName = Setting::get('company_name', 'ALKONEK');

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
        $companyName = Setting::get('company_name', 'ALKONEK');

        ActivityLog::log('Cetak Batch Voucher', 'Cetak batch '.$vouchers->count().' voucher');

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
        $totals = ['synced' => 0, 'imported' => 0, 'expiredVouchers' => 0, 'restored' => 0];
        $errors = [];

        $routers = MikrotikRouter::where('is_active', true)
            ->whereIn('type', ['general', 'pppoe'])
            ->get();

        if ($routers->isEmpty()) {
            $mikrotik = new MikrotikService;
            if (! $mikrotik->isConfigured()) {
                return back()->with('error', 'Tidak ada router aktif dan konfigurasi MikroTik belum lengkap.');
            }
            $result = $this->doSync($mikrotik);
            $totals = array_merge($totals, $result);
            if ($mikrotik->getLastError()) {
                $errors[] = $mikrotik->getLastError();
            }
        } else {
            foreach ($routers as $router) {
                $mikrotik = new MikrotikService($router);
                $result = $this->doSync($mikrotik);
                $totals['synced'] += $result['synced'];
                $totals['imported'] += $result['imported'];
                $totals['expiredVouchers'] += $result['expiredVouchers'];
                $totals['restored'] += $result['restored'] ?? 0;
                if ($mikrotik->getLastError()) {
                    $errors[] = "[$router->name] ".$mikrotik->getLastError();
                }
            }
        }

        $msg = "Sinkronasi selesai. Import {$totals['imported']} dari MikroTik, sync {$totals['synced']}, expired {$totals['expiredVouchers']}.";
        if (! empty($totals['restored'])) {
            $msg .= " {$totals['restored']} voucher dipulihkan ke aktif.";
        }
        ActivityLog::log('Sync MikroTik', $msg);

        if (! empty($errors)) {
            $msg .= ' Error: '.implode('; ', $errors);
        }

        if ($totals['synced'] === 0 && $totals['imported'] === 0 && ! empty($errors)) {
            return back()->with('error', $msg);
        }

        return back()->with('success', $msg);

        return back()->with('success', $msg);
    }

    protected function generateAndPush(array $validated): array
    {
        $hours = $validated['duration_unit'] === 'days'
            ? $validated['duration'] * 24
            : $validated['duration'];

        $timeLimitHours = null;
        if (! empty($validated['time_limit_wdhm'])) {
            $parsed = $this->parseWdhm($validated['time_limit_wdhm']);
            if ($parsed > 0) {
                $timeLimitHours = $parsed;
            }
        }

        $mikrotikProfile = $validated['mikrotik_profile_name'] ?? null;

        $extra = [
            'prefix' => $validated['prefix'] ?? '',
            'name_length' => $validated['name_length'] ?? null,
            'character_type' => $validated['character_type'] ?? 'random',
            'password_same_as_username' => $validated['password_same_as_username'] ?? false,
            'hotspot_server' => $validated['hotspot_server'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
        if ($validated['router_id']) {
            $extra['router_id'] = $validated['router_id'];
        }
        if (! empty($validated['template_id'])) {
            $extra['voucher_template_id'] = $validated['template_id'];
        }

        if (! empty($validated['data_limit'])) {
            $mb = $validated['data_unit'] === 'GB'
                ? $validated['data_limit'] * 1024
                : $validated['data_limit'];
            $extra['quota_limit'] = $mb;
        }

        $vouchers = Voucher::generate($hours, $validated['count'], $extra ?: null);

        $pushed = 0;
        $failed = 0;
        $server = $validated['hotspot_server'] ?? null;

        $uptimeHours = $timeLimitHours ?? $hours;

        if ($validated['router_id']) {
            $router = MikrotikRouter::find($validated['router_id']);
            if ($router) {
                $mikrotik = new MikrotikService($router);
                foreach ($vouchers as $voucher) {
                    $result = $mikrotik->addHotspotUser(
                        $voucher->username, $voucher->password, $server, $uptimeHours, $mikrotikProfile
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
                        $voucher->username, $voucher->password, $server, $uptimeHours, $mikrotikProfile
                    );
                    if ($result['success']) {
                        $pushed++;
                    } else {
                        $failed++;
                    }
                }
            }
        }

        return compact('vouchers', 'pushed', 'failed', 'hours');
    }

    protected function doSync(MikrotikService $mikrotik): array
    {
        $synced = 0;
        $imported = 0;

        $mikrotikUsers = $mikrotik->getHotspotUsers();
        $localUsernames = Voucher::pluck('username')->map(fn ($v) => strtolower($v))->toArray();

        $activeSessions = $mikrotik->getActiveHotspotSessions();
        $activeUsernames = array_map(fn ($s) => strtolower($s['user'] ?? ''), $activeSessions);

        foreach ($mikrotikUsers as $user) {
            $username = $user['name'] ?? null;
            if (! $username) {
                continue;
            }

            if (! in_array(strtolower($username), $localUsernames)) {
                $hasSession = in_array(strtolower($username), $activeUsernames);

                Voucher::create([
                    'username' => $username,
                    'password' => $user['password'] ?? 'imported',
                    'duration_hours' => 0,
                    'price' => 0,
                    'status' => $hasSession ? 'used' : 'active',
                    'used_at' => $hasSession ? now() : null,
                ]);
                $imported++;
            }
        }

        $activeVouchers = Voucher::where('status', 'active')->get();

        foreach ($activeVouchers as $voucher) {
            $user = $mikrotik->getUserByUsername($voucher->username);

            if ($user) {
                $sessions = $mikrotik->getUserActiveSessions($voucher->username);
                if (! empty($sessions)) {
                    $now = now();
                    $updateData = [
                        'status' => 'used',
                        'used_at' => $now,
                    ];
                    if (! $voucher->expires_at && $voucher->duration_hours > 0) {
                        $updateData['expires_at'] = $now->copy()->addHours($voucher->duration_hours);
                    }
                    $voucher->update($updateData);
                    $synced++;
                }
            } else {
                $profile = $voucher->profile;
                $mikrotikProfile = $profile ? $profile->mikrotik_profile : null;

                $mikrotik->addHotspotUser(
                    $voucher->username,
                    $voucher->password,
                    null,
                    $voucher->duration_hours,
                    $mikrotikProfile
                );
                $synced++;
            }
        }

        $expiredVouchers = Voucher::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $importedUsed = Voucher::where('status', 'used')
            ->where('duration_hours', 0)
            ->where('price', 0)
            ->whereNull('voucher_profile_id')
            ->get();

        $restored = 0;
        foreach ($importedUsed as $voucher) {
            $sessions = $mikrotik->getUserActiveSessions($voucher->username);
            if (empty($sessions)) {
                $user = $mikrotik->getUserByUsername($voucher->username);
                if ($user) {
                    $voucher->update(['status' => 'active', 'used_at' => null]);
                    $restored++;
                }
            }
        }

        ActivityLog::log('Sync MikroTik', "Import: {$imported} dari MikroTik, Sync: {$synced} voucher, {$expiredVouchers} kadaluarsa, {$restored} dipulihkan ke aktif");

        return compact('imported', 'synced', 'expiredVouchers', 'restored');
    }

    protected function parseWdhm(string $input): int
    {
        $totalHours = 0;
        preg_match_all('/(\d+)([wdhm])/i', $input, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $value = (int) $m[1];
            $unit = strtolower($m[2]);
            match ($unit) {
                'w' => $totalHours += $value * 7 * 24,
                'd' => $totalHours += $value * 24,
                'h' => $totalHours += $value,
                'm' => $totalHours += (int) ceil($value / 60),
                default => null,
            };
        }

        return $totalHours > 0 ? min($totalHours, 720) : 0;
    }
}
