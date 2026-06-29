<!-- ============================================================ -->
<!-- DESCRIPTION.md — Full Project Documentation                  -->
<!-- ============================================================ -->

# RabegNet — ISP Billing System v1.1

Sistem billing ISP terintegrasi untuk manajemen pelanggan, tagihan, pembayaran, inventaris, dan monitoring infrastruktur jaringan (OLT, ODP, MikroTik) — dibangun dengan **Laravel 12** + **Bootstrap 5.3** + **Leaflet.js** + **Chart.js**.

---

## 1. Gambaran Umum Aplikasi

RabegNet adalah sistem billing berbasis web untuk penyedia layanan internet (ISP) skala kecil hingga menengah. Aplikasi ini mencakup manajemen pelanggan, penagihan otomatis, pembayaran online, manajemen perangkat jaringan (OLT multi-brand, MikroTik, ODP/ODC), serta sistem voucher WiFi hotspot.

- **Nama Proyek:** RabegNet ISP Billing System
- **Versi:** 1.1
- **Status:** Production Active
- **Domain:** rabegnet.vercel.app

---

## 2. Tujuan Aplikasi

- Mengotomatiskan proses penagihan dan pembayaran ISP
- Memonitor perangkat jaringan (OLT, MikroTik) secara real-time
- Mengelola distribusi fiber optik (ODC/ODP) dengan peta interaktif
- Menyediakan portal customer self-service untuk cek tagihan dan pembayaran
- Mengelola voucher WiFi hotspot dengan sinkronisasi ke MikroTik
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
2. **Pembayaran offline** → Integrasi Midtrans untuk pembayaran online (QRIS, VA, dll)
3. **Monitoring OLT terbatas** → SSH multi-brand (Huawei, ZTE, FiberHome, C-Data) dengan polling otomatis
4. **Manajemen ODP tidak terstruktur** → Peta interaktif Leaflet dengan ODC/Route/Point
5. **Voucher manual** → Generate, print, push ke MikroTik otomatis
6. **Pengingat tagihan** → WA reminder otomatis via Fonnte
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

### Frontend
| Teknologi | Versi | Fungsi |
|-----------|-------|--------|
| Bootstrap | 5.3.8 | CSS framework |
| Bootstrap Icons | 1.13.1 | Icons |
| Leaflet | 1.9.4 | Peta interaktif ODP/OLT |
| Chart.js | 4.5.1 (via NPM + Vite) | Grafik dashboard |
| Alpine.js | — | Interaktivitas ringan |
| Tailwind CSS | v4 | CSS utility (via `@import 'tailwindcss'`) |
| Vite | 7.x | Asset bundler |

### Integrasi Pihak Ketiga
| Layanan | API | Fungsi |
|---------|-----|--------|
| Midtrans | Snap API | Pembayaran QRIS, VA, dll |
| Google | Socialite OAuth 2.0 | Login dengan Google |
| Fonnte | REST API | Notifikasi WhatsApp |
| MikroTik | REST API | Manajemen hotspot, PPP, queue |

---

## 6. Arsitektur Sistem

### Pola Arsitektur
- **Monolithic** dengan pemisahan Controller → Service → Model
- **Multi-tenant ringan** via Global Scope (`BelongsToTenant` trait)
- **Driver Pattern** untuk OLT multi-brand (Huawei, ZTE, FiberHome, C-Data)
- **Decorator Pattern** untuk Jump Host SSH tunnel & MikroTik SSH Proxy
- **Scheduled Tasks** untuk billing, polling OLT, auto-isolir
- **Event-driven API** untuk sinkronasi voucher MikroTik
- **Job Queue** untuk polling OLT perangkat + WA notification

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
 ├── Pelanggan bayar → Payment → Invoice status paid
 ├── Generate Voucher → push ke MikroTik → event-driven callback via API
 ├── Scan/ Poll OLT → update ONU status → RCA cable cut detection
 ├── Buat ODC → ODP Route → ODP Point / Odp → assign ke Customer
 ├── Isolir: overdue → auto-suspend → set PPP Profile → add IP ke address-list
 └── Customer akses halaman isolir → bayar → auto-activate
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
 │    ├── /logs (activity log)
  │    ├── /reports (admin only)
  │    ├── /settings (admin only)
  │    ├── /backups (admin only)
  │    ├── /voucher-profiles (admin only)
  │    ├── /mikrotik-routers (admin only)
  │    ├── /voucher-templates (admin only)
  │    ├── /odc/{odc} (admin only — detail ODC)
  │    └── /odp/{odp} (admin only — detail ODP)
  ├── /portal (public — cek tagihan & bayar)
  ├── /vouchers/public (public — beli voucher)
  ├── /vouchers/check (public — cek status voucher)
  ├── /hotspot/{page} (public — halaman hotspot MikroTik)
  ├── /isolir/{customer} (public — info bayar pelanggan kena suspen)
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
│   │   └── Commands/
│   │       ├── AutoIsolir.php               # customer:auto-isolir
│   │       ├── BillingProcess.php           # billing:process
│   │       ├── ImportHotspotFiles.php       # hotspot:import
│   │       ├── MikrotikSetupIsolir.php      # mikrotik:setup-isolir
│   │       ├── PollOlt.php                  # olt:poll
│   │       ├── SyncCustomerOnu.php          # customers:onu-sync
│   │       ├── SyncIsolirIps.php            # customer:sync-isolir-ips
│   │       └── SyncVoucherMikrotik.php      # voucher:sync-mikrotik (non-aktif)
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── MikrotikHotspotController.php
│   │   │   │   └── OdpruteController.php
│   │   │   ├── Auth/
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── RegisterController.php
│   │   │   │   └── SocialiteController.php
│   │   │   ├── BackupController.php
│   │   │   ├── CronController.php
│   │   │   ├── CustomerController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── DistributionController.php
│   │   │   ├── ExportController.php
│   │   │   ├── InvoiceController.php
│   │   │   ├── IsolirController.php
│   │   │   ├── LogController.php
│   │   │   ├── MidtransController.php
│   │   │   ├── MikrotikController.php
│   │   │   ├── MikrotikRouterController.php
│   │   │   ├── OdcController.php
│   │   │   ├── OdpController.php
│   │   │   ├── OltController.php
│   │   │   ├── PackageController.php
│   │   │   ├── PaymentController.php
│   │   │   ├── PortalController.php
│   │   │   ├── PublicVoucherController.php
│   │   │   ├── ReportController.php
│   │   │   ├── SettingController.php
│   │   │   ├── SitemapController.php
│   │   │   ├── VoucherController.php
│   │   │   ├── VoucherProfileController.php
│   │   │   ├── VoucherReportController.php
│   │   │   └── VoucherTemplateController.php
│   │   │
│   │   └── Middleware/
│   │       ├── IsAdmin.php
│   │       └── IsTeknisiOrAdmin.php
│   │
│   ├── Jobs/
│   │   ├── PollOltJob.php                  # Job polling OLT (timeout=60s, tries=3)
│   │   └── SendWhatsAppNotification.php    # Job WA via Fonnte (timeout=30s, tries=3)
│   │
│   ├── Mail/
│   │   ├── InvoiceReminder.php
│   │   └── PaymentConfirmation.php
│   │
│   ├── Models/
│   │   ├── Traits/
│   │   │   ├── BelongsToTenant.php         # Aktif — global scope tenant_id
│   │   │   └── BelongsToUser.php           # LEGACY — dead code (tidak dipakai)
│   │   ├── ActivityLog.php
│   │   ├── Customer.php
│   │   ├── Invoice.php
│   │   ├── MikrotikRouter.php
│   │   ├── Odc.php
│   │   ├── OdcPort.php                     # ⚠️ TIDAK punya BelongsToTenant — potensi data leak
│   │   ├── Odp.php                         # Model ODP baru (nama Indonesia)
│   │   ├── OdpPoint.php                    # Model ODP lama (masih ada)
│   │   ├── OdpPort.php                     # ⚠️ TIDAK punya BelongsToTenant — potensi data leak
│   │   ├── OdpRoute.php
│   │   ├── Olt.php
│   │   ├── OltPort.php
│   │   ├── Onu.php
│   │   ├── Package.php
│   │   ├── Payment.php
│   │   ├── Setting.php
│   │   ├── Tenant.php                      # Root multi-tenancy
│   │   ├── User.php
│   │   ├── Voucher.php
│   │   ├── VoucherProfile.php
│   │   └── VoucherTemplate.php
│   │
│   ├── Providers/
│   │   └── AppServiceProvider.php          # Bootstrap 5 pagination
│   │
│   └── Services/
│       ├── MidtransService.php
│       ├── MikrotikService.php             # 652 baris — REST API wrapper
│       └── Olt/
│           ├── Contracts/
│           │   └── OltConnector.php          # Interface (10 methods)
│           ├── Drivers/
│           │   ├── CDataConnector.php
│           │   ├── FiberHomeConnector.php
│           │   ├── HuaweiConnector.php
│           │   ├── JumpHostConnector.php     # SSH tunnel decorator
│           │   ├── MikrotikSshProxyConnector.php
│           │   └── ZteConnector.php
│           ├── Factory/
│           │   └── OltConnectorFactory.php
│           └── SshTunnel.php
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
│   ├── migrations/                  # 46 file migrasi (28 tabel)
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
│   │   └── app.css                  # 1570 baris custom CSS
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
│       ├── isolir/                  # index, unknown (redirect pelanggan kena suspen)
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
│   └── console.php                  # Schedule definitions
│
├── storage/                         # Log, cache, backups
│
└── tests/
    ├── Feature/
    │   ├── AuthTest.php
    │   ├── CustomerTest.php
    │   ├── DistributionTest.php
    │   ├── ExampleTest.php
    │   ├── InvoiceTest.php
    │   ├── PackageTest.php
    │   └── SitemapTest.php
    └── Unit/
        └── ExampleTest.php
