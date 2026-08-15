# Architecture Patterns — ALKONEK ISP Billing System v1.2

---

## 1. OLT Driver Pattern

Memungkinkan dukungan multi-brand OLT dengan interface yang seragam.

### Interface

```
OltConnector (Interface)
├── connect()
├── disconnect()
├── testConnection()
├── getSystemInfo()
├── getOnuList(slot, port)
├── getOnuDetail(interfaceId, onuId)
├── provisionOnu(...)
├── removeOnu(interfaceId, onuId)
├── rebootOnu(interfaceId, onuId)
├── getPortStatus()
└── getOpticalPower()
```

### Implementasi per Brand

| Brand | Class | CLI Pattern |
|-------|-------|-------------|
| Huawei | `HuaweiConnector` | `system-view` → `display ont info {slot} {port}` |
| ZTE | `ZteConnector` | `enable` → `configure terminal` → `show onu unquiet...` |
| FiberHome | `FiberHomeConnector` | `show ont list slot {s} port {p}` |
| C-Data | `CDataConnector` | `enable` → `config` → `show ont info slot {s} port {p}` |
| Chinese OLT | `ChineseOltConnector` | CLI pattern vendor China (parser: `ChineseOltParser`) |
| Global | `GlobalConnector` | CLI generik multi-vendor |
| Hioso | `HiosoConnector` | CLI Hioso |
| HSGQ | `HsgqConnector` | CLI HSGQ |
| VSOL | `VsolConnector` | CLI VSOL |

### Factory

```php
App\Services\Olt\Factory\OltConnectorFactory::make($brand, $olt)
// return driver sesuai brand + optional decorator
// SshTunnel → jump host / proxy chain
```

### Decorator Pattern Wrappers

| Wrapper | Fungsi |
|---------|--------|
| `JumpHostConnector` | SSH tunnel via server perantara |
| `MikrotikSshProxyConnector` | SSH via MikroTik REST API `tool/ssh` |

### Decorator Chain

```
Connection Chain:
  Client → [JumpHostConnector] → [MikrotikSshProxyConnector] → HuaweiConnector
         → SSH Tunnel    → MikroTik tool/ssh → OLT CLI
```

---

## 2. Multi-Tenancy (BelongsToTenant)

### Cara Kerja

```php
trait BelongsToTenant
{
    // Global Scope: WHERE tenant_id = ?
    // Auto-fill tenant_id saat create
    // Methods: forTenant($id), allTenants()
}
```

### Model yang Menggunakan

30 model menggunakan `BelongsToTenant` — di antaranya:
Customer, Invoice, Payment, Package, Olt, OltPort, Onu, Odc, Odp, OdpRoute, OdpPoint, Voucher, VoucherProfile, VoucherTemplate, Setting, ActivityLog, Incident, NetworkMetric, InventoryItem, dll.

### Model yang TIDAK Menggunakan (⚠️)

- `OdcPort` — potensi data leak
- `OdpPort` — potensi data leak
- `User`, `Tenant` — model root/infra, bukan data tenant
- `IncidentNotification`, `InterfaceChangeLog`, `MikrotikInterfaceMetadata`, `OnuMonitoringHistory`, `PingResult` — model monitor/support

`BelongsToUser` trait masih ada tapi **dead code**.

---

## 3. Isolir Subsystem

Tiga command untuk auto-isolasi pelanggan telat bayar. **Tidak ada halaman publik `/isolir/*`** — semua aksi isolir lewat command + status `suspended` di `CustomerController`.

```
Command Chain:
  1. customer:auto-isolir (00:30 daily)
     → Cari customer overdue > grace_period
     → MikroTik: set PPP Profile = "Isolir"
     → MikroTik: add IP ke address-list
     → Status customer = suspended

  2. customer:sync-isolir-ips (every 5 minutes)
     → Sync daftar IP suspended ke firewall address-list

  3. mikrotik:setup-isolir (manual)
     → Setup PPP Profile "Isolir"
     → Setup DST-NAT redirect
     → Setup DROP filter rules
```

---

## 4. Event-Driven Voucher API

Menggantikan scheduled sync dengan callback real-time.

```
Flow:
  MikroTik Hotspot → POST /api/v1/mikrotik/hotspot-login
                   → {username, password, router_ip, mac}
                   → Cari Voucher where username+password+status='active'
                   → Update status='used', record IP/MAC/timestamp
                   → Return JSON success/fail
```

---

## 5. Job Queue

| Job | Trigger | Timeout | Retry |
|-----|---------|---------|-------|
| `PollOltJob` | Scheduler `olt:poll` | 60s | 3x |
| `IncidentNotificationService` | Scheduler `incident:check-sla` | — | — |

Queue connection: `database` (worker `queue:listen --tries=1` saat dev).

---

## 6. MikroTik SSH Fallback Pattern

Automatic fallback dari REST API ke SSH ketika REST API MikroTik tidak tersedia.

### Arsitektur

