<?php

namespace App\Http\Controllers;

use App\Jobs\PollOltJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\OltPort;
use App\Models\Onu;
use App\Models\Package;
use App\Models\Setting;
use App\Services\Billing\BillingService;
use App\Services\MikrotikService;
use App\Services\SmartQos\SmartQosService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    public function create()
    {
        $packages = Package::where('is_active', true)->orderBy('price')->get();
        $odps = Odp::with('ports')->orderBy('nama_odp')->get();

        return view('customer.create', compact('packages', 'odps'));
    }

    public function createExisting()
    {
        $packages = Package::where('is_active', true)->orderBy('price')->get();
        $odps = Odp::with('ports')->orderBy('nama_odp')->get();

        return view('customer.create-existing', compact('packages', 'odps'));
    }

    public function storeExisting(Request $request, BillingService $billing)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'nik' => 'nullable|string|max:20',
            'ktp_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'type' => 'required|in:ppp,hotspot',
            'package_id' => 'required_if:type,ppp|nullable|exists:packages,id',
            'odp_id' => 'nullable|exists:odps,id',
            'odp_port_number' => 'nullable|integer|min:1',
            'pppoe_username' => 'required_if:type,ppp|nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'modem_sn' => 'nullable|string|max:255',
            'modem_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'due_date' => 'nullable|date',
        ]);

        if ($request->hasFile('ktp_photo')) {
            $validated['ktp_photo'] = $request->file('ktp_photo')->store('ktp', 'public');
        }

        if ($request->hasFile('modem_photo')) {
            $validated['modem_photo'] = $request->file('modem_photo')->store('modem', 'public');
        }

        $customer = $billing->createCustomer($validated);

        if (! empty($request->selected_onu_id)) {
            $onu = Onu::whereNull('customer_id')->find($request->selected_onu_id);
            if ($onu) {
                $onu->update(['customer_id' => $customer->id]);
                if (empty($customer->serial_number) && $onu->serial_number) {
                    $customer->update(['serial_number' => $onu->serial_number]);
                }
            }
        } elseif (! empty($validated['serial_number'])) {
            $this->linkOnuBySerial($customer, $validated['serial_number']);
        } elseif (! empty($validated['modem_sn'])) {
            $this->linkOnuBySerial($customer, $validated['modem_sn']);
        }

        if (! empty($validated['odp_id'])) {
            $this->assignOdpPort(
                (int) $validated['odp_id'],
                $customer,
                ! empty($validated['odp_port_number']) ? (int) $validated['odp_port_number'] : null
            );
        }

        if ($customer->type === 'ppp') {
            $billing->createInvoice($customer);

            try {
                SmartQosService::provisionCustomerQueue($customer);
            } catch (\Exception $e) {
                Log::warning('SmartQos: Gagal provisioning queue untuk '.$customer->name.': '.$e->getMessage());
            }
        }

        ActivityLog::log('Tambah Pelanggan Existing', 'Menambahkan pelanggan existing: '.$customer->name);

        if ($customer->type === 'ppp' && empty($validated['due_date'])) {
            $defaultDueDate = Setting::get('default_due_date', '5');
            $customer->update(['due_date' => now()->day((int) $defaultDueDate)->format('Y-m-d')]);
        }

        return redirect()->route('customers.index')->with('success', 'Pelanggan existing '.$customer->name.' berhasil ditambahkan!');
    }

    public function store(Request $request, BillingService $billing)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'nik' => 'nullable|string|max:20',
            'ktp_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'type' => 'required|in:ppp,hotspot',
            'package_id' => 'required_if:type,ppp|nullable|exists:packages,id',
            'odp_id' => 'nullable|exists:odps,id',
            'odp_port_number' => 'nullable|integer|min:1',
            'pppoe_username' => 'required_if:type,ppp|nullable|string|max:255',
            'pppoe_password' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'modem_sn' => 'nullable|string|max:255',
            'modem_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'due_date' => 'nullable|date',
        ]);

        if ($request->hasFile('ktp_photo')) {
            $validated['ktp_photo'] = $request->file('ktp_photo')->store('ktp', 'public');
        }

        if ($request->hasFile('modem_photo')) {
            $validated['modem_photo'] = $request->file('modem_photo')->store('modem', 'public');
        }

        $customer = $billing->createCustomer($validated);

        if (! empty($validated['pppoe_username']) && ! empty($validated['pppoe_password'])) {
            $this->createPppoeSecret($customer);
        }

        if (! empty($request->selected_onu_id)) {
            $onu = Onu::whereNull('customer_id')->find($request->selected_onu_id);
            if ($onu) {
                $onu->update(['customer_id' => $customer->id]);
                if (empty($customer->serial_number) && $onu->serial_number) {
                    $customer->update(['serial_number' => $onu->serial_number]);
                }
            }
        } elseif (! empty($validated['serial_number'])) {
            $this->linkOnuBySerial($customer, $validated['serial_number']);
        } elseif (! empty($validated['modem_sn'])) {
            $this->linkOnuBySerial($customer, $validated['modem_sn']);
        }

        if (! empty($validated['odp_id'])) {
            $this->assignOdpPort(
                (int) $validated['odp_id'],
                $customer,
                ! empty($validated['odp_port_number']) ? (int) $validated['odp_port_number'] : null
            );
        }

        if ($customer->type === 'ppp') {
            $billing->createInvoice($customer);

            try {
                SmartQosService::provisionCustomerQueue($customer);
            } catch (\Exception $e) {
                Log::warning('SmartQos: Gagal provisioning queue untuk '.$customer->name.': '.$e->getMessage());
            }
        }

        ActivityLog::log('Tambah Pelanggan', 'Menambahkan pelanggan baru: '.$customer->name);

        if ($customer->type === 'ppp' && empty($validated['due_date'])) {
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

    public function activation()
    {
        $customers = Customer::with('package', 'odp')
            ->where('status', 'inactive')
            ->latest()
            ->paginate(20);

        return view('customer.activation', compact('customers'));
    }

    public function suspended()
    {
        $customers = Customer::with('package', 'odp')
            ->where('status', 'suspended')
            ->latest('suspended_at')
            ->paginate(20);

        return view('customer.suspended', compact('customers'));
    }

    public function history()
    {
        $logs = ActivityLog::with('user')
            ->where('action', 'like', '%Pelanggan%')
            ->orWhere('action', 'like', '%pelanggan%')
            ->orWhere('action', 'like', '%Isolir%')
            ->orWhere('action', 'like', '%Aktifkan%')
            ->latest()
            ->paginate(30);

        return view('customer.history', compact('logs'));
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
            'nik' => 'nullable|string|max:20',
            'ktp_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'type' => 'required|in:ppp,hotspot',
            'package_id' => 'required_if:type,ppp|nullable|exists:packages,id',
            'odp_id' => 'nullable|exists:odps,id',
            'odp_port_number' => 'nullable|integer|min:1',
            'pppoe_username' => 'required_if:type,ppp|nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'modem_sn' => 'nullable|string|max:255',
            'modem_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'due_date' => 'nullable|date',
        ]);

        if ($request->hasFile('ktp_photo')) {
            if ($customer->ktp_photo) {
                Storage::disk('public')->delete($customer->ktp_photo);
            }
            $validated['ktp_photo'] = $request->file('ktp_photo')->store('ktp', 'public');
        } else {
            unset($validated['ktp_photo']);
        }

        if ($request->boolean('delete_ktp') && $customer->ktp_photo) {
            Storage::disk('public')->delete($customer->ktp_photo);
            $validated['ktp_photo'] = null;
        }

        if ($request->hasFile('modem_photo')) {
            if ($customer->modem_photo) {
                Storage::disk('public')->delete($customer->modem_photo);
            }
            $validated['modem_photo'] = $request->file('modem_photo')->store('modem', 'public');
        } else {
            unset($validated['modem_photo']);
        }

        if ($request->boolean('delete_modem_photo') && $customer->modem_photo) {
            Storage::disk('public')->delete($customer->modem_photo);
            $validated['modem_photo'] = null;
        }

        if (! empty($request->selected_onu_id)) {
            $onu = Onu::whereNull('customer_id')->find($request->selected_onu_id);
            if ($onu) {
                $onu->update(['customer_id' => $customer->id]);
                if (empty($customer->serial_number) && $onu->serial_number) {
                    $validated['serial_number'] = $onu->serial_number;
                }
            }
        } elseif (! empty($validated['serial_number']) && $validated['serial_number'] !== $customer->serial_number) {
            $this->linkOnuBySerial($customer, $validated['serial_number']);
        } elseif (! empty($validated['modem_sn']) && $validated['modem_sn'] !== $customer->modem_sn) {
            $this->linkOnuBySerial($customer, $validated['modem_sn']);
        }

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

        if ($customer->status === 'active' && isset($validated['package_id'])) {
            try {
                SmartQosService::updateCustomerQueue($customer);
            } catch (\Exception $e) {
                Log::warning('SmartQos: Gagal update queue untuk '.$customer->name.': '.$e->getMessage());
            }
        }

        ActivityLog::log('Ubah Pelanggan', 'Mengubah data pelanggan: '.$customer->name);

        return redirect()->route('customers.index')->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        $name = $customer->name;

        if ($customer->odp_port_id) {
            $customer->odpPort?->update(['status' => 'available']);
        }

        try {
            SmartQosService::removeCustomerQueue($customer);
        } catch (\Exception $e) {
            Log::warning('SmartQos: Gagal remove queue untuk '.$name.': '.$e->getMessage());
        }

        $customer->delete();

        ActivityLog::log('Hapus Pelanggan', 'Menghapus pelanggan: '.$name);

        return redirect()->route('customers.index')->with('success', 'Pelanggan '.$name.' berhasil dihapus.');
    }

    public function suspend(Customer $customer)
    {
        $customer->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        $this->syncPppStatus($customer, true);

        try {
            SmartQosService::disableCustomerQueue($customer);
        } catch (\Exception $e) {
            Log::warning('SmartQos: Gagal disable queue untuk '.$customer->name.': '.$e->getMessage());
        }

        ActivityLog::log('Isolir Pelanggan', 'Mengisolir: '.$customer->name);

        return back()->with('success', 'Pelanggan '.$customer->name.' diisolir.');
    }

    public function activate(Customer $customer)
    {
        $originalProfile = $customer->original_ppp_profile;

        $this->syncPppStatus($customer, false);

        $customer->update([
            'status' => 'active',
            'suspended_at' => null,
            'original_ppp_profile' => null,
        ]);

        $this->autoCreateOnu($customer);

        try {
            SmartQosService::enableCustomerQueue($customer);
        } catch (\Exception $e) {
            Log::warning('SmartQos: Gagal enable queue untuk '.$customer->name.': '.$e->getMessage());
        }

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
                'port_number' => 1,
                'port_type' => 'gpon',
                'status' => 'active',
            ]);
        }

        $exists = Onu::where('olt_port_id', $port->id)
            ->where('onu_id', 'mikrotik-'.$customer->id)
            ->exists();

        if ($exists) {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $active = $mikrotik->getPppActive();
                $session = collect($active)->firstWhere('name', $customer->pppoe_username);
                $mac = $session['caller-id'] ?? '';
                if ($mac) {
                    Onu::where('olt_port_id', $port->id)
                        ->where('onu_id', 'mikrotik-'.$customer->id)
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

    private function linkOnuBySerial(Customer $customer, string $serialNumber): void
    {
        $serialLower = strtolower(trim($serialNumber));

        $oltOnu = Onu::whereNull('customer_id')
            ->where(function ($q) use ($serialLower) {
                $q->whereRaw('LOWER(serial_number) = ?', [$serialLower])
                    ->orWhereRaw('LOWER(onu_id) = ?', [$serialLower])
                    ->orWhereRaw('LOWER(caller_id) = ?', [$serialLower])
                    ->orWhereRaw('LOWER(mac_address) = ?', [$serialLower]);
            })
            ->first();

        if (! $oltOnu) {
            return;
        }

        $alreadyLinked = Onu::where('customer_id', $customer->id)
            ->whereNotNull('serial_number')
            ->where('id', '!=', $oltOnu->id)
            ->exists();

        if ($alreadyLinked) {
            return;
        }

        $oltOnu->update(['customer_id' => $customer->id]);

        ActivityLog::log('Auto-Link ONU', "SN/ID: {$serialNumber} → {$customer->name}");
    }

    public function syncPppoe()
    {
        $routers = MikrotikRouter::where('is_active', true)
            ->byType('pppoe')
            ->get();
        $errors = [];

        if ($routers->isEmpty()) {
            $mikrotik = new MikrotikService;
            if (! $mikrotik->isConfigured()) {
                return back()->with('error', 'MikroTik belum dikonfigurasi.');
            }
            $this->doSyncPppoe($mikrotik);
            if ($mikrotik->getLastError()) {
                $errors[] = $mikrotik->getLastError();
            }

            if (! empty($errors)) {
                return back()->with('error', 'Gagal sinkronisasi: '.implode('; ', $errors));
            }

            return back()->with('success', 'Sinkronisasi PPPoE selesai.');
        }

        foreach ($routers as $router) {
            $mikrotik = new MikrotikService($router);
            $this->doSyncPppoe($mikrotik);
            if ($mikrotik->getLastError()) {
                $errors[] = "[$router->name] ".$mikrotik->getLastError();
            }
        }

        if (! empty($errors)) {
            return back()->with('error', 'Sinkronisasi selesai dengan error: '.implode('; ', $errors));
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
        $olts = Olt::where('status', 'active')->get();

        if ($olts->isEmpty()) {
            return back()->with('error', 'Tidak ada OLT aktif untuk di-sync.');
        }

        $totalOnus = 0;

        foreach ($olts as $olt) {
            try {
                $job = new PollOltJob($olt);
                $job->handle();
                $totalOnus += Onu::where('olt_port_id', $olt->ports()->pluck('id'))->count();
            } catch (\Exception $e) {
                Log::error("syncAllOnu gagal untuk {$olt->name}: {$e->getMessage()}");
            }
        }

        $matched = $this->syncPppoeSecrets();

        ActivityLog::log('Sync Semua ONU', "{$totalOnus} ONU, {$matched} PPPoE username terisi dari MikroTik");

        return back()->with('success', "Sync ONU selesai. {$totalOnus} ONU tercatat. {$matched} PPPoE username terisi dari MikroTik.");
    }

    private function syncPppoeSecrets(): int
    {
        $mikrotik = new MikrotikService;
        if (! $mikrotik->isConfigured()) {
            return 0;
        }

        $secrets = $mikrotik->getPppSecrets();
        if (empty($secrets)) {
            return 0;
        }

        $customers = Customer::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('pppoe_username')->orWhere('pppoe_username', '');
            })
            ->get();

        if ($customers->isEmpty()) {
            return 0;
        }

        $matched = 0;

        foreach ($customers as $customer) {
            $normalizedName = $this->normalizeName($customer->name);

            $bestSecret = null;
            $bestScore = 0;

            foreach ($secrets as $secret) {
                $secretName = $secret['name'] ?? '';
                $base = explode('@', $secretName)[0] ?? $secretName;
                $normalized = $this->normalizeName($base);

                if ($normalized === $normalizedName) {
                    $bestSecret = $secret;
                    $bestScore = 100;
                    break;
                }

                if (str_starts_with($normalized, $normalizedName) || str_starts_with($normalizedName, $normalized)) {
                    $shorter = min(strlen($normalized), strlen($normalizedName));
                    $longer = max(strlen($normalized), strlen($normalizedName));
                    $score = ($shorter / $longer) * 90;
                    if ($score > $bestScore) {
                        $bestSecret = $secret;
                        $bestScore = $score;
                    }
                } elseif (str_contains($normalized, $normalizedName) || str_contains($normalizedName, $normalized)) {
                    $shorter = min(strlen($normalized), strlen($normalizedName));
                    $longer = max(strlen($normalized), strlen($normalizedName));
                    $score = ($shorter / $longer) * 75;
                    if ($score > $bestScore) {
                        $bestSecret = $secret;
                        $bestScore = $score;
                    }
                }

                if ($bestScore < 60) {
                    $customerFirstName = $this->normalizeName(explode(' ', $customer->name)[0] ?? '');
                    if (strlen($customerFirstName) >= 3 && str_starts_with($normalized, $customerFirstName)) {
                        $bestSecret = $secret;
                        $bestScore = 65;
                    }
                }
            }

            if ($bestSecret && $bestScore >= 60) {
                $customer->update(['pppoe_username' => $bestSecret['name']]);
                $matched++;
            }
        }

        return $matched;
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9]/', '', $name);

        return $name;
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
            $currentProfile = $mikrotik->getPppSecretProfile($customer->pppoe_username);
            Log::info("SUSPEND: current profile on MikroTik for {$customer->pppoe_username}", [
                'current_profile' => $currentProfile,
            ]);
            if ($currentProfile) {
                $customer->update(['original_ppp_profile' => $currentProfile]);
            }
            $result = $mikrotik->setPppSecretProfile($customer->pppoe_username, 'Profile-Isolir');
            Log::info("SUSPEND: setPppSecretProfile({$customer->pppoe_username}, Profile-Isolir)", $result);
            $this->addCustomerIpToAddressList($mikrotik, $customer);
            $this->setupIsolirFirewall($mikrotik);
        } else {
            $profile = $customer->original_ppp_profile;
            Log::info("ACTIVATE: restoring profile for {$customer->pppoe_username}", [
                'original_ppp_profile' => $profile,
            ]);
            if ($profile) {
                $result = $mikrotik->setPppSecretProfile($customer->pppoe_username, $profile);
                Log::info('ACTIVATE: setPppSecretProfile result', $result);
            } else {
                Log::warning("ACTIVATE: no profile to restore for {$customer->pppoe_username}");
            }
            $this->removeCustomerIpFromAddressList($mikrotik, $customer);
            $mikrotik->enablePppSecret($customer->pppoe_username);
            $this->kickPppSession($mikrotik, $customer);
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

    private function kickPppSession(MikrotikService $mikrotik, Customer $customer): void
    {
        try {
            $active = $mikrotik->getPppActive();
            $session = collect($active)->firstWhere('name', $customer->pppoe_username);
            if ($session && isset($session['.id'])) {
                $mikrotik->disconnectPppSession($session['.id']);
            }
        } catch (\Throwable $e) {
            // ignore — customer may already be offline
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

    private function createPppoeSecret(Customer $customer): void
    {
        $routers = MikrotikRouter::where('is_active', true)
            ->byType('pppoe')
            ->get();

        $requestedProfile = $customer->package?->mikrotik_profile;

        if ($routers->isNotEmpty()) {
            foreach ($routers as $router) {
                $mikrotik = new MikrotikService($router);
                $profile = $this->resolveMikrotikProfile($mikrotik, $requestedProfile);
                $result = $mikrotik->addPppSecret($customer->pppoe_username, $customer->pppoe_password, 'pppoe', $profile);
                Log::info("CREATE PPPoE: {$customer->pppoe_username} on router {$router->name}", [
                    'profile_requested' => $requestedProfile,
                    'profile_resolved' => $profile,
                    'result' => $result,
                ]);
            }
        } else {
            $mikrotik = new MikrotikService;
            if ($mikrotik->isConfigured()) {
                $profile = $this->resolveMikrotikProfile($mikrotik, $requestedProfile);
                $result = $mikrotik->addPppSecret($customer->pppoe_username, $customer->pppoe_password, 'pppoe', $profile);
                Log::info("CREATE PPPoE: {$customer->pppoe_username} (default config)", [
                    'profile_requested' => $requestedProfile,
                    'profile_resolved' => $profile,
                    'result' => $result,
                ]);
            }
        }
    }

    private function resolveMikrotikProfile(MikrotikService $mikrotik, ?string $requestedProfile): ?string
    {
        if (! $requestedProfile) {
            return null;
        }

        $exists = $mikrotik->resolveProfileName($requestedProfile);
        if (! $exists) {
            Log::warning("Profile '{$requestedProfile}' tidak ada di MikroTik — menggunakan default profile");

            return null;
        }

        return $requestedProfile;
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

    public function printThermal(Customer $customer)
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $customer->load('package');

        ActivityLog::log('Cetak Form Thermal', 'Cetak thermal form pelanggan: '.$customer->name);

        return view('customer.print-thermal', compact('customer', 'settings'));
    }

    public function printA4(Customer $customer)
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $customer->load('package');

        ActivityLog::log('Cetak Form A4', 'Cetak A4 form pelanggan: '.$customer->name);

        return view('customer.print-a4', compact('customer', 'settings'));
    }

    public function downloadPdf(Customer $customer)
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $customer->load('package');

        ActivityLog::log('Download PDF Pelanggan', 'Download PDF form pelanggan: '.$customer->name);

        $pdf = Pdf::loadView('customer.pdf', compact('customer', 'settings'));

        return $pdf->download("form-pelanggan-{$customer->id}.pdf");
    }

    public function searchApi(Request $request): JsonResponse
    {
        $term = $request->get('q');
        $customers = Customer::where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('customer_code', 'like', "%{$term}%")
            ->limit(20)
            ->get(['id', 'name', 'phone', 'customer_code']);

        return response()->json($customers);
    }

    public function pppoeAvailable(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        $mikrotik = new MikrotikService;
        if (! $mikrotik->isConfigured()) {
            return response()->json([]);
        }

        try {
            $secrets = $mikrotik->getPppSecrets();
        } catch (\Exception $e) {
            Log::warning('pppoeAvailable: Gagal fetch PPPoE secrets: '.$e->getMessage());

            return response()->json([]);
        }

        $usedUsernames = Customer::whereNotNull('pppoe_username')
            ->where('pppoe_username', '!=', '')
            ->pluck('pppoe_username')
            ->map(fn ($u) => strtolower($u))
            ->toArray();

        $results = collect($secrets)
            ->filter(fn ($s) => ! in_array(strtolower($s['name'] ?? ''), $usedUsernames))
            ->filter(fn ($s) => $search === '' || str_contains(strtolower($s['name'] ?? ''), strtolower($search)))
            ->values()
            ->take(20);

        return response()->json($results);
    }
}
