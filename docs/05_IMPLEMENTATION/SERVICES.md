# Services — RabegNet ISP Billing System

> 11 Files | `App\Services\` namespace

---

## MidtransService (`app/Services/MidtransService.php`)

Payment gateway integration via Midtrans Snap API.

| Method | Parameter | Return | Description |
|--------|-----------|--------|-------------|
| `__construct` | `?int $tenantId = null` | — | Load server key dari Setting |
| `isConfigured` | — | `bool` | Cek server key terisi |
| `getServerKey` | — | `?string` | Return server key |
| `getSnapToken` | `array $params` | `['success'=>bool, 'token'?=>string, 'message'?=>string]` | Dapatkan Snap token dari Midtrans |
| `handleNotification` | — | `['success'=>bool, 'order_id'?=>string, 'status'?=>string, 'gross_amount'?=>string, 'payment_type'?=>string]` | Proses notifikasi callback Midtrans |

**Flow:**
```
Portal → getSnapToken(params) → Midtrans API → Snap Token → JS Snap popup
Midtrans Callback → handleNotification() → Update invoice → Redirect
```

---

## MikrotikService (`app/Services/MikrotikService.php`)

MikroTik REST API client — 784 baris, service terbesar.

### Constructor

| Parameter | Deskripsi |
|-----------|-----------|
| `MikrotikRouter\|int\|null $router` | Router spesifik atau `null` (load dari Setting) |

### Internal

| Method | Deskripsi |
|--------|-----------|
| `isConfigured()` | Cek host/user/pass tersedia |
| `client()` | `Http::withBasicAuth()->withoutVerifying()->timeout(30)` |
| `safeGet(path)` | GET request dengan try-catch, return `[]` jika gagal |

### Fitur — SISTEM

| Method | REST Path | Deskripsi |
|--------|-----------|-----------|
| `testConnection()` | `/system/resource` | Test koneksi, return board name |
| `getSystemResource()` | `/system/resource` | Resource info |
| `getSystemIdentity()` | `/system/identity` | Identity |
| `getSystemHealth()` | `/system/health` | Health monitoring |
| `getLatency()` | `/system/resource` | Round-trip latency (ms) |

### Fitur — INTERFACE / TRAFFIC

| Method | REST Path | Deskripsi |
|--------|-----------|-----------|
| `getInterfaces()` | `/interface` | Daftar interface |
| `getInterfaceTraffic(interface)` | `/interface/monitor-traffic` | Traffic real-time |
| `getSimpleQueues()` | `/queue/simple` | Simple queues |
| `addSimpleQueue(name, target, maxLimit)` | `/queue/simple` | Tambah queue |
| `removeSimpleQueue(queueId)` | `/queue/simple/{id}` | Hapus queue |

### Fitur — HOTSPOT

| Method | REST Path | Deskripsi |
|--------|-----------|-----------|
| `getHotspotUsers()` | `/ip/hotspot/user` | Semua user |
| `addHotspotUser(username, password, server?, limitUptime?)` | `/ip/hotspot/user` | Tambah user |
| `removeHotspotUser(username)` | `/ip/hotspot/user` | Hapus user by username lookup |
| `getUserByUsername(username)` | `/ip/hotspot/user` | Cari user |
| `getUserActiveSessions(username)` | `/ip/hotspot/active` | Sesi aktif user |
| `getActiveHotspotSessions()` | `/ip/hotspot/active` | Semua sesi aktif |
| `disconnectHotspotSession(sessionId)` | `/ip/hotspot/active/{id}` | Putus sesi |
| `getHotspotProfiles()` | `/ip/hotspot/user/profile` | Profiles |
| `addHotspotProfile(name, params)` | `/ip/hotspot/user/profile` | Tambah profile |
| `removeHotspotProfile(profileId)` | `/ip/hotspot/user/profile/{id}` | Hapus profile |
| `setHotspotUserProfile(username, profile)` | `/ip/hotspot/user/{id}` | Set profile user |

### Fitur — PPP

| Method | REST Path | Deskripsi |
|--------|-----------|-----------|
| `getPppSecrets()` | `/ppp/secret` | Semua PPP secrets |
| `getPppSecretByUsername(username)` | `/ppp/secret` | Cari by username |
| `addPppSecret(username, password, service, profile)` | `/ppp/secret` | Tambah secret |
| `removePppSecret(secretId)` | `/ppp/secret/{id}` | Hapus |
| `disablePppSecret(username)` | `/ppp/secret/{id}` | Disable |
| `enablePppSecret(username)` | `/ppp/secret/{id}` | Enable |
| `setPppSecretProfile(username, profile)` | `/ppp/secret/{id}` | Ganti profile |
| `setPppSecretAddressList(username, addressList)` | `/ppp/secret/{id}` | Set address-list |
| `getPppActive()` | `/ppp/active` | Sesi PPP aktif |
| `getActivePppSessionByUsername(username)` | `/ppp/active` | Cari sesi aktif |
| `disconnectPppSession(sessionId)` | `/ppp/active/{id}` | Putus sesi |
| `getPppProfiles()` | `/ppp/profile` | PPP profiles |
| `addPppProfile(name, params)` | `/ppp/profile` | Tambah/update profile |
| `updateProfile(name, params)` | `/ppp/profile/{id}` | Update profile |

### Fitur — FIREWALL (Isolir Subsystem)

| Method | REST Path | Deskripsi |
|--------|-----------|-----------|
| `addIpToAddressList(ip, list)` | `/ip/firewall/address-list` | Tambah IP ke address-list (skip jika sudah ada) |
| `removeIpFromAddressList(ip, list)` | `/ip/firewall/address-list` | Hapus IP dari address-list |
| `addHttpRedirect(clientIp, redirectIp, port)` | `/ip/firewall/nat` | DST-NAT redirect individual |
| `removeHttpRedirect(clientIp)` | `/ip/firewall/nat` | Hapus redirect individual |
| `addHttpRedirectForAddressList(list, redirectIp, port)` | `/ip/firewall/nat` | Redirect untuk address-list |
| `removeHttpRedirectForAddressList(list)` | `/ip/firewall/nat` | Hapus redirect address-list |
| `addFilterDropForAddressList(list, exceptIp?)` | `/ip/firewall/filter` | DROP + ACCEPT rules |
| `removeIsolirFilterRules()` | `/ip/firewall/filter` | Hapus semua filter isolir |
| `addWebProxyNatRedirect(list, proxyPort)` | `/ip/firewall/nat` | Web proxy redirect |
| `removeWebProxyNatRedirect()` | `/ip/firewall/nat` | Hapus proxy redirect |
| `enableWebProxy(port)` | `/ip/proxy/set` | Enable web proxy |
| `addWebProxyRedirectForAddressList(list, url)` | `/ip/proxy/access` | Redirect via proxy |
| `removeWebProxyRedirectForAddressList()` | `/ip/proxy/access` | Hapus proxy redirect |

### Fitur — LAINNYA

| Method | REST Path | Deskripsi |
|--------|-----------|-----------|
| `createBackup(name)` | `/system/backup` | Backup konfigurasi |
| `getLog(count)` | `/log` | System log |

---

## OLT Driver Pattern (`app/Services/Olt/`)

### Interface: `OltConnector`

| Method | Parameter | Return | Deskripsi |
|--------|-----------|--------|-----------|
| `connect` | `string $host, int $port, string $username, string $password` | `bool` | SSH ke OLT |
| `disconnect` | — | `void` | Tutup koneksi |
| `testConnection` | — | `array` | Test koneksi |
| `getSystemInfo` | — | `array` | Info sistem |
| `getOnuList` | `int $slot, int $port` | `array` | Daftar ONU |
| `getOnuDetail` | `string $onuId` | `array` | Detail ONU |
| `provisionOnu` | `array $data` | `array` | Provision ONU baru |
| `removeOnu` | `string $onuId` | `array` | Hapus ONU |
| `rebootOnu` | `string $onuId` | `array` | Reboot ONU |
| `getPortStatus` | `int $slot, int $port` | `array` | Status port |
| `getOpticalPower` | `string $onuId` | `array` | Daya optik |

### Implementasi (Drivers)

| Brand | Class | Baris | SSH Library |
|-------|-------|-------|-------------|
| Huawei | `HuaweiConnector` | 255 | phpseclib3 (SSH2) |
| ZTE | `ZteConnector` | 205 | phpseclib3 (SSH2) |
| FiberHome | `FiberHomeConnector` | 185 | phpseclib3 (SSH2) |
| C-Data | `CDataConnector` | 220 | phpseclib3 (SSH2) |

### Factory

**Class:** `OltConnectorFactory`

| Method | Parameter | Return | Deskripsi |
|--------|-----------|--------|-----------|
| `make` | `string $brand, ?Olt $olt` | `OltConnector` | Resolve + decorator |
| `makeRaw` | `string $brand` | `OltConnector` | Tanpa decorator |

**Decorator logic:**
```
make(brand, olt)
  ├── olt.hasJumpHost()
  │   ├── jump_host == mikrotik_host → MikrotikSshProxyConnector
  │   └── else → JumpHostConnector(innerDriver)
  └── else → raw driver
```

### Decorators

**JumpHostConnector** (71 baris)
- Wraps inner OltConnector
- `connect()`: create SSH tunnel via `SshTunnel` → `inner->connect('127.0.0.1', localPort, ...)`
- Delegates all other methods to inner connector

**MikrotikSshProxyConnector** (412 baris)
- Connects via MikroTik `tool/ssh` REST API command
- Uses MikroTik REST API as SSH proxy
- Parses CLI output from MikroTik

### Helper: SshTunnel (204 baris)

SSH tunnel via `proc_open`:
- Windows: `ssh` or `plink` with sshpass
- Linux/Mac: `ssh -L` with `sshpass`
- Find free local port, forward to target host:port
- Cleanup on `close()` / destructor

**Connection chains:**
```
Direct:      Client → HuaweiConnector (SSH)
Jump Host:   Client → JumpHost → HuaweiConnector
MikroTik:    Client → MikroTik REST (tool/ssh) → OLT CLI
```
