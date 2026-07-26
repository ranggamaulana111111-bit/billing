<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\OdpRoute;
use App\Models\Package;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index()
    {
        $activeIncidents = Incident::active()
            ->with('odp')
            ->latest('detected_at')
            ->take(5)
            ->get();
        $totals = Cache::remember('dashboard_totals', 90, function () {
            return [
                'totalCustomers' => Customer::count(),
                'activeCustomers' => Customer::where('status', 'active')->count(),
                'suspendedCustomers' => Customer::where('status', 'suspended')->count(),
                'inactiveCustomers' => Customer::where('status', 'inactive')->count(),
                'totalRoutes' => OdpRoute::count(),
                'totalPoints' => Odp::count(),
                'totalCapacity' => (int) Odp::sum('kapasitas_port'),
                'totalUsed' => OdpPort::where('status', 'used')->count(),
            ];
        });
        extract($totals);

        $month = now()->format('Y-m');
        $totalPaid = Invoice::where('payment_status', 'paid')->where('billing_period', $month)->sum('amount');
        $totalUnpaid = Invoice::where('payment_status', 'unpaid')->sum('amount');
        $monthUnpaid = Invoice::where('payment_status', 'unpaid')->where('billing_period', $month)->sum('amount');
        $todayRevenue = Invoice::where('payment_status', 'paid')->whereDate('paid_at', today())->sum('amount');

        $summary = [
            'total_paid' => $totalPaid,
            'total_unpaid' => $totalUnpaid,
        ];

        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();
        $revenues = Cache::remember('dashboard_revenue_'.$sixMonthsAgo->format('Y-m'), 300, function () use ($sixMonthsAgo) {
            return Invoice::where('payment_status', 'paid')
                ->where('billing_period', '>=', $sixMonthsAgo->format('Y-m'))
                ->selectRaw('billing_period, sum(amount) as total')
                ->groupBy('billing_period')
                ->pluck('total', 'billing_period');
        });

        $months = collect();
        $monthlyRevenue = collect();
        for ($i = 5; $i >= 0; $i--) {
            $period = now()->subMonths($i)->format('Y-m');
            $months->push(now()->subMonths($i)->format('M Y'));
            $monthlyRevenue->push((int) ($revenues[$period] ?? 0));
        }

        $rates = Cache::remember('dashboard_rates', 120, function () {
            $totalInvoices = Invoice::count();
            $paidCount = Invoice::where('payment_status', 'paid')->count();
            $unpaidCount = Invoice::where('payment_status', 'unpaid')->count();
            $paymentRate = $totalInvoices > 0 ? round(($paidCount / $totalInvoices) * 100) : 0;

            return [
                'totalInvoices' => $totalInvoices,
                'paidCount' => $paidCount,
                'unpaidCount' => $unpaidCount,
                'paymentRate' => $paymentRate,
            ];
        });
        extract($rates);

        $paymentMethods = Cache::remember('dashboard_paymethods_'.now()->format('Ym'), 300, function () {
            return Invoice::where('payment_status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->whereNotNull('payment_method')
                ->selectRaw('payment_method, count(*) as count, sum(amount) as total')
                ->groupBy('payment_method')
                ->get();
        });

        $packageDistribution = Package::withCount('customers')->get();
        $topPackage = $packageDistribution->sortByDesc('customers_count')->first();
        $activePackageCount = Package::where('is_active', true)->count();
        $inactivePackageCount = Package::where('is_active', false)->count();

        $overdue = Cache::remember('dashboard_overdue', 120, function () {
            $overdueCount = Invoice::where('payment_status', 'unpaid')
                ->whereHas('customer', fn ($q) => $q->whereNotNull('due_date')->whereDate('due_date', '<', now()))
                ->count();
            $overdueTotal = Invoice::where('payment_status', 'unpaid')
                ->whereHas('customer', fn ($q) => $q->whereNotNull('due_date')->whereDate('due_date', '<', now()))
                ->sum('amount');

            return ['overdueCount' => $overdueCount, 'overdueTotal' => $overdueTotal];
        });
        extract($overdue);

        $packages = Package::withCount('customers')->orderBy('price')->get();
        $odps = Cache::remember('dashboard_odps', 120, function () {
            return Odp::with('ports', 'customers', 'odc')->get()->map(function ($odp) {
                $capacity = (int) $odp->kapasitas_port;
                $used = $odp->ports->where('status', 'used')->count();
                $usagePercent = $capacity > 0 ? round(($used / $capacity) * 100) : 0;

                $odp->port_used_actual = $used;
                $odp->port_usage_percent = $usagePercent;
                $odp->port_usage_color = $usagePercent >= 80 ? '#dc2626' : ($usagePercent >= 50 ? '#d97706' : '#059669');
                $odp->port_capacity = $capacity;

                return $odp;
            });
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

        $routerStatus = Cache::remember('dashboard_router_status', 60, function () {
            $router = MikrotikRouter::where('is_active', true)
                ->orderByDesc('last_seen')
                ->first();

            if (! $router) {
                return ['core' => 'standby', 'mikrotik' => 'standby'];
            }

            $fresh = $router->last_seen && $router->last_seen->gt(now()->subMinutes(5));
            $status = $fresh ? 'online' : 'warning';

            return ['core' => $status, 'mikrotik' => $status];
        });

        return view('dashboard', compact(
            'totalCustomers', 'activeCustomers', 'suspendedCustomers', 'inactiveCustomers',
            'totalRoutes', 'totalPoints', 'totalCapacity', 'totalUsed',
            'summary', 'todayRevenue',
            'packages', 'odps', 'customers', 'activeIncidents',
            'unpaidInvoices', 'paidInvoices', 'activityLogs',
            'months', 'monthlyRevenue', 'totalInvoices', 'paidCount', 'unpaidCount',
            'paymentMethods', 'packageDistribution', 'overdueCount', 'monthUnpaid',
            'paymentRate', 'topPackage', 'activePackageCount', 'inactivePackageCount', 'overdueTotal',
            'routerStatus',
        ));
    }
}
