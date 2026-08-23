<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:60', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => null,
            'password' => Hash::make($data['password']),
            'password_plain' => $data['password'],
        ]);

        Auth::login($user->fresh());

        ActivityLog::log('Register', 'User '.$user->username.' terdaftar dan masuk');

        $dashboard = match (auth()->user()->role) {
            'teknisi' => '/teknisi/dashboard',
            'noc' => '/noc/dashboard',
            default => '/dashboard',
        };

        return redirect($dashboard);
    }
}
