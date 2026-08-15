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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:admin,teknisi,noc'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'password_plain' => $data['password'],
            'role' => $data['role'],
        ]);

        ActivityLog::log('Tambah User', 'User '.$data['email'].' ditambahkan sebagai '.$data['role']);

        return redirect()->route('settings.users')->with('success', 'Akun '.$data['role'].' berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:admin,teknisi,noc'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
            $updateData['password_plain'] = $data['password'];
        }

        $user->update($updateData);

        ActivityLog::log('Ubah User', 'User '.$user->email.' diperbarui');

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

        $email = $user->email;
        $user->delete();

        ActivityLog::log('Hapus User', 'User '.$email.' dihapus');

        return redirect()->route('settings.users')->with('success', 'Akun berhasil dihapus.');
    }
}
