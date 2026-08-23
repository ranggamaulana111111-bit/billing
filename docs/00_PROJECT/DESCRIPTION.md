# DESCRIPTION.md — ALKONEK ISP Billing System

> **Target pembaca:** Developer baru, AI Agent, stakeholder.
> **Tujuan:** Menjadi sumber utama informasi proyek — gabungan dokumen bisnis, teknis, dan arsitektur dalam satu tempat.

---

## 1. Executive Summary

**ALKONEK (PT Alkonek Network Access)** adalah sistem billing ISP berbasis web untuk penyedia layanan internet (ISP) skala kecil hingga menengah. Sistem ini mencakup manajemen pelanggan, penagihan otomatis, pembayaran online (Midtrans), manajemen perangkat jaringan (OLT multi-brand, MikroTik, ODP/ODC), sistem voucher WiFi hotspot, monitoring infrastruktur real-time, serta topologi jaringan fiber yang terintegrasi dengan modul distribusi.

**Masalah yang diselesaikan:**
- Penagihan manual → auto-generate invoice bulanan
- Pembayaran offline → integrasi Midtrans (QRIS, VA, dll)
- Monitoring OLT terbatas → SSH multi-brand dengan polling otomatis
- Manajemen ODP tidak terstruktur → peta interaktif Leaflet
- Voucher manual → generate, print, push ke MikroTik otomatis
- Pengingat tagihan → reminder otomatis via aplikasi pelanggan Android (in-development)
- Multi-tenant → setiap ISP memiliki data terpisah
- Topologi tidak terlihat → visualisasi OLT→ODC→ODP→ONU yang sinkron dengan data distribusi

**Fitur utama:** Customer management, billing & payment, OLT multi-brand monitoring, MikroTik management, fiber distribution mapping, voucher hotspot system, auto-isolir, reporting, **Live Network Topology**, **Monitoring Real-Time trafic WAN-ISP**.

**Status proyek:** v1.2 — Deployed to production (aktif digunakan di lingkungan produksi). Seluruh fitur inti sudah diimplementasikan dan berjalan, namun belum melalui verifikasi formal (benchmark, stress test, security audit). Lihat bagian 16 untuk catatan kejujuran status.

---

## 2. Project Identity

| Atribut | Nilai |
|---------|-------|
| Nama Proyek | ALKONEK ISP Billing System (PT Alkonek Network Access) |
| Versi | 1.2 |
| Framework | Laravel 12 |
| Bahasa | PHP ^8.2, JavaScript |
| Database | MySQL (production), SQLite (testing) |
| Arsitektur | Monolithic + Multi-tenant (Global Scope) |
| Brand | ALKONEK BILLING / PT Alkonek Network Access |
| Deployment | Vercel (primary), Railway.app (backup) |

---

## 3. Vision & Mission

**Visi:** Menjadi sistem billing ISP open-source yang paling mudah diadopsi untuk ISP kecil dan menengah di Indonesia.

**Misi:**
- Mengotomatiskan seluruh operasional billing ISP dari hulu ke hilir
- Mendukung multi-brand OLT dan perangkat jaringan
- Menyediakan self-service portal untuk pelanggan
- Open-source dan mudah dikustomisasi

**Tujuan jangka panjang:**
- Mendukung fitur NMS (Network Management System) penuh
- Integrasi dengan payment channel yang lebih luas
- Dashboard management/owner real-time
- API publik untuk integrasi pihak ketiga

---

## 4. Business Domain

ALKONEK beroperasi di domain **ISP FTTH (Fiber to The Home)** dengan cakupan:

| Area | Deskripsi |
|------|-----------|
| Customer Management | Registrasi, aktivasi, suspend, isolir, PPPoE management |
| Billing | Generate invoice otomatis (monthly), cetak/PDF/email |
| Payment | Midtrans (QRIS/VA), Xendit (VA/e-Wallet/QRIS), manual (cash/transfer) |
| MikroTik | Manajemen PPPoE, hotspot, queue, bandwidth monitoring |
| OLT | Multi-brand (Zte/Huawei/FiberHome/CData/ChineseOlt/Global/Hioso/Hsgq/Vsol), ONU management, polling |
| Distribution | ODC → ODP mapping dengan peta interaktif, port management |
| Voucher | Generate, print (QR code), push ke MikroTik hotspot |
| Reporting | Revenue, outstanding, payment method, export CSV |

### 4.1 Business Requirements — Gap (Belum Didukung)

Dokumen ini kuat di sisi domain & implementasi, namun **requirement bisnis billing belum terdefinisi formal**. Proses billing saat ini hanya menangani **full-pay per invoice bulanan**. Berikut item yang **belum ada** di sistem maupun dokumentasi:

| Requirement | Status | Catatan |
|-------------|--------|---------|
| SLA pelanggan | 🔜 | Belum didefinisikan |
| Prorata billing | 🔜 | Belum ada (invoice flat per paket) |
| Billing cycle khusus | 🔜 | Semua monthly, belum fleksibel |
| Grace period terkonfigurasi | ⚠️ | Hardcode di `customer:auto-isolir`; belum per-tenant |
| Diskon | 🔜 | Belum ada |
| Pajak (PPN) | 🔜 | Belum ada |
| Refund | 🔜 | Belum ada |
| Deposit | 🔜 | Belum ada |
| Multiple invoice per customer | ⚠️ | Mendukung banyak invoice, tapi tidak ada konsep tagihan gabungan |
| Partial payment | 🔜 | Belum ada (full-pay only) |
| Write-off | 🔜 | Belum ada |
| Penalti/late fee | ⚠️ | Ada setting `late_fee` tapi penerapan belum terdokumentasi |

