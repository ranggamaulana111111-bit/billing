<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,teknisi,noc,sales'],
        ]);

        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => null,
            'password' => Hash::make($data['password']),
            'password_plain' => $data['password'],
            'role' => $data['role'],
            'permissions' => in_array($data['role'], ['admin', 'teknisi', 'noc'])
                ? ['edit_map' => true, 'sync_mikrotik' => true, 'sync_olt' => true, 'sync_genieacs' => true, 'ganti_wifi' => true, 'import_export' => true]
                : ['edit_map' => false, 'sync_mikrotik' => false, 'sync_olt' => false, 'sync_genieacs' => false, 'ganti_wifi' => false, 'import_export' => false],
        ]);

        ActivityLog::log('Tambah User', 'User '.$data['username'].' ditambahkan sebagai '.$data['role']);

        return redirect()->route('settings.users')->with('success', 'Akun '.$data['role'].' berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', 'unique:users,username,'.$user->id],
            'role' => ['required', 'in:admin,teknisi,noc,sales'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $updateData = [
            'name' => $data['name'],
            'username' => $data['username'],
            'role' => $data['role'],
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
            $updateData['password_plain'] = $data['password'];
        }

        $user->update($updateData);

        ActivityLog::log('Ubah User', 'User '.$user->username.' diperbarui');

        return redirect()->route('settings.users')->with('success', 'Akun berhasil diperbarui.');
    }

    public function password(User $user)
    {
        return response()->json([
            'password' => $user->password_plain,
            'message' => 'Password asli tidak tersimpan (akun dibuat sebelum fitur ini, atau login via Google).',
        ]);
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $email = $user->username;
        $user->delete();

        ActivityLog::log('Hapus User', 'User '.$email.' dihapus');

        return redirect()->route('settings.users')->with('success', 'Akun berhasil dihapus.');
    }
}
