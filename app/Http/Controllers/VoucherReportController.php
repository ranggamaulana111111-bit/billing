<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\VoucherProfile;
use Illuminate\Http\Request;

class VoucherReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Voucher::with('profile');

        if ($request->filled('profile_id')) {
            $query->where('voucher_profile_id', $request->profile_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $vouchers = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'total' => Voucher::count(),
            'active' => Voucher::where('status', 'active')->count(),
            'used' => Voucher::where('status', 'used')->count(),
            'expired' => Voucher::where('status', 'expired')->count(),
            'revenue' => Voucher::where('status', 'used')->sum('price'),
        ];

        $profiles = VoucherProfile::orderBy('name')->pluck('name', 'id');

        return view('vouchers.report', compact('vouchers', 'stats', 'profiles'));
    }
}
