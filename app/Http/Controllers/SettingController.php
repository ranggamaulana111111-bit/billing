<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key')->toArray();

        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_short_name' => 'nullable|string|max:50',
            'company_address' => 'nullable|string|max:500',
            'company_phone' => 'nullable|string|max:50',
            'company_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:100',
            'bank_holder' => 'nullable|string|max:255',
            'invoice_footer' => 'nullable|string|max:500',
            'mikrotik_host' => 'nullable|string|max:255',
            'mikrotik_port' => 'nullable|integer|min:1|max:65535',
            'mikrotik_user' => 'nullable|string|max:255',
            'mikrotik_password' => 'nullable|string|max:255',
            'mikrotik_hotspot_server' => 'nullable|string|max:255',
            'fonnte_token' => 'nullable|string|max:500',
            'midtrans_server_key' => 'nullable|string|max:500',
            'midtrans_client_key' => 'nullable|string|max:500',
            'midtrans_is_production' => 'nullable|in:0,1',
            'voucher_username_length' => 'nullable|integer|min:4|max:20',
            'voucher_password_length' => 'nullable|integer|min:4|max:20',
            'late_fee_amount' => 'nullable|integer|min:0',
            'late_fee_grace_days' => 'nullable|integer|min:0',
            'default_due_date' => 'nullable|integer|min:1|max:28',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('logos', 'public');
            Setting::set('company_logo', $path);
        }

        ActivityLog::log('Ubah Pengaturan', 'Pengaturan sistem diperbarui');

        return redirect()->route('settings.index')->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function testMikrotik()
    {
        $mikrotik = new MikrotikService;

        if (! $mikrotik->isConfigured()) {
            $router = MikrotikRouter::where('is_active', true)
                ->whereIn('type', ['general'])
                ->first();

            if ($router) {
                $mikrotik = new MikrotikService($router);
            }
        }

        if (! $mikrotik->isConfigured()) {
            ActivityLog::log('Test MikroTik', 'Gagal test koneksi: konfigurasi belum lengkap');

            return back()->with('error', 'Konfigurasi MikroTik belum lengkap (host, user, password).');
        }

        $result = $mikrotik->testConnection();

        if ($result['success']) {
            ActivityLog::log('Test MikroTik', 'Koneksi MikroTik berhasil: '.$result['message']);

            return back()->with('success', $result['message']);
        }

        ActivityLog::log('Test MikroTik', 'Koneksi MikroTik gagal: '.$result['message']);

        return back()->with('error', $result['message']);
    }
}
