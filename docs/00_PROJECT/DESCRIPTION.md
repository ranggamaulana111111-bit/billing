# DESCRIPTION.md — RabegNet ISP Billing System

> **Target pembaca:** Developer baru, AI Agent, stakeholder.
> **Tujuan:** Menjadi sumber utama informasi proyek — gabungan dokumen bisnis, teknis, dan arsitektur dalam satu tempat.

---

## 1. Executive Summary

**RabegNet** adalah sistem billing ISP berbasis web untuk penyedia layanan internet (ISP) skala kecil hingga menengah. Sistem ini mencakup manajemen pelanggan, penagihan otomatis, pembayaran online (Midtrans), manajemen perangkat jaringan (OLT multi-brand, MikroTik, ODP/ODC), sistem voucher WiFi hotspot, serta monitoring infrastruktur.

**Masalah yang diselesaikan:**
- Penagihan manual → auto-generate invoice bulanan
- Pembayaran offline → integrasi Midtrans (QRIS, VA, dll)
- Monitoring OLT terbatas → SSH multi-brand dengan polling otomatis
- Manajemen ODP tidak terstruktur → peta interaktif Leaflet
- Voucher manual → generate, print, push ke MikroTik otomatis
- Pengingat tagihan → WA reminder via Fonnte
- Multi-tenant → setiap ISP memiliki data terpisah

**Fitur utama:** Customer management, billing & payment, OLT multi-brand monitoring, MikroTik management, fiber distribution mapping, voucher hotspot system, auto-isolir, reporting.

**Status proyek:** Production active — v1.1. Seluruh fitur inti selesai. Dalam tahap stabilisasi, security hardening, dan optimasi.

---

## 2. Project Identity

| Atribut | Nilai |
|---------|-------|
| Nama Proyek | RabegNet ISP Billing System |
| Versi | 1.1 |
| Framework | Laravel 12 |
| Bahasa | PHP ^8.2, JavaScript |
| Database | MySQL (production), SQLite (testing) |
| Arsitektur | Monolithic + Multi-tenant (Global Scope) |
| Domain | rabegnet.vercel.app |
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

RabegNet beroperasi di domain **ISP FTTH (Fiber to The Home)** dengan cakupan:

