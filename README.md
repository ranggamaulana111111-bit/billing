<!-- ============================================================ -->
<!-- README.md — Project Overview (Ringkas)                       -->
<!-- ============================================================ -->

# ALKONEK — ISP Billing System v1.2

Sistem billing ISP terintegrasi untuk manajemen pelanggan, tagihan, pembayaran, inventaris, dan monitoring infrastruktur jaringan (OLT, ODP, MikroTik) — dibangun dengan **Laravel 12** + **Bootstrap 5.3** + **Leaflet.js** + **Chart.js**.

> **Status:** v1.2 — *Deployed to production* (aktif digunakan). Seluruh fitur inti sudah diimplementasikan dan berjalan, namun belum melalui verifikasi formal (benchmark, stress test, security audit). Lihat `docs/00_PROJECT/DESCRIPTION.md` §16 untuk catatan kejujuran status.

---

## 1. Gambaran Umum Aplikasi

ALKONEK (PT Alkonek Network Access) adalah sistem billing berbasis web untuk penyedia layanan internet (ISP) skala kecil hingga menengah. Aplikasi ini mencakup manajemen pelanggan, penagihan otomatis, pembayaran online, manajemen perangkat jaringan (OLT multi-brand, MikroTik, ODP/ODC), sistem voucher WiFi hotspot, **Live Network Topology** (OLT→ODC→ODP→ONU yang sinkron dengan modul distribusi), serta **Monitoring Real-Time** trafik WAN-ISP.

- **Nama Proyek:** ALKONEK ISP Billing System (PT Alkonek Network Access)
- **Versi:** 1.2
- **Status:** Deployed to production (belum di-benchmark/security-audit)
- **Brand:** ALKONEK BILLING / PT Alkonek Network Access

---

## 2. Tujuan Aplikasi

- Mengotomatiskan proses penagihan dan pembayaran ISP
- Memonitor perangkat jaringan (OLT, MikroTik) secara real-time
- Mengelola distribusi fiber optik (ODC/ODP) dengan peta interaktif
- Menyediakan portal customer self-service untuk cek tagihan dan pembayaran
- Mengelola voucher WiFi hotspot dengan sinkronisasi ke MikroTik
- Menyediakan **Live Network Topology** yang menyambungkan OLT → ODC → ODP → ONU secara visual dan sinkron dengan data distribusi
- Mendukung multi-tenant (setiap admin memiliki data sendiri)

---

## 3. Target Pengguna

| Role | Hak Akses |
|------|-----------|
| **Admin** | Akses penuh ke semua fitur termasuk pengaturan, backup, export, CRUD paket/voucher/distribusi |
| **Teknisi** | Akses ke dashboard, pelanggan, tagihan, pembayaran, OLT (full), MikroTik (read-only), voucher (read-only), distribusi (read-only), log |
| **Pelanggan** | Akses publik ke portal cek tagihan dan pembayaran via Midtrans |

---

## 4. Permasalahan yang Diselesaikan

1. **Penagihan manual** → Auto-generate invoice bulanan via scheduler
2. **Pembayaran offline** → Integrasi Midtrans + Xendit untuk pembayaran online (QRIS, VA, dll) via payment gateway abstraction
3. **Monitoring OLT terbatas** → SSH multi-brand (Huawei, ZTE, FiberHome, C-Data) dengan polling otomatis
4. **Manajemen ODP tidak terstruktur** → Peta interaktif Leaflet dengan ODC/Route/Point
5. **Voucher manual** → Generate, print, push ke MikroTik otomatis
6. **Pengingat tagihan** → reminder otomatis via aplikasi pelanggan Android (in-development)
7. **Multi-tenant** → Setiap tenant (ISP) memiliki data terpisah, admin/teknisi dalam satu tenant berbagi data

---

## 5. Teknologi yang Digunakan

### Backend
| Teknologi | Versi | Fungsi |
|-----------|-------|--------|
| Laravel | 12.x | Framework PHP utama |
| PHP | ^8.2 | Runtime |
| MySQL (Laragon) | — | Database development/lokal |
| MySQL (Aiven) | — | Database production (Vercel) |
| SQLite | — | Fallback / testing (`:memory:`) |
| phpseclib | ^3.0 | SSH ke OLT |
| Midtrans PHP | ^2.6 | Payment gateway Snap API |
| Laravel Socialite | ^5.27 | OAuth Google Login |
| DomPDF (barryvdh) | ^3.1 | Generate PDF invoice |
| RouterOS API | ^1.7 | REST API MikroTik |
| simple-qrcode | ^4.2 | QR code voucher (inline SVG) |

### Frontend
| Teknologi | Versi | Fungsi |
|-----------|-------|--------|
| Bootstrap | 5.3.8 | CSS framework (di-load via CDN jsDelivr, offloaded dari build) |
| Bootstrap Icons | 1.13.1 | Icons |
| Leaflet | 1.9.4 | Peta interaktif ODP/OLT (CDN defer, hanya di halaman perlu) |
| Chart.js | 4.5.1 | Grafik dashboard (CDN defer, hanya di halaman perlu) |
| Alpine.js | — | Interaktivitas ringan |
| Vite | 7.x | Asset bundler (hanya `app.css` + `app.js`) |

> **Optimasi (v1.2):** Bootstrap 5.3 di-bundle via npm + Vite (`resources/js/app.js`). Chart.js & Leaflet di-load via CDN `defer` hanya di halaman yang membutuhkan (`@push('scripts')`). Font Awesome & Google Fonts non-render-blocking. DashboardController di-cache (`Cache::remember` 90–300s). **Tailwind tidak terpasang di source** — artefak `style.css` di `public/hotspot/templates/*/assets/` hanya hasil import dari editor.

### Integrasi Pihak Ketiga
| Layanan | API | Fungsi |
|---------|-----|--------|
| Midtrans | Snap API | Pembayaran QRIS, VA, dll |
| Xendit | Invoice API | Pembayaran alternatif (payment gateway abstraction) |
| Google | Socialite OAuth 2.0 | Login dengan Google |
| MikroTik | REST API + SSH fallback | Manajemen hotspot, PPP, queue |
| GenieACS | TR-069 | Manajemen perangkat CPE (di `app/Modules/GenieACS`) |

---

## 6. Arsitektur Sistem

### Pola Arsitektur
- **Monolithic** dengan pemisahan Controller → Service → Model
- **Multi-tenant ringan** via Global Scope (`BelongsToTenant` trait) — 30 model memakainya
- **Driver Pattern** untuk OLT multi-brand (Huawei, ZTE, FiberHome, C-Data, + 5 driver tambahan)
- **Decorator Pattern** untuk Jump Host SSH tunnel & MikroTik SSH Proxy
- **Payment Gateway Abstraction** (`app/Services/Payment/`: interface + Midtrans & Xendit gateway)
- **Scheduled Tasks** untuk billing, polling OLT, auto-isolir, QoS, automation
- **Event-driven API** untuk sinkronasi voucher MikroTik
- **Job Queue** untuk polling OLT perangkat & job async (queue database)

### Alur Request
```
User (Tenant) → Browser → Vite (dev) / public/build (prod)
                        → Laravel Route → Middleware (auth, teknisi/admin)
                        → Controller
                        → Service (optional)
                        → Model/ORM (global scope tenant_id)
                        → Database (MySQL / SQLite testing)
                        → View (Blade) → Response HTML
```

### Alur Data Utama
```
Tenant → User (admin/teknisi)
 ├── Membuat Pelanggan (Customer) → auto-create Invoice
 ├── Pelanggan bayar → Payment (Midtrans/Xendit/offline) → Invoice status paid
 ├── Generate Voucher → push ke MikroTik → event-driven callback via API
 ├── Scan/ Poll OLT → update ONU status → RCA cable cut detection
 ├── Buat ODC → ODP Route → ODP Point / Odp → assign ke Customer
 └── Isolir: overdue → auto-suspend → set PPP Profile → add IP ke address-list → bayar → activate
```

### Alur Navigasi Pengguna
```
/ (welcome)
 ├── /login → /dashboard
 │    ├── /customers (CRUD)
 │    ├── /invoices (CRUD, print, PDF, reminder)
 │    ├── /payments (create, history)
 │    ├── /packages (view-only teknisi, CRUD admin)
 │    ├── /olts (CRUD, scan, monitoring, map, search ONU)
 │    ├── /vouchers (create, print, sync, report)
 │    ├── /mikrotik (dashboard, profiles, active, ppp, queues, monitoring)
   │    ├── /distribution (ODC/ODP/Route map)
   │    ├── /onu-health/topology/graph (Live Network Topology — OLT→ODC→ODP→ONU)
   │    ├── /logs (activity log)
  │    ├── /reports (admin only)
  │    ├── /settings (admin only)
  │    ├── /backups (admin only)
  │    ├── /voucher-profiles (admin only)
  │    ├── /mikrotik-routers (admin only)
  │    ├── /voucher-templates (admin only)
  │    ├── /odc/{odc} (admin only — detail ODC)
  │    └── /odp/{odp} (admin only — detail ODP)
  ├── /portal (public — cek tagihan & bayar via Midtrans/Xendit)
  ├── /vouchers/public (public — beli voucher)
  ├── /vouchers/check (public — cek status voucher)
  ├── /hotspot/{page} (public — halaman hotspot MikroTik)
  ├── /noc/* (NOC dashboard, features/map, linux-server, dns, vpn, speedtest, automation, dll)
  ├── /inventory/* (items, masuk, keluar, laporan-aset)
  ├── /onu-hotspot & /hotspot-customers (teknisi)
  └── /api/v1/mikrotik/hotspot-login (public — event-driven voucher login)
```

---

## 7. Struktur Folder Project

