<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\MikrotikService;
use Illuminate\Http\Request;

class MikrotikController extends Controller
{
    protected MikrotikService $mikrotik;

    public function __construct()
    {
        $this->mikrotik = new MikrotikService;
    }

    public function dashboard()
    {
        if (! $this->mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $resource = $this->mikrotik->getSystemResource();
        $identity = $this->mikrotik->getSystemIdentity();
        $interfaces = collect($this->mikrotik->getInterfaces())->take(5);
        $activeHotspot = $this->mikrotik->getActiveHotspotSessions();
        $activePpp = $this->mikrotik->getPppActive();
        $hotspotUsers = $this->mikrotik->getHotspotUsers();

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
        if (! $this->mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $profiles = $this->mikrotik->getHotspotProfiles();

        return view('mikrotik.profiles', compact('profiles'));
    }

    public function storeProfile(Request $request)
    {
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

        $result = $this->mikrotik->addHotspotProfile($validated['name'], $params);

        if ($result['success']) {
            ActivityLog::log('Tambah Profile', 'Menambahkan profile hotspot: '.$validated['name']);

            return redirect()->route('mikrotik.profiles')->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function destroyProfile(string $profileId)
    {
        $result = $this->mikrotik->removeHotspotProfile($profileId);

        if ($result['success']) {
            ActivityLog::log('Hapus Profile', 'Menghapus profile hotspot ID: '.$profileId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    // ── ACTIVE SESSIONS ──

    public function activeSessions()
    {
        if (! $this->mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $hotspot = $this->mikrotik->getActiveHotspotSessions();
        $ppp = $this->mikrotik->getPppActive();

        return view('mikrotik.active', compact('hotspot', 'ppp'));
    }

    public function disconnectHotspot(string $sessionId)
    {
        $result = $this->mikrotik->disconnectHotspotSession($sessionId);

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function disconnectPpp(string $sessionId)
    {
        $result = $this->mikrotik->disconnectPppSession($sessionId);

        if ($result['success']) {
            ActivityLog::log('Disconnect', 'Memutus sesi '.$sessionId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    // ── PPP SECRETS ──

    public function pppSecrets()
    {
        if (! $this->mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $secrets = $this->mikrotik->getPppSecrets();
        $profiles = $this->mikrotik->getPppProfiles();

        return view('mikrotik.ppp', compact('secrets', 'profiles'));
    }

    public function storePppSecret(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'service' => 'required|in:pppoe,pptp,l2tp,ovpn',
            'profile' => 'nullable|string|max:255',
        ]);

        $result = $this->mikrotik->addPppSecret(
            $validated['username'],
            $validated['password'],
            $validated['service'],
            $validated['profile'] ?: null
        );

        if ($result['success']) {
            ActivityLog::log('Tambah PPP', 'Menambahkan PPP secret: '.$validated['username']);

            return redirect()->route('mikrotik.ppp')->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function destroyPppSecret(string $secretId)
    {
        $result = $this->mikrotik->removePppSecret($secretId);

        if ($result['success']) {
            ActivityLog::log('Hapus PPP', 'Menghapus PPP secret ID: '.$secretId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    // ── QUEUES ──

    public function queues()
    {
        if (! $this->mikrotik->isConfigured()) {
            return view('mikrotik.offline');
        }

        $queues = $this->mikrotik->getSimpleQueues();

        return view('mikrotik.queues', compact('queues'));
    }

    public function storeQueue(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target' => 'required|string|max:50',
            'max_limit' => 'required|string|max:50',
        ]);

        $result = $this->mikrotik->addSimpleQueue(
            $validated['name'],
            $validated['target'],
            $validated['max_limit']
        );

        if ($result['success']) {
            ActivityLog::log('Tambah Queue', 'Menambahkan simple queue: '.$validated['name']);

            return redirect()->route('mikrotik.queues')->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function destroyQueue(string $queueId)
    {
        $result = $this->mikrotik->removeSimpleQueue($queueId);

        if ($result['success']) {
            ActivityLog::log('Hapus Queue', 'Menghapus queue ID: '.$queueId);

            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    // ── BACKUP ──

    public function backup(Request $request)
    {
        $name = 'billing-'.now()->format('Ymd-His');

        $result = $this->mikrotik->createBackup($name);

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
        if (! $this->mikrotik->isConfigured()) {
            return back()->with('error', 'MikroTik belum dikonfigurasi.');
        }

        $sessions = $this->mikrotik->getActiveHotspotSessions();
        $pppActive = $this->mikrotik->getPppActive();
        $interfaces = $this->mikrotik->getInterfaces();
        $queues = $this->mikrotik->getSimpleQueues();

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
}