| Area | Deskripsi |
|------|-----------|
| Customer Management | Registrasi, aktivasi, suspend, isolir, PPPoE management |
| Billing | Generate invoice otomatis (monthly), cetak/PDF/email/WA |
| Payment | Midtrans (QRIS/VA), manual (cash/transfer) |
| MikroTik | Manajemen PPPoE, hotspot, queue, bandwidth monitoring |
| OLT | Multi-brand (Huawei/ZTE/FiberHome/C-Data), ONU management, polling |
| Distribution | ODC → ODP mapping dengan peta interaktif, port management |
| Voucher | Generate, print (QR code), push ke MikroTik hotspot |
| Reporting | Revenue, outstanding, payment method, export CSV |

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
│  routes/web.php (~148 routes)                   │
│  routes/api.php (3 routes)                      │
│  routes/console.php (5 schedules)               │
├─────────────────────────────────────────────────┤
│              Controller Layer                   │
│  34 Controllers (Auth, API, Backup, dll)        │
│  Middleware: IsAdmin, IsTeknisiOrAdmin           │
├─────────────────────────────────────────────────┤
│               Service Layer                     │
│  MidtransService, MikrotikService               │
│  Olt/ (Driver Pattern)                          │
│    └─ Drivers: Huawei, ZTE, FiberHome, C-Data  │
│    └─ Decorators: JumpHost, MikroTikProxy       │
├─────────────────────────────────────────────────┤
│                Model Layer                      │
│  19 Models + 2 Traits                           │
│  BelongsToTenant (Global Scope)                 │
├─────────────────────────────────────────────────┤
│              Database Layer                     │
│  MySQL (production), SQLite (testing)           │
│  28 tables, 46 migrations                       │
├─────────────────────────────────────────────────┤
│         External Integration Layer              │
│  Midtrans Snap API, MikroTik REST API,          │
│  Fonnte WA API, Google OAuth                    │
└─────────────────────────────────────────────────┘
```

### Pola Desain

| Pattern | Penerapan |
|---------|-----------|
| **Monolithic** | Satu aplikasi Laravel, pemisahan Controller → Service → Model |
| **Multi-tenant (Global Scope)** | `BelongsToTenant` trait — setiap query otomatis difilter `tenant_id` |
| **Driver Pattern** | OLT multi-brand — factory memilih driver sesuai brand |
| **Decorator Pattern** | Jump Host SSH tunnel & MikroTik SSH Proxy — membungkus driver OLT |
| **Scheduled Tasks** | 5 console command berjalan otomatis (daily/hourly) |
| **Event-driven API** | Callback dari MikroTik hotspot → update status voucher |
| **Job Queue** | Polling OLT perangkat + notifikasi WA (database queue) |

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
 └── Customer akses halaman isolir → bayar → auto-activate
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
| Bootstrap | 5.3.8 | CSS framework utama |
| Leaflet | 1.9.4 | Peta interaktif ODP/OLT |
| Chart.js | 4.5.1 | Grafik dashboard |
| Alpine.js | — | Interaktivitas ringan |
| Vite | 7.x | Asset bundler |
| simple-qrcode | 4.2 | QR code inline SVG |

### Integrasi Pihak Ketiga

| Layanan | API | Fungsi |
|---------|-----|--------|
| Midtrans | Snap API | Pembayaran QRIS, VA, Convenience Store |
| Google | Socialite OAuth 2.0 | Login dengan Google |
| Fonnte | REST API | Notifikasi WhatsApp |
| MikroTik | REST API | Manajemen hotspot, PPP, queue, firewall |

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
- **Controller:** `CustomerController` (376 baris)
- **Model:** `Customer` — BelongsToTenant, HasFactory
- **Fitur:** CRUD, Suspend/Activate (otomatis disable/enable PPPoE MikroTik + buat ONU), Sync PPPoE, Sync ONU, auto-create invoice saat register
- **Routes:** `/customers`, `/customer/create`, `/customer/{id}/edit`, `/customer/{id}/suspend`, dll

### 8.4 Tagihan (Invoice)
- **Controller:** `InvoiceController` (269 baris)
- **Model:** `Invoice` — BelongsToTenant, HasFactory
- **Fitur:** CRUD, filter/search, mark paid, print, PDF (DomPDF), WA reminder (Fonnte), email reminder & confirmation
- **Routes:** `/invoices`, `/invoice/print/{id}`, `/invoice/pdf/{id}`, `/invoice/reminder/{id}`

### 8.5 Pembayaran (Payment)
- **Controller:** `PaymentController` (71 baris)
- **Model:** `Payment` — BelongsToTenant
- **Fitur:** Catat pembayaran (cash/transfer/QRIS), history, hapus (auto-update invoice), integrasi Midtrans
- **Routes:** `/payment/create/{invoice}`, `/payment/history/{invoice}`

### 8.6 Paket Internet (Package)
- **Controller:** `PackageController` (135 baris)
- **Model:** `Package` — BelongsToTenant, HasFactory
- **Fitur:** CRUD, proteksi delete (jika ada customer), mass billing, filter
- **Routes:** `/packages`, `/packages/mass-bill`

### 8.7 OLT Management
- **Controller:** `OltController` (650 baris)
- **Models:** `Olt`, `OltPort`, `Onu` — semua BelongsToTenant
- **Driver Pattern:** Multi-brand (Huawei, ZTE, FiberHome, C-Data) via SSH + decorator JumpHost/MikroTikProxy
- **Fitur:** CRUD OLT, test SSH, scan ONU, reboot/remove ONU, link ke customer, monitoring ONU (sort by Rx power), map OLT, live JSON API, export CSV
- **Routes:** `/olts`, `/olts/{olt}/scan`, `/olts-monitoring`, `/olts/map`, `/onus/search`

### 8.8 Distribusi ODP/ODC
- **Controller:** `DistributionController` (259 baris), `OdcController`, `OdpController`
- **Models:** `Odc`, `OdcPort`, `Odp`, `OdpPort`, `OdpRoute`, `OdpPoint`
- **Struktur:** ODC → ODC Port → ODP → ODP Port → Customer
- **Fitur:** Map interaktif Leaflet, port grid (available/used/broken), relasi ODC-ODP via `connected_to_odp_id`, auto-generate port saat create, auto-refresh port status (15s polling), API port data realtime
- **Routes:** `/distribution`, `/odc/{odc}`, `/odp/{odp}`, `/api/v1/odc/{odc}/ports`, `/api/v1/odp/{odp}/ports`

### 8.9 Voucher WiFi (Hotspot)
- **Controller:** `VoucherController` (355 baris)
- **Model:** `Voucher` — BelongsToTenant, HasFactory
- **QR Code:** `simplesoftwareio/simple-qrcode` (inline SVG)
- **Fitur:** Generate (random user/pass), push ke MikroTik, print (single/batch), sync status, report, auto-expire, event-driven callback via `POST /api/v1/mikrotik/hotspot-login`
- **Templates:** 6 halaman hotspot (login, status, redirect, error, alive, logout) — bisa dikustomisasi

### 8.10 MikroTik Management
- **Controller:** `MikrotikController`
- **Service:** `MikrotikService` (784 baris) — REST API wrapper
- **Fitur:** Dashboard (system resource, health), hotspot profiles/users, PPP secrets, simple queues, active sessions (disconnect), backup, bandwidth monitoring, live JSON API

### 8.11 Portal Publik
- **Controller:** `PortalController` (110 baris)
- **Fitur:** Cek tagihan by phone, bayar via Midtrans, self-service
- **Routes:** `/portal`, `/portal/bayar/{invoice}`

### 8.12 Isolir Subsystem
- **Controller:** `IsolirController` + 3 console commands
- **Fitur:** Auto-suspend pelanggan overdue (00:30 daily), set PPP Profile "Isolir" di MikroTik, sync IP ke firewall address-list (every 5 menit), halaman publik redirect untuk pelanggan kena isolir
- **Commands:** `customer:auto-isolir`, `customer:sync-isolir-ips`, `mikrotik:setup-isolir`

### 8.13 Laporan (Report)
- **Controller:** `ReportController` (70 baris)
- **Fitur:** Revenue bulanan, outstanding, chart 12 bulan, metode pembayaran, top unpaid
- **Routes:** `/reports` (admin only)

### 8.14 Pengaturan (Setting)
- **Controller:** `SettingController` (76 baris)
- **Model:** `Setting` — key-value store per tenant
- **Keys:** company info, bank, Midtrans keys, MikroTik config, Fonnte token, voucher length, late fee, due date
- **Routes:** `/settings` (admin only)

### 8.15 Backup & Export
- **Controllers:** `BackupController`, `ExportController`
- **Fitur:** Download backup database, export CSV invoices/payments, export CSV OLT/ONU

---

## 9. Database Overview

### 28 Tabel — 46 Migrations

#### Pengelompokan Tabel

| Grup | Tabel |
|------|-------|
| **Core/System** | `tenants`, `users`, `settings`, `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs` |
| **Billing** | `customers`, `packages`, `invoices`, `payments` |
| **Infrastructure** | `olts`, `olt_ports`, `onus`, `odcs`, `odc_ports`, `odps`, `odp_ports`, `odp_routes`, `odp_points`, `mikrotik_routers` |
| **Voucher** | `vouchers`, `voucher_profiles`, `voucher_templates` |
| **Activity** | `activity_logs` |

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
      → Dispatch SendWhatsAppNotification (WA reminder)
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
```

