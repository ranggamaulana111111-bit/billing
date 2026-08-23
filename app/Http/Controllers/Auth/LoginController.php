<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            ActivityLog::log('Login', 'User '.Auth::user()->username.' masuk');

            $dashboard = match (strtolower((string) Auth::user()->role)) {
                'teknisi' => '/teknisi/dashboard',
                'noc' => '/noc/dashboard',
                default => '/dashboard',
            };

            return redirect()->intended($dashboard);
        }

        return back()->withErrors([
            'username' => 'Username atau kata sandi salah.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        ActivityLog::log('Logout', 'User keluar');
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