```
MikrotikService (REST API utama)
  ├── safeGet() → REST API → sukses? return data
  │              → gagal?   → fallback ke SSH via MikrotikSshService
  ├── Method Mapping (15+ endpoint)
  │   ├── /system/resource       → getSystemResource()
  │   ├── /system/health         → getSystemHealth()
  │   ├── /interface             → getInterfaces()
  │   ├── /ip/hotspot/user       → getHotspotUsers()
  │   ├── /ppp/active            → getPppActive()
  │   ├── /queue/simple          → getSimpleQueues()
  │   └── /log                   → getLog()
  └── initSsh() — auto-init jika router punya ssh_port

MikrotikSshService (via phpseclib3\Net\SSH2)
  ├── testConnection()
  ├── getSystemResource(), getSystemIdentity(), getSystemHealth()
  ├── getInterfaces(), getInterfaceTraffic(interface)
  ├── getHotspotUsers(), getHotspotProfiles(), getHotspotServers()
  ├── getActiveHotspotSessions()
  ├── getPppActive(), getPppSecrets(), getPppProfiles()
  ├── getSimpleQueues()
  └── getLatency(), getLog(top)
```

### Flow

```
safeGet('/system/resource')
  → SSH tersedia? → SSH::getSystemResource() → return
  → REST API?    → client()->get() → sukses? return
                 → gagal? → Log warning → return []
```

### Konfigurasi

Field baru di `mikrotik_routers` table: `ssh_port` (default 22).
Jika diisi, MikrotikService akan auto-init SSH dan menggunakannya sebagai fallback.

---

## 7. Scheduled Tasks

17 schedule di `routes/console.php`:

| Command | Schedule | Fungsi |
|---------|----------|--------|
| `billing:process` | `dailyAt('08:00')` | Generate invoice bulanan |
| `invoices:purge-paid` | `dailyAt('08:30')` | Purge invoice lunas |
| `olt:poll` | `hourly()` | Poll OLT via SSH, update ONU status |
| `customers:onu-sync` | `hourly()` | Sync ONU dari data PPPoE MikroTik |
| `customer:auto-isolir` | `dailyAt('00:30')` | Auto-suspend pelanggan overdue |
| `customer:sync-isolir-ips` | `everyFiveMinutes()` | Sync IP suspended ke firewall |
| `customers:backup` | `dailyAt('03:00')` | Backup pelanggan PPPoE/Hotspot ke JSON |
| `routeros:sync-config` | `everyFifteenMinutes()` | Sync config MikroTik ke DB |
| `network:data-collect` | `everyFiveMinutes()` | Collect metrics; `--aggregate` hourly, `--prune` 6h |
| `qos:optimize` | `everyFiveMinutes()` | Optimasi QoS |
| `incident:check-sla` | `hourly()` | Cek SLA incident |
| `incidents:purge` | `monthly()` | Purge riwayat incident |
| `incidents:purge-auto` | `everyMinute()` | Purge otomatis incident kadaluarsa |
| `automation:scheduler` | `everyMinute()` | Engine automation — jadwalkan job |
| `automation:worker --once` | `everyMinute()` | Engine automation — proses job |

Manual / legacy: `hotspot:import`, `mikrotik:setup-isolir`, `olt:batch-link`, `qos:setup`, `qos:sync`, `qos:health`, `voucher:sync-mikrotik` (non-aktif — digantikan event-driven API).

---

## 8. Payment Abstraction

`app/Services/Payment/` — interface gateway pembayaran terpusat.

```
PaymentGatewayInterface
 ├── MidtransGateway      (Snap API)
 ├── XenditGateway        (Invoice/Webhook)
 └── PaymentService       (orchestrator)

Legacy MidtransService masih dipakai di sebagian path
Callback: POST /midtrans/notification, POST /xendit/notification
```

---

## 9. GenieACS (TR-069)

Modul di `app/Modules/GenieACS/` — manajemen CPE ONT via TR-069 (controller `Noc\GenieacsController`, routes `/noc/genieacs/*`).

```
app/Modules/GenieACS/
 ├── Contracts/  (IGenieACSClient)
 ├── DTO/        (DeviceDTO, TaskDTO, PresetDTO, FaultDTO)
 ├── Exceptions/
 ├── Repositories/ (GenieACSRepository)
 ├── Services/     (GenieACSClient)
 └── Support/      (GenieacsServiceProvider)
```

---

## 10. Automation Engine

`app/Services/Automation/` — engine job otomatis berbasis trigger/log.

```
AutomationSchedulerService  ← automation:scheduler (tiap menit)
AutomationWorkerService     ← automation:worker --once (tiap menit)
AutomationTriggerService    → trigger → AutomationJob → AutomationLog
```

---

## 11. Incidents & SLA

- Model `Incident`, `IncidentNotification`
- `IncidentNotificationService` — menulis `IncidentNotification` (status pending) untuk aplikasi pelanggan Android
- `incident:check-sla` (hourly), `incidents:purge` (monthly), `incidents:purge-auto` (tiap menit)

---

## 12. Network Metrics & QoS

- `NetworkMetric` + `network:data-collect` (collect/aggregate/prune)
- `app/Services/SmartQos/SmartQosService.php` + `qos:optimize` (auto), `qos:setup`/`qos:sync`/`qos:health` (manual)
- Monitoring: `app/Services/Monitoring/` — HealthScore, PingMonitor, Diagnosis, SpeedTest, FiberTopology

---

## 13. RouterOS Config Sync

- `routeros:sync-config` (every 15 menit) → `app/Services/Mikrotik/Sync/RouterosConfigSyncService.php`
- Sinkronisasi config MikroTik (interfaces, traffic, dsb.) ke database