---

## 11. External Integrations

| Integrasi | Metode | Detail |
|-----------|--------|--------|
| **Midtrans** | REST API (Snap) | Pembayaran QRIS, VA, Convenience Store. Webhook callback. Config via settings (server key, client key, is_production) |
| **MikroTik** | REST API (port 8728) | HTTP Basic Auth, tanpa SSL verification (`withoutVerifying()`). Manajemen hotspot, PPP, queue, firewall, backup |
| **Google Login** | OAuth 2.0 (Socialite) | Login dengan akun Google. Config di `config/services.php` |
| **Fonnte** | REST API | Notifikasi WhatsApp untuk reminder tagihan + konfirmasi pembayaran. Config via settings (token) |
| **QR Code** | `simple-qrcode` | Inline SVG, tanpa external API. Untuk voucher WiFi |
| **DomPDF** | `barryvdh/laravel-dompdf` | Generate PDF invoice untuk print/download |

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
│   ├── Console/Commands/      # 8 Artisan commands
│   ├── Http/
│   │   ├── Controllers/       # 32 controllers (+ 2 Api/)
│   │   └── Middleware/        # IsAdmin, IsTeknisiOrAdmin
│   ├── Jobs/                  # PollOltJob, SendWhatsAppNotification
│   ├── Mail/                  # InvoiceReminder, PaymentConfirmation
│   ├── Models/                # 19 models + 2 traits
│   └── Services/              # MidtransService, MikrotikService, Olt/ (drivers)
│
├── bootstrap/                 # Laravel bootstrap + app.php
├── config/                    # Konfigurasi Laravel
├── database/
│   ├── factories/             # 5 factories
│   ├── migrations/            # 46 migrations (28 tables)
│   └── seeders/               # 5 seeders
│
├── public/
│   ├── build/                 # Asset compiled (Vite)
│   └── hotspot/               # HTML hotspot pages
│
├── resources/
│   ├── css/app.css            # ~1570 baris custom CSS
│   ├── js/                    # app.js, bootstrap.js
│   └── views/                 # ~58 blade files
│
├── routes/
│   ├── web.php                # ~148 routes
│   ├── api.php                # 3 API routes
│   └── console.php            # 5 scheduled commands
│
├── storage/                   # Logs, cache, backups
│
└── tests/
    ├── Feature/               # 7 test classes (54 methods)
    └── Unit/                  # 1 test class (1 method)
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