```
e-billing/
├── AGENTS.md                          # Petunjuk development
├── DESCRIPTION.md                     # Dokumentasi ini
├── PRD.md                             # Product Requirement Document
├── README.md                          # README Laravel default
├── composer.json                      # Dependency PHP
├── package.json                       # Dependency Node.js
├── vite.config.js                     # Konfigurasi Vite
├── phpunit.xml                        # Konfigurasi testing
├── vercel.json                        # Deployment Vercel
├── railway.json                       # Deployment Railway.app
├── start-ebilling.bat                 # Script start lokal
├── start-ebilling-lan.bat             # Script start LAN
│
├── app/
│   ├── Console/
│   │   └── Commands/                       # 22 commands (billing, polling, isolir, QoS, automation, dll)
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/                        # 3 (MikrotikHotspot, Odprute, + 1)
│   │   │   ├── Auth/                       # 3 (Login, Register, Socialite)
│   │   │   ├── Noc/                        # 14 controller NOC (GenieACS, Automation, TrafficEngineering, dll)
│   │   │   ├── BackupController.php
│   │   │   ├── CronController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── DistributionController.php
│   │   │   ├── ExportController.php
│   │   │   ├── HotspotCustomerController.php
│   │   │   ├── IncidentController.php
│   │   │   ├── IntegrationController.php
│   │   │   ├── InventoryController.php
│   │   │   ├── InvoiceController.php
│   │   │   ├── LogController.php
│   │   │   ├── MidtransController.php
│   │   │   ├── MikrotikController.php
│   │   │   ├── MikrotikRouterController.php
│   │   │   ├── MonitoringController.php
│   │   │   ├── NocController.php
│   │   │   ├── OdcController.php
│   │   │   ├── OdpController.php
│   │   │   ├── OltController.php
│   │   │   ├── OnuHealthController.php
│   │   │   ├── OnuHotspotController.php
│   │   │   ├── PackageController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── PortalController.php
│   │   │   ├── PublicVoucherController.php
│   │   │   ├── QosHealthController.php
│   │   │   ├── ReportController.php
│   │   │   ├── SettingController.php
│   │   │   ├── SitemapController.php
│   │   │   ├── TeknisiController.php
│   │   │   ├── UserController.php
│   │   │   ├── VoucherController.php
│   │   │   ├── VoucherPrintTemplateController.php
│   │   │   ├── VoucherProfileController.php
│   │   │   ├── VoucherReportController.php
│   │   │   ├── VoucherTemplateController.php
│   │   │   └── XenditController.php
│   │   │
│   │   └── Middleware/
│   │       ├── IsAdmin.php
│   │       ├── IsNoc.php
│   │       └── IsTeknisiOrAdmin.php
│   │
│   ├── Jobs/
│   │   └── PollOltJob.php                  # Job polling OLT (timeout=60s, tries=3)
│   │
│   ├── Mail/
│   │   ├── InvoiceReminder.php
│   │   └── PaymentConfirmation.php
│   │
│   ├── Models/                             # 40 model + 2 traits
│   │   ├── Traits/
│   │   │   ├── BelongsToTenant.php         # Aktif — global scope tenant_id (30 model)
│   │   │   └── BelongsToUser.php           # LEGACY — dead code (tidak dipakai)
│   │   ├── ActivityLog.php
│   │   ├── AutomationJob.php
│   │   ├── AutomationJobLog.php
│   │   ├── AutomationTrigger.php
│   │   ├── ConfigVersion.php
│   │   ├── Customer.php
│   │   ├── Incident.php
│   │   ├── IncidentNotification.php
│   │   ├── InterfaceChangeLog.php
│   │   ├── InventoryItem.php
│   │   ├── InventoryTransaction.php
│   │   ├── Invoice.php
│   │   ├── MikrotikInterfaceMetadata.php
│   │   ├── MikrotikRouter.php
│   │   ├── NetworkConfigAuditLog.php
│   │   ├── NetworkMetric.php
│   │   ├── NetworkMetricAggregated.php
│   │   ├── Odc.php
│   │   ├── OdcPort.php                     # ⚠️ TIDAK punya BelongsToTenant — potensi data leak
│   │   ├── Odp.php                         # Model ODP baru (nama Indonesia)
│   │   ├── OdpPoint.php                    # Model ODP lama (masih ada)
│   │   ├── OdpPort.php                     # ⚠️ TIDAK punya BelongsToTenant — potensi data leak
│   │   ├── OdpRoute.php
│   │   ├── Olt.php
│   │   ├── OltPort.php
│   │   ├── Onu.php
│   │   ├── OnuMonitoringHistory.php
│   │   ├── Package.php
│   │   ├── Payment.php
│   │   ├── PingResult.php
│   │   ├── RouterosSyncedConfig.php
│   │   ├── RouterosSyncLog.php
│   │   ├── Setting.php
│   │   ├── Tenant.php                      # Root multi-tenancy
│   │   ├── User.php
│   │   ├── Voucher.php
│   │   ├── VoucherPrintTemplate.php
│   │   ├── VoucherProfile.php
│   │   └── VoucherTemplate.php
│   │
│   ├── Modules/
│   │   └── GenieACS/                       # Modul TR-069 (Contracts, DTO, Repositories, Services, Support)
│   │
│   ├── Providers/
│   │   ├── AppServiceProvider.php          # Bootstrap 5 pagination
│   │   └── BillingServiceProvider.php
│   │
│   └── Services/                           # 55 file service
│       ├── IncidentNotificationService.php
│       ├── InterfaceCenterService.php
│       ├── MidtransService.php
│       ├── MikrotikService.php             # REST API wrapper + SSH fallback
│       ├── MikrotikSshService.php
│       ├── Automation/                     # AutomationScheduler, Worker, Trigger, JobService
│       ├── Billing/                        # BillingService, InvoiceGenerator, CustomerCodeGenerator
│       ├── Mikrotik/                       # RouterConnectionPool/Manager, RouterOSApiService, Config/, Internet/, Security/, Sync/, TrafficEngineering/
│       ├── Monitoring/                     # FiberTopology, HealthScore, PingMonitor, Diagnosis, SpeedTest
│       ├── Olt/
│       │   ├── Contracts/OltConnector.php
│       │   ├── Drivers/                    # Zte, Huawei, FiberHome, CData, ChineseOlt, Global, Hioso, Hsgq, Vsol + JumpHost, MikrotikSshProxy
│       │   ├── Factory/OltConnectorFactory.php
│       │   └── SshTunnel.php
│       ├── Payment/                        # PaymentGatewayInterface, MidtransGateway, XenditGateway, PaymentService
│       └── SmartQos/SmartQosService.php
│
├── bootstrap/                       # Laravel bootstrap
├── config/                          # Konfigurasi Laravel
├── database/
│   ├── database.sqlite              # Database lokal (fallback)
│   ├── factories/
│   │   ├── CustomerFactory.php
│   │   ├── InvoiceFactory.php
│   │   ├── PackageFactory.php
│   │   ├── UserFactory.php
│   │   └── VoucherFactory.php
│   ├── migrations/                  # 98 file migrasi
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── BillingSeeder.php
│       ├── OdpRouteSeeder.php
│       ├── SettingSeeder.php
│       └── VoucherProfileSeeder.php
│
├── public/
│   ├── build/                       # Asset terkompilasi Vite
│   ├── hotspot/                     # 6 halaman hotspot HTML
│   └── index.php
│
├── resources/
│   ├── css/
│   │   └── app.css                  # ~5.000 baris custom CSS
│   ├── js/
│   │   ├── app.js
│   │   └── bootstrap.js
│   └── views/
│       ├── auth/                    # login, register
│       ├── backups/
│       ├── customer/                # create, edit, index
│       ├── distribution/
│       ├── emails/                  # invoice-reminder, payment-confirmation
│       ├── invoices/                # create, edit, index, pdf, print
│       ├── layouts/
│       ├── logs/
│       ├── midtrans/
│       ├── mikrotik/                # 7 file (dashboard, monitoring, dll)
│       ├── mikrotik-routers/
│       ├── odc/                     # show
│       ├── odp/                     # show
│       ├── olt/                     # 7 file
│       ├── packages/
│       ├── payments/                # create, history
│       ├── portal/                  # index, invoices, pay
│       ├── reports/
│       ├── settings/
│       ├── sitemap.blade.php
│       ├── voucher-profiles/
│       ├── voucher-templates/       # preview (⚠️ index.blade.php belum ada)
│       ├── vouchers/                # 9 file
│       ├── dashboard.blade.php
│       └── welcome.blade.php
│       └── dashboard.blade.php.backup  # orphan — backup file
│
├── routes/
│   ├── web.php                      # Semua route aplikasi
│   ├── api.php                      # Route API (POST /api/v1/mikrotik/hotspot-login)
│   └── console.php                  # Schedule definitions (17 schedules)
│
├── storage/                         # Log, cache, backups
│
└── tests/
    ├── Feature/                     # 7 file — 54 test methods
    │   ├── AuthTest.php
    │   ├── CustomerTest.php
    │   ├── DistributionTest.php
    │   ├── ExampleTest.php
    │   ├── InvoiceTest.php
    │   ├── PackageTest.php
    │   └── SitemapTest.php
    └── Unit/                        # 10 file — 88 test methods (Services/Billing, Payment, GenieACS, Olt)
        ├── ExampleTest.php
        └── Services/
```

---

## 8. Database dan Relasi Data

### 98 Migrations — Relasi Antar Tabel

```
tenants ── users ──┬── customers ───────┬── invoices ───┬── payments
                   │                    │               │
                   │                    ├── onus ───────┘
                   │                    ├── odp_points
                   │                    └── odp_ports ←── odps
                   │                                    │
                   ├── packages                         └── odc_ports ←── odcs
                   │                                                    │
                   ├── vouchers ─────┬── voucher_profiles              └── odp_routes ─── odp_points
                   │                 ├── mikrotik_routers
                   │                 └── voucher_templates
                   ├── settings
                   ├── activity_logs
                   ├── olts ─── olt_ports ─── onus ──── customers
                   ├── odcs ─── odp_routes ── odp_points ─── customers
                   ├── cache / cache_locks
                   ├── jobs / job_batches / failed_jobs
                   └── sessions
```

### Model & Relasi

| Model | Table | Traits | Relasi Utama |
|-------|-------|--------|-------------|
| Tenant | tenants | — | hasMany: User, Customer, Invoice, Payment, Package, Voucher, Olt, OltPort, Onu, Odc, OdpRoute, OdpPoint, Odp, Setting, ActivityLog |
| User | users | Authenticatable, HasFactory, Notifiable | belongsTo: Tenant; hasMany: customers, invoices, payments, packages, vouchers, odcs, odpRoutes, odpPoints, settings, activityLogs |
| Customer | customers | BelongsToTenant, HasFactory | belongsTo: Package, OdpPoint, Odp, OdpPort; hasMany: Invoice, Onu |
| Invoice | invoices | BelongsToTenant, HasFactory | belongsTo: Customer; hasMany: Payment |
| Payment | payments | BelongsToTenant | belongsTo: Invoice |
| Package | packages | BelongsToTenant, HasFactory | hasMany: Customer |
| Olt | olts | BelongsToTenant | hasMany: OltPort |
| OltPort | olt_ports | BelongsToTenant | belongsTo: Olt; hasMany: Onu |
| Onu | onus | BelongsToTenant | belongsTo: OltPort; belongsTo: Customer |
| Odc | odcs | BelongsToTenant | hasMany: OdpRoute, OdcPort, Odp |
| OdpRoute | odp_routes | BelongsToTenant | belongsTo: Odc; hasMany: OdpPoint |
| OdpPoint | odp_points | BelongsToTenant | belongsTo: OdpRoute; hasMany: Customer |
| Odp | odps | BelongsToTenant | belongsTo: Odc; hasMany: OdpPort; hasMany: Customer |
| OdcPort | odc_ports | — ⚠️ | belongsTo: Odc; **TIDAK punya tenant scope** |
| OdpPort | odp_ports | — ⚠️ | belongsTo: Odp; **TIDAK punya tenant scope** |
| Voucher | vouchers | BelongsToTenant, HasFactory | belongsTo: VoucherProfile, MikrotikRouter, VoucherTemplate |
| VoucherProfile | voucher_profiles | BelongsToTenant | hasMany: Voucher |
| MikrotikRouter | mikrotik_routers | BelongsToTenant | hasMany: Voucher |
| VoucherTemplate | voucher_templates | BelongsToTenant | hasMany: Voucher |
| Setting | settings | BelongsToTenant | — |
| ActivityLog | activity_logs | BelongsToTenant | belongsTo: User |
| Incident | incidents | BelongsToTenant | belongsTo: Olt, Customer |
| IncidentNotification | incident_notifications | — | belongsTo: Incident (tidak ada tenant scope) |
| NetworkMetric | network_metrics | BelongsToTenant | morphs: subject (OLT/router) |
| NetworkMetricAggregated | network_metric_aggregated | BelongsToTenant | — |
| PingResult | ping_results | — | belongsTo: NetworkMetric target |
| AutomationJob / AutomationTrigger / AutomationJobLog | automation_jobs / automation_triggers / automation_job_logs | BelongsToTenant | trigger → job → log |
| ConfigVersion / NetworkConfigAuditLog | config_versions / network_config_audit_logs | BelongsToTenant | history config RouterOS |
| RouterosSyncedConfig / RouterosSyncLog | routeros_synced_configs / routeros_sync_logs | BelongsToTenant | snapshot config sync |
| InterfaceChangeLog / MikrotikInterfaceMetadata | interface_change_logs / mikrotik_interface_metadata | — | metadata interface |
| OnuMonitoringHistory | onu_monitoring_history | — | history sinyal ONU |
| InventoryItem / InventoryTransaction | inventory_items / inventory_transactions | BelongsToTenant | item ↔ transaksi stok |
| VoucherPrintTemplate | voucher_print_templates | BelongsToTenant | layout print voucher |

