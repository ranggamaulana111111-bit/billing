<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\OdpPoint;
use App\Models\Package;
use App\Models\Setting;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function create()
    {
        $packages = Package::where('is_active', true)->orderBy('price')->get();
        $odps = OdpPoint::all();

        return view('customer.create', compact('packages', 'odps'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'package_id' => 'required|exists:packages,id',
            'odp_point_id' => 'nullable|exists:odp_points,id',
            'pppoe_username' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
        ]);

        $customer = Customer::create($validated);

        $package = Package::find($validated['package_id']);

        Invoice::create([
            'invoice_code' => 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-'.now()->format('m'),
            'customer_id' => $customer->id,
            'amount' => $package->price,
            'payment_status' => 'unpaid',
        ]);

        ActivityLog::log('Tambah Pelanggan', 'Menambahkan pelanggan baru: '.$customer->name);

        if (empty($validated['due_date'])) {
            $defaultDueDate = Setting::get('default_due_date', '5');
            $customer->update(['due_date' => now()->day((int) $defaultDueDate)->format('Y-m-d')]);
        }

        return redirect()->route('customers.index')->with('success', 'Pelanggan '.$customer->name.' berhasil ditambahkan!');
    }

    public function index()
    {
        $customers = Customer::with('package', 'odp')->latest()->paginate(20);
        $stats = [
            'total' => Customer::count(),
            'active' => Customer::where('status', 'active')->count(),
            'suspended' => Customer::where('status', 'suspended')->count(),
            'inactive' => Customer::where('status', 'inactive')->count(),
        ];

        return view('customer.index', compact('customers', 'stats'));
    }

    public function edit(Customer $customer)
    {
        $packages = Package::where('is_active', true)
            ->orWhere('id', $customer->package_id)
            ->orderBy('price')
            ->get();
        $odps = OdpPoint::all();

        return view('customer.edit', compact('customer', 'packages', 'odps'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'package_id' => 'required|exists:packages,id',
            'odp_point_id' => 'nullable|exists:odp_points,id',
            'pppoe_username' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
        ]);

        $customer->update($validated);

        ActivityLog::log('Ubah Pelanggan', 'Mengubah data pelanggan: '.$customer->name);

        return redirect()->route('customers.index')->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        $name = $customer->name;
        $customer->delete();

        ActivityLog::log('Hapus Pelanggan', 'Menghapus pelanggan: '.$name);

        return redirect()->route('customers.index')->with('success', 'Pelanggan '.$name.' berhasil dihapus.');
    }

    public function suspend(Customer $customer)
    {
        $customer->update(['status' => 'suspended', 'suspended_at' => now()]);

        $this->syncPppStatus($customer, true);

        ActivityLog::log('Suspend Pelanggan', 'Menonaktifkan sementara: '.$customer->name);

        return back()->with('success', 'Pelanggan '.$customer->name.' ditangguhkan.');
    }

    public function activate(Customer $customer)
    {
        $customer->update(['status' => 'active', 'suspended_at' => null]);

        $this->syncPppStatus($customer, false);

        ActivityLog::log('Aktifkan Pelanggan', 'Mengaktifkan kembali: '.$customer->name);

        return back()->with('success', 'Pelanggan '.$customer->name.' diaktifkan kembali.');
    }

    public function syncPppoe()
    {
        $routers = MikrotikRouter::where('is_active', true)->get();

        if ($routers->isEmpty()) {
            $mikrotik = new MikrotikService;
            if (! $mikrotik->isConfigured()) {
                return back()->with('error', 'MikroTik belum dikonfigurasi.');
            }
            $this->doSyncPppoe($mikrotik);

            return back()->with('success', 'Sinkronisasi PPPoE selesai.');
        }

        foreach ($routers as $router) {
            $mikrotik = new MikrotikService($router);
            $this->doSyncPppoe($mikrotik);
        }

        return back()->with('success', 'Sinkronisasi PPPoE dengan semua router selesai.');
    }

    protected function syncPppStatus(Customer $customer, bool $suspended): void
    {
        if (! $customer->pppoe_username) {
            return;
        }

        $routers = MikrotikRouter::where('is_active', true)->get();

        if ($routers->isNotEmpty()) {
            foreach ($routers as $router) {
                $mikrotik = new MikrotikService($router);
                if ($suspended) {
                    $mikrotik->disablePppSecret($customer->pppoe_username);
                } else {
                    $mikrotik->enablePppSecret($customer->pppoe_username);
                }
            }
        } else {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                if ($suspended) {
                    $mikrotik->disablePppSecret($customer->pppoe_username);
                } else {
                    $mikrotik->enablePppSecret($customer->pppoe_username);
                }
            }
        }
    }

    protected function doSyncPppoe(MikrotikService $mikrotik): void
    {
        $activeCustomers = Customer::where('status', 'active')->get();
        $synced = 0;
        $skipped = 0;

        foreach ($activeCustomers as $customer) {
            if (! $customer->pppoe_username) {
                $skipped++;
                continue;
            }

            $existing = $mikrotik->getPppSecretByUsername($customer->pppoe_username);

            if ($existing) {
                $mikrotik->enablePppSecret($customer->pppoe_username);
            } else {
                $password = $customer->pppoe_username.'123';
                $mikrotik->addPppSecret($customer->pppoe_username, $password);
            }
            $synced++;
        }

        $suspendedCustomers = Customer::where('status', 'suspended')->get();
        foreach ($suspendedCustomers as $customer) {
            if (! $customer->pppoe_username) {
                continue;
            }
            $mikrotik->disablePppSecret($customer->pppoe_username);
        }

        ActivityLog::log('Sync PPPoE', "Sinkronisasi PPPoE: {$synced} aktif, {$skipped} dilewati");
    }
}