```

---

## 8. Database dan Relasi Data

### 46 Migrations (28 Tabel) — Relasi Antar Tabel

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
- **Model:** `Customer` — BelongsToUser
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
- **Model:** `Invoice` — BelongsToUser
- **Routes:**
  - `GET /invoices` — List + filter (status, search, date range)
  - `GET /invoices/create` — Form create
  - `POST /invoices` — Store
  - `PUT /invoice/{id}` — Update
  - `DELETE /invoice/{id}` — Delete
  - `GET /invoice/paid/{id}` — Mark as paid (auto WA notif)
  - `GET /invoice/print/{id}` — Print view
  - `GET /invoice/pdf/{id}` — Download PDF (DomPDF)
  - `GET /invoice/reminder/{id}` — Kirim WA reminder (Fonnte)
  - `GET /invoice/email-reminder/{id}` — Kirim email reminder
  - `GET /invoice/email-payment/{id}` — Kirim email konfirmasi pembayaran
- **Field:** invoice_code, customer_id, amount, payment_status, paid_at, payment_method, midtrans_order_id

### 9.5 Pembayaran (Payments)
- **Controller:** `PaymentController` (71 baris)
- **Model:** `Payment` — BelongsToUser
- **Routes:**
  - `GET /payment/create/{invoice}` — Form create
  - `POST /payments` — Store (auto-update invoice status)
  - `GET /payment/history/{invoice}` — History pembayaran
  - `DELETE /payment/{id}` — Hapus (auto-update invoice jika total <= 0)
- **Payment Methods:** cash, transfer, qris, midtrans

### 9.6 Paket Internet (Packages)
- **Controller:** `PackageController` (135 baris)
- **Model:** `Package` — BelongsToUser, HasFactory
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
- **Model:** `Voucher` — BelongsToUser, HasFactory
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
- **Model:** `VoucherProfile` — BelongsToUser
- **Routes (admin):**
  - `GET /voucher-profiles` — List
  - `POST /voucher-profiles` — Create
  - `PUT /voucher-profiles/{id}` — Update
  - `DELETE /voucher-profiles/{id}` — Delete (proteksi jika ada voucher)
- **Field:** name, speed, price, time_limit (jam), quota_limit (MB), validity_days, shared_users, is_active

### 9.11 MikroTik Routers
- **Controller:** `MikrotikRouterController` (92 baris)
- **Model:** `MikrotikRouter` — BelongsToUser
- **Routes (admin):**
  - `GET /mikrotik-routers` — List
  - `POST /mikrotik-routers` — Create
  - `PUT /mikrotik-routers/{id}` — Update
  - `DELETE /mikrotik-routers/{id}` — Delete (proteksi jika ada voucher)
  - `POST /mikrotik-routers/{id}/test` — Test koneksi
- **Field:** name, host, port, username, password, hotspot_server, is_active

### 9.12 Voucher Templates (Hotspot Pages)
- **Controller:** `VoucherTemplateController` (96 baris)
- **Model:** `VoucherTemplate` — BelongsToUser
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
- **Model:** `Odc`, `OdpRoute`, `OdpPoint` — semuanya BelongsToUser
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
- **Model:** `ActivityLog` — BelongsToUser
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
- **Model:** `Setting` — BelongsToUser, key-value store
- **Routes (admin):**
  - `GET /settings` — Form settings
  - `POST /settings` — Update settings
  - `GET /settings/test-mikrotik` — Test MikroTik connection
- **Setting Keys:** company_name, company_address, company_phone, bank_name, bank_account, bank_holder, invoice_footer, mikrotik_host/port/user/password/hotspot_server, fonnte_token, midtrans_server/client_key, midtrans_is_production, voucher_username/password_length, late_fee_amount/grace_days, default_due_date

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
| `/portal/finish` | — | Redirect after payment |
| `/vouchers/public` | vouchers.public | Beli voucher publik |
| `/vouchers/check` | vouchers.check | Cek status voucher |
| `/hotspot/{page}` | hotspot/*.html | Halaman hotspot MikroTik |
| `/sitemap.xml` | sitemap | XML sitemap |
| `/isolir` | isolir.landing | Landing isolir (auto-detect IP) |
| `/isolir/by-ip` | isolir.index | Cari customer by IP untuk isolir |
| `/isolir/{customer}` | isolir.unknown | Info pembayaran customer kena isolir |
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

### 10.3 Admin-Only Pages
| URL | View | Fungsi |
|-----|------|--------|
| `/settings` | settings.index | Pengaturan sistem |
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

Route split: `teknisi` middleware sebagai base auth untuk semua user; `admin` middleware khusus route sensitif.

---

## 12. Scheduled Tasks (Console)

```php
Schedule::command('billing:process')->dailyAt('08:00');
Schedule::command('olt:poll')->hourly()->withoutOverlapping();
Schedule::command('customers:onu-sync')->hourly()->withoutOverlapping();
Schedule::command('customer:auto-isolir')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('customer:sync-isolir-ips')->everyFiveMinutes()->withoutOverlapping();
```

### Command Details

| Command | File | Schedule | Fungsi |
|---------|------|----------|--------|
| `billing:process` | `Commands/BillingProcess.php` | `dailyAt('08:00')` | Generate invoice bulanan untuk semua pelanggan aktif per tenant + dispatch WA notification job |
| `olt:poll` | `Commands/PollOlt.php` | `hourly()` | Dispatch `PollOltJob` per OLT aktif (timeout=60s, tries=3). Opsi `--queue` untuk async, default sync. Fallback MikroTik jika scan 0 ONU |
| `customers:onu-sync` | `Commands/SyncCustomerOnu.php` | `hourly()` | Sync semua pelanggan aktif ke ONU di OLT dari data PPPoE MikroTik. Opsi `--olt` untuk OLT tertentu |
| `customer:auto-isolir` | `Commands/AutoIsolir.php` | `dailyAt('00:30')` | Auto-suspend pelanggan overdue, set PPP Profile isolir di MikroTik, tambah IP ke firewall address-list |
| `customer:sync-isolir-ips` | `Commands/SyncIsolirIps.php` | `everyFiveMinutes()` | Sinkronasi IP customer suspended ke firewall address-list MikroTik |
| `hotspot:import` | `Commands/ImportHotspotFiles.php` | Manual | Import file HTML dari `public/hotspot/*.html` ke database sebagai VoucherTemplate baru |
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
GET  /isolir                        → landing isolir (auto-detect IP)
GET  /isolir/by-ip                  → cari customer by IP untuk isolir
GET  /isolir/{customer}             → halaman info bayar untuk customer kena isolir
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
GET    /invoice/reminder/{invoice}        → Send WA reminder
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

### Fitur Selesai (100%)

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
| 15 | Kirim WA reminder (Fonnte) | Invoice | InvoiceController | ✅ |
| 16 | Kirim email reminder | Invoice | InvoiceController (Mail) | ✅ |
| 17 | Kirim email konfirmasi bayar | Invoice | InvoiceController (Mail) | ✅ |
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
| 85 | Halaman publik isolir (redirect DNS) | Isolir | IsolirController | ✅ |
| 86 | Event-driven voucher login API | Voucher | MikrotikHotspotController | ✅ |
| 87 | Cron trigger eksternal (Vercel workaround) | Utility | CronController | ✅ |
| 88 | Manajemen ODP baru (model Odp) | Distribution | DistributionController | ✅ |
| 89 | Manajemen ODC Port / ODP Port | Distribution | OdcController, OdpController | ✅ |

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
| 3 | Invoice reminder WA auto schedule dengan teks terpisah per kondisi | WA reminder sudah ada di billing:process tapi menggunakan template hardcoded (bukan dari settings) |

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

# Manual artisan
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan {command}
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
- **Test files:** 8 files (7 Feature, 1 Unit) — **50 test methods total**
- **5 Feature test classes** menggunakan `RefreshDatabase`: Auth, Customer, Distribution, Invoice, Package
- **Coverage:**
  - Auth: login, register, dashboard, logout, ODP data in dashboard
  - Customer: CRUD, suspend, activate, validation, auto-create invoice
  - Invoice: CRUD, mark paid, print, destroy
  - Package: CRUD (via admin), search, status filter, destroy protection (with customers)
  - Distribution: ODC/Route/Point full CRUD, duplicate rejection, cascade delete protection
  - Sitemap: XML sitemap, public URLs included
- **Factory:** `UserFactory` default role `teknisi` + `admin()` state
- **Command:** `php artisan test` atau `./vendor/bin/phpunit`

---

## 19. Catatan Pengembangan

1. **CSS sepenuhnya custom** — `resources/css/app.css` (1570 baris) menggunakan custom design system dengan CSS custom properties, gradient, glassmorphism. Tailwind hanya di-import tapi tidak digunakan secara aktif.
2. **OLT polling** menggunakan Job per-OLT (`PollOltJob`) dengan timeout 60s, tries=3. Jika scan OLT gagal, fallback sync dari MikroTik. Juga menjalankan RCA (Root Cause Analysis) untuk cable cut detection.
3. **MikroTik multi-router** — MikrotikRouter model dengan CRUD terpisah. Service mendukung konstruktor dengan parameter router.
4. **Voucher** memiliki kolom traffic tracking (downloaded, uploaded, total_traffic) namun belum ada mekanisme sinkronasi otomatis dari MikroTik.
5. **Migrations idempotent** — semua migration baru menggunakan guard `hasTable()`/`hasColumn()` untuk safety Vercel cold-start.
6. **Password OLT** dienkripsi menggunakan Laravel `encrypted` cast. **Namun password MikroTik router tidak di-encrypt** (stored in plaintext).
7. **Hotspot files** di `public/hotspot/` ditulis otomatis saat VoucherTemplate disimpan, tapi di Vercel (readonly filesystem) mekanisme ini akan gagal — perlu dynamic route.
8. **Fonnte token** disimpan di settings per user, fallback ke `config('services.fonnte.token')`.
9. **WA notification** dikirim dari `BillingProcess` command via `SendWhatsAppNotification` job (async, timeout=30s, tries=3).
10. **BelongsToUser** trait sudah tidak dipakai — semua model menggunakan **`BelongsToTenant`** dengan global scope `tenant_id`. `BelongsToUser` adalah dead code.
11. **Isolir Subsystem** — 3 commands (`AutoIsolir`, `SyncIsolirIps`, `MikrotikSetupIsolir`) + 1 controller (`IsolirController`) untuk auto-suspend pelanggan telat bayar: set PPP Profile isolir, tambah IP ke firewall address-list, DST-NAT redirect ke halaman pembayaran.
12. **Event-driven voucher sync** — `POST /api/v1/mikrotik/hotspot-login` menggantikan scheduler `voucher:sync-mikrotik`. Dipicu dari On-Login script MikroTik User Profile.
13. **Chart.js** dipasang via NPM (`chart.js@4.5.1`) dan diimport di `resources/js/app.js` — bukan via CDN.
14. **Security concerns:** `OdcPort` dan `OdpPort` model tidak punya BelongsToTenant scope (potensi data leak). SSL verification disabled untuk koneksi MikroTik REST API (`withoutVerifying()`). Cron token lewat query parameter `?token=` yang terbaca di server access logs.

---

## 20. Roadmap Pengembangan Selanjutnya

### Immediate
- Export CSV voucher
- Halaman index Voucher Template (view blade)

### Short-term
- Sinkronasi traffic voucher dari MikroTik
- Template WA reminder yang bisa dikustom dari settings
- Notifikasi email untuk payment reminder bulk
- Filter export CSV yang lebih kaya

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
 └── Isolir Subsystem
      ├── auto-isolir (command) → suspend + PPP Profile isolir
      ├── sync-isolir-ips (command) → firewall address-list
      ├── setup-isolir (command) → DST-NAT redirect
      └── IsolirController → halaman bayar publik
```

<!-- ============================================================ -->
<!-- AGENTS.md — Development Reference                            -->
<!-- ============================================================ -->

---

# AGENTS.md — Development Reference

## Stack

- **Framework:** Laravel 12 (PHP ^8.2)
- **Database:** MySQL via Laragon (local `.env` uses `DB_CONNECTION=mysql`), Aiven MySQL (Vercel prod)
- **Frontend:** Bootstrap 5.3 + custom CSS (~1570 baris `resources/css/app.css`) + Tailwind CSS v4 (import saja, tidak aktif) + Vite via `laravel-vite-plugin`
- **CSS Framework Utama:** **Bootstrap 5.3** (bukan Tailwind) — custom design system dengan CSS custom properties, gradient, glassmorphism. Tailwind di-import tapi tidak digunakan.
- **Asset JS:** Chart.js (via NPM + Vite), Leaflet 1.9.4 + MarkerCluster, Alpine.js, Bootstrap JS
- **QR Code:** `simplesoftwareio/simple-qrcode` v4.2 (inline SVG, no external API)
- **Code style:** Laravel Pint (default rules, no local `pint.json`)
- **Testing:** PHPUnit 11 + Mockery — SQLite `:memory:` in tests (see `phpunit.xml`)
- **Deployment:** Vercel (`vercel-php@0.9.0`, `api/index.php`) + Railway.app backup
- **No CI setup** (no `.github/`)

## PHP CLI note

PHP CLI default (`php`) = 8.1.10 (tidak cukup untuk Laravel 12).
Gunakan path lengkap ke Laragon's PHP 8.2:
```
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan {command}
```

## Commands

| Command | Runs |
|---|---|
| `composer setup` | `composer install`, copies `.env`, `key:generate`, `migrate --force`, `npm install && npm run build` |
| `composer dev` | concurrently: `artisan serve`, `queue:listen`, `pail`, `npm run dev` |
| `composer test` | `artisan config:clear --ansi && artisan test` |
| `npm run build` | `vite build` |
| `npm run dev` | `vite` (dev server) |
| `./vendor/bin/pint` | Auto-format code (default Laravel rules) |
| `php artisan migrate` | Run pending migrations |
| **Test via CLI** | `C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe vendor/bin/phpunit` |
| **Artisan via CLI** | `C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan {command}` |

## Artisan Commands

| Command | Schedule | Fungsi |
|---|---|---|
| `billing:process` | `dailyAt('08:00')` | Generate invoice bulanan + WA reminder |
| `olt:poll` | `hourly()` | Poll OLT via SSH, update ONU status |
| `customers:onu-sync` | `hourly()` | Sync ONU dari data PPPoE MikroTik |
| `customer:auto-isolir` | `dailyAt('00:30')` | Auto-suspend pelanggan overdue, set PPP Profile isolir, add IP ke address-list |
| `customer:sync-isolir-ips` | `everyFiveMinutes()` | Sync IP pelanggan suspended ke firewall address-list |
| `hotspot:import` | Manual | Import file HTML hotspot ke database VoucherTemplate |
| `mikrotik:setup-isolir` | Manual | Setup PPP Profile-Isolir, DST-NAT redirect, DROP rules di MikroTik |
| `voucher:sync-mikrotik` | Manual (dulu automated, sekarang non-aktif) | Sync status voucher dengan MikroTik (digantikan event-driven API) |

## Testing

- **50 test methods** across 8 files (7 Feature + 1 Unit)
- **5 feature test classes** use `RefreshDatabase`: Auth, Customer, Distribution, Invoice, Package
- SQLite `:memory:` — no external DB needed
- Two suites: `tests/Unit` (plain PHPUnit) and `tests/Feature` (Laravel HTTP tests)
- Run focused: `php artisan test --filter=ExampleTest`
- Run single suite: `php artisan test --testsuite=Unit`
- Coverage: Auth (login/register/logout/dashboard), Customer (CRUD/suspend/activate), Invoice (CRUD/paid/print), Package (CRUD/destroy protection), Distribution (ODC/Route/Point CRUD + cascade protection), Sitemap

## Architecture

**RabegNet** adalah sistem billing ISP lengkap dengan ~80 file PHP di `app/`, 46 migrations, 28 tabel database, dan ~143 route.

### Multi-Tenancy
- **`BelongsToTenant` trait** (bukan `BelongsToUser`) — global scope `tenant_id` pada semua model utama
- `Tenant` model sebagai root, `User` belongsTo `Tenant`
- `BelongsToUser` trait masih ada tapi **sudah tidak dipakai** (dead code)

### Key Patterns
- Monolithic dengan Controller → Service → Model
- **Driver Pattern** untuk OLT multi-brand (Huawei, ZTE, FiberHome, C-Data)
- Decorator Pattern untuk Jump Host SSH tunnel & MikroTik SSH Proxy
- Event-driven API untuk sinkronasi voucher MikroTik (`POST /api/v1/mikrotik/hotspot-login`)
- Isolir subsystem: auto-suspend + firewall integration MikroTik

### Isolir Subsystem (tidak ada di DOCS versi lama)
Tiga command + satu controller untuk auto-isolasi pelanggan telat bayar:
- `customer:auto-isolir` — suspend otomatis, set PPP Profile ke "Isolir", tambah IP ke address-list
- `customer:sync-isolir-ips` — sinkronasi IP suspended ke firewall address-list tiap 5 menit
- `mikrotik:setup-isolir` — setup awal aturan firewall di MikroTik
- `IsolirController` — halaman publik untuk pelanggan yang di-isolir (redirect DNS)
- `GET /isolir/{customer}` — tampilkan info pembayaran ke pelanggan yang kena isolir

## Conventions

- PSR-4: `App\` → `app/`, `Database\Factories\` → `database/factories/`, `Database\Seeders\` → `database/seeders/`, `Tests\` → `tests/`
- `RefreshDatabase` is **not** used by default — 5 test classes use it explicitly
- `.env` is gitignored — copy `.env.example` and set `APP_KEY` on fresh clone. **Note:** `.env` uses MySQL locally (`DB_CONNECTION=mysql`, database `e_billing`)
- Key `.env` variance from default: `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `DB_CONNECTION=mysql`

## Security Notes

- **JANGAN commit `.env` atau `vercel.json`** — berisi produksi credentials (DB password, APP_KEY, Midtrans server key, Fonnte token)
- **reset_data.php** adalah destructive script — HAPUS file ini sebelum production atau beri proteksi
- `checker.md` juga mengandung sensitive tokens — jangan commit ke public repo
- Password MikroTik router **tidak di-encrypt** (beda dengan OLT yang pakai `encrypted` cast)
- `OdcPort` dan `OdpPort` model **tidak punya** `BelongsToTenant` scope — potensi data leak
- SSL verification disabled untuk koneksi MikroTik REST API (`withoutVerifying()`)

## File Structure (key files only)

```
app/
├── Console/Commands/       # 8 commands (billing, olt, voucher, isolir, dll)
├── Http/
│   ├── Controllers/        # 32 files (Auth, API, Backup, Customer, Dashboard, dll)
│   ├── Controllers/Api/    # OdpruteController, MikrotikHotspotController
│   └── Middleware/          # IsAdmin, IsTeknisiOrAdmin
├── Jobs/                   # PollOltJob, SendWhatsAppNotification
├── Mail/                   # InvoiceReminder, PaymentConfirmation
├── Models/                 # 19 models + 2 traits (BelongsToTenant, BelongsToUser legacy)
└── Services/               # MidtransService, MikrotikService, Olt/ (drivers, factory, SSH tunnel)
database/
├── migrations/             # 46 files (28 tables)
├── factories/              # 5 factories
└── seeders/                # 5 seeders (DatabaseSeeder, BillingSeeder, SettingSeeder, dll)
resources/views/            # 58 blade files + 1 orphan backup
routes/
├── web.php                 # ~141 routes
├── api.php                 # POST /api/v1/mikrotik/hotspot-login
└── console.php             # 5 scheduled commands
```

<!-- ============================================================ -->
<!-- PRD.md — Product Requirement Document (Legacy)               -->
<!-- ============================================================ -->

---

# PRD — RabegNet ISP Billing System

> **Version:** 1.1
> **Status:** Production Active
> **URL:** https://rabegnet.vercel.app
> **Stack:** Laravel 12 + PHP 8.2 / 8.5 (Vercel) + MySQL (Aiven)
> **Repository:** `github.com/ranggamaulana111111-bit/billing`

---

## 1. Ringkasan Eksekutif

Sistem billing untuk ISP RabegNet yang mencakup manajemen pelanggan, penagihan, pembayaran (Midtrans), monitoring jaringan (MikroTik multi-router), manajemen perangkat OLT multi-brand (Huawei/ZTE/FiberHome/C-Data), distribusi ODP/ODC/ODP dengan peta Leaflet, portal customer self-service, sistem isolir otomatis pelanggan telat bayar (PPP profile swap + firewall address-list), serta hotspot voucher management dengan custom HTML templates dari database. Multi-tenant via `BelongsToTenant` trait, dideploy di Vercel dengan Aiven MySQL.

---

## 2. Target Pengguna

| Role | Deskripsi | Akses |
|------|-----------|-------|
| **Admin** | Pemilik ISP, full akses ke semua fitur | `role = 'admin'` |
| **Teknisi** | Teknisi lapangan — monitor gangguan, lihat pelanggan, OLT, ODP | `role = 'teknisi'` |
| **Pelanggan** | End-user — lihat tagihan, bayar via portal publik, generate voucher publik | Tidak login |

---

## 3. Arsitektur Sistem

```
┌─────────────────────────────────────────────────┐
│  Vercel Edge (PHP 8.5 Runtime)                  │
│  ┌───────────────────────────────────────────┐  │
│  │  api/index.php  (cold boot → migrate)     │  │
│  └──────────┬────────────────────────────────┘  │
│             │                                    │
│  ┌──────────▼────────────────────────────────┐  │
│  │  Laravel 12 App                           │  │
│  │  ┌─────┐ ┌──────┐ ┌──────┐ ┌──────────┐  │  │
│  │  │Web  │ │CLI   │ │Queue │ │Schedule  │  │  │
│  │  │Routes│ │Cmds  │ │Jobs  │ │(poll OLT,│  │  │
│  │  │     │ │(8)   │ │(2)   │ │ billing, │  │  │
│  │  └─────┘ └──────┘ └──────┘ │ isolir)  │  │  │
│  │                            └──────────┘  │  │
│  └───────────────────────────────────────────┘  │
│             │                                    │
├─────────────┼────────────────────────────────────┤
│  ┌──────────▼────────────────────────────────┐  │
│  │  Aiven MySQL (SSL required)               │  │
│  │  mysql-2b9ccfa-ranggamar.e.aivencloud.com │  │
│  │  :15501 / defaultdb                       │  │
│  └───────────────────────────────────────────┘  │
│                                                 │
│  External Services:                             │
│  • Midtrans (pembayaran)                        │
│  • Fonnte (WA gateway)                         │
│  • Google OAuth + GitHub OAuth (login)         │
│  • MikroTik REST API (router, multi-device)    │
│  • SSH ke OLT (Huawei/ZTE/FiberHome/C-Data)   │
│  • SSH Tunnel / Jump Host + MikroTik SSH Proxy │
└─────────────────────────────────────────────────┘
```

### Key Architecture Decisions

| Keputusan | Alasan |
|-----------|--------|
| **Individual DB env vars** (bukan DB_URL) | DB_URL parsing unreliable di Vercel |
| **User::create() di seeder** (bukan factory) | `fake()` undefined di PHP 8.5 Vercel |
| **Tidak pakai db:seed di request path** | Kurangi cold start latency |
| **Multi-tenant via BelongsToTenant trait** | Global scope `tenant_id` otomatis, Tenant model sebagai root |
| **SQLite :memory: di test** | Test independen tanpa DB eksternal |
| **Cookie session di prod** | File session tidak work di Vercel serverless |
| **Driver Pattern untuk OLT** | Multi-brand (Huawei, ZTE, FiberHome, C-Data) via interface |
| **Decorator Pattern untuk SSH** | Jump Host tunnel + MikroTik SSH Proxy |
| **External cron via HTTP** | Vercel Hobby tidak punya native cron → endpoint `/api/cron/run` |
| **Hotspot dari database** | Vercel readonly filesystem → HTML served via route dinamis |

---

## 4. Fitur — Lengkap

### 4.1 Autentikasi & Pengguna
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 1.1 | Login email/password | ✅ | `GET/POST /login` |
| 1.2 | Register | ✅ | `GET/POST /register` |
| 1.3 | Google OAuth (Socialite) | ✅ | `GET /auth/google/{redirect\|callback}` |
| 1.4 | GitHub OAuth | ✅ | `GET /auth/github/{redirect\|callback}` |
| 1.5 | Role-based access | ✅ | `role` column: `admin` / `teknisi` |
| 1.6 | Middleware IsAdmin | ✅ | Guard `.admin` — akses admin only |
| 1.7 | Middleware IsTeknisiOrAdmin | ✅ | Guard `.teknisi` — admin + teknisi |

### 4.2 Dashboard
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 2.1 | Dashboard utama | ✅ | Statistik pelanggan, tagihan, pembayaran, ODP utilisation |
| 2.2 | Info ODP | ✅ | Total port, used, available, full ODPs, down ODPs |
| 2.3 | Chart ODP | ✅ | Bar chart port usage + doughnut |

### 4.3 Pelanggan (Customer)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 3.1 | CRUD pelanggan | ✅ | `customers.index` + create/edit/destroy |
| 3.2 | Suspend/Activate | ✅ | POST route, update status + `suspended_at` |
| 3.3 | PPPoE sync | ✅ | Sync username ke MikroTik |
| 3.4 | Relasi dengan ODP | ✅ | `odp_point_id` — titik ODP (legacy), `odp_id` — ODP baru, `odp_port_id` — port ODP |
| 3.5 | Relasi dengan ONU | ✅ | `hasMany Onu` — perangkat ONU |
| 3.6 | Sync single ONU | ✅ | `POST /customer/{customer}/sync-onu` |

### 4.4 Tagihan (Invoices)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 4.1 | CRUD tagihan | ✅ | Manual create, edit, delete |
| 4.2 | Mark as paid | ✅ | `GET /invoice/paid/{invoice}` |
| 4.3 | Print tagihan | ✅ | Layout print-friendly |
| 4.4 | Download PDF | ✅ | dompdf, `GET /invoice/pdf/{invoice}` |
| 4.5 | Email reminder | ✅ | `InvoiceReminder` mailable via SMTP |
| 4.6 | Email payment confirmation | ✅ | `PaymentConfirmation` mailable |
| 4.7 | WA reminder (Fonnte) | ✅ | `SendWhatsAppNotification` job via Fonnte API |

### 4.5 Pembayaran (Payments)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 5.1 | Manual payment entry | ✅ | Create + history per invoice |
| 5.2 | Midtrans gateway | ✅ | `midtrans/pay` + notification handler |
| 5.3 | Riwayat pembayaran | ✅ | Per invoice |

### 4.6 Paket Internet
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 6.1 | CRUD paket | ✅ | Nama, speed, harga, billing_cycle, mikrotik_profile, is_active |
| 6.2 | Mass billing | ✅ | Generate tagihan semua pelanggan aktif |

### 4.7 Laporan (Reports)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 7.1 | Laporan pendapatan | ✅ | Filter by date range, export |

### 4.8 Export CSV
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 8.1 | Export tagihan | ✅ | `GET /export/invoices?status=&from=&to=` |
| 8.2 | Export pembayaran | ✅ | `GET /export/payments?from=&to=` |
| 8.3 | Export OLT | ✅ | `GET /olts/export` — CSV OLT list |
| 8.4 | Export ONU | ✅ | `GET /onus/export?olt_id=&status=` — CSV ONU |

### 4.9 Manajemen OLT (Optical Line Terminal) ⭐
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 9.1 | CRUD OLT | ✅ | `olts` table: name, brand, model, IP, SSH, SNMP, jump host, lokasi, lat/lng, notes |
| 9.2 | Test koneksi SSH | ✅ | `POST /olts/{olt}/test` — log aktivitas |
| 9.3 | Scan ONU per OLT | ✅ | `POST /olts/{olt}/scan` — pull ONU list via SSH |
| 9.4 | Reboot ONU remote | ✅ | `POST /olts/{olt}/onu/{onu}/reboot` |
| 9.5 | Hapus ONU dari OLT | ✅ | `DELETE /olts/{olt}/onu/{onu}` |
| 9.6 | Taut ONU ke pelanggan | ✅ | `POST /onu/{onu}/link-customer` |
| 9.7 | Sync port OLT | ✅ | Bulk create port |
| 9.8 | Sync ONU dari MikroTik | ✅ | `POST /olts/{olt}/sync-mikrotik` |
| 9.9 | Monitor Gangguan | ✅ | `GET /olts-monitoring` — **semua pelanggan** dengan Rx Power, sort by redaman tertinggi (early warning). Filter by status & level redaman, progress bar visual, warna baris (hijau/kuning/merah) |
| 9.10 | Map OLT (Leaflet) | ✅ | `GET /olts/map` — peta interaktif semua OLT |
| 9.11 | Live data OLT | ✅ | `GET /olts/{olt}/live` — JSONリアルタイム data |
| 9.12 | Cari ONU | ✅ | `GET /onus/search` — filter by keyword, status, OLT |
| 9.13 | Log aktivitas OLT | ✅ | Setiap test/scan/reboot/hapus/taut tercatat |
| 9.14 | Export OLT/ONU CSV | ✅ | Tombol di halaman index + monitoring |

**Driver OLT yang didukung:**
- **Huawei** — CLI: `display ont info`, `display ont optical-info`, `ont add`, `ont reset`
- **ZTE** — CLI: `show onu unquiet`, `interface gpon-olt`, `onu reset`
- **FiberHome** — CLI: `show ont list`, `ont add`, `ont reset`
- **C-Data** — CLI: perintah spesifik C-Data

**Konektivitas OLT:**
- **Direct SSH** — via phpseclib3
- **Jump Host SSH Tunnel** — SSH tunnel melalui server perantara
- **MikroTik SSH Proxy** — SSH melalui MikroTik sebagai proxy (decorator pattern)

### 4.10 MikroTik Management
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 10.1 | Multi-router support | ✅ | CRUD MikrotikRouter, masing-masing dengan host/port/kredensial sendiri |
| 10.2 | Dashboard MikroTik | ✅ | Resource, uptime, interfaces |
| 10.3 | Hotspot profiles | ✅ | CRUD, sync to router |
| 10.4 | Active sessions | ✅ | Hotspot + PPP, disconnect |
| 10.5 | PPP secrets | ✅ | CRUD, sync |
| 10.6 | Queue management | ✅ | Simple queues CRUD |
| 10.7 | Bandwidth monitoring | ✅ | Real-time RX/TX, live JSON endpoint |
| 10.8 | Backup router | ✅ | Download backup |
| 10.9 | Test koneksi router | ✅ | `POST /mikrotik-routers/{router}/test` |

### 4.11 Distribusi ODP/ODC (Optical Distribution)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 11.1 | ODC management | ✅ | CRUD, kapasitas port (4/8/16), auto-create ports |
| 11.2 | ODC detail view | ✅ | `GET /odc/{odc}` — ports + connected ODPs |
| 11.3 | ODP management | ✅ | CRUD, port capacity, kabel tube/warna, kondisi jalur |
| 11.4 | ODP detail view | ✅ | `GET /odp/{odp}` — ports + customer list |
| 11.5 | ODP Port management | ✅ | Status per port (available/used/broken) |
| 11.6 | ODC Port management | ✅ | Port numbering, tipe outlet, koneksi ke ODP |
| 11.7 | OdpRoute (legacy) | ✅ | Line di peta antar ODC-ODP |
| 11.8 | OdpPoint (legacy) | ✅ | Marker beda warna (green/orange/red) per utilisasi |
| 11.9 | Leaflet map | ✅ | Visualisasi ODP/route/ODC |
| 11.10 | Chart | ✅ | Bar chart port usage + doughnut total |

### 4.12 Voucher WiFi
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 12.1 | Generate voucher | ✅ | Create + print individual. QR code inline SVG (lokal, tanpa external API) |
| 12.2 | Batch print | ✅ | Multiple vouchers + quick-print |
| 12.3 | Mark used | ✅ | Tandai sudah dipakai |
| 12.4 | Sync ke MikroTik | ✅ | Push ke hotspot router |
| 12.5 | Voucher Profiles | ✅ | Template konfigurasi: speed, price, time_limit, quota, validity, shared_users |
| 12.6 | Voucher Templates | ✅ | Custom HTML hotspot pages (login/status/redirect/error/alive/logout) dari DB |
| 12.7 | Hotspot dynamic pages | ✅ | `GET /hotspot/{page}` — serve HTML dari DB, fallback ke file |
| 12.8 | Public voucher generation | ✅ | `GET/POST /vouchers/public` — self-service oleh pelanggan |
| 12.9 | Voucher check status | ✅ | `GET /vouchers/check` + `POST /vouchers/check-status` |
| 12.10 | Voucher report | ✅ | Filter by profile, status, date range — stats (total, active, used, expired, revenue) |
| 12.11 | Import hotspot files | ✅ | `hotspot:import` — import HTML dari `public/hotspot/` ke DB |

### 4.13 Portal Pelanggan (Public)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 13.1 | Cari tagihan | ✅ | Input nomor pelanggan |
| 13.2 | Lihat tagihan | ✅ | Daftar tagihan + status |
| 13.3 | Bayar via Midtrans | ✅ | Redirect ke Midtrans |

### 4.14 Isolir Subsystem (Auto-Suspend) ⭐
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 14.1 | Auto-isolir command | ✅ | `customer:auto-isolir` — suspend otomatis pelanggan overdue, swap PPP profile ke "Isolir" |
| 14.2 | Dry-run mode | ✅ | `--dry-run` flag untuk preview tanpa eksekusi |
| 14.3 | Sync isolir IPs | ✅ | `customer:sync-isolir-ips` — sync IP suspended ke firewall address-list tiap 5 menit |
| 14.4 | Setup isolir di MikroTik | ✅ | `mikrotik:setup-isolir` — setup PPP Profile-Isolir, DST-NAT redirect, DROP rules |
| 14.5 | Isolir landing page | ✅ | `GET /isolir/{customer}` + `GET /isolir/by-ip` — public page untuk info pembayaran |
| 14.6 | IP detection otomatis | ✅ | `byIp()` — deteksi IP dari PPPoE session di MikroTik |
| 14.7 | Original profile backup | ✅ | `original_ppp_profile` disimpan sebelum suspend, bisa dikembalikan |

### 4.15 Event-Driven API (MikroTik Hotspot)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 15.1 | Hotspot login callback | ✅ | `POST /api/v1/mikrotik/hotspot-login` — endpoint dipanggil MikroTik saat user login |
| 15.2 | Voucher auto-mark used | ✅ | Voucher marked used + catat IP/MAC/router |

### 4.16 Lainnya
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 16.1 | Activity Log | ✅ | Semua aksi pengguna tercatat di DB |
| 16.2 | Settings | ✅ | Key-value per tenant (Midtrans, MikroTik, Fonnte, dll) |
| 16.3 | Backup database | ✅ | Download SQLite backup |
| 16.4 | Billing otomatis | ✅ | `billing:process` — generate + WA reminder per tenant |
| 16.5 | Poll OLT otomatis | ✅ | `olt:poll` — tiap jam via queue job, update status ONU |
| 16.6 | Sync ONU dari PPPoE | ✅ | `customers:onu-sync` — sync ONU dari session MikroTik |
| 16.7 | External cron trigger | ✅ | `GET /api/cron/run?token=` — untuk Vercel external cron |
| 16.8 | Sitemap XML | ✅ | `GET /sitemap.xml` — untuk SEO |
| 16.9 | Midtrans notification | ✅ | `POST /midtrans/notification` — webhook dari Midtrans |

---

## 5. Skema Database (46 Migrations — 28+ Tables)

### 5.1 Tenant & Users
| Tabel | Kolom Kunci |
|-------|-------------|
| `tenants` | id, name, address, phone, email, logo |
| `users` | id, tenant_id, name, email, password, role (default `teknisi`), provider, provider_id, avatar, remember_token |
| `cache` | key, value, expiration |
| `jobs` | id, queue, payload, attempts, reserved_at, available_at |

### 5.2 Billing
| Tabel | Kolom Kunci |
|-------|-------------|
| `customers` | id, tenant_id, name, phone, email, location, package_id, odp_point_id, odp_id, odp_port_id, pppoe_username, original_ppp_profile, due_date, status (active/suspended/inactive), suspended_at |
| `packages` | id, tenant_id, name, speed, price, description, billing_cycle (monthly/weekly), mikrotik_profile, is_active |
| `invoices` | id, tenant_id, customer_id, invoice_code, amount, payment_status (unpaid/paid), paid_at, payment_method, midtrans_order_id |
| `payments` | id, tenant_id, invoice_id, amount, payment_method, payment_date, notes |
| `vouchers` | id, tenant_id, voucher_profile_id, voucher_template_id, username, password, duration_hours, price, prefix, speed, quota_limit, validity_days, shared_users, printed_count, downloaded, uploaded, total_traffic, ip_address, mac_address, last_login_at, router_id, status (active/used/expired), used_at, expires_at |
| `voucher_profiles` | id, tenant_id, name, speed, price, time_limit, quota_limit, validity_days, shared_users, description, is_active |
| `voucher_templates` | id, tenant_id, name, content (login), status_page, redirect_page, error_page, alive_page, logout_page, is_active |

### 5.3 Network Infrastructure
| Tabel | Kolom Kunci |
|-------|-------------|
| `olts` | id, tenant_id, name, brand (huawei/zte/fiberhome/cdata), model, ip_address, ssh_port, username, password (encrypted), jump_host, jump_port, jump_username, jump_password (encrypted), snmp_community, snmp_version, snmp_port, location, latitude(10,7), longitude(10,7), status, notes, last_polled_at |
| `olt_ports` | id, tenant_id, olt_id, slot_number, port_number, port_type (gpon/xgspon/epon), status, description — unique(olt_id, slot_number, port_number) |
| `onus` | id, tenant_id, olt_port_id, customer_id (nullable), onu_id, serial_number, vendor, model, mac_address, status (online/offline), rx_power (float), tx_power (float), distance, uptime (seconds), slot_number, port_number, notes, last_seen_at |
| `odcs` | id, tenant_id, nama_odc, koordinat, kapasitas_port (4/8/16) |
| `odc_ports` | id, odc_id, port_number, port_type (outlet), status (available/used/broken), connected_to_odp_id |
| `odps` (new) | id, tenant_id, odc_id, nama_odp, koordinat, kapasitas_port, kabel_tube_color, kabel_core_number, kondisi_jalur |
| `odp_ports` | id, odp_id, port_number, status (available/used/broken) |
| `odp_routes` | id, tenant_id, odc_id, name, description, color, coordinates (JSON) |
| `odp_points` | id, tenant_id, odp_route_id, name, address, latitude, longitude, port_capacity, port_used, status |
| `mikrotik_routers` | id, tenant_id, name, host, port, username, password, hotspot_server, is_active |

### 5.4 Operations
| Tabel | Kolom Kunci |
|-------|-------------|
| `activity_logs` | id, tenant_id, user_id, action, details, created_at |
| `settings` | id, tenant_id, key, value — unique(tenant_id, key) |

---

## 6. Route Map — Semua Endpoint (148 Route:: calls)

### Public Routes
```
GET  /                          → welcome view
GET  /login                     → LoginController@showLoginForm
POST /login                     → LoginController@login
POST /logout                    → LoginController@logout
GET  /register                  → RegisterController@showRegistrationForm
POST /register                  → RegisterController@register
GET  /auth/{provider}/redirect  → SocialiteController@redirect
GET  /auth/{provider}/callback  → SocialiteController@callback
POST /midtrans/notification     → MidtransController@notification
GET  /portal                    → PortalController@index
POST /portal                    → PortalController@lookup
GET  /portal/bayar/{invoice}    → PortalController@bayar
GET  /portal/finish             → PortalController@finish
GET  /isolir/{customer}         → IsolirController@index
GET  /isolir/by-ip              → IsolirController@byIp
GET  /isolir                    → redirect to by-ip
GET  /hotspot/{page}            → VoucherTemplate dinamis (login/status/redirect/error/alive/logout)
GET  /sitemap.xml               → SitemapController@index
GET  /vouchers/public           → PublicVoucherController@index
POST /vouchers/public/generate  → PublicVoucherController@generate
GET  /vouchers/check            → PublicVoucherController@check
POST /vouchers/check-status     → PublicVoucherController@checkStatus
POST /api/v1/mikrotik/hotspot-login → MikrotikHotspotController@hotspotLogin
GET  /api/cron/run              → CronController@run
```

### Authenticated Routes (`middleware: auth`, `middleware: teknisi`)

| Prefix | Routes |
|--------|--------|
| `/dashboard` | GET → dashboard |
| `/customers*` | CRUD + suspend/activate + sync-pppoe + sync-single-onu |
| `/invoices*` | CRUD + paid + print + pdf + reminder (email & WA) |
| `/payments*` | create + store + history + destroy |
| `/reports` | GET → report index |
| `/mikrotik*` | dashboard, profiles, active, ppp, queues, backup, monitoring, live |
| `/settings*` | index + update + test-mikrotik |
| `/logs` | GET → activity log index |
| `/distribution*` | ODC/ODP route/point/odp CRUD |
| `/olts*` | CRUD + test + scan + reboot + remove + syncPorts + linkCustomer + syncFromMikrotik + live |
| `/olts-monitoring` | GET → monitor gangguan |
| `/olts/map` | GET → leaflet map |
| `/olts/export` | GET → CSV export OLT |
| `/onus/export` | GET → CSV export ONU |
| `/onus/search` | GET → cari ONU |
| `/vouchers*` | index + print + print-batch + mark-used + report |
| `/vouchers/public*` | public_index + generate + check |
| `/packages` | GET → index |
| `/midtrans/*` | pay + finish |
| `/api/odp-routes` | GET → JSON data routes |
| `/api/odp-points` | GET → JSON data points |
| `/invoice/pdf/{invoice}` | GET → download PDF |

### Admin Routes (`middleware: auth`, `middleware: admin`)

| Prefix | Routes |
|--------|--------|
| `/settings` | index + update + test-mikrotik |
| `/reports` | GET → report index |
| `/mikrotik/profiles*` | store + destroy |
| `/mikrotik/ppp*` | store + destroy |
| `/mikrotik/queues*` | store + destroy |
| `/mikrotik/backup` | POST → backup |
| `/mikrotik/live` | GET → live data |
| `/mikrotik-routers*` | CRUD + test |
| `/voucher-profiles*` | CRUD |
| `/voucher-templates*` | CRUD + preview |
| `/vouchers*` | create + store + quick-print + destroy + sync-mikrotik |
| `/packages*` | store + update + destroy + mass-bill |
| `/customers/sync-pppoe` | POST → bulk sync |
| `/olts/sync-all-onu` | POST → bulk sync |
| `/distribution/odcs*` | CRUD + proteksi cascade |
| `/distribution/routes*` | CRUD |
| `/distribution/points*` | CRUD |
| `/distribution/odps*` | store + CRUD |
| `/odc/{odc}` | GET → detail ODC |
| `/odp/{odp}` | GET → detail ODP |
| `/backups*` | index + download + destroy + database |
| `/export/*` | invoices CSV + payments CSV |

---

## 7. Artisan Commands (8 Commands)

| Command | Deskripsi | Schedule | CLI Path |
|---------|-----------|----------|----------|
| `billing:process` | Generate invoice bulanan + kirim WA reminder per tenant | `dailyAt('08:00')` | `app/Console/Commands/BillingProcess.php` |
| `olt:poll` | Dispatch OLT polling jobs (queue) — update status ONU | `hourly()` — tanpa overlapping | `app/Console/Commands/PollOlt.php` |
| `customers:onu-sync` | Sync ONU dari data PPPoE session MikroTik | `hourly()` — tanpa overlapping | `app/Console/Commands/SyncCustomerOnu.php` |
| `customer:auto-isolir` | Auto-suspend pelanggan overdue, swap PPP profile, tambah IP address-list | `dailyAt('00:30')` — tanpa overlapping | `app/Console/Commands/AutoIsolir.php` |
| `customer:sync-isolir-ips` | Sync IP pelanggan suspended ke firewall address-list | `everyFiveMinutes()` — tanpa overlapping | `app/Console/Commands/SyncIsolirIps.php` |
| `mikrotik:setup-isolir` | Setup PPP Profile-Isolir, DST-NAT redirect, DROP rules di MikroTik | Manual | `app/Console/Commands/MikrotikSetupIsolir.php` |
| `hotspot:import` | Import file HTML hotspot dari `public/hotspot/` ke database | Manual | `app/Console/Commands/ImportHotspotFiles.php` |
| `voucher:sync-mikrotik` | Sync status voucher dengan MikroTik (auto-mark expired/used) | Manual (digantikan event-driven API) | `app/Console/Commands/SyncVoucherMikrotik.php` |

**Scheduled (console.php):**
```
billing:process               → dailyAt('08:00')
olt:poll                      → hourly() (tanpa overlapping)
customers:onu-sync            → hourly() (tanpa overlapping)
customer:auto-isolir          → dailyAt('00:30') (tanpa overlapping)
customer:sync-isolir-ips      → everyFiveMinutes() (tanpa overlapping)
```

**External Cron:**
```
GET /api/cron/run?token={CRON_TOKEN}  → triggers Artisan schedule:run
```
Digunakan untuk Vercel Hobby tier yang tidak memiliki native cron. Panggil dari cron-job.org atau UptimeRobot.

---

## 8. Environment & Deployment

### 8.1 Vercel
- **Runtime:** `vercel-php@0.9.0`
- **Bootstrap:** `api/index.php` — `migrate --force` tiap cold request
- **Framework:** `null` (bukan Laravel Vite)
- **Build:** `php artisan config:cache && php artisan route:cache && npm run build`
- **Output:** `public/` directory
- **Cold start:** Migrate setiap request, tanpa `db:seed`

### 8.2 Vercel Env
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rabegnet.vercel.app
SESSION_DRIVER=cookie
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
DB_CONNECTION=mysql → Aiven
```

### 8.3 Local Env (.env)
```
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_CONNECTION=mysql → Laragon MySQL
APP_TIMEZONE=Asia/Jakarta
```

### 8.4 SSL Database
- Aiven MySQL requires SSL
- Config: `config/database.php` → `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` = `false` via env

### 8.5 Queue
- **Local:** `QUEUE_CONNECTION=database` — jobs table, `php artisan queue:listen`
- **Vercel:** `QUEUE_CONNECTION=sync` — blocking, tidak async

### 8.6 Deployment
- Branch `main` push → auto-deploy to Vercel
- `.github/workflows/deploy.yml`

---

## 9. Testing

| Detail | Value |
|--------|-------|
| Framework | PHPUnit 11 + Mockery |
| Database | SQLite `:memory:`, no external DB |
| Suites | `tests/Unit` (1 file) + `tests/Feature` (7 files) |
| Run | `composer test` / `vendor/bin/phpunit` |
| Total test methods | ~55 (semua pass) |
| Use RefreshDatabase | 5 feature classes (Auth, Customer, Distribution, Invoice, Package) |
| Coverage | Auth (login/register/logout/dashboard+ODP), Customer (CRUD/invoice creation), Invoice (CRUD/paid/destroy/print), Package (CRUD/filter/destroy protection), Distribution (ODC/Route/Point CRUD + cascade), Sitemap, Example |

**Test files:**
```
tests/
├── Feature/
│   ├── AuthTest.php          # 7 tests
│   ├── CustomerTest.php      # 10 tests
│   ├── DistributionTest.php  # 18 tests (ODC, Route, Point, ODP, cascade)
│   ├── InvoiceTest.php       # 6 tests
│   ├── PackageTest.php       # 11 tests
│   ├── SitemapTest.php       # 2 tests
│   └── ExampleTest.php       # 1 test
└── Unit/
    └── ExampleTest.php       # 1 test
```

---

## 10. Constraints & Catatan

| # | Constraint | Detail |
|---|-----------|--------|
| 1 | PHP 8.5 di Vercel | `fake()` unavailable — jangan pakai Factory di production path |
| 2 | Cold start | `migrate` per request — hindari `db:seed` |
| 3 | Multi-tenant via BelongsToTenant | Semua model utama pakai `BelongsToTenant` — scope per `tenant_id`, Tenant model sebagai root |
| 4 | BelongsToUser legacy | Trait masih ada tapi sudah tidak dipakai (dead code) — semua model migrated ke BelongsToTenant |
| 5 | SSH ke OLT | Wajib koneksi internet keluar dari Vercel ke IP OLT |
| 6 | Rx power threshold | `< -27 dBm` = merah (sinyal terlalu lemah) |
| 7 | Aiven SSL | `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false` |
| 8 | No file sessions | Cookie session di Vercel (file system readonly) |
| 9 | Queue sync di Vercel | `QUEUE_CONNECTION=sync` — blocking, tidak async |
| 10 | OdcPort & OdpPort | Tidak punya `BelongsToTenant` scope — potensi data leak antar tenant |
| 11 | Password MikroTik | Tidak di-encrypt (beda dengan OLT yang pakai `encrypted` cast) |
| 12 | SSL verification disabled | MikroTik REST API pakai `withoutVerifying()` |
| 13 | Queue jobs timeout | PollOltJob: 60s, SendWhatsApp: 30s |
| 14 | Orphan file | `resources/views/dashboard.blade.php.backup` — tidak dipakai |
| 15 | Voucher sync dihapus dari schedule | Digantikan event-driven API `POST /api/v1/mikrotik/hotspot-login` |

---

## 11. Future Roadmap

| Fitur | Prioritas | Estimasi |
|-------|-----------|----------|
| SNMP monitoring (alternatif SSH) | Low | — |
| Notifikasi otomatis ONU offline (WA/Email) | Medium | — |
| Mobile-responsive dashboard teknisi | Medium | — |
| API publik untuk mobile app | Low | — |
| Multi-brand OLT: Nokia/Alcatel | Low | — |
| Integrasi Telegram Bot notifikasi | Low | — |
| Dark mode | Low | — |

---

## 12. File Structure — Key Files (81 PHP files di app/)

```
app/
├── Console/Commands/
│   ├── AutoIsolir.php              # Auto-suspend pelanggan overdue
│   ├── BillingProcess.php          # Generate invoice bulanan + WA
│   ├── ImportHotspotFiles.php      # Import HTML hotspot ke DB
│   ├── MikrotikSetupIsolir.php     # Setup firewall isolir di MikroTik
│   ├── PollOlt.php                 # Dispatch OLT polling jobs
│   ├── SyncCustomerOnu.php         # Sync ONU dari PPPoE sessions
│   ├── SyncIsolirIps.php           # Sync IP suspended ke firewall
│   └── SyncVoucherMikrotik.php     # Sync voucher dengan MikroTik
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── MikrotikHotspotController.php  # Event-driven hotspot login
│   │   │   └── OdpruteController.php          # JSON API ODP routes/points
│   │   ├── Auth/
│   │   │   ├── LoginController.php
│   │   │   ├── RegisterController.php
│   │   │   └── SocialiteController.php
│   │   ├── BackupController.php
│   │   ├── CronController.php             # External cron trigger
│   │   ├── CustomerController.php
│   │   ├── DashboardController.php
│   │   ├── DistributionController.php     # ODC/Route/Point/ODP CRUD
│   │   ├── ExportController.php
│   │   ├── InvoiceController.php
│   │   ├── IsolirController.php           # Public isolir landing pages
│   │   ├── LogController.php
│   │   ├── MidtransController.php
│   │   ├── MikrotikController.php
│   │   ├── MikrotikRouterController.php   # Multi-router CRUD
│   │   ├── OdcController.php              # Detail ODC
│   │   ├── OdpController.php              # Detail ODP
│   │   ├── OltController.php
│   │   ├── PackageController.php
│   │   ├── PaymentController.php
│   │   ├── PortalController.php
│   │   ├── PublicVoucherController.php    # Public voucher generation
│   │   ├── ReportController.php
│   │   ├── SettingController.php
│   │   ├── SitemapController.php
│   │   ├── VoucherController.php
│   │   ├── VoucherProfileController.php
│   │   ├── VoucherReportController.php
│   │   └── VoucherTemplateController.php
│   └── Middleware/
│       ├── IsAdmin.php
│       └── IsTeknisiOrAdmin.php
├── Jobs/
│   ├── PollOltJob.php                 # OLT polling queue job
│   └── SendWhatsAppNotification.php   # Fonnte WA queue job
├── Mail/
│   ├── InvoiceReminder.php
│   └── PaymentConfirmation.php
├── Models/
│   ├── ActivityLog.php
│   ├── Customer.php
│   ├── Invoice.php
│   ├── MikrotikRouter.php            # Multi-router support
│   ├── Odc.php
│   ├── OdcPort.php                   # No BelongsToTenant scope
│   ├── Odp.php                       # New ODP model
│   ├── OdpPoint.php
│   ├── OdpPort.php                   # No BelongsToTenant scope
│   ├── OdpRoute.php
│   ├── Olt.php
│   ├── OltPort.php
│   ├── Onu.php
│   ├── Package.php
│   ├── Payment.php
│   ├── Setting.php
│   ├── Tenant.php                    # Root multi-tenant model
│   ├── User.php
│   ├── Voucher.php
│   ├── VoucherProfile.php
│   ├── VoucherTemplate.php
│   └── Traits/
│       ├── BelongsToTenant.php       # Active multi-tenant scope
│       └── BelongsToUser.php         # Dead code (legacy)
├── Services/
│   ├── MidtransService.php
│   ├── MikrotikService.php           # 784 lines — REST API client
│   └── Olt/
│       ├── Contracts/OltConnector.php
│       ├── Drivers/
│       │   ├── CDataConnector.php
│       │   ├── FiberHomeConnector.php
│       │   ├── HuaweiConnector.php
│       │   ├── JumpHostConnector.php        # Decorator: SSH tunnel
│       │   ├── MikrotikSshProxyConnector.php # Decorator: via MikroTik
│       │   └── ZteConnector.php
│       ├── Factory/OltConnectorFactory.php
│       └── SshTunnel.php                   # SSH tunnel manager
database/migrations/    (46 files — 28+ tables)
resources/views/        (58 blade files)
routes/web.php          (148 Route:: calls)
routes/api.php          (POST /api/v1/mikrotik/hotspot-login)
routes/console.php      (5 scheduled commands)
bootstrap/app.php       (middleware alias: admin, teknisi. API routes via `api:`)
config/database.php     (mysql default local, sqlite testing)
api/index.php           (Vercel entry point)
vercel.json
```

---

## 13. Glossary

| Istilah | Definisi |
|---------|----------|
| **OLT** | Optical Line Terminal — perangkat induk fiber optik di ISP |
| **ONU** | Optical Network Unit — perangkat di rumah pelanggan |
| **ODC** | Optical Distribution Cabinet — kabinet distribusi fiber |
| **ODP** | Optical Distribution Point — titik distribusi fiber ke pelanggan (model baru) |
| **OdpPoint** | Legacy ODP model (masih dipakai untuk peta Leaflet) |
| **GPON** | Gigabit Passive Optical Network — standar fiber optik |
| **Rx Power** | Receive power — kekuatan sinyal yang diterima ONU |
| **Tx Power** | Transmit power — kekuatan sinyal yang dikirim ONU |
| **Fonnte** | WhatsApp gateway service Indonesia |
| **Midtrans** | Payment gateway Indonesia |
| **MikroTik** | RouterOS — manajemen bandwidth & hotspot |
| **Isolir** | Mekanisme auto-suspend pelanggan telat bayar + firewall blocking |
| **Jump Host** | SSH server perantara untuk reach OLT di jaringan terisolasi |
| **Voucher Profile** | Template konfigurasi voucher WiFi (speed, quota, price) |
| **Voucher Template** | Custom HTML pages untuk hotspot MikroTik (login, status, dll) |