---

## 9. Modul Utama

### 9.1 Autentikasi & Manajemen User
- **Controller:** `LoginController`, `RegisterController`, `SocialiteController`
- **Model:** `User`
- **Routes:** `/login`, `/register`, `/auth/google/redirect`, `/auth/google/callback`
- **Fitur:** Login email/password, Register, Login Google OAuth, Logout
- **Role:** admin dan teknisi

### 9.2 Dashboard
- **Controller:** `DashboardController`
- **Route:** `GET /dashboard`
- **View:** `dashboard.blade.php`
- **Fitur:**
  - 7 stat cards: total/active/suspended/inactive customers, total routes, total ODP points, capacity usage
  - Revenue chart (bar chart 6 bulan)
  - Payment status donut chart
  - Payment method breakdown
  - Package distribution chart
  - Tabel: paket internet, ODP points (progress bar), pembayaran terakhir, pelanggan terbaru, tagihan belum dibayar
  - Aktivitas terakhir (timeline)
  - Maps ODP interaktif (Leaflet marker hijau/merah)

### 9.3 Manajemen Pelanggan (Customers)
- **Controller:** `CustomerController` (376 baris)
- **Model:** `Customer` — BelongsToTenant
- **Routes:**
  - `GET /customers` — List + stat cards (total, active, suspended, inactive)
  - `GET /customer/create` — Form create
  - `POST /customer` — Store (auto-create invoice)
  - `GET /customer/{id}/edit` — Edit form
  - `PUT /customer/{id}` — Update
  - `DELETE /customer/{id}` — Delete
  - `POST /customer/{id}/suspend` — Suspend + disable PPPoE MikroTik
  - `POST /customer/{id}/activate` — Activate + enable PPPoE + auto-create ONU
  - `POST /customer/{id}/sync-onu` — Sync single ONU
  - `POST /customers/sync-pppoe` — Sync PPPoE ke MikroTik (admin)
  - `POST /olts/sync-all-onu` — Sync semua ONU (admin)
- **Field:** name, location, phone, email, package_id, odp_point_id, pppoe_username, due_date, status, suspended_at
- **Otomatis:** Saat create customer, invoice pertama langsung dibuat

### 9.4 Tagihan (Invoices)
- **Controller:** `InvoiceController` (269 baris)
- **Model:** `Invoice` — BelongsToTenant
- **Routes:**
  - `GET /invoices` — List + filter (status, search, date range)
  - `GET /invoices/create` — Form create
  - `POST /invoices` — Store
  - `PUT /invoice/{id}` — Update
  - `DELETE /invoice/{id}` — Delete
  - `GET /invoice/paid/{id}` — Mark as paid
  - `GET /invoice/print/{id}` — Print view
  - `GET /invoice/pdf/{id}` — Download PDF (DomPDF)
  - `GET /invoice/email-reminder/{id}` — Kirim email reminder
  - `GET /invoice/email-payment/{id}` — Kirim email konfirmasi pembayaran
- **Field:** invoice_code, customer_id, amount, payment_status, paid_at, payment_method, midtrans_order_id

### 9.5 Pembayaran (Payments)
- **Controller:** `PaymentController` (71 baris)
- **Model:** `Payment` — BelongsToTenant
- **Routes:**
  - `GET /payment/create/{invoice}` — Form create
  - `POST /payments` — Store (auto-update invoice status)
  - `GET /payment/history/{invoice}` — History pembayaran
  - `DELETE /payment/{id}` — Hapus (auto-update invoice jika total <= 0)
- **Payment Methods:** cash, transfer, qris, midtrans

### 9.6 Paket Internet (Packages)
- **Controller:** `PackageController` (135 baris)
- **Model:** `Package` — BelongsToTenant, HasFactory
- **Routes:**
  - `GET /packages` — List + search + filter status (teknisi read-only)
  - `POST /packages` — Create (admin)
  - `PUT /packages/{id}` — Update (admin)
  - `DELETE /packages/{id}` — Delete (admin, proteksi jika ada customer)
  - `POST /packages/mass-bill` — Generate tagihan massal (admin)
- **Field:** name, speed, description, price, billing_cycle, mikrotik_profile, is_active

### 9.7 OLT Management
- **Controller:** `OltController` (650 baris)
- **Model:** `Olt`, `OltPort`, `Onu`
- **Routes:**
  - `GET /olts` — List OLT + stat ONU (total/online/offline)
  - `GET /olts/create` — Form create
  - `POST /olts` — Store
  - `GET /olts/{olt}` — Detail per-port dengan ONU dashboard
  - `GET /olts/{olt}/edit` — Edit form
  - `PUT /olts/{olt}` — Update
  - `DELETE /olts/{olt}` — Delete
  - `POST /olts/{olt}/test` — Test SSH connection
  - `POST /olts/{olt}/scan` — Scan ONU semua port
  - `POST /olts/{olt}/onu/{onu}/reboot` — Reboot ONU
  - `DELETE /olts/{olt}/onu/{onu}` — Remove ONU
  - `POST /olts/{olt}/ports` — Sync ports manual
  - `POST /onu/{onu}/link-customer` — Link ONU ke customer
  - `POST /olts/{olt}/sync-mikrotik` — Sync ONU dari MikroTik
  - `GET /olts-monitoring` — Monitoring Gangguan: **semua pelanggan** dengan redaman (Rx Power), urut dari sinyal terlemah (early warning sebelum putus)
  - `GET /olts/map` — Map OLT Leaflet
  - `GET /olts/{olt}/live` — Live data JSON API
  - `GET /olts/export` — Export CSV OLT
  - `GET /onus/export` — Export CSV ONU
  - `GET /onus/search` — Search ONU
- **Field OLT:** name, brand, model, ip_address, ssh_port, username, password, snmp (community, version, port), jump_host, jump_port, jump_username, jump_password, location, latitude, longitude, status, notes, last_polled_at
- **Password:** encrypted cast
- **Jump Host Support:** SSH tunnel via host lain
- **MikroTik Proxy:** SSH via MikroTik `tool/ssh` REST API

### 9.8 OLT Multi-Brand Driver Pattern
- **Interface:** `App\Services\Olt\Contracts\OltConnector`
- **Method:** connect, disconnect, testConnection, getSystemInfo, getOnuList, getOnuDetail, provisionOnu, removeOnu, rebootOnu, getPortStatus, getOpticalPower
- **Factory:** `OltConnectorFactory::make($brand, $olt)` — return driver sesuai brand + optional jump/proxy wrapper
- **Drivers:**

| Driver | Class | CLI Pattern | Enable Mode |
|--------|-------|-------------|-------------|
| Huawei | HuaweiConnector | `system-view` → `display ont info {slot} {port}` | `system-view` |
| ZTE | ZteConnector | `enable` → `configure terminal` → `show onu unquiet...` | `enable` → `configure terminal` |
| FiberHome | FiberHomeConnector | `show ont list slot {s} port {p}` | Langsung |
| C-Data | CDataConnector | `enable` → `config` → `show ont info slot {s} port {p}` | `enable` → `config` |

- **Wrappers:**
  - `JumpHostConnector` — SSH tunnel decorator (wrapper)
  - `MikrotikSshProxyConnector` — SSH via MikroTik REST API `tool/ssh`

### 9.9 Voucher WiFi (Hotspot)
- **Controller:** `VoucherController` (355 baris)
- **Model:** `Voucher` — BelongsToTenant, HasFactory
- **QR Code:** `simplesoftwareio/simple-qrcode` (inline SVG, no external API)
- **Routes:**
  - `GET /vouchers` — List + report + profiles + routers + templates (multi-tab)
  - `GET /vouchers/create` — Form create (admin)
  - `POST /vouchers` — Store + push ke MikroTik (admin)
  - `POST /vouchers/quick-print` — Quick print dari dashboard (admin)
  - `GET /vouchers/{id}/print` — Print single voucher
  - `POST /vouchers/print-batch` — Print batch
  - `POST /vouchers/{id}/used` — Mark as used
  - `DELETE /vouchers/{id}` — Delete + remove dari MikroTik (admin)
  - `POST /vouchers/sync-mikrotik` — Sync status ke MikroTik (admin)
  - `GET /vouchers/report` — Report voucher (filterable)
- **Field:** voucher_profile_id, voucher_template_id, username, password, duration_hours, price, prefix, speed, quota_limit, validity_days, shared_users, printed_count, downloaded, uploaded, total_traffic, ip_address, mac_address, last_login_at, router_id, status, used_at, expires_at
- **Generate:** `Voucher::generate($hours, $count, $extra)` — static method dengan random username/password
- **Store/QuickPrint:** Logic generate + push MikroTik diekstrak ke private method `generateAndPush()` (no duplicated code)
- **Status:** active, used, expired
- **Push:** Otomatis push ke MikroTik saat generate

### 9.10 Voucher Profiles
- **Controller:** `VoucherProfileController` (75 baris)
- **Model:** `VoucherProfile` — BelongsToTenant
- **Routes (admin):**
  - `GET /voucher-profiles` — List
  - `POST /voucher-profiles` — Create
  - `PUT /voucher-profiles/{id}` — Update
  - `DELETE /voucher-profiles/{id}` — Delete (proteksi jika ada voucher)
- **Field:** name, speed, price, time_limit (jam), quota_limit (MB), validity_days, shared_users, is_active

### 9.11 MikroTik Routers
- **Controller:** `MikrotikRouterController` (92 baris)
- **Model:** `MikrotikRouter` — BelongsToTenant
- **Routes (admin):**
  - `GET /mikrotik-routers` — List
  - `POST /mikrotik-routers` — Create
  - `PUT /mikrotik-routers/{id}` — Update
  - `DELETE /mikrotik-routers/{id}` — Delete (proteksi jika ada voucher)
  - `POST /mikrotik-routers/{id}/test` — Test koneksi
- **Field:** name, host, port, username, password, hotspot_server, is_active

