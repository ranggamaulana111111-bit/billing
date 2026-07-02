<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Setting;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class IsolirController extends Controller
{
    public function index(Customer $customer)
    {
        if ($customer->status !== 'suspended') {
            abort(404);
        }

        $invoice = $customer->invoices()
            ->where('payment_status', 'unpaid')
            ->latest()
            ->first();

        $adminPhone = Setting::get('admin_phone', '');
        $adminName = Setting::get('admin_name', 'Admin');

        return view('isolir.index', compact('customer', 'invoice', 'adminPhone', 'adminName'));
    }

    public function byIp(Request $request)
    {
        $clientIp = $request->ip();

        $routers = MikrotikRouter::where('is_active', true)
            ->byType('pppoe')
            ->get();

        if ($routers->isEmpty()) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $routers = collect([null]);
            } else {
                $routers = collect();
            }
        }

        $customer = null;
        foreach ($routers as $router) {
            $mikrotik = $router ? new MikrotikService($router) : new MikrotikService;
            try {
                $active = $mikrotik->getPppActive();
                $session = collect($active)->firstWhere('address', $clientIp);
                if ($session && isset($session['name'])) {
                    $customer = Customer::where('pppoe_username', $session['name'])
                        ->where('status', 'suspended')
                        ->first();
                    if ($customer) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (! $customer) {
            return view('isolir.unknown', compact('clientIp'));
        }

        $invoice = $customer->invoices()
            ->where('payment_status', 'unpaid')
            ->latest()
            ->first();

        $adminPhone = Setting::get('admin_phone', '');
        $adminName = Setting::get('admin_name', 'Admin');

        return view('isolir.index', compact('customer', 'invoice', 'adminPhone', 'adminName'));
    }
}
