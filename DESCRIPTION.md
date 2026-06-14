# RabegNet — ISP Billing System v1.0

Sistem billing ISP terintegrasi untuk manajemen pelanggan, tagihan, pembayaran, inventaris, dan monitoring infrastruktur jaringan (OLT, ODP, MikroTik) — dibangun dengan **Laravel 12** + **Bootstrap 5** + **Leaflet.js** + **Chart.js**.

---

## 1. Stack Teknologi

| Layer | Teknologi |
|-------|-----------|
| Backend | Laravel 12 (PHP ^8.2) |
| Database | SQLite (lokal / dev), MySQL Aiven via SSL (production) |
| Frontend | Bootstrap 5.3, Font Awesome 6, Leaflet 1.9, Chart.js 4 |
| Asset Bundler | Vite + laravel-vite-plugin |
| CSS | Tailwind CSS v4 (via `@import 'tailwindcss'`), CSS custom |
| Deployment | Vercel + GitHub Actions (push main trigger) |
| Payment Gateway | Midtrans (Snap API) |
| OAuth | Google Login (Laravel Socialite) |
| PDF | DomPDF (invoice print) |
| OLT SSH | phpseclib3 (multi-brand) |
| MikroTik | REST API (http:// + basic auth) |
| WA Gateway | Fonnte API |

---

## 2. Arsitektur

### Database — 25 Migrations

```
users ─┬── customers ──────┬── invoices ───┬── payments
       │                    │               │
       │                    ├── onus ───────┘
       │                    └── odp_points
       ├── packages                         
       ├── vouchers
       ├── settings
       ├── activity_logs
       ├── olts ─── olt_ports ─── onus
       ├── odcs ─── odp_routes ── odp_points ─── customers
       ├── cache / cache_locks
       ├── jobs / job_batches / failed_jobs
       └── sessions
```

### Multi-Tenancy (Lightweight)

Semua model utama menggunakan trait `BelongsToUser` yang menerapkan:
- **Global scope** `WHERE user_id = ?` pada setiap query
- **Auto-fill** `user_id` saat create (dari `Auth::id()`)
- **Scope helpers**: `forUser($id)`, `allUsers()` (skip scope)

Setiap user (tenant) melihat data mereka sendiri.

### Role System (Aktif)

- Kolom `users.role` — default `teknisi`
- Middleware `IsAdmin` (alias `admin`) — hanya `role === 'admin'` lolos
- Middleware `IsTeknisiOrAdmin` (alias `teknisi`) — role `admin` atau `teknisi` lolos
- **Route split**: `teknisi` middleware sebagai base auth untuk semua user; `admin` middleware khusus route sensitif (settings, backup, packages CRUD, voucher CRUD, reports, export, distribution CRUD)

### OLT Multi-Brand Driver Pattern

```
OltConnector (interface)
├── HuaweiConnector  → CLI: system-view, display ont info, display ont optical-info
├── ZteConnector     → CLI: enable, configure terminal, show onu unquiet, show onu optical-info
└── FiberHomeConnector → CLI: show ont list, show ont optic
```

Factory `OltConnectorFactory::make($brand)` memilih driver sesuai brand.

---

## 3. Fitur Lengkap

### 3.1 Autentikasi & Manajemen User

- Login / Register (email + password)
- Login via Google (Socialite)
- Logout
- Role-based access (admin / teknisi) — middleware siap pakai

### 3.2 Dashboard

- **4 stat cards**: Total pelanggan, pemasukan hari ini, piutang, total tagihan
- **Revenue chart**: Bar chart 6 bulan dengan gradient
- **Status tagihan**: Donut chart (lunas vs belum)
- **Metode pembayaran**: Donut chart (cash, transfer, QRIS, Midtrans)
- **Distribusi paket**: Horizontal bar chart
- **Tabel**: Paket internet, titik ODP (dengan progress bar port), pembayaran terakhir, pelanggan terbaru, tagihan belum dibayar
- **Live map**: Peta Leaflet marker ODP (hijau/merah per ketersediaan port)
- **Aktivitas terakhir**: Timeline log
- **Jam live**: Clock real-time

### 3.3 Manajemen Pelanggan (Customers)

- CRUD lengkap
- Field: nama, lokasi, telepon, email, paket, titik ODP, PPPoE username, tanggal jatuh tempo, status (active/suspended/inactive)
- Suspend / Activate pelanggan
- Sync PPPoE ke MikroTik (push secret ke PPP profile)

### 3.4 Tagihan (Invoices)

- Generate invoice manual
- Auto-generate via schedule `billing:process` (daily pukul 08:00) — per tenant
- Mark as paid (lunas)
- Print invoice (DOM PDF)
- Kirim reminder WA (Fonnte): H-3, H-1, jatuh tempo, telat H+1, H+3, H+7
- Kirim email reminder (Mail)
- Kirim email payment confirmation

### 3.5 Pembayaran (Payments)

- Catat pembayaran (cash, transfer, QRIS, Midtrans)
- History pembayaran per invoice
- Batal pembayaran (update status invoice otomatis)
- Integrasi Midtrans (QRIS, Virtual Account, dll) via Snap API

### 3.6 Paket Internet (Packages)

- CRUD paket
- Field: nama, speed, harga, deskripsi, billing cycle (monthly), MikroTik profile, status aktif/nonaktif
- Mass billing: generate tagihan untuk semua pelanggan aktif sekaligus

### 3.7 Voucher WiFi (Hotspot)

- Generate voucher dengan random username/password
- Durasi: jam atau hari (1-720 jam)
- Cetak per voucher atau batch (print layout)
- Quick print dari dashboard
- Status: active, used, expired
- Tandai terpakai manual
- Push ke MikroTik hotspot user otomatis
- Sync dari MikroTik — deteksi session aktif → mark used
- Sinkronisasi otomatis via schedule `voucher:sync-mikrotik` (tiap 5 menit)
- **Belum ada**: kolom `price`, profile/server selector, export CSV, 6 halaman hotspot statis

### 3.8 OLT Management

- **CRUD OLT**: Multi-brand (Huawei, ZTE, FiberHome)
- **Field**: nama, brand, model, IP, port SSH/SNMP, username, password (encrypted), lokasi, lat/lng, status
- **Test koneksi**: SSH ke OLT, display version/system info
- **Scan ONU**: Scan semua port → update/create ONU dengan serial, status, Rx/Tx power
- **Reboot ONU**: Remote reboot via SSH
- **Remove ONU**: Hapus dari OLT dan database
- **Link ONU ke customer**: Binding ONU ke pelanggan
- **Sync ports**: Tambah port OLT manual
- **Show detail**: Per-port ONU dashboard dengan mini map
- **Polling otomatis**: `olt:poll` tiap 15 menit — update status ONU

### 3.9 Monitoring Gangguan

- **Offline ONU**: Daftar ONU yang tidak online
- **Sinyal lemah**: Filter Rx power < -27 dBm (merah, masalah optik)
- **Tombol reboot langsung** dari panel monitoring
- **CSV Export**: Export OLT list + ONU list (filter by OLT/status)

### 3.10 Map OLT (Leaflet)

- Peta dengan marker per OLT
- Warna marker: hijau (active), kuning (maintenance), merah (inactive)
- Popup: nama, brand, IP, lokasi, status, total ONU, online ONU, last polled
- Sidebar daftar OLT dengan info ringkas
- Klik marker → zoom ke detail
- **Create/Edit form**: Peta klik untuk set lat/lng — marker draggable

### 3.11 Search / Filter ONU

- Cari berdasarkan ONU ID, serial number, nama customer
- Filter status (online/offline)
- Filter per OLT
- Aksi: reboot, hapus

### 3.12 Distribusi ODP

- **ODC**: CRUD dengan lokasi + kapasitas
- **ODP Route**: CRUD per ODC, dengan color + coordinates (polyline di Leaflet)
- **ODP Point**: CRUD per route, dengan lokasi + port capacity/used
- Map Leaflet interaktif dengan marker ODP per status port
- Proteksi delete: route dengan points tidak bisa dihapus, point dengan customers tidak bisa dihapus

### 3.13 Manajemen MikroTik

- Dashboard: system resource, identity, health, uptime
- **Hotspot Profiles**: CRUD
- **Hotspot Active Sessions**: Lihat + disconnect session
- **PPP Secrets**: CRUD (PPPoE user management)
- **PPP Active Sessions**: Lihat + disconnect
- **Simple Queues**: CRUD (bandwidth management)
- **Backup**: Trigger backup dari panel
- **Monitoring**: Interface traffic real-time, log sistem, interface list

### 3.14 Laporan (Reports)

- Halaman index dengan link ke berbagai export:
  - Export pelanggan (CSV)
  - Export tagihan (CSV)
  - Export pembayaran (CSV)
  - Export paket (CSV)

### 3.15 Portal Publik

- Halaman publik untuk pelanggan cek tagihan (via input telepon)
- Lihat daftar tagihan
- Bayar via Midtrans (QRIS/VA)
- Halaman selesai pembayaran

### 3.16 Midtrans Payment Gateway

- Snap API (popup QR)
- Server-side notification handler (webhook)
- Update status invoice otomatis
- Midtrans order ID tracking

### 3.17 Pengaturan (Settings)

- Key-value settings per user
- MikroTik config: host, user, password, port, hotspot server
- Fonnte token (WA gateway)
- Voucher settings: username length, password length
- Company name
- Test MikroTik koneksi dari panel

### 3.18 Log Aktivitas

- Semua aktivitas tercatat: CRUD, login, export, sync, dll
- Fitur: action, details, user, timestamp
- Filter: search, date range
- Auto-log dari berbagai controller via `ActivityLog::log()`

### 3.19 Backup

- Download database SQLite
- Hapus backup
- Proteksi: backup via password

### 3.20 Export CSV

- Export OLT
- Export ONU (filter by OLT/status)
- Export Invoices
- Export Payments
- Export Customers
- Export Packages

---

## 4. Scheduled Tasks (Console)

```php
Schedule::command('billing:process')->dailyAt('08:00');
Schedule::command('voucher:sync-mikrotik')->everyFiveMinutes();
Schedule::command('olt:poll')->everyFifteenMinutes();
```

| Command | Fungsi |
|---------|--------|
| `billing:process` | Generate invoice bulanan + kirim WA reminder per tenant |
| `voucher:sync-mikrotik` | Sync status voucher (active → used/expired) + push ke MikroTik |
| `olt:poll` | Poll semua OLT aktif via job per-OLT (`PollOltJob`), update status & Rx/Tx ONU. Default sync (`dispatchSync`), `--queue` flag untuk async via worker. `withoutOverlapping()` |

---

## 5. Route Map

### Public Routes
```
GET  /                   → welcome
GET  /login              → login form
POST /login              → login action
POST /logout             → logout
GET  /register           → register form
POST /register           → register action
GET  /auth/{provider}    → socialite redirect
GET  /auth/{provider}/callback → socialite callback
GET  /portal             → portal index (cek tagihan)
POST /portal             → portal lookup
GET  /portal/bayar/{invoice} → portal bayar
GET  /portal/finish      → portal finish
POST /midtrans/notification   → midtrans webhook
```

### Authenticated Routes — Teknisi & Admin (`teknisi` middleware)
```
GET  /dashboard                          → Dashboard
GET  /customers                          → Customer list (CRUD)
POST /customer                           → Create customer
GET  /customer/{id}/edit                 → Edit customer
PUT  /customer/{id}                      → Update customer
DELETE /customer/{id}                    → Hapus customer
POST /customer/{id}/suspend              → Suspend customer
POST /customer/{id}/activate             → Activate customer
GET  /invoices                           → Invoice list
GET  /invoices/create                    → Create invoice
POST /invoices                           → Store invoice
GET  /invoice/{id}/edit                  → Edit invoice
PUT  /invoice/{id}                       → Update invoice
DELETE /invoice/{id}                     → Hapus invoice
GET  /invoice/paid/{id}                  → Mark paid
GET  /invoice/print/{id}                 → Print invoice
GET  /invoice/pdf/{id}                   → Download PDF
GET  /invoice/reminder/{id}              → Kirim WA reminder
GET  /invoice/email-reminder/{id}        → Kirim email reminder
GET  /payment/create/{invoice}           → Create payment
POST /payments                           → Store payment
GET  /payment/history/{invoice}          → Payment history
DELETE /payment/{id}                     → Hapus payment
GET  /packages                           → Package list (read-only for teknisi)
GET  /vouchers                           → Voucher list
GET  /vouchers/{id}/print                → Print voucher
POST /vouchers/print-batch               → Print batch
POST /vouchers/{id}/used                 → Mark used
GET  /olts                               → OLT list (CRUD)
GET  /olts/create                        → Create OLT
POST /olts                               → Store OLT
GET  /olts/{olt}                         → Show OLT detail
GET  /olts/{olt}/edit                    → Edit OLT
PUT  /olts/{olt}                         → Update OLT
DELETE /olts/{olt}                       → Hapus OLT
POST /olts/{olt}/test                    → Test SSH koneksi
POST /olts/{olt}/scan                    → Scan ONU
POST /olts/{olt}/onu/{onu}/reboot        → Reboot ONU
DELETE /olts/{olt}/onu/{onu}             → Remove ONU
POST /olts/{olt}/ports                   → Sync ports
POST /onu/{onu}/link-customer            → Link ONU ke customer
GET  /olts-monitoring                    → Monitoring ONU
GET  /olts/map                           → Map OLT
GET  /olts/export                        → Export CSV OLT
GET  /onus/export                        → Export CSV ONU
GET  /onus/search                        → Search ONU
GET  /mikrotik                           → MikroTik dashboard
GET  /mikrotik/profiles                  → Hotspot profiles (read-only)
GET  /mikrotik/active                    → Active sessions
POST /mikrotik/active/disconnect/{id}    → Disconnect hotspot
POST /mikrotik/active/ppp-disconnect/{id} → Disconnect PPP
GET  /mikrotik/ppp                       → PPP secrets (read-only)
GET  /mikrotik/queues                    → Simple queues (read-only)
GET  /monitoring                         → Monitoring BW
GET  /logs                               → Activity log
GET  /distribution                       → ODC/ODP/Route (read-only)
GET  /midtrans/pay/{invoice}             → Pay via Midtrans
GET  /midtrans/finish                    → Midtrans finish
GET  /api/odp-routes                     → JSON API routes
GET  /api/odp-points                     → JSON API points
```

### Admin-Only Routes (`admin` middleware)
```
GET  /settings                           → Settings
POST /settings                           → Update settings
GET  /settings/test-mikrotik             → Test MikroTik
GET  /reports                            → Reports index
POST /mikrotik/profiles                  → Create profile
DELETE /mikrotik/profiles/{id}           → Hapus profile
POST /mikrotik/ppp                       → Create PPP secret
DELETE /mikrotik/ppp/{id}                → Hapus PPP secret
POST /mikrotik/queues                    → Create queue
DELETE /mikrotik/queues/{id}             → Hapus queue
POST /mikrotik/backup                    → Trigger backup
POST /distribution/odcs                  → Create ODC
PUT  /distribution/odcs/{id}             → Update ODC
DELETE /distribution/odcs/{id}           → Hapus ODC
POST /distribution/routes                → Create route
PUT  /distribution/routes/{id}           → Update route
DELETE /distribution/routes/{id}         → Hapus route
POST /distribution/points                → Create ODP point
PUT  /distribution/points/{id}           → Update ODP point
DELETE /distribution/points/{id}         → Hapus ODP point
POST /vouchers                           → Create voucher
GET  /vouchers/create                    → Create voucher form
POST /vouchers/quick-print               → Quick print
DELETE /vouchers/{id}                    → Hapus voucher
POST /vouchers/sync-mikrotik             → Sync ke MikroTik
POST /packages                           → Create package
PUT  /packages/{id}                      → Update package
DELETE /packages/{id}                    → Hapus package
POST /packages/mass-bill                 → Mass billing
POST /customers/sync-pppoe               → Sync PPPoE
GET  /backups                            → Backup index
GET  /backups/download/{filename}        → Download backup
DELETE /backups/{filename}               → Hapus backup
POST /backups/database                   → Create backup
GET  /export/invoices                    → Export CSV invoices
GET  /export/payments                    → Export CSV payments
```

---

## 6. OLT Driver Interface

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

| Aksi | Huawei | ZTE | FiberHome |
|------|--------|-----|-----------|
| Masuk mode | `system-view` | `enable` → `configure terminal` | (langsung) |
| Info sistem | `display version` | `show system information` | `show system-info` |
| Daftar ONU | `display ont info {slot} {port}` | `show onu unquiet interface gpon-olt_{slot}/{port}` | `show ont list slot {slot} port {port}` |
| Rx/Tx power | `display ont optical-info {s} {p} {o}` | `show onu optical-info {s} {p} {o}` | `show ont optic slot {s} port {p} ont {o}` |
| Reboot ONU | `interface gpon {s}/{p}` → `ont reset {o}` | `interface gpon-olt_{s}/{p}` → `onu reset {o}` | `ont reset slot {s} port {p} ont {o}` |
| Hapus ONU | `interface gpon {s}/{p}` → `ont delete {o}` | `interface gpon-olt_{s}/{p}` → `no onu {o}` | `ont delete slot {s} port {p} ont {o}` |
| Provision | `ont add {o} {sn}` + `ont port native-vlan` | `onu {o} type ont sn {sn}` | `ont add slot {s} port {p} sn {sn}` |

---

## 7. Deployment (Vercel)

- **Trigger**: Push ke branch `main` / `master`
- **CI**: GitHub Actions (`deploy.yml`)
- **Runtime**: `vercel-php@0.9.0`
- **Output**: `public/` directory
- **Routes**: Semua request → `api/index.php`, kecuali `/build/*`
- **Environment**: Cookie session, array cache, sync queue, stderr log
- **Database**: MySQL Aiven dengan SSL (CA cert + verify server cert = false)
- **Domain**: `rabegnet.vercel.app`

---

## 8. Testing

- **Framework**: PHPUnit 11 + Mockery
- **DB**: SQLite `:memory:` (tidak perlu DB eksternal)
- **Suites**: `Unit` (plain PHPUnit) + `Feature` (Laravel HTTP tests)
- **Tests**: 53 tests / 124 assertions (semua pass)
  - Auth: login, register, dashboard, logout
  - Customer: CRUD, suspend, activate
  - Invoice: CRUD, mark paid, print
  - Package: CRUD (via user admin), search, destroy protection
  - Distribution: ODC/Route/Point CRUD (via user admin), destroy protection
- **Factory**: `UserFactory` default role `teknisi` + `admin()` state untuk test admin-only routes
- **Code style**: Laravel Pint (default rules)

---

## 9. Lingkungan Development

```bash
# Serve lokal
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan serve

# Test
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan test

# Migrate
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan migrate

# Pint (format)
./vendor/bin/pint
```

---

## 10. Infrastruktur Jaringan (Data Model)

```
User (tenant)
 ├── ODC (cabang fiber)
 │    └── ODP Route (jalur kabel)
 │         └── ODP Point (titik splitter) ── port capacity, port used
 │              └── Customer (pelanggan)
 │
 ├── OLT (device)
 │    └── OLT Port (GPON port)
 │         └── ONU (ONT pelanggan) ── serial, Rx/Tx, status
 │              └── Customer (pemilik)
 │
 ├── Package (paket internet)
 │    └── Customer (subscribe)
 │
 └── Customer
      └── Invoice (tagihan bulanan)
           └── Payment (pembayaran)
```

---

## 11. Model & Relasi

| Model | Table | Traits | Relasi |
|-------|-------|--------|--------|
| User | users | Authenticatable, HasFactory, Notifiable | hasMany: customers, invoices, payments, packages, vouchers, odcs, odpRoutes, odpPoints, settings, activityLogs |
| Customer | customers | BelongsToUser, HasFactory | belongsTo: Package, OdpPoint; hasMany: Invoice, Onu |
| Invoice | invoices | BelongsToUser, HasFactory | belongsTo: Customer; hasMany: Payment |
| Payment | payments | BelongsToUser | belongsTo: Invoice |
| Package | packages | BelongsToUser, HasFactory | hasMany: Customer |
| Olt | olts | BelongsToUser | hasMany: OltPort |
| OltPort | olt_ports | — | belongsTo: Olt; hasMany: Onu |
| Onu | onus | — | belongsTo: OltPort; belongsTo: Customer |
| Odc | odcs | BelongsToUser | hasMany: OdpRoute |
| OdpRoute | odp_routes | BelongsToUser | belongsTo: Odc; hasMany: OdpPoint |
| OdpPoint | odp_points | BelongsToUser | belongsTo: OdpRoute; hasMany: Customer |
| Voucher | vouchers | BelongsToUser, HasFactory | — |
| Setting | settings | BelongsToUser | — |
| ActivityLog | activity_logs | BelongsToUser | belongsTo: User |

---

## 12. Catatan & Issues Terbuka

- **Role middleware aktif** — route dipisah: `teknisi` sebagai base auth, `admin` untuk route sensitif
- **UserFactory** — default role `teknisi`, method `admin()` untuk testing
- **OLT polling pakai Job per-OLT** — `PollOltJob` dengan timeout 120s, tries=2, internal try/catch — OLT gagal tidak blokir yang lain
- **Kolom `price` di vouchers** — belum ada, perlu migration
- **6 halaman hotspot statis** — belum dibuat (`public/hotspot/*.html`)
- **Export CSV voucher** — belum ada
- **Selector profile/server** — belum ada di form create voucher
- **SQLite lokal** — beberapa migration record pernah corrupt (tabel hilang tapi record ada), sudah di-fix
- **Voucher `user_id`** — sudah ditambahkan ke lokal (kolom sudah ada di migration batch 2 untuk production)