### 9.12 Voucher Templates (Hotspot Pages)
- **Controller:** `VoucherTemplateController` (96 baris)
- **Model:** `VoucherTemplate` — BelongsToTenant
- **Routes (admin):**
  - `POST /voucher-templates` — Create
  - `PUT /voucher-templates/{id}` — Update
  - `DELETE /voucher-templates/{id}` — Delete
  - `GET /voucher-templates/{id}/preview` — Preview halaman
  - `GET /voucher-templates/{id}/preview/{page}` — Preview page tertentu
- **6 Halaman:** login (content), status, redirect, error, alive, logout
- **Auto-write:** Saat saved, otomatis menulis file ke `public/hotspot/`
- **Hotspot Import Command:** `hotspot:import` — import file HTML ke database

### 9.13 Distribusi ODP/ODC
- **Controller:** `DistributionController` (218 baris)
- **Model:** `Odc`, `OdpRoute`, `OdpPoint` — semuanya BelongsToTenant
- **Routes (teknisi read-only, admin CRUD):**
  - `GET /distribution` — Map interaktif + stats
  - `POST /distribution/odcs` — Create ODC
  - `PUT /distribution/odcs/{id}` — Update ODC
  - `DELETE /distribution/odcs/{id}` — Delete (proteksi jika ada route)
  - `POST /distribution/routes` — Create route (dengan coordinates JSON polyline)
  - `PUT /distribution/routes/{id}` — Update route
  - `DELETE /distribution/routes/{id}` — Delete (proteksi jika ada point)
  - `POST /distribution/points` — Create ODP point
  - `PUT /distribution/points/{id}` — Update point
  - `DELETE /distribution/points/{id}` — Delete (proteksi jika ada customer)
- **API JSON:**
  - `GET /api/odp-routes` — Data routes untuk Leaflet
  - `GET /api/odp-points` — Data points untuk Leaflet
- **Struktur Hirarki:** ODC → OdpRoute (dengan polyline) → OdpPoint (dengan lat/lng + port capacity)

### 9.14 Manajemen MikroTik
- **Controller:** `MikrotikController`
- **Service:** `MikrotikService` — REST API wrapper (lengkap)
- **Routes (teknisi view, admin CRUD):**
  - `GET /mikrotik` — Dashboard (system resource, identity, health, uptime, latency)
  - `GET /mikrotik/profiles` — Hotspot profiles (teknisi view, admin CRUD)
  - `GET /mikrotik/active` — Active hotspot sessions + disconnect
  - `GET /mikrotik/ppp` — PPP secrets (teknisi view, admin CRUD)
  - `GET /mikrotik/queues` — Simple queues (teknisi view, admin CRUD)
  - `GET /monitoring` — Bandwidth monitoring
  - `POST /mikrotik/backup` — Trigger backup (admin)
  - `GET /mikrotik/live` — Live data JSON API (admin)
- **MikrotikService methods:**
  - System: testConnection, getSystemResource, getSystemIdentity, getSystemHealth
  - Interfaces: getInterfaces, getInterfaceTraffic
  - Hotspot: addHotspotUser, removeHotspotUser, getHotspotUsers, getUserByUsername, getActiveHotspotSessions, disconnectHotspotSession
  - Hotspot Profile: getHotspotProfiles, addHotspotProfile, removeHotspotProfile
  - PPP: getPppSecrets, addPppSecret, removePppSecret, getPppActive, disconnectPppSession
  - Queue: getSimpleQueues, addSimpleQueue, removeSimpleQueue
  - Utility: getLatency, createBackup, getLog

### 9.15 Portal Publik
- **Controller:** `PortalController` (110 baris)
- **Routes (public):**
  - `GET /portal` — Halaman cek tagihan (input nomor telepon)
  - `POST /portal` — Lookup invoice by phone
  - `GET /portal/bayar/{invoice}` — Bayar via Midtrans
  - `GET /portal/finish` — Halaman selesai pembayaran

### 9.16 Midtrans Payment Gateway
- **Controller:** `MidtransController` (117 baris)
- **Service:** `MidtransService` — Midtrans Snap API wrapper
- **Routes:**
  - `GET /midtrans/pay/{invoice}` — Redirect pembayaran (authenticated)
  - `POST /midtrans/notification` — Webhook notification (public)
  - `GET /midtrans/finish` — Halaman finish (authenticated)
- **Fitur:** Snap token generation, notification handler auto-update invoice status

### 9.17 Public Voucher Self-Service
- **Controller:** `PublicVoucherController` (90 baris)
- **Routes (public):**
  - `GET /vouchers/public` — Halaman pembelian voucher mandiri
  - `POST /vouchers/public/generate` — Generate voucher publik
  - `GET /vouchers/check` — Form cek status voucher
  - `POST /vouchers/check-status` — Cek status voucher (by username + password)

### 9.18 Laporan (Reports)
- **Controller:** `ReportController` (70 baris)
- **Routes (admin):**
  - `GET /reports` — Halaman report dengan statistik revenue bulanan, outstanding, customer, chart 12 bulan, metode pembayaran, top unpaid

### 9.19 Log Aktivitas
- **Controller:** `LogController` (38 baris)
- **Model:** `ActivityLog` — BelongsToTenant
- **Routes:**
  - `GET /logs` — Log aktivitas dengan filter (action, search, date range)

### 9.20 Export CSV
- **Controller:** `ExportController` (81 baris)
- **Routes (admin):**
  - `GET /export/invoices` — Export CSV invoices
  - `GET /export/payments` — Export CSV payments
- **Export OLT/ONU:** via OltController (`/olts/export`, `/onus/export`)

### 9.21 Backup
- **Controller:** `BackupController` (79 baris)
- **Routes (admin):**
  - `GET /backups` — Daftar backup
  - `POST /backups/database` — Download backup SQLite
  - `GET /backups/download/{filename}` — Download backup
  - `DELETE /backups/{filename}` — Hapus backup

### 9.22 Pengaturan (Settings)
- **Controller:** `SettingController` (76 baris)
- **Model:** `Setting` — BelongsToTenant, key-value store
- **Routes (admin):**
  - `GET /settings` — Form settings
  - `POST /settings` — Update settings
  - `GET /settings/test-mikrotik` — Test MikroTik connection
- **Setting Keys:** company_name, company_address, company_phone, bank_name, bank_account, bank_holder, invoice_footer, mikrotik_host/port/user/password/hotspot_server, midtrans_server/client_key, midtrans_is_production, voucher_username/password_length, late_fee_amount/grace_days, default_due_date, wa_enabled/wa_api_url/wa_api_key/wa_sender/wa_recipient, telegram_enabled/telegram_bot_token/telegram_chat_id

### 9.23 Xendit Payment Gateway
- **Controller:** `XenditController`
- **Service:** `XenditGateway` (implement `PaymentGatewayInterface` di `app/Services/Payment/`)
- **Routes:**
  - `GET /xendit/pay/{invoice}` — Redirect pembayaran
  - `POST /xendit/notification` — Webhook notification (public)
  - `GET /xendit/finish` — Halaman finish
  - `GET /xendit/gateway` — Form settings gateway
  - `GET /portal/bayar-xendit/{invoice}` — Bayar via portal
- **Fitur:** Payment gateway alternatif selain Midtrans via abstraction `PaymentService`

### 9.24 Integrasi Perangkat (Settings → Integrations)
- **Controller:** `IntegrationController`
- **Routes:**
  - `GET /settings/integrations` — Dashboard integrasi MikroTik/OLT
  - CRUD + test + live untuk MikroTik router dan OLT
- **Fitur:** Kelola koneksi perangkat runtime tanpa deploy, live data per perangkat

### 9.25 Manajemen Inventory
- **Controller:** `InventoryController`
- **Model:** `InventoryItem`, `InventoryTransaction`
- **Routes:**
  - `GET /inventory/items` — CRUD item aset
  - `GET /inventory/masuk` — Transaksi barang masuk
  - `GET /inventory/keluar` — Transaksi barang keluar
  - `GET /inventory/laporan-aset` — Laporan aset

### 9.26 NOC (Network Operation Center) — 14 controller di `app/Http/Controllers/Noc/`
- **Dashboard:** `Noc\DashboardController` → `GET /noc/dashboard`
- **Map Features:** `Noc\FeaturesController` → `/noc/features/map/*` (mikrotik/olt/genieacs CRUD + connect + sync)
- **Traffic Engineering:** `Noc\TrafficEngineeringController`
- **Network Config:** `Noc\NetworkConfigController`, `ConfigModuleController`, `ConfigRepositoryController`
- **Security Policy:** `Noc\SecurityPolicyController`
- **Sync Dashboard:** `Noc\SyncDashboardController`
- **Internet Service:** `Noc\InternetServiceController`
- **MikroTik Dashboard:** `Noc\MikrotikDashboardController`, `MikrotikDeviceController`
- **Interface Center:** `Noc\InterfaceCenterController`
- **Automation:** `Noc\AutomationController`
- **GenieACS:** `Noc\GenieacsController`
- Banyak route di bawah `/noc/*` masih commented-out (menunggu implementasi penuh); lihat `routes/web.php`

### 9.26b Live FTTH Map Screen (NOC Features Map)

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

### 9.27 GenieACS (TR-069)
- **Modul:** `app/Modules/GenieACS/` (Contracts, DTO, Exceptions, Repositories, Services, Support)
- **Controller:** `Noc\GenieacsController` (route di-comment saat ini)
- **Fitur:** Manajemen CPE via TR-069 — discovery, presets, faults, reboot, factory reset, refresh object
- **Test:** `GenieACSClientTest`, `GenieacsDTOTest`, `GenieacsRepositoryTest`

### 9.28 Automation Engine
- **Model:** `AutomationJob`, `AutomationTrigger`, `AutomationJobLog`
- **Services:** `app/Services/Automation/` (Scheduler, Worker, Trigger, JobService)
- **Commands:** `automation:scheduler` + `automation:worker --once` (everyMinute)
- **Fitur:** Trigger-based job automation (scheduler + worker + trigger engine)

### 9.29 Incidents & SLA
- **Model:** `Incident`, `IncidentNotification`
- **Services:** `IncidentNotificationService`
- **Command:** `incident:check-sla` (hourly), `incidents:purge` (monthly), `incidents:purge-auto` (everyMinute)
- **Fitur:** Pencatatan incident jaringan, notifikasi SLA via WA, purge otomatis riwayat

### 9.30 Monitoring, QoS & SmartQoS
- **Model:** `NetworkMetric`, `NetworkMetricAggregated`, `PingResult`, `OnuMonitoringHistory`
- **Services:** `app/Services/Monitoring/` (HealthScore, PingMonitor, Diagnosis, SpeedTest, FiberTopology) + `app/Services/SmartQos/SmartQosService.php`
- **Commands:** `network:data-collect` (5min + aggregate hourly + prune 6h), `qos:optimize`, `qos:setup`, `qos:sync`, `qos:health`
- **Fitur:** Collect metrics trafik, skor kesehatan jaringan, diagnosis, ping monitor, speedtest, optimasi QoS otomatis, topology fiber OLT→ODC→ODP→ONU

### 9.31 RouterOS Config Sync
- **Model:** `RouterosSyncedConfig`, `RouterosSyncLog`, `ConfigVersion`, `NetworkConfigAuditLog`
- **Service:** `app/Services/Mikrotik/Sync/RouterosConfigSyncService.php` + `Config/`
- **Command:** `routeros:sync-config` (everyFifteenMinutes)
- **Fitur:** Sinkronisasi config MikroTik ke DB, versioning config, audit log

