<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Onu;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class MikrotikController extends Controller
{
    protected function resolveMikrotik(): MikrotikService
    {
        $routerId = request('router');

        if ($routerId) {
            $router = MikrotikRouter::find($routerId);
            if ($router) {
                return new MikrotikService($router);
            }
        }

        return new MikrotikService;
    }

    public function dashboard()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $resource = $mikrotik->getSystemResource();
        $identity = $mikrotik->getSystemIdentity();
        $interfaces = collect($mikrotik->getInterfaces())->take(5);
        $activeHotspot = $mikrotik->getActiveHotspotSessions();
        $activePpp = $mikrotik->getPppActive();
        $hotspotUsers = $mikrotik->getHotspotUsers();

        $uptimeSeconds = $this->parseUptime($resource['uptime'] ?? '0s');

        return view('mikrotik.dashboard', compact(
            'resource', 'identity', 'interfaces',
            'activeHotspot', 'activePpp', 'hotspotUsers',
            'uptimeSeconds'
        ));
    }

    // ── HOTSPOT PROFILES ──

    public function profiles()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $profiles = $mikrotik->getHotspotProfiles();

        return view('mikrotik.profiles', compact('profiles'));
    }

    public function storeProfile(Request $request)
    {
        $mikrotik = $this->resolveMikrotik();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate_limit' => 'nullable|string|max:100',
            'shared_users' => 'nullable|string',
            'parent_queue' => 'nullable|string|max:100',
        ]);

        $params = [];
        if ($validated['rate_limit']) {
            $params['rate-limit'] = $validated['rate_limit'];
        }
        if ($validated['shared_users']) {
            $params['shared-users'] = $validated['shared_users'];
        }

        $result = $mikrotik->addHotspotProfile($validated['name'], $params);

        if ($result['success']) {
            ActivityLog::log('Tambah Profile', 'Menambahkan profile hotspot: '.$validated['name']);

            return redirect()->route('mikrotik.profiles', ['router' => request('router')])->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function destroyProfile(string $profileId)
    {
        $mikrotik = $this->resolveMikrotik();

        $result = $mikrotik->removeHotspotProfile($profileId);

        if ($result['success']) {
            ActivityLog::log('Hapus Profile', 'Menghapus profile hotspot ID: '.$profileId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function updateProfile(Request $request, string $profileId)
    {
        $mikrotik = $this->resolveMikrotik();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate_limit' => 'nullable|string|max:100',
            'shared_users' => 'nullable|string',
        ]);

        $params = ['name' => $validated['name']];
        if ($validated['rate_limit']) {
            $params['rate-limit'] = $validated['rate_limit'];
        }
        if ($validated['shared_users']) {
            $params['shared-users'] = $validated['shared_users'];
        }

        $result = $mikrotik->updateHotspotProfile($profileId, $params);

        if ($result['success']) {
            ActivityLog::log('Update Profile', 'Memperbarui profile hotspot: '.$validated['name']);

            return redirect()->route('mikrotik.profiles', ['router' => request('router')])->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function syncProfiles()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return back()->with('error', 'MikroTik tidak terkonfigurasi');
        }

        $profiles = $mikrotik->getHotspotProfiles();

        ActivityLog::log('Sync Profile', 'Menyinkronkan daftar hotspot profile dari MikroTik');

        return redirect()->route('mikrotik.profiles', ['router' => request('router')])->with('success', 'Daftar hotspot profile berhasil disinkronkan ('.count($profiles).' profile)');
    }

    // ── HOTSPOT USERS (VOUCHER) ──

    public function hotspotUsers()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $users = $mikrotik->getHotspotUsers();
        $profiles = $mikrotik->getHotspotProfiles();

        return view('mikrotik.hotspot-users', compact('users', 'profiles'));
    }

    public function storeHotspotUser(Request $request)
    {
        $mikrotik = $this->resolveMikrotik();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'profile' => 'nullable|string|max:255',
            'limit_uptime' => 'nullable|string|max:50',
        ]);

        $hours = null;
        if ($validated['limit_uptime']) {
            preg_match('/\d+/', $validated['limit_uptime'], $m);
            $hours = isset($m[0]) ? (int) $m[0] : null;
        }

        $result = $mikrotik->addHotspotUser(
            $validated['name'],
            $validated['password'],
            null,
            $hours
        );

        if ($result['success']) {
            ActivityLog::log('Tambah Hotspot User', 'Menambahkan hotspot user: '.$validated['name']);

            return redirect()->route('mikrotik.hotspot-users', ['router' => request('router')])->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function updateHotspotUser(Request $request, string $userId)
    {
        $mikrotik = $this->resolveMikrotik();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'profile' => 'nullable|string|max:255',
            'limit_uptime' => 'nullable|string|max:50',
        ]);

        $params = ['name' => $validated['name']];
        if ($validated['password']) {
            $params['password'] = $validated['password'];
        }
        if ($validated['profile']) {
            $params['profile'] = $validated['profile'];
        }
        if ($validated['limit_uptime']) {
            $params['limit-uptime'] = $validated['limit_uptime'];
        }

        $result = $mikrotik->updateHotspotUser($userId, $params);

        if ($result['success']) {
            ActivityLog::log('Update Hotspot User', 'Memperbarui hotspot user: '.$validated['name']);

            return redirect()->route('mikrotik.hotspot-users', ['router' => request('router')])->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function destroyHotspotUser(string $userId)
    {
        $mikrotik = $this->resolveMikrotik();

        $result = $mikrotik->removeHotspotUserById($userId);

        if ($result['success']) {
            ActivityLog::log('Hapus Hotspot User', 'Menghapus hotspot user ID: '.$userId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function toggleHotspotUser(Request $request, string $userId)
    {
        $mikrotik = $this->resolveMikrotik();

        $disable = $request->boolean('disable');
        $result = $mikrotik->updateHotspotUser($userId, ['disabled' => $disable ? 'yes' : 'no']);

        if ($result['success']) {
            $label = $disable ? 'dinonaktifkan' : 'diaktifkan';
            ActivityLog::log('Toggle Hotspot User', "Hotspot user ID {$userId} {$label}");

            return back()->with('success', "User {$label}");
        }

        return back()->with('error', $result['message']);
    }

    public function syncHotspotUsers()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return back()->with('error', 'MikroTik tidak terkonfigurasi');
        }

        $users = $mikrotik->getHotspotUsers();

        ActivityLog::log('Sync Hotspot User', 'Menyinkronkan daftar hotspot user dari MikroTik');

        return redirect()->route('mikrotik.hotspot-users', ['router' => request('router')])->with('success', 'Daftar hotspot user berhasil disinkronkan ('.count($users).' user)');
    }

    // ── ACTIVE SESSIONS ──

    public function activeSessions()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $hotspot = $mikrotik->getActiveHotspotSessions();
        $ppp = $mikrotik->getPppActive();

        return view('mikrotik.active', compact('hotspot', 'ppp'));
    }

    public function disconnectHotspot(string $sessionId)
    {
        $mikrotik = $this->resolveMikrotik();

        $result = $mikrotik->disconnectHotspotSession($sessionId);

        if ($result['success']) {
            ActivityLog::log('Disconnect Hotspot', 'Memutus sesi hotspot '.$sessionId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function disconnectPpp(string $sessionId)
    {
        $mikrotik = $this->resolveMikrotik();

        $result = $mikrotik->disconnectPppSession($sessionId);

        if ($result['success']) {
            ActivityLog::log('Disconnect', 'Memutus sesi '.$sessionId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    // ── PPP SECRETS ──

    public function pppSecrets()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $secrets = $mikrotik->getPppSecrets();
        $profiles = $mikrotik->getPppProfiles();

        return view('mikrotik.ppp', compact('secrets', 'profiles'));
    }

    public function storePppSecret(Request $request)
    {
        $mikrotik = $this->resolveMikrotik();

        $validated = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'service' => 'required|in:pppoe,pptp,l2tp,ovpn',
            'profile' => 'nullable|string|max:255',
        ]);

        $result = $mikrotik->addPppSecret(
            $validated['username'],
            $validated['password'],
            $validated['service'],
            $validated['profile'] ?: null
        );

        if ($result['success']) {
            ActivityLog::log('Tambah PPP', 'Menambahkan PPP secret: '.$validated['username']);

            return redirect()->route('mikrotik.ppp', ['router' => request('router')])->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function destroyPppSecret(string $secretId)
    {
        $mikrotik = $this->resolveMikrotik();

        $result = $mikrotik->removePppSecret($secretId);

        if ($result['success']) {
            ActivityLog::log('Hapus PPP', 'Menghapus PPP secret ID: '.$secretId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    // ── PPPoE PROFILES ──

    public function pppProfiles()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $profiles = $mikrotik->getPppProfiles();

        return view('mikrotik.ppp-profiles', compact('profiles'));
    }

    public function storePppProfile(Request $request)
    {
        $mikrotik = $this->resolveMikrotik();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate_limit' => 'nullable|string|max:100',
        ]);

        $params = [];
        if ($validated['rate_limit']) {
            $params['rate-limit'] = $validated['rate_limit'];
        }

        $result = $mikrotik->addPppProfile($validated['name'], $params);

        if ($result['success']) {
            ActivityLog::log('Tambah PPP Profile', 'Menambahkan PPP profile: '.$validated['name']);

            return redirect()->route('mikrotik.ppp-profiles', ['router' => request('router')])->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function updatePppProfile(Request $request, string $profileId)
    {
        $mikrotik = $this->resolveMikrotik();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rate_limit' => 'nullable|string|max:100',
        ]);

        $params = ['name' => $validated['name']];
        if ($validated['rate_limit']) {
            $params['rate-limit'] = $validated['rate_limit'];
        }

        $result = $mikrotik->updateProfileById($profileId, $params);

        if ($result['success']) {
            ActivityLog::log('Update PPP Profile', 'Memperbarui PPP profile: '.$validated['name']);

            return redirect()->route('mikrotik.ppp-profiles', ['router' => request('router')])->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function destroyPppProfile(string $profileId)
    {
        $mikrotik = $this->resolveMikrotik();

        $result = $mikrotik->removePppProfile($profileId);

        if ($result['success']) {
            ActivityLog::log('Hapus PPP Profile', 'Menghapus PPP profile ID: '.$profileId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function syncPppProfiles()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return back()->with('error', 'MikroTik tidak terkonfigurasi');
        }

        $profiles = $mikrotik->getPppProfiles();

        if ($mikrotik->getLastError()) {
            return back()->with('error', 'Gagal terhubung ke MikroTik: '.$mikrotik->getLastError());
        }

        ActivityLog::log('Sync PPP Profile', 'Menyinkronkan daftar PPP profile dari MikroTik');

        return redirect()->route('mikrotik.ppp-profiles', ['router' => request('router')])->with('success', 'Daftar PPP profile berhasil disinkronkan ('.count($profiles).' profile)');
    }

    // ── QUEUES (SIMPLE QUEUE) ──

    public function queues()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $queues = $mikrotik->getSimpleQueues();

        return view('mikrotik.queues', compact('queues'));
    }

    public function storeQueue(Request $request)
    {
        $mikrotik = $this->resolveMikrotik();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target' => 'required|string|max:50',
            'max_limit' => 'required|string|max:50',
        ]);

        $result = $mikrotik->addSimpleQueue(
            $validated['name'],
            $validated['max_limit'],
            $validated['target']
        );

        if ($result['success']) {
            ActivityLog::log('Tambah Queue', 'Menambahkan simple queue: '.$validated['name']);

            return redirect()->route('mikrotik.queues', ['router' => request('router')])->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function updateQueue(Request $request, string $queueId)
    {
        $mikrotik = $this->resolveMikrotik();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target' => 'required|string|max:50',
            'max_limit' => 'required|string|max:50',
        ]);

        $result = $mikrotik->updateSimpleQueue(
            $queueId,
            $validated['name'],
            $validated['max_limit'],
            $validated['target']
        );

        if ($result['success']) {
            ActivityLog::log('Update Queue', 'Memperbarui simple queue: '.$validated['name']);

            return redirect()->route('mikrotik.queues', ['router' => request('router')])->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function destroyQueue(string $queueId)
    {
        $mikrotik = $this->resolveMikrotik();

        $result = $mikrotik->removeSimpleQueue($queueId);

        if ($result['success']) {
            ActivityLog::log('Hapus Queue', 'Menghapus queue ID: '.$queueId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function syncQueue()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return back()->with('error', 'MikroTik tidak terkonfigurasi');
        }

        $mikrotik->getSimpleQueues();

        ActivityLog::log('Sync Queue', 'Menyinkronkan daftar queue dari MikroTik');

        return redirect()->route('mikrotik.queues', ['router' => request('router')])->with('success', 'Daftar queue berhasil disinkronkan');
    }

    // ── BACKUP ──

    public function backup(Request $request)
    {
        $mikrotik = $this->resolveMikrotik();

        $name = 'billing-'.now()->format('Ymd-His');

        $result = $mikrotik->createBackup($name);

        if ($result['success']) {
            ActivityLog::log('Backup MikroTik', 'Backup konfigurasi MikroTik: '.$name);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    // ── HELPER ──

    protected function parseUptime(string $uptime): int
    {
        $seconds = 0;
        if (preg_match('/(\d+)w/', $uptime, $m)) {
            $seconds += $m[1] * 604800;
        }
        if (preg_match('/(\d+)d/', $uptime, $m)) {
            $seconds += $m[1] * 86400;
        }
        if (preg_match('/(\d+)h/', $uptime, $m)) {
            $seconds += $m[1] * 3600;
        }
        if (preg_match('/(\d+)m(\d+)s/', $uptime, $m)) {
            $seconds += $m[1] * 60 + $m[2];
        } elseif (preg_match('/(\d+)m/', $uptime, $m)) {
            $seconds += $m[1] * 60;
        } elseif (preg_match('/(\d+)s/', $uptime, $m)) {
            $seconds += $m[1];
        }

        return $seconds;
    }

    public function monitoring()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return back()->with('error', 'MikroTik belum dikonfigurasi.');
        }

        $sessions = [];
        $pppActive = [];
        $interfaces = [];
        $queues = [];

        try {
            $sessions = $mikrotik->getActiveHotspotSessions();
        } catch (\Exception $e) {
        }
        try {
            $pppActive = $mikrotik->getPppActive();
        } catch (\Exception $e) {
        }
        try {
            $interfaces = $mikrotik->getInterfaces();
        } catch (\Exception $e) {
        }
        try {
            $queues = $mikrotik->getSimpleQueues();
        } catch (\Exception $e) {
        }

        $totalBandwidthRx = 0;
        $totalBandwidthTx = 0;

        foreach ($sessions as $s) {
            $totalBandwidthRx += (int) ($s['bytes-in'] ?? 0);
            $totalBandwidthTx += (int) ($s['bytes-out'] ?? 0);
        }

        foreach ($pppActive as $p) {
            $totalBandwidthRx += (int) ($p['bytes-in'] ?? 0);
            $totalBandwidthTx += (int) ($p['bytes-out'] ?? 0);
        }

        $modemClients = $this->buildModemClients($sessions, $pppActive);

        return view('mikrotik.monitoring', compact(
            'sessions', 'pppActive', 'interfaces', 'queues',
            'totalBandwidthRx', 'totalBandwidthTx', 'modemClients'
        ));
    }

    // ── LIVE DATA API ──

    public function liveData()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return response()->json(['error' => 'MikroTik not configured'], 400);
        }

        $interfaces = [];
        $sessions = [];
        $pppActive = [];
        $ping = null;

        try {
            $interfaces = $mikrotik->getInterfaces();
        } catch (\Exception $e) {
        }
        try {
            $sessions = $mikrotik->getActiveHotspotSessions();
        } catch (\Exception $e) {
        }
        try {
            $pppActive = $mikrotik->getPppActive();
        } catch (\Exception $e) {
        }
        try {
            $ping = $mikrotik->getLatency();
        } catch (\Exception $e) {
        }

        $totalRx = 0;
        $totalTx = 0;
        foreach ($sessions as $s) {
            $totalRx += (int) ($s['bytes-in'] ?? 0);
            $totalTx += (int) ($s['bytes-out'] ?? 0);
        }
        foreach ($pppActive as $p) {
            $totalRx += (int) ($p['bytes-in'] ?? 0);
            $totalTx += (int) ($p['bytes-out'] ?? 0);
        }

        $modemClients = $this->buildModemClients($sessions, $pppActive);

        return response()->json([
            'ping' => $ping,
            'total_rx' => $totalRx,
            'total_tx' => $totalTx,
            'hotspot_count' => count($sessions),
            'ppp_count' => count($pppActive),
            'modem_count' => count($modemClients),
            'interfaces' => collect($interfaces)->map(fn ($i) => [
                'name' => $i['name'] ?? '-',
                'type' => $i['type'] ?? '-',
                'running' => ($i['running'] ?? '') === 'true',
                'tx_byte' => (int) ($i['tx-byte'] ?? 0),
                'rx_byte' => (int) ($i['rx-byte'] ?? 0),
            ])->values(),
            'sessions' => collect($sessions)->map(fn ($s) => [
                'user' => $s['user'] ?? '-',
                'address' => $s['address'] ?? '-',
                'bytes_in' => (int) ($s['bytes-in'] ?? 0),
                'bytes_out' => (int) ($s['bytes-out'] ?? 0),
                'uptime' => $s['uptime'] ?? '-',
            ])->values(),
            'ppp' => collect($pppActive)->map(fn ($p) => [
                'user' => $p['name'] ?? '-',
                'address' => $p['address'] ?? '-',
                'bytes_in' => (int) ($p['bytes-in'] ?? 0),
                'bytes_out' => (int) ($p['bytes-out'] ?? 0),
                'uptime' => $p['uptime'] ?? '-',
            ])->values(),
            'modems' => $modemClients,
        ]);
    }

    private function buildModemClients(array $sessions, array $pppActive): array
    {
        $allUsernames = collect();
        $allIps = collect();

        foreach ($sessions as $s) {
            $allUsernames->push($s['user'] ?? null);
            $allIps->push($s['address'] ?? null);
        }
        foreach ($pppActive as $p) {
            $allUsernames->push($p['name'] ?? null);
            $allIps->push($p['address'] ?? null);
        }

        $allUsernames = $allUsernames->filter()->unique()->values();
        $allIps = $allIps->filter()->unique()->values();

        if ($allUsernames->isEmpty() && $allIps->isEmpty()) {
            return [];
        }

        $customersByPppoe = Customer::whereIn('pppoe_username', $allUsernames)->get()->keyBy('pppoe_username');
        $customersByCode = Customer::whereIn('customer_code', $allUsernames)->get()->keyBy('customer_code');
        $customerIds = $customersByPppoe->pluck('id')->merge($customersByCode->pluck('id'))->unique()->values();

        $ipToCustomer = Customer::query()
            ->whereRaw("INET_ATON(phone) IN ({$allIps->map(fn ($ip) => "'".addslashes($ip)."'")->implode(',')})")
            ->get()
            ->keyBy(fn ($c) => $c->phone);

        $allCustomerIds = $customerIds->merge($ipToCustomer->pluck('id'))->unique()->values();

        if ($allCustomerIds->isEmpty()) {
            return [];
        }

        $onus = Onu::with('oltPort.olt', 'customer')
            ->whereIn('customer_id', $allCustomerIds)
            ->get()
            ->keyBy('customer_id');

        $modemClients = [];

        foreach ($sessions as $s) {
            $user = $s['user'] ?? null;
            $ip = $s['address'] ?? null;

            $customer = $customersByPppoe->get($user)
                ?? $customersByCode->get($user)
                ?? $ipToCustomer->get($ip);

            if (! $customer) {
                continue;
            }

            $onu = $onus->get($customer->id);

            $modemClients[] = [
                'user' => $user,
                'ip' => $ip,
                'customer_name' => $customer->name,
                'customer_code' => $customer->customer_code,
                'modem_sn' => $onu?->serial_number ?? $customer->modem_sn ?? '-',
                'modem_model' => $onu?->model ?? '-',
                'olt_name' => $onu?->oltPort?->olt?->name ?? '-',
                'olt_port' => $onu?->oltPort ? ($onu->oltPort->slot_number.'/'.$onu->oltPort->port_number) : '-',
                'rx_power' => $onu?->rx_power,
                'tx_power' => $onu?->tx_power,
                'distance' => $onu?->distance,
                'onu_status' => $onu?->status ?? 'unknown',
                'last_seen' => $onu?->last_seen_at?->format('d M H:i') ?? '-',
                'source' => 'hotspot',
            ];
        }

        foreach ($pppActive as $p) {
            $user = $p['name'] ?? null;
            $ip = $p['address'] ?? null;

            $customer = $customersByPppoe->get($user)
                ?? $customersByCode->get($user)
                ?? $ipToCustomer->get($ip);

            if (! $customer) {
                continue;
            }

            if (in_array($user, array_column($modemClients, 'user'))) {
                continue;
            }

            $onu = $onus->get($customer->id);

            $modemClients[] = [
                'user' => $user,
                'ip' => $ip,
                'customer_name' => $customer->name,
                'customer_code' => $customer->customer_code,
                'modem_sn' => $onu?->serial_number ?? $customer->modem_sn ?? '-',
                'modem_model' => $onu?->model ?? '-',
                'olt_name' => $onu?->oltPort?->olt?->name ?? '-',
                'olt_port' => $onu?->oltPort ? ($onu->oltPort->slot_number.'/'.$onu->oltPort->port_number) : '-',
                'rx_power' => $onu?->rx_power,
                'tx_power' => $onu?->tx_power,
                'distance' => $onu?->distance,
                'onu_status' => $onu?->status ?? 'unknown',
                'last_seen' => $onu?->last_seen_at?->format('d M H:i') ?? '-',
                'source' => 'pppoe',
            ];
        }

        return $modemClients;
    }
}
