<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Odp;
use App\Models\OdpPoint;
use App\Models\OdpRoute;
use App\Models\Package;

class DashboardController extends Controller
{
    public function index()
    {
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();
        $suspendedCustomers = Customer::where('status', 'suspended')->count();
        $inactiveCustomers = Customer::where('status', 'inactive')->count();
        $totalRoutes = OdpRoute::count();
        $totalPoints = OdpPoint::count();
        $totalCapacity = OdpPoint::sum('port_capacity');
        $totalUsed = OdpPoint::sum('port_used');

        $totalPaid = Invoice::where('payment_status', 'paid')
            ->where('billing_period', now()->format('Y-m'))
            ->sum('amount');

        $totalUnpaid = Invoice::where('payment_status', 'unpaid')
            ->sum('amount');

        $monthUnpaid = Invoice::where('payment_status', 'unpaid')
            ->where('billing_period', now()->format('Y-m'))
            ->sum('amount');

        $todayRevenue = Invoice::where('payment_status', 'paid')
            ->whereDate('paid_at', today())
            ->sum('amount');

        $summary = [
            'total_paid' => $totalPaid,
            'total_unpaid' => $totalUnpaid,
        ];

        $months = collect();
        $monthlyRevenue = collect();
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = Invoice::where('payment_status', 'paid')
                ->where('billing_period', $date->format('Y-m'))
                ->sum('amount');
            $months->push($date->format('M Y'));
            $monthlyRevenue->push((int) $revenue);
        }

        $totalInvoices = Invoice::count();
        $paidCount = Invoice::where('payment_status', 'paid')->count();
        $unpaidCount = Invoice::where('payment_status', 'unpaid')->count();
        $paymentRate = $totalInvoices > 0 ? round(($paidCount / $totalInvoices) * 100) : 0;

        $paymentMethods = Invoice::where('payment_status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, count(*) as count, sum(amount) as total')
            ->groupBy('payment_method')
            ->get();

        $packageDistribution = Package::withCount('customers')->get();
        $topPackage = $packageDistribution->sortByDesc('customers_count')->first();
        $activePackageCount = Package::where('is_active', true)->count();
        $inactivePackageCount = Package::where('is_active', false)->count();

        $overdueCount = Invoice::where('payment_status', 'unpaid')
            ->whereHas('customer', fn ($q) => $q->whereNotNull('due_date')->whereDate('due_date', '<', now()))
            ->count();
        $overdueTotal = Invoice::where('payment_status', 'unpaid')
            ->whereHas('customer', fn ($q) => $q->whereNotNull('due_date')->whereDate('due_date', '<', now()))
            ->sum('amount');

        $packages = Package::withCount('customers')->orderBy('price')->get();
        $odps = OdpPoint::with('customers')->get()->map(function (OdpPoint $odp) {
            $used = $odp->customers->count();
            $capacity = (int) $odp->port_capacity;
            $usagePercent = $capacity > 0 ? round(($used / $capacity) * 100) : 0;

            $odp->port_used_actual = $used;
            $odp->port_usage_percent = $usagePercent;
            $odp->port_usage_color = $usagePercent >= 80 ? '#dc2626' : ($usagePercent >= 50 ? '#d97706' : '#059669');

            return $odp;
        });
        $customers = Customer::with('package', 'odp')->latest()->take(20)->get();
        $unpaidInvoices = Invoice::with('customer')
            ->where('payment_status', 'unpaid')
            ->latest()
            ->take(10)
            ->get();
        $paidInvoices = Invoice::with('customer')
            ->where('payment_status', 'paid')
            ->latest()
            ->take(5)
            ->get();
        $activityLogs = ActivityLog::latest()->take(5)->get();

        $newOdps = Odp::with('ports', 'odc')->get()->map(fn ($o) => [
            'id' => $o->id,
            'name' => $o->nama_odp,
            'latitude' => $o->latitude,
            'longitude' => $o->longitude,
            'port_capacity' => (int) $o->kapasitas_port,
            'used' => $o->usedPortsCount(),
            'address' => $o->odc?->nama_odc,
        ]);

        return view('dashboard', compact(
            'totalCustomers', 'activeCustomers', 'suspendedCustomers', 'inactiveCustomers',
            'totalRoutes', 'totalPoints', 'totalCapacity', 'totalUsed',
            'summary', 'todayRevenue',
            'packages', 'odps', 'newOdps', 'customers',
            'unpaidInvoices', 'paidInvoices', 'activityLogs',
            'months', 'monthlyRevenue', 'totalInvoices', 'paidCount', 'unpaidCount',
            'paymentMethods', 'packageDistribution', 'overdueCount', 'monthUnpaid',
            'paymentRate', 'topPackage', 'activePackageCount', 'inactivePackageCount', 'overdueTotal',
        ));
    }
}