### 9.32 Multi-Tenancy & Roles
- **Model:** `Tenant` (root), `User` (belongsTo Tenant)
- **Trait:** `BelongsToTenant` — global scope `tenant_id` pada 30 model
- **Middleware:** `IsAdmin` (admin), `IsTeknisiOrAdmin` (teknisi), `IsNoc` (noc)
- **⚠️ Catatan:** `OdcPort` & `OdpPort` tidak punya tenant scope (potensi data leak)

---

## 10. Daftar Halaman

### 10.1 Public Pages (Tanpa Login)
| URL | View | Fungsi |
|-----|------|--------|
| `/` | welcome | Landing page |
| `/login` | auth.login | Form login |
| `/register` | auth.register | Form register |
| `/portal` | portal.index | Cek tagihan pelanggan |
| `/portal/bayar/{invoice}` | portal.pay | Bayar via Midtrans |
| `/portal/bayar-xendit/{invoice}` | portal.pay | Bayar via Xendit |
| `/portal/finish` | — | Redirect after payment |
| `/vouchers/public` | vouchers.public | Beli voucher publik |
| `/vouchers/check` | vouchers.check | Cek status voucher |
| `/hotspot/{page}` | hotspot/*.html | Halaman hotspot MikroTik |
| `/sitemap.xml` | sitemap | XML sitemap |
| `/api/cron/run` | — | Trigger scheduler eksternal |
| `POST /api/v1/mikrotik/hotspot-login` | — | Event-driven voucher login (dari MikroTik hotspot) |
| `/api/v1/mikrotik/hotspot-login` | — | Route API (via `routes/api.php`) |

### 10.2 Authenticated Pages (Teknisi & Admin)
| URL | View | Fungsi |
|-----|------|--------|
| `/dashboard` | dashboard | Dashboard utama |
| `/customers` | customer.index | Daftar pelanggan |
| `/customer/create` | customer.create | Tambah pelanggan |
| `/customer/{id}/edit` | customer.edit | Edit pelanggan |
| `/invoices` | invoices.index | Daftar tagihan |
| `/invoices/create` | invoices.create | Buat tagihan |
| `/invoice/{id}/edit` | invoices.edit | Edit tagihan |
| `/invoice/print/{id}` | invoices.print | Cetak tagihan |
| `/payment/create/{invoice}` | payments.create | Catat pembayaran |
| `/payment/history/{invoice}` | payments.history | Riwayat pembayaran |
| `/packages` | packages.index | Daftar paket |
| `/olts` | olt.index | Daftar OLT |
| `/olts/create` | olt.create | Tambah OLT |
| `/olts/{olt}` | olt.show | Detail OLT |
| `/olts/{olt}/edit` | olt.edit | Edit OLT |
| `/olts-monitoring` | olt.monitoring | Monitoring ONU |
| `/olts/map` | olt.map | Map OLT |
| `/onus/search` | olt.search | Cari ONU |
| `/onu-hotspot` | onu-hotspot.index | ONU hotspot (sync/link-customer/unlink) |
| `/hotspot-customers` | hotspot-customers.index | Customer hotspot dari OLT |
| `/vouchers` | vouchers.index | Daftar voucher (multi-tab) |
| `/vouchers/create` | vouchers.create | Buat voucher |
| `/voucher-profiles` | voucher-profiles.index | Profile voucher |
| `/mikrotik` | mikrotik.dashboard | Dashboard MikroTik |
| `/mikrotik/profiles` | mikrotik.profiles | Hotspot profiles |
| `/mikrotik/active` | mikrotik.active | Active sessions |
| `/mikrotik/ppp` | mikrotik.ppp | PPP secrets |
| `/mikrotik/queues` | mikrotik.queues | Simple queues |
| `/monitoring` | mikrotik.monitoring | BW monitoring |
| `/distribution` | distribution.index | Map ODP/ODC |
| `/logs` | logs.index | Activity log |
| `/teknisi/dashboard` | teknisi.dashboard | Dashboard teknisi |
| `/xendit/pay/{invoice}` | — | Bayar via Xendit |
| `/xendit/gateway` | — | Form settings Xendit |
| `/noc/dashboard` | noc.dashboard | Dashboard NOC |
| `/noc/features/map` | noc.features.map | Map features NOC (mikrotik/olt/genieacs) |
| `/inventory/items` | inventory.items | Inventory aset |
| `/inventory/masuk` | inventory.masuk | Barang masuk |
| `/inventory/keluar` | inventory.keluar | Barang keluar |
| `/inventory/laporan-aset` | inventory.laporan-aset | Laporan aset |

### 10.3 Admin-Only Pages
| URL | View | Fungsi |
|-----|------|--------|
| `/settings` | settings.index | Pengaturan sistem |
| `/settings/integrations` | settings.integrations | Integrasi MikroTik/OLT |
| `/reports` | reports.index | Laporan keuangan |
| `/backups` | backups.index | Backup database |
| `/mikrotik-routers` | mikrotik-routers.index | Multi-router config |
| `/odc/{odc}` | odc.show | Detail ODC |
| `/odp/{odp}` | odp.show | Detail ODP |

---

## 11. Sistem Authentication

### Autentikasi
- Login via email + password (session-based)
- Login via Google OAuth (Laravel Socialite)
- Register akun baru (default role: `teknisi`)
- Logout → session di-invalidate

### Multi-Tenancy (BelongsToTenant Trait)
Semua model utama menggunakan trait `BelongsToTenant` yang:
- Menerapkan **Global Scope** `WHERE tenant_id = ?` pada setiap query
- **Auto-fill** `tenant_id` saat create (dari `Auth()->user()->tenant_id`)
- Menyediakan scope `forTenant($id)` dan `allTenants()` (skip scope)
- **Legacy:** `BelongsToUser` trait masih ada di `app/Models/Traits/` tapi **sudah tidak dipakai** (dead code)

### Middleware
| Middleware | Alias | Filter |
|-----------|-------|--------|
| `IsTeknisiOrAdmin` | `teknisi` | role === 'admin' atau 'teknisi' |
| `IsAdmin` | `admin` | role === 'admin' |
| `IsNoc` | `noc` | akses area NOC (`app/Http/Middleware/IsNoc.php`) |

Route split: `teknisi` middleware sebagai base auth untuk semua user; `admin` middleware khusus route sensitif.

---

## 12. Scheduled Tasks (Console) — 17 schedules

```php
Schedule::command('billing:process')->dailyAt('08:00');
Schedule::command('invoices:purge-paid')->dailyAt('08:30');
Schedule::command('olt:poll')->hourly()->withoutOverlapping();
Schedule::command('customers:onu-sync')->hourly()->withoutOverlapping();
Schedule::command('customer:auto-isolir')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('customer:sync-isolir-ips')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('customers:backup')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('routeros:sync-config')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('network:data-collect')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('network:data-collect --aggregate')->hourly()->withoutOverlapping();
Schedule::command('network:data-collect --prune')->everySixHours()->withoutOverlapping();
Schedule::command('qos:optimize')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('incident:check-sla')->hourly();
Schedule::command('incidents:purge')->monthly()->withoutOverlapping();
Schedule::command('incidents:purge-auto')->everyMinute()->withoutOverlapping();
Schedule::command('automation:scheduler')->everyMinute()->withoutOverlapping();
Schedule::command('automation:worker --once')->everyMinute()->withoutOverlapping();
```

### Command Details (22 commands)

| Command | File | Schedule | Fungsi |
|---------|------|----------|--------|
| `billing:process` | `Commands/BillingProcess.php` | `dailyAt('08:00')` | Generate invoice bulanan untuk semua pelanggan aktif per tenant |
| `invoices:purge-paid` | `Commands/PurgePaidInvoices.php` | `dailyAt('08:30')` | Purge invoice lunas |
| `olt:poll` | `Commands/PollOlt.php` | `hourly()` | Dispatch `PollOltJob` per OLT aktif (timeout=60s, tries=3). Opsi `--queue` untuk async, default sync. Fallback MikroTik jika scan 0 ONU |
| `customers:onu-sync` | `Commands/SyncCustomerOnu.php` | `hourly()` | Sync semua pelanggan aktif ke ONU di OLT dari data PPPoE MikroTik. Opsi `--olt` untuk OLT tertentu |
| `customer:auto-isolir` | `Commands/AutoIsolir.php` | `dailyAt('00:30')` | Auto-suspend pelanggan overdue, set PPP Profile isolir di MikroTik, tambah IP ke firewall address-list |
| `customer:sync-isolir-ips` | `Commands/SyncIsolirIps.php` | `everyFiveMinutes()` | Sinkronasi IP customer suspended ke firewall address-list MikroTik |
| `customers:backup` | `Commands/BackupCustomers.php` | `dailyAt('03:00')` | Backup data pelanggan PPPoE/Hotspot ke JSON |
| `routeros:sync-config` | `Commands/RouterosSyncConfig.php` | `everyFifteenMinutes()` | Sync config RouterOS ke DB |
| `network:data-collect` | `Commands/NetworkDataCollect.php` | `everyFiveMinutes()` | Collect metrics; `--aggregate` hourly, `--prune` 6h |
| `qos:optimize` | `Commands/QosOptimize.php` | `everyFiveMinutes()` | Optimasi QoS |
| `incident:check-sla` | `Commands/CheckIncidentSla.php` | `hourly()` | Cek SLA incident |
| `incidents:purge` / `incidents:purge-auto` | `Commands/PurgeIncidentHistory*.php` | monthly / everyMinute | Purge riwayat incident |
| `automation:scheduler` / `automation:worker --once` | `Commands/AutomationSchedulerTick.php` / `AutomationWorkerProcess.php` | everyMinute | Engine automation |
| `hotspot:import` | `Commands/ImportHotspotFiles.php` | Manual | Import file HTML dari `public/hotspot/*.html` ke database sebagai VoucherTemplate baru |
| `olt:batch-link` | `Commands/BatchLinkOnu.php` | Manual | Batch link ONU ke customer |
| `qos:setup` / `qos:sync` / `qos:health` | `Commands/QosSetup.php` / `QosSync.php` / `QosHealth.php` | Manual | Setup, sync, cek health QoS |
| `voucher:sync-mikrotik` | `Commands/SyncVoucherMikrotik.php` | **Non-aktif** | Dulu otomatis, sekarang digantikan oleh event-driven API `POST /api/v1/mikrotik/hotspot-login` |
| `mikrotik:setup-isolir` | `Commands/MikrotikSetupIsolir.php` | Manual | Setup PPP Profile-Isolir, DST-NAT redirect, DROP filter rules di MikroTik |

---

## 13. Route Map Lengkap

### Public Routes
```
GET  /                              → welcome
GET  /login                         → login form
POST /login                         → login action
POST /logout                        → logout
GET  /register                      → register form
POST /register                      → register action
GET  /auth/{provider}/redirect      → socialite redirect
GET  /auth/{provider}/callback      → socialite callback
POST /midtrans/notification         → midtrans webhook
GET  /sitemap.xml                   → sitemap XML
GET  /portal                        → portal index (cek tagihan)
POST /portal                        → portal lookup
GET  /portal/bayar/{invoice}        → portal bayar
GET  /portal/finish                 → portal finish
GET  /vouchers/public               → voucher public self-service
POST /vouchers/public/generate      → generate voucher publik
GET  /vouchers/check                → cek status voucher form
POST /vouchers/check-status         → cek status voucher action
GET  /hotspot/{page}                → serve hotspot static page
GET  /api/cron/run                  → trigger scheduler eksternal (token-protected)
POST /api/v1/mikrotik/hotspot-login → event-driven voucher login callback
```

### Authenticated Routes (Teknisi & Admin — `teknisi` middleware)
```
GET    /dashboard                         → Dashboard
GET    /customers                         → Customer list
GET    /customer/create                   → Create customer form
POST   /customer                          → Store customer
GET    /customer/{customer}/edit          → Edit customer form
PUT    /customer/{customer}               → Update customer
DELETE /customer/{customer}               → Delete customer
POST   /customer/{customer}/suspend       → Suspend + disable PPPoE
POST   /customer/{customer}/activate      → Activate + enable PPPoE
POST   /customer/{customer}/sync-onu      → Sync single ONU
GET    /invoices                          → Invoice list (filterable)
GET    /invoices/create                   → Create invoice form
POST   /invoices                          → Store invoice
GET    /invoice/{invoice}/edit            → Edit invoice form
PUT    /invoice/{invoice}                 → Update invoice
DELETE /invoice/{invoice}                 → Delete invoice
GET    /invoice/paid/{invoice}            → Mark as paid
GET    /invoice/print/{invoice}           → Print invoice
GET    /invoice/email-reminder/{invoice}  → Send email reminder
GET    /invoice/email-payment/{invoice}   → Send payment confirmation email
GET    /invoice/pdf/{invoice}             → Download PDF invoice
GET    /payment/create/{invoice}          → Create payment form
POST   /payments                          → Store payment
GET    /payment/history/{invoice}         → Payment history
DELETE /payment/{payment}                 → Delete payment
GET    /vouchers                          → Voucher list (multi-tab)
GET    /vouchers/{voucher}/print          → Print single voucher
GET    /vouchers/print-batch              → Print batch vouchers
POST   /vouchers/{voucher}/used           → Mark voucher as used
GET    /vouchers/report                   → Voucher report
GET    /mikrotik                          → MikroTik dashboard
GET    /mikrotik/profiles                 → Hotspot profiles (read-only)
GET    /mikrotik/active                   → Active hotspot sessions
POST   /mikrotik/active/disconnect/{id}   → Disconnect hotspot session
POST   /mikrotik/active/ppp-disconnect/{id} → Disconnect PPP session
GET    /mikrotik/ppp                      → PPP secrets (read-only)
GET    /mikrotik/queues                   → Simple queues (read-only)
GET    /monitoring                        → Bandwidth monitoring
GET    /logs                              → Activity log
GET    /distribution                      → ODP/ODC distribution map
GET    /olts                              → OLT list
GET    /olts/create                       → Create OLT form
POST   /olts                              → Store OLT
GET    /olts/{olt}                        → OLT detail
GET    /olts/{olt}/edit                   → Edit OLT form
PUT    /olts/{olt}                        → Update OLT
DELETE /olts/{olt}                        → Delete OLT
POST   /olts/{olt}/test                   → Test SSH connection
POST   /olts/{olt}/scan                   → Scan ONU all ports
POST   /olts/{olt}/onu/{onu}/reboot       → Reboot ONU
DELETE /olts/{olt}/onu/{onu}              → Remove ONU
POST   /olts/{olt}/ports                  → Sync OLT ports
POST   /onu/{onu}/link-customer           → Link ONU to customer
POST   /olts/{olt}/sync-mikrotik          → Sync ONU from MikroTik
GET    /olts-monitoring                   → ONU monitoring view
GET    /olts/map                          → OLT map
GET    /olts/{olt}/live                   → Live data JSON API
GET    /olts/export                       → Export CSV OLT
GET    /onus/export                       → Export CSV ONU
GET    /onus/search                       → Search ONU
GET    /packages                          → Package list (read-only)
GET    /midtrans/pay/{invoice}            → Pay via Midtrans
GET    /midtrans/finish                   → Midtrans finish page
GET    /api/odp-routes                    → JSON API routes
GET    /api/odp-points                    → JSON API points
```

### Admin-Only Routes (`admin` middleware)
```
GET    /settings                              → Settings form
POST   /settings                              → Update settings
GET    /settings/test-mikrotik                → Test MikroTik connection
GET    /reports                               → Reports index
POST   /mikrotik/profiles                     → Create hotspot profile
DELETE /mikrotik/profiles/{id}                → Delete hotspot profile
POST   /mikrotik/ppp                          → Create PPP secret
DELETE /mikrotik/ppp/{id}                     → Delete PPP secret
POST   /mikrotik/queues                       → Create simple queue
DELETE /mikrotik/queues/{id}                  → Delete simple queue
POST   /mikrotik/backup                       → Trigger MikroTik backup
GET    /mikrotik/live                         → Live data JSON API
GET    /voucher-profiles                      → Voucher profile list
POST   /voucher-profiles                      → Create voucher profile
PUT    /voucher-profiles/{voucherProfile}     → Update voucher profile
DELETE /voucher-profiles/{voucherProfile}     → Delete voucher profile
GET    /mikrotik-routers                      → Router list
POST   /mikrotik-routers                      → Create router
PUT    /mikrotik-routers/{mikrotikRouter}     → Update router
DELETE /mikrotik-routers/{mikrotikRouter}     → Delete router
POST   /mikrotik-routers/{mikrotikRouter}/test → Test router connection
POST   /voucher-templates                     → Create voucher template
PUT    /voucher-templates/{template}          → Update voucher template
DELETE /voucher-templates/{template}          → Delete voucher template
GET    /voucher-templates/{template}/preview   → Preview template
GET    /voucher-templates/{template}/preview/{page?} → Preview specific page
POST   /vouchers                              → Create voucher
GET    /vouchers/create                       → Create voucher form
POST   /vouchers/quick-print                  → Quick print from dashboard
DELETE /vouchers/{voucher}                    → Delete voucher
POST   /vouchers/sync-mikrotik                → Sync vouchers to MikroTik
POST   /packages                              → Create package
PUT    /packages/{package}                    → Update package
DELETE /packages/{package}                    → Delete package
POST   /packages/mass-bill                    → Mass billing
POST   /customers/sync-pppoe                  → Sync PPPoE to MikroTik
POST   /olts/sync-all-onu                     → Sync all ONU
POST   /distribution/odcs                     → Create ODC
PUT    /distribution/odcs/{odc}               → Update ODC
DELETE /distribution/odcs/{odc}               → Delete ODC
POST   /distribution/routes                   → Create ODP route
PUT    /distribution/routes/{odpRoute}         → Update ODP route
DELETE /distribution/routes/{odpRoute}         → Delete ODP route
POST   /distribution/points                   → Create ODP point
PUT    /distribution/points/{odpPoint}         → Update ODP point
DELETE /distribution/points/{odpPoint}         → Delete ODP point
POST   /distribution/odps                     → Create ODP baru (model baru)
GET    /odc/{odc}                             → Detail ODC dengan ports/ODPs
GET    /odp/{odp}                             → Detail ODP dengan ports/customer
GET    /backups                               → Backup list
GET    /backups/download/{filename}            → Download backup
DELETE /backups/{filename}                     → Delete backup
POST   /backups/database                      → Create database backup
GET    /export/invoices                       → Export CSV invoices
GET    /export/payments                       → Export CSV payments
```

---

## 14. Daftar Fitur Lengkap

### Fitur Diimplementasikan

> Status "diimplementasikan" berarti fitur ada, berjalan, dan sudah dipakai — bukan hasil pengukuran formal. Beberapa aspek (security, NFR, error architecture) masih partial; lihat `DESCRIPTION.md` §16.

| # | Fitur | Kategori | Controller | Status |
|---|-------|----------|------------|--------|
| 1 | Login/Register | Auth | LoginController, RegisterController | ✅ |
| 2 | Google OAuth Login | Auth | SocialiteController | ✅ |
| 3 | Logout | Auth | LoginController | ✅ |
| 4 | Role-based access (admin/teknisi) | Auth | IsAdmin, IsTeknisiOrAdmin middleware | ✅ |
| 5 | Dashboard statistik & grafik | Dashboard | DashboardController | ✅ |
| 6 | Manajemen pelanggan CRUD | Customer | CustomerController | ✅ |
| 7 | Suspend/Activate pelanggan | Customer | CustomerController | ✅ |
| 8 | Sync PPPoE ke MikroTik | Customer | CustomerController | ✅ |
| 9 | Auto-create ONU saat activate | Customer | CustomerController | ✅ |
| 10 | Sync ONU single/all | Customer | CustomerController | ✅ |
| 11 | Manajemen tagihan CRUD | Invoice | InvoiceController | ✅ |
| 12 | Mark invoice as paid | Invoice | InvoiceController | ✅ |
| 13 | Print invoice (view) | Invoice | InvoiceController | ✅ |
| 14 | Download PDF invoice | Invoice | InvoiceController | ✅ |
| 15 | Kirim email reminder | Invoice | InvoiceController (Mail) | ✅ |
| 16 | Kirim email konfirmasi bayar | Invoice | InvoiceController (Mail) | ✅ |
| 18 | Catat pembayaran (cash/transfer/QRIS) | Payment | PaymentController | ✅ |
| 19 | History pembayaran | Payment | PaymentController | ✅ |
| 20 | Hapus pembayaran (auto-update invoice) | Payment | PaymentController | ✅ |
| 21 | Manajemen paket CRUD | Package | PackageController | ✅ |
| 22 | Mass billing (generate tagihan massal) | Package | PackageController | ✅ |
| 23 | OLT CRUD multi-brand | OLT | OltController | ✅ |
| 24 | Test koneksi SSH OLT | OLT | OltController | ✅ |
| 25 | Scan ONU per port | OLT | OltController | ✅ |
| 26 | Reboot ONU remote | OLT | OltController | ✅ |
| 27 | Remove ONU | OLT | OltController | ✅ |
| 28 | Link ONU ke customer | OLT | OltController | ✅ |
| 29 | Sync port OLT | OLT | OltController | ✅ |
| 30 | Detail OLT per-port | OLT | OltController | ✅ |
| 31 | Monitoring ONU (offline/sinyal lemah) | OLT | OltController | ✅ |
| 32 | Map OLT Leaflet | OLT | OltController | ✅ |
| 33 | Live data JSON API OLT | OLT | OltController | ✅ |
| 34 | Export CSV OLT | OLT | OltController | ✅ |
| 35 | Export CSV ONU | OLT | OltController | ✅ |
| 36 | Search ONU | OLT | OltController | ✅ |
| 37 | Sync ONU dari MikroTik | OLT | OltController | ✅ |
| 38 | Jump Host SSH tunnel | OLT | JumpHostConnector | ✅ |
| 39 | MikroTik SSH Proxy | OLT | MikrotikSshProxyConnector | ✅ |
| 40 | Generate voucher WiFi | Voucher | VoucherController | ✅ |
| 41 | Print voucher (single/batch) | Voucher | VoucherController | ✅ |
| 42 | Mark voucher used | Voucher | VoucherController | ✅ |
| 43 | Delete voucher | Voucher | VoucherController | ✅ |
| 44 | Push voucher ke MikroTik | Voucher | VoucherController | ✅ |
| 45 | Sync voucher dengan MikroTik | Voucher | VoucherController | ✅ |
| 46 | Quick print dari dashboard | Voucher | VoucherController | ✅ |
| 47 | Voucher Profile CRUD | Voucher | VoucherProfileController | ✅ |
| 48 | MikroTik Router CRUD | Voucher | MikrotikRouterController | ✅ |
| 49 | Voucher Template CRUD | Voucher | VoucherTemplateController | ✅ |
| 50 | Preview halaman hotspot | Voucher | VoucherTemplateController | ✅ |
| 51 | Public voucher self-service | Voucher | PublicVoucherController | ✅ |
| 52 | Public cek status voucher | Voucher | PublicVoucherController | ✅ |
| 53 | Voucher report filterable | Voucher | VoucherReportController | ✅ |
| 54 | Manajemen ODC CRUD | Distribution | DistributionController | ✅ |
| 55 | Manajemen ODP Route CRUD | Distribution | DistributionController | ✅ |
| 56 | Manajemen ODP Point CRUD | Distribution | DistributionController | ✅ |
| 57 | Map ODP/ODC interaktif | Distribution | DistributionController | ✅ |
| 58 | Proteksi delete berantai | Distribution | DistributionController | ✅ |
| 59 | MikroTik dashboard | MikroTik | MikrotikController | ✅ |
| 60 | Hotspot profiles view | MikroTik | MikrotikController | ✅ |
| 61 | Active sessions + disconnect | MikroTik | MikrotikController | ✅ |
| 62 | PPP secrets CRUD | MikroTik | MikrotikController | ✅ |
| 63 | Simple queues CRUD | MikroTik | MikrotikController | ✅ |
| 64 | Backup MikroTik | MikroTik | MikrotikController | ✅ |
| 65 | Bandwidth monitoring | MikroTik | MikrotikController | ✅ |
| 66 | Live data JSON MikroTik | MikroTik | MikrotikController | ✅ |
| 67 | Midtrans payment (Snap API) | Payment | MidtransController | ✅ |
| 68 | Midtrans webhook notification | Payment | MidtransController | ✅ |
| 69 | Portal publik cek tagihan | Portal | PortalController | ✅ |
| 70 | Portal bayar via Midtrans | Portal | PortalController | ✅ |
| 71 | Pengaturan sistem (key-value) | Settings | SettingController | ✅ |
| 72 | Test MikroTik dari settings | Settings | SettingController | ✅ |
| 73 | Backup database (download) | Backup | BackupController | ✅ |
| 74 | Export CSV invoice | Export | ExportController | ✅ |
| 75 | Export CSV payment | Export | ExportController | ✅ |
| 76 | Laporan keuangan | Report | ReportController | ✅ |
| 77 | Activity log dengan filter | Log | LogController | ✅ |
| 78 | Sitemap XML | Utility | SitemapController | ✅ |
| 79 | Billing otomatis harian | Schedule | BillingProcess | ✅ |
| 80 | Polling OLT otomatis (hourly) | Schedule | PollOlt (PollOltJob) | ✅ |
| 81 | Sync ONU customer otomatis (hourly) | Schedule | SyncCustomerOnu | ✅ |
| 82 | Auto-isolir pelanggan overdue | Isolir | AutoIsolir | ✅ |
| 83 | Sync IP suspended ke firewall MikroTik | Isolir | SyncIsolirIps | ✅ |
| 84 | Setup isolir di MikroTik (PPP Profile, DST-NAT, DROP) | Isolir | MikrotikSetupIsolir | ✅ |
| 85 | Setup isolir di MikroTik (PPP Profile, DST-NAT, DROP) | Isolir | MikrotikSetupIsolir | ✅ |
| 86 | Event-driven voucher login API | Voucher | MikrotikHotspotController | ✅ |
| 87 | Cron trigger eksternal (Vercel workaround) | Utility | CronController | ✅ |
| 88 | Manajemen ODP baru (model Odp) | Distribution | DistributionController | ✅ |
| 89 | Manajemen ODC Port / ODP Port | Distribution | OdcController, OdpController | ✅ |
| 90 | Live Network Topology (OLT→ODC→ODP→ONU) | Topology | OnuHealthController (FiberTopologyService) | ✅ |
| 91 | Topology sinkron dengan modul Distribution | Topology | FiberTopologyService | ✅ |
| 92 | Monitoring Real-Time trafik WAN-ISP (rate delta 1s) | Monitoring | MonitoringController | ✅ |
| 93 | Xendit payment gateway | Payment | XenditController, XenditGateway | ✅ |
| 94 | Payment gateway abstraction (interface) | Payment | PaymentService, PaymentGatewayInterface | ✅ |
| 95 | Integrasi perangkat (Settings → Integrations) | Integration | IntegrationController | ✅ |
| 96 | Manajemen inventory & aset | Inventory | InventoryController | ✅ |
| 97 | Dashboard NOC | NOC | Noc\DashboardController | ✅ |
| 98 | NOC features map (MikroTik/OLT/GenieACS) | NOC | Noc\FeaturesController | ✅ |
| 99 | Automation engine (scheduler + worker + trigger) | NOC | Noc\AutomationController, app/Services/Automation/ | ✅ |
| 100 | Incidents & SLA check | NOC | IncidentController, incident:check-sla | ✅ |
| 101 | Network metrics & SmartQoS | Monitoring | NetworkDataCollect, qos:optimize, SmartQosService | ✅ |
| 102 | RouterOS config sync | NOC | routeros:sync-config | ✅ |
| 103 | ONU hotspot (sync/link/unlink) | OLT | OnuHotspotController | ✅ |
| 104 | Hotspot customers dari OLT | OLT | HotspotCustomerController | ✅ |
| 105 | Teknisi dashboard | Dashboard | TeknisiController | ✅ |
| 106 | Backup customers (PPPoE/Hotspot) JSON | Schedule | BackupCustomers | ✅ |
| 107 | Purge invoice lunas otomatis | Schedule | PurgePaidInvoices | ✅ |
| 108 | Voucher print templates (multi-layout) | Voucher | VoucherPrintTemplateController | ✅ |

### Fitur Sebagian Selesai

| # | Fitur | Status | Catatan |
|---|-------|--------|---------|
| 1 | Multi-router MikroTik support | ✅ Selesai | CRUD router + implementasi di semua service |
| 2 | Voucher traffic tracking (download/upload) | ✅ Selesai | Kolom sudah ada di model, namun belum ada sinkronasi traffic otomatis dari MikroTik |

### Fitur Non-Aktif / Digantikan

| # | Fitur | Status | Keterangan |
|---|-------|--------|------------|
| 1 | Sync voucher MikroTik otomatis (scheduler) | 🔄 Digantikan | Dulu `everyFiveMinutes()`, sekarang digantikan event-driven API `POST /api/v1/mikrotik/hotspot-login` |

### Fitur Belum Tersedia

| # | Fitur | Keterangan |
|---|-------|------------|
| 1 | Export CSV voucher | Belum ada route/controller untuk export voucher ke CSV |
| 2 | Halaman index Voucher Template | Controller menyebut `voucher-templates.index` tapi file view tidak ditemukan |

---

## 15. OLT Driver Interface

```php
interface OltConnector {
    connect(host, port, username, password): bool
    disconnect(): void
    testConnection(): array
    getSystemInfo(): array
    getOnuList(slot, port): array
    getOnuDetail(onuId): array
    provisionOnu(data): array
    removeOnu(onuId): array
    rebootOnu(onuId): array
    getPortStatus(slot, port): array
    getOpticalPower(onuId): array
}
```

### Perintah CLI per Brand

| Aksi | Huawei | ZTE | FiberHome | C-Data |
|------|--------|-----|-----------|--------|
| Masuk mode | `system-view` | `enable` → `configure terminal` | (langsung) | `enable` → `config` |
| Info sistem | `display version` | `show system information` | `show system-info` | `show version` |
| Daftar ONU | `display ont info {slot} {port}` | `show onu unquiet interface gpon-olt_{slot}/{port}` | `show ont list slot {slot} port {port}` | `show ont info slot {slot} port {port}` |
| Rx/Tx power | `display ont optical-info {s} {p} {o}` | `show onu optical-info {s} {p} {o}` | `show ont optic slot {s} port {p} ont {o}` | `show ont optical-info slot {s} port {p} ont {o}` |
| Reboot ONU | `interface gpon {s}/{p}` → `ont reset {o}` | `interface gpon-olt_{s}/{p}` → `onu reset {o}` | `ont reset slot {s} port {p} ont {o}` | `interface gpon {s}/{p}` → `ont reset {o}` |
| Hapus ONU | `interface gpon {s}/{p}` → `ont delete {o}` | `interface gpon-olt_{s}/{p}` → `no onu {o}` | `ont delete slot {s} port {p} ont {o}` | `interface gpon {s}/{p}` → `no ont add {o}` |
| Provision | `ont add {o} {sn}` + `ont port native-vlan` | `onu {o} type ont sn {sn}` | `ont add slot {s} port {p} sn {sn}` | `ont add {o} sn-auth {sn}` + line/srv profile |

---

## 16. State Management

- **Multi-Tenancy:** Global scope `tenant_id` di semua model utama (`BelongsToTenant` trait)
- **Session:** Database-driven (MySQL local), Cookie (Vercel)
- **Cache:** Database-driven (MySQL local), Array (Vercel)
- **Queue:** Database-driven (MySQL local), Sync (Vercel)
- **Settings:** Key-value store di tabel `settings` per tenant
- **Legacy:** `BelongsToUser` trait masih ada tapi dead code (tidak dipakai model manapun)

---

## 17. Build dan Deployment

### Local Development
```bash
# Setup awal
composer setup

# Development (serve + queue + logs + vite)
composer dev

# Testing
composer test

# Format code
./vendor/bin/pint

# Manual artisan (Laragon PHP 8.3 — php tidak ada di PATH)
"C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe" artisan {command}
```

### Start Scripts
- `start-ebilling.bat` — Start lokal di `127.0.0.1:8000`
- `start-ebilling-lan.bat` — Start LAN di `0.0.0.0:8000` (bisa diakses dari HP)

### Vercel Deployment
- **Trigger:** Push ke branch `main` / `master`
- **Runtime:** `vercel-php@0.9.0` (PHP 8.5 di Vercel)
- **Framework:** `null` (bukan Laravel Vite) — custom `api/index.php`
- **Output:** `public/` directory
- **Routes:** Semua request → `api/index.php`
- **Database:** MySQL Aiven dengan SSL (`MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false`)
- **Cold start:** Migrations di `api/index.php` dengan try-catch
- **Session:** Cookie (file system readonly)
- **Cache:** Array (tidak bisa file/database)
- **Queue:** Sync (blocking)
- **Constraint:** `fake()` unavailable di PHP 8.5 — jangan pakai Factory di production path

### Railway.app Backup
- **Builder:** Nixpacks
- **Command:** `composer install --no-dev --optimize-autoloader` + cache config/route/view
- **Start:** `php artisan serve --host=0.0.0.0 --port=$PORT`

### Environment Variables
Lihat `.env.example` dan `vercel.json` untuk daftar lengkap environment variables.

---

## 18. Testing

- **Framework:** PHPUnit 11 + Mockery
- **Database:** SQLite `:memory:` (tidak perlu DB eksternal)
- **Suites:** Unit + Feature
- **Test files:** 17 files (7 Feature + 10 Unit) — **142 test methods total** (54 Feature + 88 Unit)
- **6 Feature test classes** menggunakan `RefreshDatabase`: Auth, Customer, Distribution, Invoice, Package, Sitemap
- **Coverage:**
  - Auth: login, register, dashboard, logout, ODP data in dashboard
  - Customer: CRUD, suspend, activate, validation, auto-create invoice
  - Invoice: CRUD, mark paid, print, destroy
  - Package: CRUD (via admin), search, status filter, destroy protection (with customers)
  - Distribution: ODC/Route/Point full CRUD, duplicate rejection, cascade delete protection
  - Sitemap: XML sitemap, public URLs included
  - Unit `Services/`: Billing (InvoiceGenerator, CustomerCodeGenerator), Payment (Midtrans, Xendit, PaymentService), GenieACS (Client, DTO, Repository), Olt (ChineseOltParser)
- **Factory:** `UserFactory` default role `teknisi` + `admin()` state
- **Command:** `php artisan test` atau `./vendor/bin/phpunit`
- **⚠️ KNOWN ISSUE:** suite saat ini RED (sebagian besar test `RefreshDatabase` error akibat migrasi tenant). Penyebab utama: migrasi `2026_06_22_000004_add_tenant_id_to_business_tables.php` memakai `DROP CONSTRAINT IF EXISTS` (sintaks MySQL/Postgres) yang gagal di SQLite. Lihat `AGENTS.md`.

---

## 19. Catatan Pengembangan

1. **CSS sepenuhnya custom** — `resources/css/app.css` (~5.000 baris) menggunakan custom design system dengan CSS custom properties, gradient, glassmorphism. **Tailwind tidak terpasang** di source (hanya artefak compiled di `public/hotspot/templates/*/assets/style.css`). Seluruh tabel pakai `.mon-table` + `.mon-thead`. Bootstrap 5.3 di-bundle via Vite (`resources/js/app.js`), bukan CDN.
2. **OLT polling** menggunakan Job per-OLT (`PollOltJob`) dengan timeout 60s, tries=3. Jika scan OLT gagal, fallback sync dari MikroTik. Juga menjalankan RCA (Root Cause Analysis) untuk cable cut detection.
3. **MikroTik multi-router** — MikrotikRouter model dengan CRUD terpisah. Service mendukung konstruktor dengan parameter router.
4. **Voucher** memiliki kolom traffic tracking (downloaded, uploaded, total_traffic) namun belum ada mekanisme sinkronasi otomatis dari MikroTik.
5. **Migrations idempotent** — semua migration baru menggunakan guard `hasTable()`/`hasColumn()` untuk safety Vercel cold-start.
6. **Password OLT** dienkripsi menggunakan Laravel `encrypted` cast. **Namun password MikroTik router tidak di-encrypt** (stored in plaintext).
7. **Hotspot files** di `public/hotspot/` ditulis otomatis saat VoucherTemplate disimpan, tapi di Vercel (readonly filesystem) mekanisme ini akan gagal — perlu dynamic route.
8. **Notifikasi pelanggan (Fonnte/WhatsApp) telah dihapus total** dari codebase (`FonnteService`, `SendWhatsAppNotification`, config `services.fonnte`, `fonnte_token`). Notifikasi direncanakan via aplikasi Android pelanggan (in-development); `IncidentNotification` dibuat berstatus `pending` sebagai data untuk aplikasi tersebut. Yang tersisa hanyalah **link kontak/chat WA** (tombol WA di peta NOC & "No. WA:" di portal).
9. **Pengaturan notifikasi di peta FTTH** — tombol Notifikasi (bell) di toolbar map membuka dropdown "Pengaturan WhatsApp" & "Pengaturan Telegram" (enable checkbox + URL/API key/nomor atau bot token/chat id, tersimpan di tabel `settings` dengan key `wa_*` / `telegram_*`).
10. **BelongsToUser** trait sudah tidak dipakai — semua model menggunakan **`BelongsToTenant`** dengan global scope `tenant_id`. `BelongsToUser` adalah dead code.
11. **Isolir Subsystem** — 3 commands (`AutoIsolir`, `SyncIsolirIps`, `MikrotikSetupIsolir`) untuk auto-suspend pelanggan telat bayar: set PPP Profile isolir, tambah IP ke firewall address-list, DST-NAT redirect ke halaman pembayaran. **Tidak ada halaman publik `/isolir/{customer}` lagi** — semua aksi lewat command + status `suspended` di `CustomerController`.
12. **Event-driven voucher sync** — `POST /api/v1/mikrotik/hotspot-login` menggantikan scheduler `voucher:sync-mikrotik`. Dipicu dari On-Login script MikroTik User Profile.
13. **Chart.js & Leaflet** di-load via CDN (`defer`) hanya di halaman yang membutuhkan (`@push('scripts')`), tidak lagi diimport di `resources/js/app.js`. Bootstrap 5.3 di-bundle via npm + Vite (bukan CDN). Build Vite hanya menghasilkan `app.css` + `app.js`.
14. **Security concerns:** `OdcPort` dan `OdpPort` model tidak punya BelongsToTenant scope (potensi data leak). SSL verification disabled untuk koneksi MikroTik REST API (`withoutVerifying()`). Cron token lewat query parameter `?token=` yang terbaca di server access logs. Password MikroTik tidak di-encrypt (beda OLT yang pakai `encrypted` cast).
15. **Payment abstraction** — `app/Services/Payment/` menyediakan `PaymentGatewayInterface` dengan dua implementasi: `MidtransGateway` & `XenditGateway`, dipakai via `PaymentService` (legacy `MidtransService` masih dipakai di beberapa path).

---

## 20. Roadmap Pengembangan Selanjutnya

### Immediate (Prioritas P1)
- Encrypt password MikroTik di database (saat ini plaintext)
- Tambah `BelongsToTenant` ke `OdcPort` & `OdpPort` (tutup celah data leak)
- Hapus sensitive file (`.env`, `vercel.json`, `checker.md`) dari git history
- Export CSV voucher
- Halaman index Voucher Template (view blade)
- Sinkronasi traffic voucher dari MikroTik (kolom sudah ada, belum ada mekanisme)

### Short-term (Prioritas P2)
- Enable SSL verification MikroTik (configurable)
- Proteksi `reset_data.php`
- API auth + rate limiting (`/api/v1/mikrotik/hotspot-login`)
- Template reminder/pesan notifikasi yang bisa dikustom dari settings
- Notifikasi email untuk payment reminder bulk
- Filter export CSV yang lebih kaya
- Define RPO/RTO + backup encryption
- Aktifkan route NOC yang masih commented-out (GenieACS, Interface Center, MikroTik Dashboard, dsb.)

### Long-term
- API RESTful untuk integrasi pihak ketiga
- Mobile app (Flutter/React Native)
- Multi-language support
- Real-time notification (WebSocket/Pusher)
- Automated billing reports via email
- SNMP-based OLT monitoring (selain SSH)
- Automated backup ke cloud storage

---

## 21. Infrastruktur Jaringan (Data Model)

```
Tenant (root)
 │
 ├── ODC (cabang fiber)
 │    ├── ODC Port (port kabinet)
 │    ├── ODP (titik splitter baru — model Odp, nama Indonesia)
 │    │    └── ODP Port (port ODP)
 │    │         └── Customer (via odp_port_id)
 │    ├── ODP Route (jalur kabel dengan polyline)
 │    │    └── ODP Point (titik splitter lama — model OdpPoint)
 │    │         └── Customer (pelanggan)
 │
 ├── OLT (perangkat)
 │    └── OLT Port (GPON port)
 │         └── ONU (ONT pelanggan) ── serial, Rx/Tx, status
 │              └── Customer (pemilik)
 │
 ├── Package (paket internet)
 │    └── Customer (subscribe)
 │
 ├── Voucher Profile (template paket voucher)
 │    └── Voucher (voucher WiFi)
 │         ├── Mikrotik Router (router tujuan push)
 │         └── Voucher Template (halaman hotspot)
 │
 ├── Customer
 │    └── Invoice (tagihan bulanan)
 │         └── Payment (pembayaran)
 │
 └── Isolir Subsystem (via command + status suspended di CustomerController)
      ├── auto-isolir (command) → suspend + PPP Profile isolir
      ├── sync-isolir-ips (command) → firewall address-list
      └── setup-isolir (command) → DST-NAT redirect
