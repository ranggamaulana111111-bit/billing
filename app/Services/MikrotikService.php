<?php

namespace App\Services;

use App\Models\MikrotikRouter;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MikrotikService
{
    protected ?string $host;

    protected ?string $localIp = null;

    protected ?string $activeHost = null;

    protected ?int $localPort = null;

    protected bool $localMode = false;

    protected ?string $user;

    protected ?string $pass;

    protected int $port;

    protected ?string $hotspotServer;

    protected ?MikrotikSshService $ssh = null;

    protected ?string $lastError = null;

    public function __construct(MikrotikRouter|int|null $router = null)
    {
        if ($router instanceof MikrotikRouter) {
            $this->host = $router->host;
            $this->localIp = $router->local_ip;
            $this->localPort = $router->local_port ? (int) $router->local_port : null;
            $this->localMode = ($router->connection_mode ?? 'tunnel') === 'local_ip';
            $this->user = $router->username;
            $this->pass = $router->password;
            $this->port = (int) $router->port;
            $this->hotspotServer = $router->hotspot_server;
            if ($router->ssh_port) {
                $this->initSsh($router);
            }
        } elseif ($router !== null) {
            $router = MikrotikRouter::find($router);
            if ($router) {
                $this->host = $router->host;
                $this->localIp = $router->local_ip;
                $this->localPort = $router->local_port ? (int) $router->local_port : null;
                $this->localMode = ($router->connection_mode ?? 'tunnel') === 'local_ip';
                $this->user = $router->username;
                $this->pass = $router->password;
                $this->port = (int) $router->port;
                $this->hotspotServer = $router->hotspot_server;
                if ($router->ssh_port) {
                    $this->initSsh($router);
                }
            }
        } else {
            $router = MikrotikRouter::where('is_active', true)->first();
            if ($router) {
                $this->host = $router->host;
                $this->localIp = $router->local_ip;
                $this->localPort = $router->local_port ? (int) $router->local_port : null;
                $this->localMode = ($router->connection_mode ?? 'tunnel') === 'local_ip';
                $this->user = $router->username;
                $this->pass = $router->password;
                $this->port = (int) $router->port;
                $this->hotspotServer = $router->hotspot_server;
            } else {
                $this->host = Setting::get('mikrotik_host');
                $this->user = Setting::get('mikrotik_user');
                $this->pass = Setting::get('mikrotik_password');
                $this->port = (int) (Setting::get('mikrotik_port', '80'));
                $this->hotspotServer = Setting::get('mikrotik_hotspot_server', 'all');
            }
        }
    }

    public function isConfigured(): bool
    {
        return $this->host && $this->user && $this->pass;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    protected function client(): PendingRequest
    {
        return Http::withBasicAuth($this->user, $this->pass)
            ->withoutVerifying()
            ->timeout(10)
            ->throw();
    }

    protected function restUrl(string $path): string
    {
        $isLocal = $this->localMode || $this->activeHost !== null;
        $port = $isLocal ? ($this->localPort ?? 80) : $this->port;
        $scheme = $port === 443 ? 'https' : 'http';
        $host = $this->activeHost ?? $this->host;

        return "{$scheme}://{$host}:{$port}/rest{$path}";
    }

    protected function switchToLocalIp(): bool
    {
        if (! $this->localIp || $this->localIp === $this->host || $this->activeHost === $this->localIp) {
            return false;
        }

        $this->activeHost = $this->localIp;
        Log::warning('MikroTik fallback ke IP lokal: '.$this->localIp);

        return true;
    }

    protected function initSsh(MikrotikRouter $router): void
    {
        try {
            $this->ssh = new MikrotikSshService($router);
        } catch (\Exception $e) {
            Log::warning('SSH init gagal: '.$e->getMessage());
        }
    }

    protected function safeGet(string $path, array $query = []): array
    {
        $this->lastError = null;

        if ($this->ssh) {
            try {
                return $this->sshSafeGet($path, $query);
            } catch (\Exception $e) {
                $this->lastError = 'SSH GET '.$path.' gagal: '.$e->getMessage();
                Log::error($this->lastError);

                return [];
            }
        }

        try {
            return $this->client()->get($this->restUrl($path), $query)->json();
        } catch (\Exception $e) {
            if ($this->switchToLocalIp()) {
                return $this->retryGet($path, $query);
            }

            $this->lastError = 'MikroTik GET '.$path.' gagal: '.$e->getMessage();
            Log::error($this->lastError);

            return [];
        }
    }

    protected function retryGet(string $path, array $query): array
    {
        try {
            return $this->client()->get($this->restUrl($path), $query)->json();
        } catch (\Exception $e) {
            $this->lastError = 'MikroTik GET '.$path.' gagal (fallback IP lokal): '.$e->getMessage();
            Log::error($this->lastError);

            return [];
        }
    }

    protected function sshSafeGet(string $path, array $query): array
    {
        // Special case: interface monitor-traffic
        if ($path === '/interface/monitor-traffic' && isset($query['interface'])) {
            return $this->ssh->getInterfaceTraffic($query['interface']);
        }

        // /system/identity: REST returns [{...}], SSH returns {...} — wrap for compatibility
        if ($path === '/system/identity') {
            $id = $this->ssh->getSystemIdentity();

            return [$id];
        }

        $map = [
            '/system/resource' => 'getSystemResource',
            '/system/health' => 'getSystemHealth',
            '/interface' => 'getInterfaces',
            '/ip/hotspot/active' => 'getActiveHotspotSessions',
            '/ip/hotspot/user' => 'getHotspotUsers',
            '/ip/hotspot' => 'getHotspotServers',
            '/ip/hotspot/user/profile' => 'getHotspotProfiles',
            '/ppp/active' => 'getPppActive',
            '/ppp/secret' => 'getPppSecrets',
            '/ppp/profile' => 'getPppProfiles',
            '/queue/simple' => 'getSimpleQueues',
        ];

        $method = $map[$path] ?? null;

        if ($method && method_exists($this->ssh, $method)) {
            return $this->ssh->$method();
        }

        if ($path === '/log') {
            return $this->ssh->getLog($query['.top'] ?? 50);
        }

        throw new \Exception("SSH fallback not available for {$path}");
    }

    // ── SISTEM ──

    public function testConnection(): array
    {
        if ($this->ssh) {
            try {
                return $this->ssh->testConnection();
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Gagal SSH: '.$e->getMessage(),
                ];
            }
        }

        try {
            $res = $this->client()->get($this->restUrl('/system/resource'))->json();

            return [
                'success' => true,
                'message' => 'Terhubung ke '.($res['board-name'] ?? 'MikroTik'),
            ];
        } catch (\Exception $e) {
            if ($this->switchToLocalIp()) {
                return $this->retryTestConnection();
            }

            return [
                'success' => false,
                'message' => 'Gagal: '.$e->getMessage(),
            ];
        }
    }

    protected function retryTestConnection(): array
    {
        try {
            $res = $this->client()->get($this->restUrl('/system/resource'))->json();

            return [
                'success' => true,
                'message' => 'Terhubung ke '.($res['board-name'] ?? 'MikroTik').' (via IP lokal)',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal via IP lokal: '.$e->getMessage(),
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

    public function addHotspotUser(string $username, string $password, ?string $server = null, ?int $limitUptimeHours = null, ?string $profile = null): array
    {
        $server = $server ?: $this->hotspotServer ?: Setting::get('mikrotik_hotspot_server', 'all');

        try {
            $data = [
                'name' => $username,
                'password' => $password,
                'server' => $server,
            ];

            if ($limitUptimeHours) {
                $data['limit-uptime'] = "{$limitUptimeHours}h";
            }

            if ($profile) {
                $data['profile'] = $profile;
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

    public function removeHotspotUserById(string $userId): array
    {
        try {
            $this->client()->delete($this->restUrl("/ip/hotspot/user/{$userId}"));

            return ['success' => true, 'message' => 'User berhasil dihapus dari MikroTik'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateHotspotUser(string $userId, array $params): array
    {
        try {
            $this->client()->patch($this->restUrl("/ip/hotspot/user/{$userId}"), $params);

            return ['success' => true, 'message' => 'User berhasil diperbarui'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function setHotspotUserDisabled(string $username, bool $disabled = true): array
    {
        try {
            $user = $this->getUserByUsername($username);
            if (! $user) {
                return ['success' => false, 'message' => "Hotspot user {$username} tidak ditemukan"];
            }

            $id = $user['.id'] ?? null;
            if (! $id) {
                return ['success' => false, 'message' => 'ID hotspot user tidak ditemukan'];
            }

            $this->client()->patch($this->restUrl("/ip/hotspot/user/{$id}"), [
                'disabled' => $disabled ? 'yes' : 'no',
            ]);

            $label = $disabled ? 'dinonaktifkan' : 'diaktifkan';

            return ['success' => true, 'message' => "Hotspot user {$username} {$label}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function disableHotspotUser(string $username): array
    {
        return $this->setHotspotUserDisabled($username, true);
    }

    public function enableHotspotUser(string $username): array
    {
        return $this->setHotspotUserDisabled($username, false);
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

    public function getHotspotServers(): array
    {
        return $this->safeGet('/ip/hotspot');
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

    public function updateHotspotProfile(string $profileId, array $params): array
    {
        try {
            $this->client()->patch($this->restUrl("/ip/hotspot/user/profile/{$profileId}"), $params);

            return ['success' => true, 'message' => 'Profile berhasil diperbarui'];
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

    public function getPppSecretByUsername(string $username): ?array
    {
        $secrets = $this->safeGet('/ppp/secret', ['name' => $username]);

        return $secrets[0] ?? null;
    }

    public function getPppSecretProfile(string $username): ?string
    {
        $secret = $this->getPppSecretByUsername($username);

        return $secret['profile'] ?? null;
    }

    public function setPppSecretDisabled(string $username, bool $disabled = true): array
    {
        try {
            $secret = $this->getPppSecretByUsername($username);
            if (! $secret) {
                return ['success' => false, 'message' => "PPP secret {$username} tidak ditemukan"];
            }

            $id = $secret['.id'] ?? null;
            if (! $id) {
                return ['success' => false, 'message' => 'ID PPP secret tidak ditemukan'];
            }

            $this->client()->patch($this->restUrl("/ppp/secret/{$id}"), [
                'disabled' => $disabled ? 'yes' : 'no',
            ]);

            $label = $disabled ? 'dinonaktifkan' : 'diaktifkan';

            return ['success' => true, 'message' => "PPP secret {$username} {$label}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function disablePppSecret(string $username): array
    {
        return $this->setPppSecretDisabled($username, true);
    }

    public function enablePppSecret(string $username): array
    {
        return $this->setPppSecretDisabled($username, false);
    }

    public function getPppActive(): array
    {
        return $this->safeGet('/ppp/active');
    }

    /**
     * Ambil ringkasan jumlah user PPPoE & hotspot (online/offline) dari router.
     *
     * @return array{pppoe_online: int, pppoe_offline: int, hotspot_online: int, hotspot_offline: int}
     */
    public function getUserStats(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $pppActive = $this->getPppActive();
        $pppSecrets = $this->getPppSecrets();
        $hotspotActive = $this->getActiveHotspotSessions();
        $hotspotUsers = $this->getHotspotUsers();

        $pppoeOnline = count($pppActive);
        $pppoeTotal = count($pppSecrets);
        $hotspotOnline = count($hotspotActive);
        $hotspotTotal = count($hotspotUsers);

        return [
            'pppoe_online' => $pppoeOnline,
            'pppoe_offline' => max($pppoeTotal - $pppoeOnline, 0),
            'hotspot_online' => $hotspotOnline,
            'hotspot_offline' => max($hotspotTotal - $hotspotOnline, 0),
        ];
    }

    public function getActivePppSessionByUsername(string $username): ?array
    {
        $active = $this->safeGet('/ppp/active', ['name' => $username]);

        return $active[0] ?? null;
    }

    public function addHttpRedirect(string $clientIp, string $redirectIp, int $redirectPort = 80): array
    {
        try {
            $rule = [
                'chain' => 'dstnat',
                'src-address' => $clientIp,
                'protocol' => 'tcp',
                'dst-port' => '80',
                'action' => 'dst-nat',
                'to-addresses' => $redirectIp,
                'to-ports' => (string) $redirectPort,
                'comment' => 'isolir-redirect-'.$clientIp,
            ];

            $this->client()->put($this->restUrl('/ip/firewall/nat'), $rule);

            return ['success' => true, 'message' => "HTTP redirect added for {$clientIp}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeHttpRedirect(string $clientIp): array
    {
        try {
            $rules = $this->safeGet('/ip/firewall/nat', ['comment' => 'isolir-redirect-'.$clientIp]);
            foreach ($rules as $rule) {
                $id = $rule['.id'] ?? null;
                if ($id) {
                    $this->client()->delete($this->restUrl("/ip/firewall/nat/{$id}"));
                }
            }

            return ['success' => true, 'message' => "HTTP redirect removed for {$clientIp}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addHttpRedirectForAddressList(string $addressList, string $redirectIp, int $redirectPort = 80, ?int $toPort = null): array
    {
        $toPort = $toPort ?? $redirectPort;

        try {
            $rule = [
                'chain' => 'dstnat',
                'src-address-list' => $addressList,
                'protocol' => 'tcp',
                'dst-port' => (string) $redirectPort,
                'action' => 'dst-nat',
                'to-addresses' => $redirectIp,
                'to-ports' => (string) $toPort,
                'comment' => 'isolir-http-redirect-'.$redirectPort,
            ];

            $this->client()->put($this->restUrl('/ip/firewall/nat'), $rule);

            return ['success' => true, 'message' => "HTTP redirect rule added for address-list {$addressList}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeHttpRedirectForAddressList(string $addressList): array
    {
        try {
            $rules = $this->safeGet('/ip/firewall/nat', ['src-address-list' => $addressList]);
            foreach ($rules as $rule) {
                $comment = $rule['comment'] ?? '';
                if (str_starts_with($comment, 'isolir-http-redirect-')) {
                    $id = $rule['.id'] ?? null;
                    if ($id) {
                        $this->client()->delete($this->restUrl("/ip/firewall/nat/{$id}"));
                    }
                }
            }

            return ['success' => true, 'message' => "HTTP redirect removed for address-list {$addressList}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addPppProfile(string $name, array $params = []): array
    {
        try {
            $data = array_merge(['name' => $name], $params);
            $this->client()->put($this->restUrl('/ppp/profile'), $data);

            return ['success' => true, 'message' => "PPP profile {$name} created/updated"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function resolveProfileName(string $name): ?string
    {
        $profiles = $this->getPppProfiles();
        $exists = collect($profiles)->firstWhere('name', $name);

        return $exists['name'] ?? null;
    }

    public function updateProfileById(string $profileId, array $params): array
    {
        try {
            $this->client()->patch($this->restUrl("/ppp/profile/{$profileId}"), $params);

            return ['success' => true, 'message' => 'PPP profile updated'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateProfile(string $name, array $params): array
    {
        try {
            $profiles = $this->safeGet('/ppp/profile', ['name' => $name]);
            if (empty($profiles)) {
                return ['success' => false, 'message' => "Profile {$name} not found"];
            }

            $id = $profiles[0]['.id'] ?? null;
            if (! $id) {
                return ['success' => false, 'message' => 'Profile ID not found'];
            }

            $this->client()->patch($this->restUrl("/ppp/profile/{$id}"), $params);

            return ['success' => true, 'message' => "Profile {$name} updated"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removePppProfile(string $profileId): array
    {
        try {
            $this->client()->delete($this->restUrl("/ppp/profile/{$profileId}"));

            return ['success' => true, 'message' => 'PPP profile berhasil dihapus'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── FIREWALL ADDRESS LIST ──

    public function addIpToAddressList(string $ip, string $list): array
    {
        try {
            $existing = $this->safeGet('/ip/firewall/address-list', ['address' => $ip, 'list' => $list]);
            if (! empty($existing)) {
                return ['success' => true, 'message' => "IP {$ip} already in {$list}"];
            }

            $this->client()->put($this->restUrl('/ip/firewall/address-list'), [
                'address' => $ip,
                'list' => $list,
            ]);

            return ['success' => true, 'message' => "IP {$ip} added to {$list}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeIpFromAddressList(string $ip, string $list): array
    {
        try {
            $entries = $this->safeGet('/ip/firewall/address-list', ['address' => $ip, 'list' => $list]);
            foreach ($entries as $entry) {
                $id = $entry['.id'] ?? null;
                if ($id) {
                    $this->client()->delete($this->restUrl("/ip/firewall/address-list/{$id}"));
                }
            }

            return ['success' => true, 'message' => "IP {$ip} removed from {$list}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── FIREWALL FILTER ──

    public function addFilterDropForAddressList(string $addressList, ?string $exceptIp = null): array
    {
        try {
            // ACCEPT rule for HTTP/HTTPS traffic going to the redirect server (must come before DROP)
            if ($exceptIp) {
                // Remove old HTTP-only accept rule if exists
                $oldAccept = $this->safeGet('/ip/firewall/filter', [
                    'comment' => 'ISOLIR-ACCEPT-HTTP',
                ]);
                foreach ($oldAccept as $r) {
                    if (isset($r['.id'])) {
                        $this->client()->delete($this->restUrl('/ip/firewall/filter/'.$r['.id']));
                    }
                }

                $acceptExists = $this->safeGet('/ip/firewall/filter', [
                    'comment' => 'ISOLIR-ACCEPT-REDIRECT',
                ]);
                if (empty($acceptExists)) {
                    $this->client()->put($this->restUrl('/ip/firewall/filter'), [
                        'chain' => 'forward',
                        'src-address-list' => $addressList,
                        'dst-address' => $exceptIp,
                        'protocol' => 'tcp',
                        'action' => 'accept',
                        'comment' => 'ISOLIR-ACCEPT-REDIRECT',
                    ]);
                }
            }

            // DROP rule for all other traffic
            $dropExists = $this->safeGet('/ip/firewall/filter', [
                'comment' => 'BLOCK-ISOLIR',
            ]);
            if (empty($dropExists)) {
                $this->client()->put($this->restUrl('/ip/firewall/filter'), [
                    'chain' => 'forward',
                    'src-address-list' => $addressList,
                    'action' => 'drop',
                    'comment' => 'BLOCK-ISOLIR',
                ]);
            }

            $msg = "Filter rules for {$addressList}";
            if ($exceptIp) {
                $msg .= " (HTTP/HTTPS allowed to {$exceptIp})";
            }

            return ['success' => true, 'message' => $msg];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeIsolirFilterRules(): array
    {
        try {
            foreach (['BLOCK-ISOLIR', 'ISOLIR-ACCEPT-HTTP'] as $comment) {
                $rules = $this->safeGet('/ip/firewall/filter', ['comment' => $comment]);
                foreach ($rules as $rule) {
                    $id = $rule['.id'] ?? null;
                    if ($id) {
                        $this->client()->delete($this->restUrl("/ip/firewall/filter/{$id}"));
                    }
                }
            }

            return ['success' => true, 'message' => 'Isolir filter rules removed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
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

    public function setPppSecretProfile(string $username, string $profile): array
    {
        return $this->patchPppSecret($username, ['profile' => $profile]);
    }

    public function setPppSecretAddressList(string $username, ?string $addressList): array
    {
        $data = [];
        if ($addressList) {
            $data['address-list'] = $addressList;
        } else {
            $data['address-list'] = '';
        }

        return $this->patchPppSecret($username, $data);
    }

    private function patchPppSecret(string $username, array $data): array
    {
        try {
            $secret = $this->getPppSecretByUsername($username);
            if (! $secret) {
                return ['success' => false, 'message' => "PPP secret {$username} tidak ditemukan"];
            }

            $id = $secret['.id'] ?? null;
            if (! $id) {
                return ['success' => false, 'message' => 'ID PPP secret tidak ditemukan'];
            }

            $this->client()->patch($this->restUrl("/ppp/secret/{$id}"), $data);

            return ['success' => true, 'message' => "PPP secret {$username} updated"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getPppProfiles(): array
    {
        return $this->safeGet('/ppp/profile');
    }

    // ── WEB PROXY ──

    public function enableWebProxy(int $port = 8080): array
    {
        try {
            $this->client()->patch($this->restUrl('/ip/proxy/set'), [
                'enabled' => 'yes',
                'port' => $port,
            ]);

            return ['success' => true, 'message' => "Web proxy enabled on port {$port}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addWebProxyRedirectForAddressList(string $addressList, string $redirectUrl): array
    {
        try {
            $this->client()->put($this->restUrl('/ip/proxy/access'), [
                'src-address' => $addressList,
                'dst-host' => '*',
                'action' => 'deny',
                'redirect-to' => $redirectUrl,
            ]);

            return ['success' => true, 'message' => "Web proxy redirect added for {$addressList}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeWebProxyRedirectForAddressList(): array
    {
        try {
            $entries = $this->safeGet('/ip/proxy/access', ['action' => 'deny']);
            foreach ($entries as $entry) {
                $id = $entry['.id'] ?? null;
                if ($id) {
                    $this->client()->delete($this->restUrl("/ip/proxy/access/{$id}"));
                }
            }

            return ['success' => true, 'message' => 'Web proxy redirect removed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addWebProxyNatRedirect(string $addressList, int $proxyPort = 8080): array
    {
        try {
            $this->client()->put($this->restUrl('/ip/firewall/nat'), [
                'chain' => 'dstnat',
                'src-address-list' => $addressList,
                'protocol' => 'tcp',
                'dst-port' => '80',
                'action' => 'redirect',
                'to-ports' => (string) $proxyPort,
                'comment' => 'isolir-proxy-redirect',
            ]);

            return ['success' => true, 'message' => "Web proxy NAT redirect added for {$addressList}"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function removeWebProxyNatRedirect(): array
    {
        try {
            $rules = $this->safeGet('/ip/firewall/nat', ['comment' => 'isolir-proxy-redirect']);
            foreach ($rules as $rule) {
                $id = $rule['.id'] ?? null;
                if ($id) {
                    $this->client()->delete($this->restUrl("/ip/firewall/nat/{$id}"));
                }
            }

            return ['success' => true, 'message' => 'Web proxy NAT redirect removed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── QUEUE / BANDWIDTH ──

    public function getSimpleQueues(): array
    {
        return $this->safeGet('/queue/simple');
    }

    public function addSimpleQueue(string $name, string $maxLimit, string $target = ''): array
    {
        try {
            $data = [
                'name' => $name,
                'max-limit' => $maxLimit,
            ];

            if ($target) {
                $data['target'] = $target;
            }

            $this->client()->put($this->restUrl('/queue/simple'), $data);

            return ['success' => true, 'message' => "Queue {$name} ditambahkan"];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function updateSimpleQueue(string $queueId, string $name, string $maxLimit, string $target = ''): array
    {
        try {
            $data = [
                'name' => $name,
                'max-limit' => $maxLimit,
            ];

            if ($target) {
                $data['target'] = $target;
            }

            $this->client()->patch($this->restUrl("/queue/simple/{$queueId}"), $data);

            return ['success' => true, 'message' => "Queue {$name} diperbarui"];
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

    // ── LATENCY / PING ──

    public function getLatency(): ?float
    {
        if (! $this->isConfigured()) {
            return null;
        }

        if ($this->ssh) {
            try {
                return $this->ssh->getLatency();
            } catch (\Exception $e) {
                return null;
            }
        }

        try {
            $start = microtime(true);
            $this->safeGet('/system/resource');

            return round((microtime(true) - $start) * 1000, 1);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ── LOG ──

    public function getLog(int $count = 50): array
    {
        return $this->safeGet('/log', ['.top' => $count]);
    }
}
