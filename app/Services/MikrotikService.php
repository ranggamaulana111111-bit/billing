<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    protected ?string $host;

    protected ?string $user;

    protected ?string $pass;

    protected int $port;

    public function __construct(?int $userId = null)
    {
        $this->host = Setting::get('mikrotik_host', null, $userId);
        $this->user = Setting::get('mikrotik_user', null, $userId);
        $this->pass = Setting::get('mikrotik_password', null, $userId);
        $this->port = (int) (Setting::get('mikrotik_port', '80', $userId));
    }

    public function isConfigured(): bool
    {
        return $this->host && $this->user && $this->pass;
    }

    protected function client(): PendingRequest
    {
        return Http::withBasicAuth($this->user, $this->pass)
            ->withoutVerifying()
            ->timeout(30)
            ->throw();
    }

    protected function restUrl(string $path): string
    {
        $scheme = $this->port === 443 ? 'https' : 'http';

        return "{$scheme}://{$this->host}:{$this->port}/rest{$path}";
    }

    protected function safeGet(string $path, array $query = []): array
    {
        try {
            return $this->client()->get($this->restUrl($path), $query)->json();
        } catch (\Exception $e) {
            Log::error("MikroTik GET {$path} gagal: ".$e->getMessage());

            return [];
        }
    }

    // ── SISTEM ──

    public function testConnection(): array
    {
        try {
            $res = $this->client()->get($this->restUrl('/system/resource'))->json();

            return [
                'success' => true,
                'message' => 'Terhubung ke '.($res['board-name'] ?? 'MikroTik'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal: '.$e->getMessage(),
            ];
        }
    }

    public function getSystemResource(): array
    {
        return $this->safeGet('/system/resource');
    }

    public function getSystemIdentity(): array
    {
        $data = $this->safeGet('/system/identity');

        return $data[0] ?? $data;
    }

    public function getSystemHealth(): array
    {
        return $this->safeGet('/system/health');
    }

    // ── INTERFACE / TRAFFIC ──

    public function getInterfaces(): array
    {
        return $this->safeGet('/interface');
    }

    public function getInterfaceTraffic(string $interface): array
    {
        return $this->safeGet('/interface/monitor-traffic', [
            'interface' => $interface,
            'once' => '',
        ]);
    }

    // ── HOTSPOT ──

    public function addHotspotUser(string $username, string $password, ?string $server = null, ?int $limitUptimeHours = null): array
    {
        $server = $server ?: Setting::get('mikrotik_hotspot_server', 'all');

        try {
            $data = [
                'name' => $username,
                'password' => $password,
                'server' => $server,
            ];

            if ($limitUptimeHours) {
                $data['limit-uptime'] = "{$limitUptimeHours}h";
            }

            $this->client()->put($this->restUrl('/ip/hotspot/user'), $data);

            return ['success' => true, 'message' => "User {$username} ditambahkan ke MikroTik"];
        } catch (\Exception $e) {
            Log::error('MikroTik add hotspot user gagal: '.$e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeHotspotUser(string $username): array
    {
        try {
            $users = $this->client()->get($this->restUrl('/ip/hotspot/user'), ['name' => $username])->json();
            if (empty($users)) {
                return ['success' => false, 'message' => "User {$username} tidak ditemukan di MikroTik"];
            }
            $id = $users[0]['.id'] ?? null;
            if ($id) {
                $this->client()->delete($this->restUrl("/ip/hotspot/user/{$id}"));
            }

            return ['success' => true, 'message' => "User {$username} dihapus dari MikroTik"];
        } catch (\Exception $e) {
            Log::error('MikroTik remove hotspot user gagal: '.$e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getHotspotUsers(): array
    {
        return $this->safeGet('/ip/hotspot/user');
    }

    public function getUserByUsername(string $username): ?array
    {
        $users = $this->safeGet('/ip/hotspot/user', ['name' => $username]);

        return $users[0] ?? null;
    }

    public function getUserActiveSessions(string $username): array
    {
        return $this->safeGet('/ip/hotspot/active', ['user' => $username]);
    }

    public function getActiveHotspotSessions(): array
    {
        return $this->safeGet('/ip/hotspot/active');
    }

    public function disconnectHotspotSession(string $sessionId): array
    {
        try {
            $this->client()->delete($this->restUrl("/ip/hotspot/active/{$sessionId}"));

            return ['success' => true, 'message' => 'Sesi berhasil diputuskan'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── HOTSPOT PROFILES ──

    public function getHotspotProfiles(): array
    {
        return $this->safeGet('/ip/hotspot/user/profile');
    }

    public function addHotspotProfile(string $name, array $params = []): array
    {
        try {
            $data = array_merge(['name' => $name], $params);
            $this->client()->put($this->restUrl('/ip/hotspot/user/profile'), $data);

            return ['success' => true, 'message' => "Profile {$name} dibuat"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeHotspotProfile(string $profileId): array
    {
        try {
            $this->client()->delete($this->restUrl("/ip/hotspot/user/profile/{$profileId}"));

            return ['success' => true, 'message' => 'Profile berhasil dihapus'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function setHotspotUserProfile(string $username, string $profile): array
    {
        try {
            $users = $this->client()->get($this->restUrl('/ip/hotspot/user'), ['name' => $username])->json();
            if (empty($users)) {
                return ['success' => false, 'message' => "User {$username} tidak ditemukan"];
            }
            $id = $users[0]['.id'] ?? null;
            if ($id) {
                $this->client()->patch($this->restUrl("/ip/hotspot/user/{$id}"), ['profile' => $profile]);
            }

            return ['success' => true, 'message' => "Profile {$profile} diterapkan ke {$username}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── PPP ──

    public function getPppSecrets(): array
    {
        return $this->safeGet('/ppp/secret');
    }

    public function addPppSecret(string $username, string $password, string $service = 'pppoe', ?string $profile = null): array
    {
        try {
            $data = [
                'name' => $username,
                'password' => $password,
                'service' => $service,
            ];
            if ($profile) {
                $data['profile'] = $profile;
            }
            $this->client()->put($this->restUrl('/ppp/secret'), $data);

            return ['success' => true, 'message' => "PPP secret {$username} ditambahkan"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removePppSecret(string $secretId): array
    {
        try {
            $this->client()->delete($this->restUrl("/ppp/secret/{$secretId}"));

            return ['success' => true, 'message' => 'PPP secret berhasil dihapus'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPppActive(): array
    {
        return $this->safeGet('/ppp/active');
    }

    public function disconnectPppSession(string $sessionId): array
    {
        try {
            $this->client()->delete($this->restUrl("/ppp/active/{$sessionId}"));

            return ['success' => true, 'message' => 'Sesi PPP berhasil diputuskan'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPppProfiles(): array
    {
        return $this->safeGet('/ppp/profile');
    }

    // ── QUEUE / BANDWIDTH ──

    public function getSimpleQueues(): array
    {
        return $this->safeGet('/queue/simple');
    }

    public function addSimpleQueue(string $name, string $target, string $maxLimit, string $parent = 'all'): array
    {
        try {
            $this->client()->put($this->restUrl('/queue/simple'), [
                'name' => $name,
                'target' => $target,
                'max-limit' => $maxLimit,
                'parent' => $parent,
            ]);

            return ['success' => true, 'message' => "Queue {$name} ditambahkan"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeSimpleQueue(string $queueId): array
    {
        try {
            $this->client()->delete($this->restUrl("/queue/simple/{$queueId}"));

            return ['success' => true, 'message' => 'Queue berhasil dihapus'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── BACKUP ──

    public function createBackup(string $name): array
    {
        try {
            $this->client()->post($this->restUrl('/system/backup'), ['name' => $name]);

            return ['success' => true, 'message' => "Backup {$name} berhasil dibuat"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── LOG ──

    public function getLog(int $count = 50): array
    {
        return $this->safeGet('/log', ['.top' => $count]);
    }
}
