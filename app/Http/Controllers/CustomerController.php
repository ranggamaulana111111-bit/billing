<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
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

        ActivityLog::log('Suspend Pelanggan', 'Menonaktifkan sementara: '.$customer->name);

        return back()->with('success', 'Pelanggan '.$customer->name.' ditangguhkan.');
    }

    public function activate(Customer $customer)
    {
        $customer->update(['status' => 'active', 'suspended_at' => null]);

        ActivityLog::log('Aktifkan Pelanggan', 'Mengaktifkan kembali: '.$customer->name);

        return back()->with('success', 'Pelanggan '.$customer->name.' diaktifkan kembali.');
    }

    public function syncPppoe()
    {
        $mikrotik = new MikrotikService;
        if (! $mikrotik->isConfigured()) {
            return back()->with('error', 'MikroTik belum dikonfigurasi.');
        }

        $customers = Customer::where('status', 'active')->get();
        $synced = 0;
        $skipped = 0;

        foreach ($customers as $customer) {
            if (! $customer->pppoe_username) {
                $skipped++;

                continue;
            }

            $password = $customer->pppoe_username.'123';
            $result = $mikrotik->addPppSecret($customer->pppoe_username, $password);

            if ($result['success']) {
                $synced++;
            }
        }

        ActivityLog::log('Sync PPPoE', "Sinkronisasi PPPoE: {$synced} ditambahkan, {$skipped} dilewati");

        return back()->with('success', "Sinkronisasi selesai. {$synced} user PPPoE ditambahkan, {$skipped} dilewati (tanpa username).");
    }
}