PHP CLI default = 8.1 (tidak cukup untuk Laravel 12). Gunakan:
```
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan {command}
```

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
- **CSS:** Bootstrap 5.3 classes + custom CSS di `app.css`

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

### Functional Completeness

| Modul | Status | Catatan |
|-------|--------|---------|
| Auth (Login/Register/Google OAuth) | ✅ 100% | Role admin & teknisi |
| Dashboard | ✅ 100% | Stats, charts, map, timeline |
| Customer CRUD | ✅ 100% | + Suspend/Activate/Sync |
| Invoice CRUD | ✅ 100% | + Print/PDF/WA/Email |
| Payment | ✅ 100% | + Midtrans integration |
| Package CRUD | ✅ 100% | + Mass billing |
| OLT Management | ✅ 100% | Multi-brand, scan, monitoring |
| MikroTik Management | ✅ 100% | Hotspot, PPP, queue, monitoring |
| Distribution (ODC/ODP) | ✅ 100% | Map, port grid, realtime API |
| Voucher System | ✅ 100% | Generate, print, push, sync |
| Portal Publik | ✅ 100% | Cek tagihan, bayar |
| Isolir Subsystem | ✅ 100% | Auto-suspend, firewall sync |
| Settings | ✅ 100% | Key-value store |
| Activity Log | ✅ 100% | Filterable log |
| Backup & Export | ✅ 100% | DB backup, CSV export |
| Report | ✅ 100% | Revenue, outstanding, charts |

### Non-Functional Completeness

| Aspek | Status | Catatan |
|-------|--------|---------|
| Testing (55 tests) | ✅ 100% | 7 feature + 1 unit test class |
| Security | ⚠️ Partial | Password MikroTik plaintext, SSL verify disabled |
| Multi-tenant | ✅ 100% | BelongsToTenant global scope |
| Error Handling | ✅ 100% | Try-catch di service layer |
| Logging | ✅ 100% | Activity log + Laravel log |
| Queue | ✅ 100% | Database queue untuk OLT polling + WA |
| Validation | ✅ 100% | Form request validation |

### Security Issues (Known)

1. **Password MikroTik di DB tidak di-encrypt** — plaintext di `mikrotik_routers.password`
2. **SSL verification disabled** — `withoutVerifying()` di koneksi REST API MikroTik
3. **`OdcPort` & `OdpPort`** — tidak punya `BelongsToTenant` (potensi data leak antar tenant)
4. **`reset_data.php`** — script destruktif tanpa proteksi
5. **Token/kredensial di file commit** — `.env`, `vercel.json`, `checker.md` mengandung sensitive credentials

---

## 17. Roadmap Summary

### v1.0 (Current — Production Active)
- ✅ Semua fitur inti selesai
- ✅ Multi-tenant dengan BelongsToTenant
- ✅ Deployment Vercel + Railway

### v1.2 (Next — Stabilisasi & Security)
- 🔜 Encrypt password MikroTik di database
- 🔜 Enable SSL verification (opsional/configurable)
- 🔜 Tambah BelongsToTenant ke OdcPort & OdpPort
- 🔜 Proteksi reset_data.php
- 🔜 Hapus sensitive file dari git history

### v2.0 (Medium-term)
- Dashboard khusus Owner/Management
- Notifikasi realtime (WebSocket/Pusher)
- API publik untuk integrasi pihak ketiga
- Dark mode

### v3.0 (Long-term)
- NMS (Network Management System) features
- Advanced reporting & analytics
- Multi-language support
- Mobile app (optional)

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
| **Fonnte** | Platform WhatsApp Gateway untuk notifikasi |
| **Isolir** | Status suspend otomatis karena overdue pembayaran |
| **Queue** | Antrian job (polling OLT, WA notification) yang diproses async |
| **REST API** | HTTP API untuk integrasi dengan perangkat eksternal (MikroTik) |
| **Driver** | Implementasi spesifik per brand OLT (Huawei, ZTE, dsb) |
| **Jump Host** | SSH tunnel via server perantara untuk akses OLT |
