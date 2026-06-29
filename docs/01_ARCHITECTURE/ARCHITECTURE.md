# Architecture Patterns — RabegNet ISP Billing System

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

### Factory

```php
OltConnectorFactory::make($brand, $olt)
// return driver sesuai brand + optional decorator
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

- Customer, Invoice, Payment, Package
- Olt, OltPort, Onu
- Odc, Odp, OdpRoute, OdpPoint
- Voucher, VoucherProfile, VoucherTemplate
- Setting, ActivityLog

### Model yang TIDAK Menggunakan (⚠️)

- `OdcPort` — potensi data leak
- `OdpPort` — potensi data leak

---

## 3. Isolir Subsystem

Tiga command + satu controller untuk auto-isolasi pelanggan telat bayar.

```
Command Chain:
  1. customer:auto-isolir (00:30 daily)
     → Cari customer overdue > grace_period
     → MikroTik: set PPP Profile = "Isolir"
     → MikroTik: add IP ke address-list

  2. customer:sync-isolir-ips (every 5 minutes)
     → Sync daftar IP suspended ke firewall address-list

  3. mikrotik:setup-isolir (manual)
     → Setup PPP Profile "Isolir"
     → Setup DST-NAT redirect
     → Setup DROP filter rules
```

### Halaman Isolir Publik

```
GET /isolir → auto-detect IP → redirect
GET /isolir/by-ip → cari customer by IP
GET /isolir/{customer} → info pembayaran → bayar → auto-activate
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
| `SendWhatsAppNotification` | Scheduler `billing:process` | 30s | 3x |

---

## 6. Scheduled Tasks

| Command | Schedule | Fungsi |
|---------|----------|--------|
| `billing:process` | `dailyAt('08:00')` | Generate invoice bulanan + WA reminder |
| `olt:poll` | `hourly()` | Poll OLT via SSH, update ONU status |
| `customers:onu-sync` | `hourly()` | Sync ONU dari data PPPoE MikroTik |
| `customer:auto-isolir` | `dailyAt('00:30')` | Auto-suspend pelanggan overdue |
| `customer:sync-isolir-ips` | `everyFiveMinutes()` | Sync IP suspended ke firewall |
