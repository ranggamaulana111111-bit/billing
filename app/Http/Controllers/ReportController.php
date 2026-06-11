<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        // Revenue monthly
        $monthlyRevenue = Payment::whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->sum('amount');

        $monthlyCount = Payment::whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->count();

        // Outstanding
        $totalOutstanding = Invoice::where('payment_status', 'unpaid')->sum('amount');
        $outstandingCount = Invoice::where('payment_status', 'unpaid')->count();

        // Customer stats
        $activeCustomers = Customer::where('status', 'active')->count();
        $totalCustomers = Customer::count();

        // Yearly chart data
        $months = collect();
        $revenueData = collect();
        for ($i = 11; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $rev = Payment::whereYear('payment_date', $d->year)
                ->whereMonth('payment_date', $d->month)
                ->sum('amount');
            $months->push($d->format('M Y'));
            $revenueData->push((int) $rev);
        }

        // Payment method breakdown for selected month
        $methodBreakdown = Payment::whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->selectRaw('payment_method, sum(amount) as total, count(*) as count')
            ->groupBy('payment_method')
            ->get();

        // Top unpaid customers
        $topUnpaid = Invoice::with('customer')
            ->where('payment_status', 'unpaid')
            ->orderByDesc('amount')
            ->take(10)
            ->get();

        return view('reports.index', compact(
            'year', 'month',
            'monthlyRevenue', 'monthlyCount',
            'totalOutstanding', 'outstandingCount',
            'activeCustomers', 'totalCustomers',
            'months', 'revenueData',
            'methodBreakdown',
            'topUnpaid',
        ));
    }
}
