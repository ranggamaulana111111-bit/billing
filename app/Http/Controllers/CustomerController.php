<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\OltPort;
use App\Models\Onu;
use App\Models\Package;
use App\Models\Setting;
use App\Services\MikrotikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function create()
    {
        $packages = Package::where('is_active', true)->orderBy('price')->get();
        $odps = Odp::with('ports')->orderBy('nama_odp')->get();

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
            'odp_id' => 'nullable|exists:odps,id',
            'odp_port_number' => 'nullable|integer|min:1',
            'pppoe_username' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
        ]);

        $customer = Customer::create($validated);

        if (! empty($validated['odp_id'])) {
            $this->assignOdpPort(
                (int) $validated['odp_id'],
                $customer,
                ! empty($validated['odp_port_number']) ? (int) $validated['odp_port_number'] : null
            );
        }

        $package = Package::find($validated['package_id']);

        Invoice::create([
            'invoice_code' => 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-ALK-'.now()->format('m').'-PRDT',
            'customer_id' => $customer->id,
            'amount' => $package->price,
            'payment_status' => 'unpaid',
            'billing_period' => now()->format('Y-m'),
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
        $customers = Customer::with('package', 'odp', 'odpPort', 'onus.oltPort.olt')->latest()->paginate(20);
        $stats = [
            'total' => Customer::count(),
            'active' => Customer::where('status', 'active')->count(),
            'suspended' => Customer::where('status', 'suspended')->count(),
            'inactive' => Customer::where('status', 'inactive')->count(),
        ];

        $totalOlts = Olt::where('status', 'active')->count();

        return view('customer.index', compact('customers', 'stats', 'totalOlts'));
    }

    public function edit(Customer $customer)
    {
        $packages = Package::where('is_active', true)
            ->orWhere('id', $customer->package_id)
            ->orderBy('price')
            ->get();
        $odps = Odp::with('ports')->orderBy('nama_odp')->get();

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
            'odp_id' => 'nullable|exists:odps,id',
            'odp_port_number' => 'nullable|integer|min:1',
            'pppoe_username' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
        ]);

        if (! empty($validated['odp_id']) && $validated['odp_id'] != $customer->odp_id) {
            if ($customer->odp_port_id) {
                $customer->odpPort?->update(['status' => 'available']);
            }
            $this->assignOdpPort(
                (int) $validated['odp_id'],
                $customer,
                ! empty($validated['odp_port_number']) ? (int) $validated['odp_port_number'] : null
            );
        } elseif (empty($validated['odp_id']) && $customer->odp_id) {
            if ($customer->odp_port_id) {
                $customer->odpPort?->update(['status' => 'available']);
            }
            $validated['odp_id'] = null;
            $validated['odp_port_id'] = null;
        }

        $customer->update($validated);

        ActivityLog::log('Ubah Pelanggan', 'Mengubah data pelanggan: '.$customer->name);

        return redirect()->route('customers.index')->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        $name = $customer->name;

        if ($customer->odp_port_id) {
            $customer->odpPort?->update(['status' => 'available']);
        }

        $customer->delete();

        ActivityLog::log('Hapus Pelanggan', 'Menghapus pelanggan: '.$name);

        return redirect()->route('customers.index')->with('success', 'Pelanggan '.$name.' berhasil dihapus.');
    }

    public function suspend(Customer $customer)
    {
        $originalProfile = $customer->package?->mikrotik_profile;
        $customer->update([
            'original_ppp_profile' => $originalProfile,
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        $this->syncPppStatus($customer, true);

        ActivityLog::log('Isolir Pelanggan', 'Mengisolir: '.$customer->name);

        return back()->with('success', 'Pelanggan '.$customer->name.' diisolir.');
    }

    public function activate(Customer $customer)
    {
        $customer->update([
            'status' => 'active',
            'suspended_at' => null,
            'original_ppp_profile' => null,
        ]);

        $this->syncPppStatus($customer, false);

        $this->autoCreateOnu($customer);

        ActivityLog::log('Aktifkan Pelanggan', 'Mengaktifkan kembali: '.$customer->name);

        return back()->with('success', 'Pelanggan '.$customer->name.' diaktifkan kembali.');
    }

    public function activateManual(int $id)
    {
        $customer = Customer::findOrFail($id);

        return $this->activate($customer);
    }

    private function autoCreateOnu(Customer $customer): void
    {
        if (! $customer->pppoe_username) {
            return;
        }

        $olt = Olt::where('status', 'active')->first();
        if (! $olt) {
            return;
        }

        $port = $olt->ports()->first();
        if (! $port) {
            $port = OltPort::create([
                'olt_id' => $olt->id,
                'slot_number' => 0,
                'port_number' => 0,
                'port_type' => 'gpon',
                'status' => 'active',
            ]);
        }

        $exists = Onu::where('olt_port_id', $port->id)
            ->where('customer_id', $customer->id)
            ->exists();

        if ($exists) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $active = $mikrotik->getPppActive();
                $session = collect($active)->firstWhere('name', $customer->pppoe_username);
                $mac = $session['caller-id'] ?? '';
                if ($mac) {
                    Onu::where('olt_port_id', $port->id)
                        ->where('customer_id', $customer->id)
                        ->update(['caller_id' => $mac, 'status' => 'online', 'last_seen_at' => now()]);
                }
            }

            return;
        }

        $mikrotik = new MikrotikService;
        $mac = '';
        if ($mikrotik->isConfigured()) {
            $active = $mikrotik->getPppActive();
            $session = collect($active)->firstWhere('name', $customer->pppoe_username);
            $mac = $session['caller-id'] ?? '';
        }

        Onu::create([
            'olt_port_id' => $port->id,
            'customer_id' => $customer->id,
            'onu_id' => 'mikrotik-'.$customer->id,
            'caller_id' => $mac ?: 'PPPoE-'.$customer->pppoe_username,
            'status' => 'online',
            'slot_number' => $port->slot_number,
            'port_number' => $port->port_number,
            'last_seen_at' => now(),
        ]);
    }

    public function syncPppoe()
    {
        $routers = MikrotikRouter::where('is_active', true)
            ->byType('pppoe')
            ->get();

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

    public function syncSingleOnu(Customer $customer)
    {
        $this->autoCreateOnu($customer);

        ActivityLog::log('Sync ONU', 'Sinkron ONU perorangan: '.$customer->name);

        return back()->with('success', 'ONU untuk '.$customer->name.' berhasil disinkron.');
    }

    public function syncAllOnu()
    {
        $mikrotik = new MikrotikService;
        $active = [];
        try {
            if ($mikrotik->isConfigured()) {
                $active = $mikrotik->getPppActive();
            }
        } catch (\Exception $e) {
            // proceed without MikroTik
        }

        $sessions = collect($active)->keyBy('name');

        $olts = Olt::where('status', 'active')->get();
        $synced = 0;

        foreach ($olts as $olt) {
            $port = $olt->ports()->first();
            if (! $port) {
                $port = OltPort::create([
                    'olt_id' => $olt->id,
                    'slot_number' => 0,
                    'port_number' => 0,
                    'port_type' => 'gpon',
                    'status' => 'active',
                ]);
            }

            $customers = Customer::where('status', 'active')
                ->whereNotNull('pppoe_username')
                ->where('pppoe_username', '!=', '')
                ->get();

            foreach ($customers as $customer) {
                $session = $sessions->get($customer->pppoe_username);
                $mac = $session['caller-id'] ?? '';

                Onu::updateOrCreate(
                    [
                        'olt_port_id' => $port->id,
                        'customer_id' => $customer->id,
                    ],
                    [
                        'onu_id' => 'mikrotik-'.$customer->id,
                        'caller_id' => $mac ?: 'PPPoE-'.$customer->pppoe_username,
                        'status' => $session ? 'online' : 'offline',
                        'slot_number' => $port->slot_number,
                        'port_number' => $port->port_number,
                        'last_seen_at' => $session ? now() : null,
                    ]
                );

                $synced++;
            }

            $olt->update(['last_polled_at' => now()]);
        }

        ActivityLog::log('Sync Semua ONU', "{$synced} ONU dari {$olts->count()} OLT");

        return back()->with('success', "Sync semua ONU selesai. {$synced} ONU diproses.");
    }

    protected function syncPppStatus(Customer $customer, bool $suspended): void
    {
        if (! $customer->pppoe_username) {
            return;
        }

        $routers = MikrotikRouter::where('is_active', true)
            ->byType('pppoe')
            ->get();

        if ($routers->isNotEmpty()) {
            foreach ($routers as $router) {
                $mikrotik = new MikrotikService($router);
                $this->syncPppOnRouter($mikrotik, $customer, $suspended);
            }
        } else {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $this->syncPppOnRouter($mikrotik, $customer, $suspended);
            }
        }
    }

    private function syncPppOnRouter(MikrotikService $mikrotik, Customer $customer, bool $suspended): void
    {
        if ($suspended) {
            if ($customer->original_ppp_profile) {
                $mikrotik->setPppSecretProfile($customer->pppoe_username, 'Profile-Isolir');
            }
            $this->addCustomerIpToAddressList($mikrotik, $customer);
            $this->setupIsolirFirewall($mikrotik);
        } else {
            $profile = $customer->original_ppp_profile ?? $customer->package?->mikrotik_profile;
            if ($profile) {
                $mikrotik->setPppSecretProfile($customer->pppoe_username, $profile);
            }
            $this->removeCustomerIpFromAddressList($mikrotik, $customer);
            $mikrotik->enablePppSecret($customer->pppoe_username);
        }
    }

    private function addCustomerIpToAddressList(MikrotikService $mikrotik, Customer $customer): void
    {
        $ip = $this->getCustomerPppIp($mikrotik, $customer->pppoe_username);
        if ($ip) {
            $mikrotik->addIpToAddressList($ip, 'isolir-users');
        }
    }

    private function removeCustomerIpFromAddressList(MikrotikService $mikrotik, Customer $customer): void
    {
        $ip = $this->getCustomerPppIp($mikrotik, $customer->pppoe_username);
        if ($ip) {
            $mikrotik->removeIpFromAddressList($ip, 'isolir-users');
        }
    }

    private function getCustomerPppIp(MikrotikService $mikrotik, string $username): ?string
    {
        try {
            $active = $mikrotik->getPppActive();
            $session = collect($active)->firstWhere('name', $username);

            return $session['address'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function setupIsolirFirewall(MikrotikService $mikrotik): void
    {
        $redirectIp = Setting::get('isolir_redirect_ip', '');
        if (! $redirectIp) {
            return;
        }

        try {
            $mikrotik->addHttpRedirectForAddressList('isolir-users', $redirectIp, 80);
            $mikrotik->addHttpRedirectForAddressList('isolir-users', $redirectIp, 443);
            $mikrotik->addFilterDropForAddressList('isolir-users', $redirectIp);
        } catch (\Exception $e) {
            Log::warning("Gagal setup isolir firewall: {$e->getMessage()}");
        }
    }

    private function disconnectPppSession(MikrotikService $mikrotik, string $username): void
    {
        try {
            $active = $mikrotik->getPppActive();
            $session = collect($active)->firstWhere('name', $username);
            if ($session && isset($session['.id'])) {
                $mikrotik->disconnectPppSession($session['.id']);
            }
        } catch (\Exception $e) {
            Log::warning("Gagal putus sesi PPP {$username}: {$e->getMessage()}");
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
                $profile = $customer->package?->mikrotik_profile;
                if ($profile) {
                    $mikrotik->setPppSecretProfile($customer->pppoe_username, $profile);
                }
                $mikrotik->setPppSecretAddressList($customer->pppoe_username, null);
            } else {
                $password = $customer->pppoe_username.'123';
                $profile = $customer->package?->mikrotik_profile;
                $mikrotik->addPppSecret($customer->pppoe_username, $password, 'pppoe', $profile);
            }
            $synced++;
        }

        $suspendedCustomers = Customer::where('status', 'suspended')->get();
        foreach ($suspendedCustomers as $customer) {
            if (! $customer->pppoe_username) {
                continue;
            }
            $mikrotik->setPppSecretProfile($customer->pppoe_username, 'Profile-Isolir');
            $this->addCustomerIpToAddressList($mikrotik, $customer);
        }

        ActivityLog::log('Sync PPPoE', "Sinkronisasi PPPoE: {$synced} aktif, {$skipped} dilewati");
    }

    private function assignOdpPort(int $odpId, Customer $customer, ?int $portNumber = null): void
    {
        $odp = Odp::find($odpId);
        if (! $odp) {
            return;
        }

        if ($portNumber) {
            $port = $odp->ports()->where('port_number', $portNumber)->first();
            if (! $port || $port->status !== 'available') {
                return;
            }
        } else {
            $port = $odp->ports()->where('status', 'available')->first();
            if (! $port) {
                return;
            }
        }

        $port->update(['status' => 'used']);
        $customer->updateQuietly([
            'odp_id' => $odp->id,
            'odp_port_id' => $port->id,
        ]);
    }
}
