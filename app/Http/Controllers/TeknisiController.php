<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\OdpPoint;
use App\Models\Olt;
use App\Models\Onu;

class TeknisiController extends Controller
{
    public function dashboard()
    {
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();
        $suspendedCustomers = Customer::where('status', 'suspended')->count();
        $inactiveCustomers = Customer::where('status', 'inactive')->count();

        $olts = Olt::with('ports.onus')->get()->map(function (Olt $olt) {
            $allOnus = $olt->ports->flatMap->onus;
            $olt->total_onus = $allOnus->count();
            $olt->online_onus = $allOnus->where('status', 'online')->count();
            unset($olt->ports);

            return $olt;
        });

        $odps = OdpPoint::with('customers')->get()->map(function (OdpPoint $odp) {
            $used = $odp->customers->count();
            $capacity = (int) $odp->port_capacity;
            $usagePercent = $capacity > 0 ? round(($used / $capacity) * 100) : 0;

            $odp->port_used_actual = $used;
            $odp->port_usage_percent = $usagePercent;
            $odp->port_usage_color = $usagePercent >= 80 ? '#dc2626' : ($usagePercent >= 50 ? '#d97706' : '#059669');

            return $odp;
        });

        $activityLogs = ActivityLog::latest()->take(10)->get();

        $recentOnuOffline = Onu::where('status', 'offline')
            ->with('oltPort.olt')
            ->latest('updated_at')
            ->take(10)
            ->get();

        return view('teknisi.dashboard', compact(
            'totalCustomers', 'activeCustomers', 'suspendedCustomers', 'inactiveCustomers',
            'olts', 'odps', 'activityLogs', 'recentOnuOffline',
        ));
    }
}