```

---

## Referensi Detail

Dokumentasi lengkap tersedia di folder **`docs/`**:

| File | Lokasi |
|------|--------|
| Project Overview | `docs/00_PROJECT/DESCRIPTION.md` |
| PRD | `docs/00_PROJECT/PRD.md` |
| ROADMAP | `docs/00_PROJECT/ROADMAP.md` |
| CHANGELOG | `docs/00_PROJECT/CHANGELOG.md` |
| System Design | `docs/01_ARCHITECTURE/SYSTEM_DESIGN.md` |
| Database | `docs/01_ARCHITECTURE/DATABASE.md` |
| API | `docs/01_ARCHITECTURE/API.md` |
| Security | `docs/01_ARCHITECTURE/SECURITY.md` |
| Deployment | `docs/01_ARCHITECTURE/DEPLOYMENT.md` |
| Business Process | `docs/02_BUSINESS/BUSINESS_PROCESS.md` |
| Modules | `docs/02_BUSINESS/MODULES.md` |
| User Flow | `docs/02_BUSINESS/USER_FLOW.md` |
| Business Rules | `docs/02_BUSINESS/BUSINESS_RULES.md` |
| Coding Standard | `docs/03_DEVELOPMENT/CODING_STANDARD.md` |
| Folder Structure | `docs/03_DEVELOPMENT/FOLDER_STRUCTURE.md` |
| UI Guideline | `docs/03_DEVELOPMENT/UI_GUIDELINE.md` |
| Testing | `docs/03_DEVELOPMENT/TESTING.md` |
| Contributing | `docs/03_DEVELOPMENT/CONTRIBUTING.md` |
| AGENTS.md | `docs/04_AI/AGENTS.md` |
| Prompts | `docs/04_AI/PROMPTS.md` |
| AI Workflow | `docs/04_AI/AI_WORKFLOW.md` |
| Controllers | `docs/05_IMPLEMENTATION/CONTROLLERS.md` |
| Models | `docs/05_IMPLEMENTATION/MODELS.md` |
| Services | `docs/05_IMPLEMENTATION/SERVICES.md` |
| Migrations | `docs/05_IMPLEMENTATION/MIGRATIONS.md` |
| Seeders | `docs/05_IMPLEMENTATION/SEEDERS.md` |
| Isolir MikroTik | `docs/05_IMPLEMENTATION/ISOLIR_MIKROTIK.md` |
