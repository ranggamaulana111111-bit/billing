<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\MikrotikRouter;
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

    // ── QUEUES ──

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
            $validated['target'],
            $validated['max_limit']
        );

        if ($result['success']) {
            ActivityLog::log('Tambah Queue', 'Menambahkan simple queue: '.$validated['name']);

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

        $sessions = $mikrotik->getActiveHotspotSessions();
        $pppActive = $mikrotik->getPppActive();
        $interfaces = $mikrotik->getInterfaces();
        $queues = $mikrotik->getSimpleQueues();

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

        return view('mikrotik.monitoring', compact(
            'sessions', 'pppActive', 'interfaces', 'queues',
            'totalBandwidthRx', 'totalBandwidthTx'
        ));
    }

    // ── LIVE DATA API ──

    public function liveData()
    {
        $mikrotik = $this->resolveMikrotik();

        if (! $mikrotik->isConfigured()) {
            return response()->json(['error' => 'MikroTik not configured'], 400);
        }

        $interfaces = $mikrotik->getInterfaces();
        $sessions = $mikrotik->getActiveHotspotSessions();
        $pppActive = $mikrotik->getPppActive();
        $ping = $mikrotik->getLatency();

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

        return response()->json([
            'ping' => $ping,
            'total_rx' => $totalRx,
            'total_tx' => $totalTx,
            'hotspot_count' => count($sessions),
            'ppp_count' => count($pppActive),
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
        ]);
    }
}
