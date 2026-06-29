<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Voucher;
use Illuminate\Http\Request;

class MikrotikHotspotController extends Controller
{
    public function hotspotLogin(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');
        $routerIp = $request->input('router_ip');
        $mac = $request->input('mac');

        if (! $username || ! $password) {
            return response()->json(['status' => 'error', 'message' => 'username dan password wajib'], 400);
        }

        $voucher = Voucher::where('username', $username)
            ->where('password', $password)
            ->whereIn('status', ['active'])
            ->first();

        if (! $voucher) {
            return response()->json(['status' => 'error', 'message' => 'Voucher tidak valid'], 401);
        }

        $voucher->update([
            'status' => 'used',
            'used_at' => now(),
            'ip_address' => $request->ip(),
            'mac_address' => $mac,
            'last_login_at' => now(),
            'router_id' => $voucher->router_id,
        ]);

        ActivityLog::log('Hotspot Login', "Voucher {$username} digunakan dari {$routerIp}");

        return response()->json(['status' => 'success', 'message' => 'Login tercatat']);
    }
}
