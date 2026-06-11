<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index()
    {
        $query = Package::withCount('customers')->orderBy('price');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('speed', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('mikrotik_profile', 'like', "%{$search}%");
            });
        }

        if (request()->filled('status')) {
            $query->where('is_active', request('status') === 'active');
        }

        $packages = $query->paginate(15)->withQueryString();

        return view('packages.index', compact('packages'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'speed' => 'required|numeric|min:1|max:10000',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'nullable|in:daily,weekly,monthly,yearly',
            'mikrotik_profile' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $validated['billing_cycle'] = $validated['billing_cycle'] ?? 'monthly';

        Package::create($validated);

        ActivityLog::log('Tambah Paket', 'Menambahkan paket: '.$validated['name']);

        return redirect()->route('packages.index')->with('success', 'Paket berhasil ditambahkan.');
    }

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'speed' => 'required|numeric|min:1|max:10000',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'nullable|in:daily,weekly,monthly,yearly',
            'mikrotik_profile' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : $package->is_active;
        $validated['billing_cycle'] = $validated['billing_cycle'] ?? 'monthly';

        $package->update($validated);

        ActivityLog::log('Ubah Paket', 'Mengubah paket: '.$package->name);

        return redirect()->route('packages.index')->with('success', 'Paket berhasil diperbarui.');
    }

    public function destroy(Package $package)
    {
        if ($package->customers()->exists()) {
            ActivityLog::log('Gagal Hapus Paket', 'Paket '.$package->name.' masih dipakai '.$package->customers()->count().' pelanggan');

            return redirect()->route('packages.index')->with('error', 'Paket tidak bisa dihapus karena masih dipakai pelanggan. Nonaktifkan paket jika tidak ingin dipakai lagi.');
        }

        $name = $package->name;
        $package->delete();

        ActivityLog::log('Hapus Paket', 'Menghapus paket: '.$name);

        return redirect()->route('packages.index')->with('success', 'Paket '.$name.' berhasil dihapus.');
    }

    public function massBill()
    {
        $customers = Customer::with('package')->where('status', 'active')->get();
        $generated = 0;

        foreach ($customers as $customer) {
            if (! $customer->package) {
                continue;
            }

            $exists = Invoice::where('customer_id', $customer->id)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->exists();

            if ($exists) {
                continue;
            }

            $invoiceCode = 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-'.now()->format('m');
            $counter = 1;
            while (Invoice::where('invoice_code', $invoiceCode)->exists()) {
                $invoiceCode = 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-'.now()->format('m').'-'.$counter;
                $counter++;
            }

            Invoice::create([
                'invoice_code' => $invoiceCode,
                'customer_id' => $customer->id,
                'amount' => $customer->package->price,
                'payment_status' => 'unpaid',
            ]);

            $generated++;
        }

        ActivityLog::log('Tagih Massal', "Generate {$generated} tagihan untuk semua pelanggan aktif");

        return back()->with('success', "Berhasil membuat {$generated} tagihan.");
    }
}