> Lihat juga [16.6](#) untuk implikasi race-condition pada alur payment.

---

## 5. User Roles

| Role | Hak Akses |
|------|-----------|
| **Administrator** | Akses penuh ke semua fitur: CRUD customer/invoice/paket/voucher/distribusi, pengaturan, backup, export, report, manajemen OLT & MikroTik |
| **Teknisi** | Dashboard, customer (read + create/edit), tagihan, pembayaran, OLT (full), MikroTik (read-only kecuali disconnect session), voucher (read-only), distribusi (read-only), log |
| **Pelanggan** | Akses publik: portal cek tagihan, bayar via Midtrans, beli voucher, cek status voucher |
| **Owner/Management** | (Belum ada role khusus — sementara via admin) |

---

## 6. System Architecture

### Layer Arsitektur

```
┌─────────────────────────────────────────────────┐
│              Presentation Layer                 │
│  Blade Templates + Bootstrap 5.3 + Leaflet.js   │
│  Chart.js + Alpine.js                           │
├─────────────────────────────────────────────────┤
│               Routing Layer                     │
│  routes/web.php (~510 routes)                   │
│  routes/api.php (3 routes)                      │
│  routes/console.php (17 schedules)              │
├─────────────────────────────────────────────────┤
│              Controller Layer                   │
│  58 Controllers (38 root + 14 Noc + Api/Auth)   │
│  Middleware: IsAdmin, IsTeknisiOrAdmin, IsNoc   │
├─────────────────────────────────────────────────┤
│               Service Layer                     │
│  MikrotikService, Payment/, Olt/ (Driver),       │
│  Monitoring/, Automation/, SmartQos, GenieACS    │
│    └─ Drivers: Zte, Huawei, FiberHome, CData,  │
│       ChineseOlt, Global, Hioso, Hsgq, Vsol    │
│    └─ Decorators: JumpHost, MikrotikSshProxy    │
├─────────────────────────────────────────────────┤
│                Model Layer                      │
│  40 Models + 2 Traits                           │
│  BelongsToTenant (Global Scope, 30 model)       │
├─────────────────────────────────────────────────┤
│              Database Layer                     │
│  MySQL (production), SQLite (testing)           │
│  48 tables, 100 migrations                       │
├─────────────────────────────────────────────────┤
│         External Integration Layer              │
│  Midtrans Snap API, MikroTik REST API,          │
│  Google OAuth (chat/link WA & Telegram)          │
└─────────────────────────────────────────────────┘
```

### Pola Desain

| Pattern | Penerapan |
|---------|-----------|
| **Monolithic** | Satu aplikasi Laravel, pemisahan Controller → Service → Model |
| **Multi-tenant (Global Scope)** | `BelongsToTenant` trait — setiap query otomatis difilter `tenant_id` |
| **Driver Pattern** | OLT multi-brand — factory memilih driver sesuai brand |
| **Decorator Pattern** | Jump Host SSH tunnel & MikrotikSshProxy — membungkus driver OLT |
| **Scheduled Tasks** | 22 console command, 17 jadwal berjalan otomatis (daily/hourly/5-menit) |
| **Event-driven API** | Callback dari MikroTik hotspot → update status voucher |
| **Job Queue** | Polling OLT perangkat + job async (database queue) |

### Alur Request

```
User → Browser → Vite (dev) / public/build (prod)
              → Laravel Route
              → Middleware (auth → teknisi/admin)
              → Controller
              → Service (optional)
              → Model/ORM (auto-filter tenant_id)
              → Database (MySQL)
              → View (Blade) → Response HTML
```

### Alur Data Utama

```
Tenant → User (admin/teknisi)
 ├── Membuat Pelanggan → auto-create Invoice pertama
 ├── Pelanggan bayar → Payment → Invoice status=paid
 ├── Generate Voucher → push ke MikroTik → callback API saat login
 ├── Scan/Poll OLT → update ONU status → deteksi redaman
 ├── Buat ODC → ODP → assign port ke Customer
 ├── Isolir: overdue → auto-suspend → MikroTik PPP Profile isolir
 └── Pelanggan bayar tagihan → auto-activate
```

---

## 7. Technology Stack

### Backend

| Teknologi | Versi | Fungsi |
|-----------|-------|--------|
| Laravel | 12.x | Framework PHP utama |
| PHP | ^8.2 | Runtime |
| MySQL (Laragon) | — | Database development |
| MySQL (Aiven) | — | Database production (Vercel) |
| SQLite | — | Testing (`:memory:`) |
| phpseclib | ^3.0 | SSH ke OLT |
| Midtrans PHP | ^2.6 | Payment gateway |
| DomPDF | ^3.1 | Generate PDF invoice |
| RouterOS API | ^1.7 | REST API MikroTik |

### Frontend

| Teknologi | Versi | Fungsi |
|-----------|-------|--------|
| Bootstrap | 5.3.8 | CSS framework utama (di-load via CDN jsDelivr, offloaded dari build) |
| Leaflet | 1.9.4 | Peta interaktif ODP/OLT (CDN defer, hanya di halaman perlu) |
| Chart.js | 4.5.1 | Grafik dashboard (CDN defer, hanya di halaman perlu) |
| Alpine.js | — | Interaktivitas ringan |
| Vite | 7.x | Asset bundler (hanya app.css + app.js) |
| simple-qrcode | 4.2 | QR code inline SVG |

> **Optimasi Performa (v1.2):** Bootstrap CSS, Chart.js, dan Leaflet di-load dari CDN (`defer`/`media=swap`) sehingga build Vite hanya menghasilkan ~103KB CSS + ~122KB JS (gzip ≈ 19KB + 41KB). Font Awesome & Google Fonts di-load non-render-blocking. DashboardController di-cache (`Cache::remember` 90–300s) + fix N+1 query.

### Integrasi Pihak Ketiga

| Layanan | API | Fungsi |
|---------|-----|--------|
| Midtrans | Snap API | Pembayaran QRIS, VA, Convenience Store |
| Xendit | REST API | Pembayaran VA, e-Wallet, QRIS (channel tambahan) |
| Google | Socialite OAuth 2.0 | Login dengan Google |
| MikroTik | REST API + SSH fallback | Manajemen hotspot, PPP, queue, firewall |

---

## 8. Module Documentation

### 8.1 Autentikasi & Manajemen User
- **Controller:** `LoginController`, `RegisterController`, `SocialiteController`
- **Fitur:** Login email/password, Register, Google OAuth, Logout, Role-based access (admin/teknisi)
- **Routes:** `/login`, `/register`, `/auth/google/*`

### 8.2 Dashboard
- **Controller:** `DashboardController`
- **View:** `dashboard.blade.php`
- **Fitur:** 7 stat cards, revenue chart (bar 6 bulan), payment donut chart, package distribution, recent activity timeline, tabel unpaid invoices, Leaflet map ODP

### 8.3 Manajemen Pelanggan (Customer)
- **Controller:** `CustomerController`
- **Model:** `Customer` — BelongsToTenant, HasFactory
- **Fitur:** CRUD, Suspend/Activate (otomatis disable/enable PPPoE MikroTik + buat ONU), Sync PPPoE, Sync ONU, auto-create invoice saat register
- **Routes:** `/customers`, `/customer/create`, `/customer/{id}/edit`, `/customer/{id}/suspend`, dll

### 8.4 Tagihan (Invoice)
- **Controller:** `InvoiceController`
- **Model:** `Invoice` — BelongsToTenant, HasFactory
- **Fitur:** CRUD, filter/search, mark paid, print, PDF (DomPDF), email reminder & confirmation
- **Routes:** `/invoices`, `/invoice/print/{id}`, `/invoice/pdf/{id}`, `/invoice/email-reminder/{id}`

### 8.5 Pembayaran (Payment)
- **Controller:** `PaymentController`
- **Model:** `Payment` — BelongsToTenant
- **Fitur:** Catat pembayaran (cash/transfer/QRIS), history, hapus (auto-update invoice), integrasi Midtrans
- **Routes:** `/payment/create/{invoice}`, `/payment/history/{invoice}`

### 8.6 Paket Internet (Package)
- **Controller:** `PackageController`
- **Model:** `Package` — BelongsToTenant, HasFactory
- **Fitur:** CRUD, proteksi delete (jika ada customer), mass billing, filter
- **Routes:** `/packages`, `/packages/mass-bill`

### 8.7 OLT Management
- **Controller:** `OltController`
- **Models:** `Olt`, `OltPort`, `Onu` — semua BelongsToTenant
- **Driver Pattern:** Multi-brand (Zte, Huawei, FiberHome, CData, ChineseOlt, Global, Hioso, Hsgq, Vsol) via SSH + decorator JumpHost/MikrotikSshProxy
- **Fitur:** CRUD OLT, test SSH, scan ONU, reboot/remove ONU, link ke customer, monitoring ONU (sort by Rx power), map OLT, live JSON API, export CSV
- **Routes:** `/olts`, `/olts/{olt}/scan`, `/olts-monitoring`, `/olts/map`, `/onus/search`

### 8.8 Distribusi ODP/ODC
- **Controller:** `DistributionController`, `OdcController`, `OdpController`
- **Models:** `Odc`, `OdcPort`, `Odp`, `OdpPort`, `OdpRoute`, `OdpPoint`
- **Struktur:** ODC → ODC Port → ODP → ODP Port → Customer
- **Fitur:** Map interaktif Leaflet, port grid (available/used/broken), relasi ODC-ODP via `connected_to_odp_id`, auto-generate port saat create, auto-refresh port status (15s polling), API port data realtime
- **Routes:** `/distribution`, `/odc/{odc}`, `/odp/{odp}`, `/api/v1/odc/{odc}/ports`, `/api/v1/odp/{odp}/ports`

### 8.8b Live Network Topology (Topologi Jaringan)

- **Route:** `/onu-health/topology/graph` (`OnuHealthController@topology`)
- **Service:** `FiberTopologyService::getTopologyData()`
- **Fitur:** Visualisasi infrastruktur fiber dari OLT sampai pelanggan (Internet → Core Router → OLT → ODC → ODP → PON → ONU → Pelanggan). **Sync dengan modul Distribution**: node ODC/ODP diambil langsung dari entitas `Odc`/`Odp` (via `odc_id`), edge `OLT→ODC→ODP→ONU` dibentuk dari `Odc.odps()` dan `OdpPort.customer_id`. Layout compact (`mon-table`-style) agar skalabel saat jumlah node bertambah.
- **Catatan:** Sebelumnya topologi mengandalkan `OdcPort.connected_to_odp_id` yang tidak diisi oleh UI Distribution, sehingga ODC/ODP tidak muncul. Sekarang terhubung penuh ke data distribusi.

### 8.9 Voucher WiFi (Hotspot)
- **Controller:** `VoucherController`
- **Model:** `Voucher` — BelongsToTenant, HasFactory
- **QR Code:** `simplesoftwareio/simple-qrcode` (inline SVG)
- **Fitur:** Generate (random user/pass), push ke MikroTik, print (single/batch), sync status, report, auto-expire, event-driven callback via `POST /api/v1/mikrotik/hotspot-login`
- **Templates:** 6 halaman hotspot (login, status, redirect, error, alive, logout) — bisa dikustomisasi

### 8.10 MikroTik Management
- **Controller:** `MikrotikController`
- **Service:** `MikrotikService` — REST API wrapper
- **Fitur:** Dashboard (system resource, health), hotspot profiles/users, PPP secrets, simple queues, active sessions (disconnect), backup, bandwidth monitoring, live JSON API

### 8.11 Portal Publik
- **Controller:** `PortalController`
- **Fitur:** Cek tagihan by phone, bayar via Midtrans, self-service
- **Routes:** `/portal`, `/portal/bayar/{invoice}`

### 8.12 Isolir Subsystem
- **Controller:** Tidak ada controller khusus — ditangani via command + status `suspended` di `CustomerController`
- **Fitur:** Auto-suspend pelanggan overdue (00:30 daily), set PPP Profile "Isolir" di MikroTik, sync IP ke firewall address-list (every 5 menit)
- **Commands:** `customer:auto-isolir`, `customer:sync-isolir-ips`, `mikrotik:setup-isolir`

### 8.13 Laporan (Report)
- **Controller:** `ReportController`
- **Fitur:** Revenue bulanan, outstanding, chart 12 bulan, metode pembayaran, top unpaid
- **Routes:** `/reports` (admin only)

### 8.14 Pengaturan (Setting)
- **Controller:** `SettingController`
- **Model:** `Setting` — key-value store per tenant
- **Keys:** company info, bank, Midtrans keys, MikroTik config, notifikasi WA/Telegram, voucher length, late fee, due date
- **Routes:** `/settings` (admin only)

### 8.15 Backup & Export
- **Controllers:** `BackupController`, `ExportController`
- **Fitur:** Download backup database, export CSV invoices/payments, export CSV OLT/ONU

### 8.16 Module Boundaries & Dependencies

Sistem masih **monolith tunggal**; *bounded context* belum dienkapsulasi formal. Dependency implisit via Model/Service (bukan event bus):

| Domain | Modul Utama | Dependensi Keluar | Source of Truth |
|--------|-------------|-------------------|-----------------|
| Customer | CustomerController | → Billing, → Network (PPPoE/ONU) | `customers` |
| Billing | Invoice, Payment, Package | → Customer, → MikroTik, → Midtrans | `invoices`, `payments` |
| Network (OLT/ONU) | OltController, OnuHealth | → Customer, → Distribution | `olts`, `onus` |
| Distribution (ODC/ODP) | DistributionController | → Topology | `odcs`, `odps`, `odp_ports` |
| MikroTik | MikrotikController, MikrotikService | ← Billing, ← Isolir | `mikrotik_routers` |
| Voucher | VoucherController | → MikroTik, → Customer | `vouchers` |
| Monitoring/Topology | OnuHealth, FiberTopologyService | → OLT, → Distribution (read-only) | derived |
| Isolir | AutoIsolir, SyncIsolirIps, MikrotikSetupIsolir | → Billing, → MikroTik | derived dari `invoices` |
| Incidents & SLA | IncidentController, IncidentNotificationService | → Customer, → (Android app) | `incidents` |
| Inventory | InventoryController | → Customer (optional) | `inventory_items` |
| Automation | AutomationController + `automation:*` | → OLT | `noc_automation_jobs` |

> Coupling via ORM relation + Service call sinkron; belum ada event/message bus antar domain (kecuali callback MikroTik hotspot → voucher status).

### 8.17 Payment Abstraction & Xendit
- **Services:** `app/Services/Payment/` — `PaymentGatewayInterface`, `MidtransGateway`, `XenditGateway`, `PaymentService`
- **Controllers:** `MidtransController`, `XenditController`
- **Fitur:** Pembayaran Midtrans (QRIS/VA) + Xendit (VA/e-Wallet/QRIS) lewat interface seragam; legacy `MidtransService` masih dipakai untuk beberapa alur
- **Routes:** `/midtrans/*`, `/xendit/*`

### 8.18 NOC Modules
- **Controllers:** 14 controller di `app/Http/Controllers/Noc/` (namespace `Noc\`) — Automation, ConfigModule, ConfigRepository, Dashboard, Features, Genieacs, InterfaceCenter, InternetService, MikrotikDashboard, MikrotikDevice, NetworkConfig, SecurityPolicy, SyncDashboard, TrafficEngineering
- **Middleware:** `IsNoc` — semua route di bawah `/noc/*`

### 8.18b Live FTTH Map Screen (NOC Features Map)

- **Route:** `/noc/features/map` (`Noc\FeaturesController`)
- **View:** `resources/views/noc/features/map.blade.php` — peta interaktif berbasis Leaflet dalam satu halaman
- **Tujuan:** Satu peta FTTH yang menampilkan seluruh infrastruktur (OLT, ODC, ODP, ONU, MikroTik, pelanggan) lengkap dengan **monitoring trafik real-time** langsung dari perangkat.
- **Fitur utama:**
  - **Marker perangkat** berwarna berdasarkan tipe & status (online/offline); klik membuka *detail card*.
  - **Live trafik ONU / PPPoE pelanggan** — rate Rx/Tx dihitung dari *delta* counter PPPoE MikroTik antar poll (akurat vs MikroTik, tidak nol/spike). Index sesi di-cache 3 detik (`built_at` dipakai sebagai timestamp sampel).
  - **Live trafik Tower Hotspot** — agregat sesi aktif per *hotspot server* (aware server bersama / *shared*). Badge menampilkan `Server: <nama>`; daftar klien di-cap (maks 50) agar tidak membanjiri kartu saat ribuan pelanggan.
  - **Live trafik WAN-ISP MikroTik** — grafik Rx/Tx real-time pada card *Sync Mikrotik* (berada di bawah tombol *Sync All Saved Routes*, di atas daftar router).
  - **Live trafik PON OLT** — agregat trafik PON per OLT (poll ~3 detik, berbasis timestamp `microtime` bukan cache statis 10 detik).
  - **Card Sync MikroTik / OLT** — input IP/Port/User/Pass (satu baris via `ftth-form-grid2`), tombol Simpan/Konek/Sync, grafik trafik live, daftar perangkat terhubung.
  - **Topologi & kabel** — ODC→ODP→ONU, edit kabel, ukur jarak, dll.
- **Performa:** caching terarah (hotspot active 10 detik, index ONU 3 detik, rate OLT ~3 detik) + *locking* agar tidak ada poll duplikat; data trafik tetap di-sample di latar meski card ditutup.
- **Stack:** Leaflet (peta) + Chart.js (grafik, CDN `defer`) + custom CSS design system (`resources/css/app.css`).

### 8.19 GenieACS (TR-069)
- **Module:** `app/Modules/GenieACS/` — Contracts, Exceptions, Repositories, Services, Support (`GenieacsServiceProvider`)
- **Controller:** `Noc\GenieacsController` — routes di bawah `/noc/genieacs`
- **Fitur:** Manajemen perangkat CPE via TR-069 (provisioning, parameter, reboot) tanpa ekspos ke global bundle

### 8.20 Incidents & SLA
- **Models:** `Incident`, `IncidentNotification`
- **Service:** `IncidentNotificationService` — menulis notifikasi `IncidentNotification` (pending) untuk aplikasi pelanggan Android
- **Commands:** `incident:check-sla` (hourly), `incidents:purge` (monthly), `incidents:purge-auto` (every minute)

### 8.21 Network Metrics & QoS
- **Models:** `NetworkMetric`, `NetworkMetricAggregated`, `PingResult`
- **Services:** `app/Services/SmartQos/SmartQosService.php`, `app/Services/Monitoring/` (HealthScore, PingMonitor, Diagnosis, SpeedTest, FiberTopology)
- **Commands:** `network:data-collect` (every 5 menit; `--aggregate` hourly, `--prune` 6 jam), `qos:optimize`, `qos:setup`, `qos:sync`, `qos:health`

### 8.22 RouterOS Config Sync & Inventory
- **RouterOS:** `routeros:sync-config` (every 15 menit) — sinkronisasi config MikroTik ke DB (`RouterosSyncedConfig`, `RouterosSyncLog`, `ConfigVersion`)
- **Inventory:** `InventoryController` + model `InventoryItem`, `InventoryTransaction`

---

## 9. Database Overview

### 47 Tabel — 98 Migrations

#### Pengelompokan Tabel

| Grup | Tabel |
|------|-------|
| **Core/System** | `tenants`, `users`, `settings`, `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs` |
| **Billing** | `customers`, `packages`, `invoices`, `payments`, `bandwidth_profiles` |
| **Infrastructure** | `olts`, `olt_ports`, `onus`, `onu_monitoring_history`, `odcs`, `odc_ports`, `odps`, `odp_ports`, `odp_routes`, `odp_points`, `mikrotik_routers`, `mikrotik_interface_metadata`, `interface_change_logs` |
| **Voucher** | `vouchers`, `voucher_profiles`, `voucher_templates`, `voucher_print_templates` |
| **Activity** | `activity_logs` |
| **Incidents & SLA** | `incidents`, `incident_notifications` |
| **Inventory** | `inventory_items`, `inventory_transactions` |
| **Automation** | `noc_automation_jobs`, `noc_automation_job_logs`, `noc_automation_triggers` |
| **Network Metrics & QoS** | `network_metrics`, `network_metrics_aggregated`, `ping_results`, `config_versions`, `network_config_audit_logs` |
| **RouterOS Sync** | `routeros_sync_logs`, `routeros_synced_configs` |

#### Relasi Utama

```
tenants
 └── users ──┬── customers ──┬── invoices ──── payments
              │               └── onus ──────── olt_ports ── olts
              │               └── odp_ports ─── odps ──────── odc_ports ── odcs
              ├── packages
              ├── vouchers ──── voucher_profiles
              │              └── mikrotik_routers
              │              └── voucher_templates
              ├── settings
              ├── activity_logs
              ├── odp_routes ── odp_points ──── customers (legacy)
              └── odcs ──────── odp_routes ──── odp_points (legacy)
```

#### Catatan Penting

- **`OdcPort` & `OdpPort`** — TIDAK menggunakan `BelongsToTenant` (potensi data leak antar tenant)
- **`BelongsToUser` trait** — masih ada di codebase tapi sudah dead code (digantikan `BelongsToTenant`)
- **`kondisi_jalur`** di `odps` — string sederhana (`UP`/`DOWN_LINK_FAILURE`), bukan enum
- **Password MikroTik** — tidak di-encrypt di tabel `mikrotik_routers`

---

## 10. Business Process

### 10.1 Alur Pelanggan Baru

```
Admin create customer
  → Pilih paket, isi data, PPPoE username
  → CustomerController@store
    → Customer.create
    → Invoice.create (pertama, langsung jatuh tempo)
    → ActivityLog.log('Create Customer')
  → Admin activate customer
    → CustomerController@activate
      → MikrotikService.addPppSecret (create PPPoE di MikroTik)
      → Onu.create (auto-create ONU record)
      → Customer.status = 'active'
```

### 10.2 Alur Billing (Otomatis)

```
Setiap hari jam 08:00
  → BillingProcess command
    → For each active customer per tenant:
      → Cek invoice bulan ini sudah ada?
      → Jika belum: Invoice.create (amount = package.price)
```

### 10.3 Alur Pembayaran

```
Pelanggan bayar via Midtrans (QRIS/VA):
  → Portal/Midtrans → Snap Token → Redirect ke Midtrans
  → Pelanggan bayar di Midtrans
  → Midtrans callback POST /midtrans/notification
    → MidtransController@notification
      → Invoice.update(payment_status='paid', paid_at=now)
      → Payment.create

Pelanggan bayar via Xendit (VA/e-Wallet/QRIS):
  → XenditController → Invoice dibuatkan invoice di Xendit
  → Webhook Xendit → update status invoice + Payment.create

Admin catat pembayaran manual (cash/transfer):
  → PaymentController@store
    → Payment.create
    → Invoice.update(payment_status, paid_at)
    → Jika total bayar >= amount: invoice lunas
    → Jika customer kena isolir: auto-activate
```

### 10.4 Alur Aktivasi Layanan

```
Customer.status = 'suspended' (karena overdue)
  → Admin/System bayar tagihan
  → Payment.create → Invoice.paid
  → Auto-detect: if customer.suspended
    → CustomerController@activate
      → MikrotikService: remove PPP active → add PPP secret (enable)
      → Customer.status = 'active'
      → ActivityLog.log('Activate Customer')
```

### 10.5 Alur Monitoring Jaringan

```
Setiap jam (scheduler):
  → olt:poll → PollOltJob per OLT
    → SSH ke OLT → scan ONU per port
    → Update/Rebatalkan data ONU (status online/offline, Rx power)
    → Deteksi redaman → sort by weakest signal

Setiap jam (scheduler):
  → customers:onu-sync
    → Ambil data PPPoE active dari MikroTik
    → Cocokkan dengan customer PPPoE username
    → Update/create ONU record

Auto-isolir jam 00:30:
  → customer:auto-isolir
    → Cari customer overdue > grace period
    → Set PPP Profile = "Isolir" di MikroTik
    → Tambah IP ke address-list (blokir internet)
    
Sinkronasi IP isolir setiap 5 menit:
  → customer:sync-isolir-ips
    → Sync daftar IP suspended ke firewall address-list MikroTik

Collect metrics setiap 5 menit:
  → network:data-collect (+ --aggregate hourly, --prune 6 jam)
    → Simpan NetworkMetric / aggregated
  → qos:optimize → optimasi QoS otomatis

Sync config MikroTik setiap 15 menit:
  → routeros:sync-config → snapshot config ke DB

Cek SLA incident setiap jam:
  → incident:check-sla → catat notifikasi untuk aplikasi Android
```

---

## 11. External Integrations

| Integrasi | Metode | Detail |
|-----------|--------|--------|
| **Midtrans** | REST API (Snap) | Pembayaran QRIS, VA, Convenience Store. Webhook callback. Config via settings (server key, client key, is_production) |
| **Xendit** | REST API | Pembayaran VA, e-Wallet, QRIS. Webhook callback. Via `app/Services/Payment/` abstraction (`XenditGateway`) |
| **MikroTik** | REST API + SSH fallback (phpseclib3) | HTTP Basic Auth, tanpa SSL verification (`withoutVerifying()`). Manajemen hotspot, PPP, queue, firewall, backup |
| **Google Login** | OAuth 2.0 (Socialite) | Login dengan akun Google. Config di `config/services.php` |
| **QR Code** | `simple-qrcode` | Inline SVG, tanpa external API. Untuk voucher WiFi |
| **DomPDF** | `barryvdh/laravel-dompdf` | Generate PDF invoice untuk print/download |
| **GenieACS (TR-069)** | REST API | Manajemen perangkat CPE via `app/Modules/GenieACS/` |

---

## 12. Folder Structure

```
e-billing/
├── AGENTS.md                  # Petunjuk untuk AI Agent & developer
├── DESCRIPTION.md             # Dokumen ini
├── PRD.md                     # Product Requirement Document
├── vercel.json                # Deployment config (Vercel)
├── railway.json               # Deployment backup (Railway)
│
├── app/
│   ├── Console/Commands/      # 22 Artisan commands
│   ├── Http/
│   │   ├── Controllers/       # 58 controllers (38 root + 3 Api + 3 Auth + 14 Noc)
│   │   └── Middleware/        # IsAdmin, IsTeknisiOrAdmin, IsNoc
│   ├── Jobs/                  # PollOltJob
│   ├── Mail/                  # InvoiceReminder, PaymentConfirmation
│   ├── Models/                # 40 models + 2 traits
│   ├── Modules/               # GenieACS (TR-069)
│   └── Services/              # Payment/, Mikrotik/, Olt/ (drivers), Monitoring/, Automation/, SmartQos/
│
├── bootstrap/                 # Laravel bootstrap + app.php
├── config/                    # Konfigurasi Laravel
├── database/
│   ├── factories/             # 5 factories
│   ├── migrations/            # 100 migrations (48 tables)
│   └── seeders/               # 5 seeders
│
├── public/
│   ├── build/                 # Asset compiled (Vite: ~103KB CSS + ~122KB JS)
│   └── hotspot/               # HTML hotspot pages
│
├── resources/
│   ├── css/app.css            # ~5000 baris custom CSS (design system)
│   ├── js/                    # app.js (bootstrap JS only), bootstrap.js
│   └── views/                 # 172 blade files
│
├── routes/
│   ├── web.php                # ~510 routes
│   ├── api.php                # API routes (hotspot-login callback, dll)
│   └── console.php            # 17 scheduled commands
│
├── storage/                   # Logs, cache, backups
│
└── tests/
    ├── Feature/               # 7 test classes (54 methods)
    └── Unit/                  # 10 test classes (88 methods)
```

---

## 13. Development Workflow

### Alur Pengembangan Fitur Baru

1. **Buat migration** — `php artisan make:migration create_nama_tabel`
2. **Buat model** — `php artisan make:model NamaModel`
3. **Buat controller** — `php artisan make:controller NamaController`
4. **Buat view** — Blade file di `resources/views/`
5. **Daftar route** — Tambah di `routes/web.php`
6. **Testing** — `php artisan test` atau `vendor/bin/phpunit`

### Command Penting

| Command | Fungsi |
|---------|--------|
| `php artisan make:migration` | Buat file migration baru |
| `php artisan make:model` | Buat model baru |
| `php artisan make:controller` | Buat controller baru |
| `php artisan migrate` | Jalankan migration |
| `php artisan migrate:fresh --seed` | Reset DB + seed |
| `php artisan route:list` | Lihat semua route |
| `php artisan tinker` | Interactive shell |
| `./vendor/bin/pint` | Auto-format code (Laravel Pint) |
| `npm run build` | Build frontend assets |
| `npm run dev` | Vite dev server |

### Lokasi PHP CLI

`php` tidak ada di PATH. Gunakan path lengkap ke Laragon PHP 8.3:
```
"C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe" artisan {command}
```
> Folder PHP 8.3 bernama persis `php-8.3.31-Win32-vs16-x64 (1)` (termasuk spasi & kurung) — jangan hapus tanda kutip.

---

## 14. Coding Convention

### Naming Convention
- **PSR-4:** `App\` → `app/`, `Database\Factories\` → `database/factories/`
- **Model:** Singular, PascalCase (`Customer`, `Invoice`)
- **Table:** Plural, snake_case (`customers`, `invoice_items`)
- **Controller:** PascalCase + `Controller` suffix (`CustomerController`)
- **Migration:** `YYYY_MM_DD_HHMMSS_create_table_table_name`
- **Route:** snake_case (`customers.index`, `invoice.paid`)
- **Variable:** camelCase (`$dueDate`, `$paidAt`)
- **Method:** camelCase (`generateAndPush()`, `assignOdpPort()`)

### Route Convention
- **Resourceful:** `GET/POST/PUT/DELETE /resource` + `/{id}` untuk CRUD
- **Action:** `GET /resource/{id}/action` untuk custom action
- **Middleware:** Public → `web`, Auth → `auth` + `teknisi`, Admin → `auth` + `teknisi` + `admin`

### Controller Convention
- **Resource controller** untuk CRUD standar
- **Private method** untuk logic yang digunakan >1 method (e.g., `generateAndPush()`)
- **Service class** untuk logic kompleks (e.g., `MikrotikService`, `MidtransService`)

### View Convention
- **Layout:** `layouts.app` sebagai base template
- **Section:** `@section('title')`, `@section('content')`, `@push('scripts')`
- **CSS:** Bootstrap 5.3 classes (CDN) + custom CSS di `app.css` (~5000 baris design system)
- **Design System Table:** Seluruh tabel menggunakan class `.mon-table` + wrapper `.mon-table-wrap` + header solid `.mon-thead` (atau `.mon-table > tbody > tr:first-child > th`). Style seragam mengikuti tampilan Monitoring Real-Time.
- **Header Card:** Gradient indigo→ungu→pink (`.mon-card-head`, `.topo-header`, `.alarm-head`).
- **Script berat (Chart.js/Leaflet):** di-load via CDN `defer` di `@push('scripts')` hanya pada halaman yang membutuhkan — **JANGAN** import di `resources/js/app.js`.

### Database Convention
- **Timestamps:** `created_at`, `updated_at` otomatis (Laravel default)
- **Soft deletes:** Tidak digunakan (hard delete + aktivitas log)
- **Foreign keys:** `{table}_id` (e.g., `customer_id`, `package_id`)
- **Enum fields:** Implemented as VARCHAR dengan validasi di controller/model

---

## 15. Deployment Overview

### Requirement Server
- PHP ^8.2
- Composer
- Node.js + NPM
- MySQL 8.0+
- Extension: PDO, MySQL, BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML

### Deployment Steps (Vercel)
```bash
# 1. Clone & install
composer install --no-dev
npm install && npm run build

# 2. Environment
cp .env.example .env
# Isi: DB connection (Aiven MySQL), APP_KEY, Midtrans keys, dll

# 3. Build frontend
npm run build

# 4. Deploy
vercel --prod
```

### Production Config
- **Database:** Aiven MySQL (Vercel tidak bisa persistent storage)
- **Queue:** Database queue (`QUEUE_CONNECTION=database`)
- **Cache:** Database cache (`CACHE_STORE=database`)
- **Session:** Database session (`SESSION_DRIVER=database`)
- **Schedule:** Trigger via external cron service hitting `/api/cron/run`

### Railway Backup
- Konfigurasi di `railway.json`
- Siap untuk deployment cepat jika Vercel bermasalah

---

## 16. Project Status

> **Catatan Kejujuran Status.** Istilah *"Production"* / *"Deployed to production"* di dokumen ini berarti **aplikasi sudah di-deploy dan digunakan secara nyata**, BUKAN berarti telah lolos verifikasi formal. Sampai saat ini **belum ada benchmark, stress test, load test, atau security audit** yang dilakukan, sehingga klaim kapasitas (concurrent user, throughput, jumlah tenant/pelanggan/ONU maksimal) **belum terukur**. Status modul menggunakan skala: ✅ Implemented, ⚠️ Partial, 🔜 Planned.

### Functional Completeness

| Modul | Status | Catatan |
|-------|--------|---------|
| Auth (Login/Register/Google OAuth) | ✅ Implemented | Role admin & teknisi |
| Dashboard | ✅ Implemented | Stats, charts, map, timeline |
| Customer CRUD | ✅ Implemented | + Suspend/Activate/Sync |
| Invoice CRUD | ✅ Implemented | + Print/PDF/Email |
| Payment | ✅ Implemented | + Midtrans integration |
| Package CRUD | ✅ Implemented | + Mass billing |
| OLT Management | ✅ Implemented | Multi-brand, scan, monitoring |
| MikroTik Management | ✅ Implemented | Hotspot, PPP, queue, monitoring |
| Distribution (ODC/ODP) | ✅ Implemented | Map, port grid, realtime API |
| Live Network Topology | ✅ Implemented | OLT→ODC→ODP→ONU sync distribusi |
| Monitoring Real-Time | ✅ Implemented | Rate trafic WAN-ISP (delta 1s server-side) |
| Voucher System | ✅ Implemented | Generate, print, push, sync |
| Portal Publik | ✅ Implemented | Cek tagihan, bayar |
| Isolir Subsystem | ✅ Implemented | Auto-suspend, firewall sync |
| Settings | ✅ Implemented | Key-value store |
| Activity Log | ✅ Implemented | Filterable log |
| Backup & Export | ✅ Implemented | DB backup, CSV export |
| Report | ✅ Implemented | Revenue, outstanding, charts |

### Non-Functional Completeness

| Aspek | Status | Catatan |
|-------|--------|---------|
| Testing (142 tests) | ⚠️ Partial | 17 file (7 feature + 10 unit); **suite saat ini RED** — migrasi `2026_06_22_000004_add_tenant_id_to_business_tables.php` memakai `DROP CONSTRAINT IF EXISTS` (sintaks MySQL/Postgres) yang gagal di SQLite `:memory:`, sehingga semua test `RefreshDatabase` error; belum ada coverage %, integration/load/UI test |
| Security | ⚠️ Partial | Password MikroTik plaintext, SSL verify disabled (lihat 16.3) |
| Multi-tenant | ⚠️ Partial | `BelongsToTenant` global scope aktif, tapi `OdcPort`/`OdpPort` belum ter-cover |
| Error Handling | ⚠️ Partial | Try-catch di service layer; belum ada error architecture terpusat (16.5) |
| Logging | ✅ Implemented | Activity log + Laravel log |
| Queue | ✅ Implemented | Database queue untuk OLT polling + job async |
| Validation | ✅ Implemented | Form request validation |
| Performa Frontend | ✅ Implemented | Bootstrap/Chart/Leaflet via CDN, JS turun 504KB→122KB, caching query |
| Konsistensi UI | ✅ Implemented | Seluruh tabel pakai `.mon-table` + `.mon-thead` |

### 16.3 Security Coverage

**Sudah ditangani (framework default Laravel):** CSRF (`VerifyCsrfToken`), mass assignment via `$fillable`/`$guarded`, SQL injection terhindar (Eloquent parameterized), authorization via `IsAdmin`/`IsTeknisiOrAdmin`/`IsNoc`, hashing password user (bcrypt), session database.

**Known Gaps (belum ditangani):**
1. Password MikroTik di DB tidak di-encrypt (plaintext di `mikrotik_routers.password`)
2. SSL verification disabled (`withoutVerifying()` di REST API MikroTik)
3. `OdcPort` & `OdpPort` tidak punya `BelongsToTenant` (potensi data leak)
4. `reset_data.php` script destruktif tanpa proteksi
5. Token/kredensial di file commit (`.env`, `vercel.json`, `checker.md`)
6. API auth — `POST /api/v1/mikrotik/hotspot-login` belum punya auth/throttling terdocumentasi
7. Rate limiting belum dikonfigurasi eksplisit
8. Audit log integrity — `activity_logs` belum append-only/worm
9. Backup encryption belum diterapkan
10. Secrets management masih di `.env` plaintext

### 16.4 NFR (Target, Belum Teruji)

| NFR | Target / Catatan | Status Pengukuran |
|-----|------------------|-------------------|
| Availability | Menargetkan 99.5% (belum SLA-kan) | 🔜 Belum diukur |
| Scalability | Horizontal via Vercel serverless; DB Aiven | 🔜 Belum load-test |
| Reliability | Queue + retry untuk OLT poll & job async | ⚠️ Best-effort |
| Recoverability / RPO-RTO | Backup DB terjadwal; belum didefinisikan | 🔜 Belum didefinisikan |
| Disaster Recovery | Railway.app failover | ⚠️ Belum diuji |
| Monitoring & Alerting | Laravel log + activity log; belum APM/alerting | 🔜 Perlu ditambah |

### 16.5 Error & Resilience Architecture (Gap)

Belum ada strategi error terpusat. Try-catch lokal di Service layer → return null/log. Queue job pakai database queue + retry default. **Belum ada:** error code standar, circuit breaker, fallback, timeout eksplisit, compensation transaction, dokumentasi retry/race-condition billing.

### 16.6 Data Flow Kritis: Billing & Payment (Gap)

Alur `Customer → Invoice → Payment → Activate` berjalan, tapi belum didokumentasikan/dites: idempotensi webhook Midtrans (duplikasi `Payment`), race condition manual vs otomatis, rollback bila `Invoice.update` gagal setelah `Payment.create`, partial payment / multiple invoice / write-off / refund / deposit / prorata / diskon / pajak / penalti **belum didukung** (hanya full-pay per invoice).

### 16.7 Multi-Tenant Lifecycle (Gap)

`BelongsToTenant` menangani isolasi query, tapi lifecycle tenant belum didokumentasikan: onboarding, configuration & branding per tenant, backup/restore/migration, subscription & limitasi, isolation guarantee saat `OdcPort`/`OdpPort` belum scoped.

---

## 17. Roadmap Summary

### v1.2 (Current — Deployed to Production)

**Selesai:** ✅ Rebranding ALKONEK · ✅ Live Network Topology sync Distribution · ✅ Monitoring Real-Time WAN-ISP · ✅ Optimasi performa (CDN, JS 504→122KB, caching) · ✅ Penyatuan desain tabel `.mon-table`/`.mon-thead` + pembersihan bug Blade.

**Planned (Prioritas P1 tertinggi):**

| Priority | Item | Business Value | Risk | Acceptance Criteria |
|----------|------|----------------|------|---------------------|
| P1 | Encrypt password MikroTik | Cegah kebocoran kredensial | Rendah | `encrypted` cast, dekripsi otomatis |
| P1 | `BelongsToTenant` ke `OdcPort`/`OdpPort` | Tutup data leak | Rendah | Query otomatis difilter tenant |
| P1 | Hapus sensitive file dari git history | Cegah kebocoran secret | Menengah | File tak ada di history; ganti cred |
| P2 | Enable SSL verification (configurable) | Keamanan koneksi | Rendah | Toggle di settings |
| P2 | Proteksi `reset_data.php` | Cegah destructif | Rendah | Gate auth/env flag |
| P2 | API auth + rate limiting | Cegah abuse | Rendah | Token/hmac + throttle |
| P3 | Define RPO/RTO + backup encryption | Recoverability terukur | Menengah | Dokumen + backup terenkripsi |

### v2.0 (Medium-term)
- Dashboard Owner/Management (role terpisah)
- Notifikasi realtime (WebSocket/Pusher)
- API publik (dengan auth)
- Dark mode
- **Billing gaps (4.1):** prorata, partial payment, diskon, pajak, refund, deposit, write-off, multiple invoice, grace period terkonfigurasi

### v3.0 (Long-term)
- NMS features
- Advanced reporting & analytics
- Multi-language support
- Mobile app (optional)
- Chaos/resilience test, formal load test, security audit

---

## 18. Glossary

| Istilah | Definisi |
|---------|----------|
| **OLT** | Optical Line Terminal — Perangkat di sisi ISP yang mengkonversi sinyal listrik ke optik |
| **ONU** | Optical Network Unit — Perangkat di sisi pelanggan (ONT/Modem fiber) |
| **ODC** | Optical Distribution Cabinet — Kabinet distribusi fiber optik |
| **ODP** | Optical Distribution Point — Titik distribusi fiber ke pelanggan |
| **ODC Port** | Port pada ODC yang menghubungkan ke ODP (inlet/outlet) |
| **ODP Port** | Port pada ODP yang terhubung ke customer |
| **STP** | Splicing / Termination Point — Titik sambungan fiber (upstream dari ODC) |
| **PPP** | Point-to-Point Protocol — Protokol koneksi internet PPPoE |
| **PPPoE** | PPP over Ethernet — Metode autentikasi koneksi internet pelanggan |
| **FTTH** | Fiber to The Home — Teknologi koneksi fiber optik sampai ke rumah |
| **Voucher** | Kode akses WiFi hotspot (username + password + durasi) |
| **Tenant** | Entitas bisnis (ISP) dalam sistem multi-tenant |
| **Midtrans** | Payment gateway untuk pembayaran online (QRIS, VA, dll) |
| **Isolir** | Status suspend otomatis karena overdue pembayaran |
| **Queue** | Antrian job (polling OLT, billing, dll) yang diproses async |
| **REST API** | HTTP API untuk integrasi dengan perangkat eksternal (MikroTik) |
| **Driver** | Implementasi spesifik per brand OLT (Huawei, ZTE, dsb) |
| **Jump Host** | SSH tunnel via server perantara untuk akses OLT |
