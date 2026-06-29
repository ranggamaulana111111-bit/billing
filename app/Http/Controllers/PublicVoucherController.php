<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\Voucher;
use App\Models\VoucherProfile;
use App\Models\VoucherTemplate;
use Illuminate\Http\Request;

class PublicVoucherController extends Controller
{
    public function index()
    {
        $profiles = VoucherProfile::where('is_active', true)->get();
        $templates = VoucherTemplate::where('is_active', true)->get();
        $company = Setting::get('company_name', 'RabegNet');
        $showPrice = Setting::get('voucher_show_price', 'true');

        return view('vouchers.public', compact('profiles', 'templates', 'company', 'showPrice'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|exists:voucher_profiles,id',
            'count' => 'required|integer|min:1|max:50',
            'prefix' => 'nullable|string|max:10|alpha_num',
            'template_id' => 'nullable|exists:voucher_templates,id',
        ]);

        $profile = VoucherProfile::findOrFail($validated['profile_id']);
        $count = (int) $validated['count'];

        $durationHours = $profile->time_limit ?? ($profile->validity_days ? $profile->validity_days * 24 : 24);

        $extra = [
            'voucher_profile_id' => $profile->id,
            'price' => $profile->price,
            'prefix' => $validated['prefix'] ?? '',
            'speed' => $profile->speed,
            'quota_limit' => $profile->quota_limit,
            'validity_days' => $profile->validity_days,
            'shared_users' => $profile->shared_users,
        ];

        if ($validated['template_id']) {
            $extra['voucher_template_id'] = $validated['template_id'];
        }

        $vouchers = Voucher::generate($durationHours, $count, $extra);

        ActivityLog::log('Generate Voucher Public', "{$count} voucher {$profile->name} dibuat dari halaman public");

        $company = Setting::get('company_name', 'RabegNet');

        $template = null;
        if ($validated['template_id']) {
            $template = VoucherTemplate::find($validated['template_id']);
        }

        return view('vouchers.public-result', compact('vouchers', 'profile', 'template', 'company'));
    }

    public function check()
    {
        return view('vouchers.check');
    }

    public function checkStatus(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $voucher = Voucher::where('username', $validated['username'])
            ->where('password', $validated['password'])
            ->first();

        if (! $voucher) {
            return back()->with('error', 'Username atau password voucher tidak valid.');
        }

        return view('vouchers.check-result', compact('voucher'));
    }
}
