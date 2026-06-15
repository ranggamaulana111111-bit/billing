<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\VoucherProfile;
use Illuminate\Http\Request;

class VoucherProfileController extends Controller
{
    public function index()
    {
        $profiles = VoucherProfile::orderBy('name')->get();

        return view('voucher-profiles.index', compact('profiles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'speed' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'time_limit' => 'nullable|integer|min:0',
            'quota_limit' => 'nullable|integer|min:0',
            'validity_days' => 'nullable|integer|min:0',
            'shared_users' => 'required|integer|min:1|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['is_active'] = $request->has('is_active');

        VoucherProfile::create($validated);

        ActivityLog::log('Tambah Profile Voucher', 'Menambahkan profile: '.$validated['name']);

        return back()->with('success', 'Profile voucher berhasil ditambahkan.');
    }

    public function update(Request $request, VoucherProfile $voucherProfile)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'speed' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'time_limit' => 'nullable|integer|min:0',
            'quota_limit' => 'nullable|integer|min:0',
            'validity_days' => 'nullable|integer|min:0',
            'shared_users' => 'required|integer|min:1|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $voucherProfile->update($validated);

        ActivityLog::log('Ubah Profile Voucher', 'Mengubah profile: '.$validated['name']);

        return back()->with('success', 'Profile voucher berhasil diperbarui.');
    }

    public function destroy(VoucherProfile $voucherProfile)
    {
        if ($voucherProfile->vouchers()->exists()) {
            return back()->with('error', 'Profile tidak bisa dihapus karena masih memiliki voucher.');
        }

        $name = $voucherProfile->name;
        $voucherProfile->delete();

        ActivityLog::log('Hapus Profile Voucher', 'Menghapus profile: '.$name);

        return back()->with('success', 'Profile voucher berhasil dihapus.');
    }
}
