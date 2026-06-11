<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\Voucher;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        Voucher::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

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

        $mikrotikConnected = (new MikrotikService)->isConfigured();

        return view('vouchers.index', compact('vouchers', 'stats', 'search', 'filterStatus', 'mikrotikConnected'));
    }

    public function create()
    {
        $mikrotikConnected = (new MikrotikService)->isConfigured();

        return view('vouchers.create', compact('mikrotikConnected'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'duration' => 'required|integer|min:1|max:720',
            'duration_unit' => 'required|in:hours,days',
            'count' => 'required|integer|min:1|max:100',
        ]);

        $hours = $validated['duration_unit'] === 'days'
            ? $validated['duration'] * 24
            : $validated['duration'];

        $vouchers = Voucher::generate($hours, $validated['count']);

        $mikrotik = new MikrotikService;
        $pushed = 0;
        $failed = 0;

        if ($mikrotik->isConfigured()) {
            foreach ($vouchers as $voucher) {
                $result = $mikrotik->addHotspotUser(
                    $voucher->username,
                    $voucher->password,
                    'all',
                    $hours
                );

                if ($result['success']) {
                    $pushed++;
                } else {
                    $failed++;
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
        ]);

        $hours = $validated['duration_unit'] === 'days'
            ? $validated['duration'] * 24
            : $validated['duration'];

        $vouchers = Voucher::generate($hours, $validated['count']);

        $mikrotik = new MikrotikService;
        if ($mikrotik->isConfigured()) {
            foreach ($vouchers as $voucher) {
                $mikrotik->addHotspotUser($voucher->username, $voucher->password, null, $hours);
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
        $mikrotik = new MikrotikService;
        if ($mikrotik->isConfigured()) {
            $mikrotik->removeHotspotUser($voucher->username);
        }

        $voucher->delete();

        ActivityLog::log('Hapus Voucher', 'Voucher '.$voucher->username.' dihapus');

        return back()->with('success', 'Voucher dihapus'.($mikrotik->isConfigured() ? ' (termasuk dari MikroTik)' : '').'.');
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
        $mikrotik = new MikrotikService;

        if (! $mikrotik->isConfigured()) {
            return back()->with('error', 'Konfigurasi MikroTik belum lengkap.');
        }

        $synced = 0;
        $expired = 0;
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
                    'all',
                    $voucher->duration_hours
                );
                $synced++;
            }
        }

        $expiredVouchers = Voucher::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $total = $synced + $expired;

        ActivityLog::log('Sync MikroTik', "Sinkronasi: {$synced} voucher diselaraskan, {$expiredVouchers} kadaluarsa");

        return back()->with('success', "Sinkronasi selesai. {$synced} voucher diselaraskan.".($expiredVouchers > 0 ? " {$expiredVouchers} kadaluarsa." : ''));
    }
}
