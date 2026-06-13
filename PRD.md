# PRD — RabegNet ISP Billing System

> **Version:** 1.0  
> **Status:** Production Active  
> **URL:** https://rabegnet.vercel.app  
> **Stack:** Laravel 12 + PHP 8.2 / 8.5 (Vercel) + MySQL (Aiven)  
> **Repository:** `github.com/ranggadydriver-cmyk/e-billing`

---

## 1. Ringkasan Eksekutif

Sistem billing untuk ISP RabegNet yang mencakup manajemen pelanggan, penagihan, pembayaran (Midtrans), monitoring jaringan (MikroTik), manajemen perangkat OLT multi-brand (Huawei/ZTE/FiberHome), distribusi ODP/ODC dengan peta Leaflet, serta portal customer self-service. Multi-tenant via `BelongsToUser` trait, dideploy di Vercel dengan Aiven MySQL.

---

## 2. Target Pengguna

| Role | Deskripsi | Akses |
|------|-----------|-------|
| **Admin** | Pemilik ISP, full akses ke semua fitur | `role = 'admin'` |
| **Teknisi** | Teknisi lapangan — monitor gangguan, lihat pelanggan, OLT, ODP | `role = 'teknisi'` |
| **Pelanggan** | End-user — lihat tagihan, bayar via portal publik | Tidak login |

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
│  │  │Routes│ │Cmds  │ │Sync  │ │(poll OLT │  │  │
│  │  └─────┘ └──────┘ └──────┘ │ billing) │  │  │
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
│  • Google OAuth (login)                        │
│  • MikroTik API (router)                       │
│  • SSH ke OLT (Huawei/ZTE/FiberHome)           │
└─────────────────────────────────────────────────┘
```

### Key Architecture Decisions

| Keputusan | Alasan |
|-----------|--------|
| **Individual DB env vars** (bukan DB_URL) | DB_URL parsing unreliable di Vercel |
| **User::create() di seeder** (bukan factory) | `fake()` undefined di PHP 8.5 Vercel |
| **Tidak pakai db:seed di request path** | Kurangi cold start latency |
| **Multi-tenant via BelongsToUser trait** | Global scope `user_id` otomatis |
| **SQLite :memory: di test** | Test independen tanpa DB eksternal |
| **Cookie session di prod** | File session tidak work di Vercel serverless |

---

## 4. Fitur — Lengkap

### 4.1 Autentikasi & Pengguna
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 1.1 | Login email/password | ✅ | `GET/POST /login` |
| 1.2 | Register | ✅ | `GET/POST /register` |
| 1.3 | Google OAuth (Socialite) | ✅ | `GET /auth/google/{redirect\|callback}` |
| 1.4 | Role-based access | ✅ | `role` column: `admin` / `teknisi` |
| 1.5 | Middleware IsAdmin | ✅ | Guard `.admin` — akses admin only |
| 1.6 | Middleware IsTeknisiOrAdmin | ✅ | Guard `.teknisi` — admin + teknisi |

### 4.2 Dashboard
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 2.1 | Dashboard utama | ✅ | Statistik pelanggan, tagihan, pembayaran, OLT |
| 2.2 | Info infrastruktur | ✅ | Ringkasan OLT online/offline, MikroTik monitoring |

### 4.3 Pelanggan (Customer)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 3.1 | CRUD pelanggan | ✅ | `customers.index` + create/edit/destroy |
| 3.2 | Suspend/Activate | ✅ | POST route, update status + `suspended_at` |
| 3.3 | PPPoE sync | ✅ | Sync username ke MikroTik |
| 3.4 | Relasi dengan ODP | ✅ | `odp_point_id` — titik ODP |
| 3.5 | Relasi dengan ONU | ✅ | `hasMany Onu` — perangkat ONU |

### 4.4 Tagihan (Invoices)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 4.1 | CRUD tagihan | ✅ | Manual create, edit, delete |
| 4.2 | Mark as paid | ✅ | `GET /invoice/paid/{invoice}` |
| 4.3 | Print tagihan | ✅ | Layout print-friendly |
| 4.4 | Download PDF | ✅ | dompdf, `GET /invoice/pdf/{invoice}` |
| 4.5 | Email reminder | ✅ | `InvoiceReminder` mailable via SMTP |
| 4.6 | Email payment confirmation | ✅ | `PaymentConfirmation` mailable |
| 4.7 | WA reminder (Fonnte) | ✅ | `sendReminder()` via Fonnte API |

### 4.5 Pembayaran (Payments)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 5.1 | Manual payment entry | ✅ | Create + history per invoice |
| 5.2 | Midtrans gateway | ✅ | `midtrans/pay` + notification handler |
| 5.3 | Riwayat pembayaran | ✅ | Per invoice |

### 4.6 Paket Internet
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 6.1 | CRUD paket | ✅ | Nama, harga, detail |
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
| 8.3 | **Export OLT** | ✅ | `GET /olts/export` — CSV OLT list |
| 8.4 | **Export ONU** | ✅ | `GET /onus/export?olt_id=&status=` — CSV ONU |

### 4.9 Manajemen OLT (Optical Line Terminal) ⭐
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 9.1 | CRUD OLT | ✅ | `olts` table: name, brand, IP, SSH, SNMP, lokasi, lat/lng |
| 9.2 | Test koneksi SSH | ✅ | `POST /olts/{olt}/test` — log aktivitas |
| 9.3 | Scan ONU per OLT | ✅ | `POST /olts/{olt}/scan` — pull ONU list via SSH |
| 9.4 | Reboot ONU remote | ✅ | `POST /olts/{olt}/onu/{onu}/reboot` |
| 9.5 | Hapus ONU dari OLT | ✅ | `DELETE /olts/{olt}/onu/{onu}` |
| 9.6 | Taut ONU ke pelanggan | ✅ | `POST /onu/{onu}/link-customer` |
| 9.7 | Sync port OLT | ✅ | Bulk create port |
| 9.8 | **Monitor Gangguan** | ✅ | `GET /olts-monitoring` — offline ONU + Rx < -27 dBm + reboot |
| 9.9 | **Map OLT (Leaflet)** | ✅ | `GET /olts/map` — peta interaktif semua OLT |
| 9.10 | **Cari ONU** | ✅ | `GET /onus/search` — filter by keyword, status, OLT |
| 9.11 | **Log aktivitas OLT** | ✅ | Setiap test/scan/reboot/hapus/taut tercatat |
| 9.12 | **Export OLT/ONU CSV** | ✅ | Tombol di halaman index + monitoring |

**Driver OLT yang didukung:**
- **Huawei** — CLI: `display ont info`, `display ont optical-info`, `ont add`, `ont reset`
- **ZTE** — CLI: `show onu unquiet`, `interface gpon-olt`, `onu reset`
- **FiberHome** — CLI: `show ont list`, `ont add`, `ont reset`

### 4.10 MikroTik Management
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 10.1 | Dashboard MikroTik | ✅ | Resource, uptime, interfaces |
| 10.2 | Hotspot profiles | ✅ | CRUD, sync to router |
| 10.3 | Active sessions | ✅ | Hotspot + PPP, disconnect |
| 10.4 | PPP secrets | ✅ | CRUD, sync |
| 10.5 | Queue management | ✅ | Simple queues CRUD |
| 10.6 | Bandwidth monitoring | ✅ | Real-time RX/TX |
| 10.7 | Backup router | ✅ | Download backup |

### 4.11 Distribusi ODP (Optical Distribution Point)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 11.1 | ODC management | ✅ | CRUD, kapasitas, status |
| 11.2 | Route ODP | ✅ | Line di peta antar ODC-ODP |
| 11.3 | ODP points | ✅ | CRUD, port capacity, customer count |
| 11.4 | Leaflet map | ✅ | Marker beda warna (green/orange/red) per utilisasi |
| 11.5 | Chart | ✅ | Bar chart port usage + doughnut total |

### 4.12 Voucher WiFi
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 12.1 | Generate voucher | ✅ | Create + print individual |
| 12.2 | Batch print | ✅ | Multiple vouchers in one page |
| 12.3 | Mark used | ✅ | Tandai sudah dipakai |
| 12.4 | Sync ke MikroTik | ✅ | Push ke hotspot router |

### 4.13 Portal Pelanggan (Public)
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 13.1 | Cari tagihan | ✅ | Input nomor pelanggan |
| 13.2 | Lihat tagihan | ✅ | Daftar tagihan + status |
| 13.3 | Bayar via Midtrans | ✅ | Redirect ke Midtrans |

### 4.14 Lainnya
| # | Fitur | Status | Detail |
|---|-------|--------|--------|
| 14.1 | Activity Log | ✅ | Semua aksi pengguna tercatat |
| 14.2 | Settings | ✅ | Key-value per user |
| 14.3 | Backup database | ✅ | Download SQLite backup |
| 14.4 | Billing otomatis | ✅ | `billing:process` — generate + WA reminder |
| 14.5 | Poll OLT otomatis | ✅ | `olt:poll` — tiap 15 menit update status ONU |

---

## 5. Skema Database (25 Migrations)

### 5.1 Users & Auth
| Tabel | Kolom Kunci |
|-------|-------------|
| `users` | id, name, email, password, role (default `teknisi`), provider, provider_id, avatar, remember_token |
| `cache` | key, value, expiration |
| `jobs` | id, queue, payload, attempts, reserved_at, available_at |

### 5.2 Billing
| Tabel | Kolom Kunci |
|-------|-------------|
| `customers` | id, user_id, name, phone, email, location, package_id, odp_point_id, pppoe_username, due_date, status, suspended_at |
| `packages` | id, user_id, name, price, description, speed, details |
| `invoices` | id, user_id, customer_id, invoice_code, amount, due_date, status, paid_at, midtrans_order_id |
| `payments` | id, user_id, invoice_id, amount, payment_method, notes, paid_at |
| `vouchers` | id, user_id, code, duration, profile, price, status, used_at |

### 5.3 Network Infrastructure
| Tabel | Kolom Kunci |
|-------|-------------|
| `olts` | id, user_id, name, brand (huawei/zte/fiberhome), ip_address, ssh_port, username, password (encrypted), snmp_community, snmp_version, snmp_port, location, latitude(10,7), longitude(10,7), status, last_polled_at |
| `olt_ports` | id, olt_id, slot_number, port_number, port_type (gpon/xgspon/epon), status, description — unique(olt_id, slot_number, port_number) |
| `onus` | id, olt_port_id, customer_id (nullable), onu_id, serial_number, vendor, model, mac_address, status (online/offline), rx_power (float), tx_power (float), distance, uptime (seconds), slot_number, port_number, notes, last_seen_at — unique(olt_port_id, onu_id) |
| `odcs` | id, user_id, name, address, latitude, longitude, capacity, status |
| `odp_routes` | id, user_id, odc_id, name, description, color, coordinates (JSON) |
| `odp_points` | id, user_id, odp_route_id, name, address, latitude, longitude, port_capacity, status |

### 5.4 Operations
| Tabel | Kolom Kunci |
|-------|-------------|
| `activity_logs` | id, user_id, action, details, created_at |
| `settings` | id, user_id, key, value — unique(user_id, key) |

---

## 6. Route Map — Semua Endpoint

### Public Routes
```
GET  /                     → welcome view
GET  /login                → LoginController@showLoginForm
POST /login                → LoginController@login
POST /logout               → LoginController@logout
GET  /register             → RegisterController@showRegistrationForm
POST /register             → RegisterController@register
GET  /auth/{provider}/redirect   → SocialiteController@redirect
GET  /auth/{provider}/callback   → SocialiteController@callback
POST /midtrans/notification      → MidtransController@notification
GET  /portal               → PortalController@index
POST /portal               → PortalController@lookup
GET  /portal/bayar/{invoice}     → PortalController@bayar
GET  /portal/finish        → PortalController@finish
```

### Authenticated Routes (`middleware: auth`)

| Prefix | Routes |
|--------|--------|
| `/dashboard` | GET → dashboard |
| `/customers*` | CRUD + suspend/activate + sync-pppoe |
| `/invoices*` | CRUD + paid + print + pdf + reminder (email & WA) |
| `/payments*` | create + store + history + destroy |
| `/reports` | GET → report index |
| `/mikrotik*` | dashboard, profiles, active, ppp, queues, backup, monitoring |
| `/settings*` | index + update + test-mikrotik |
| `/logs` | GET → activity log index |
| `/distribution*` | ODC/ODP route/point CRUD |
| `/olts*` | CRUD + test + scan + reboot + remove + syncPorts + linkCustomer |
| `/olts-monitoring` | GET → monitor gangguan |
| `/olts/map` | GET → leaflet map |
| `/olts/export` | GET → CSV export OLT |
| `/onus/export` | GET → CSV export ONU |
| `/onus/search` | GET → cari ONU |
| `/vouchers*` | CRUD + print + print-batch + mark-used + sync-mikrotik |
| `/packages*` | CRUD + mass-bill |
| `/backups*` | index + download + destroy + database |
| `/export/*` | invoices CSV + payments CSV |
| `/midtrans/*` | pay + finish |
| `/api/odp-routes` | GET → JSON data routes |
| `/api/odp-points` | GET → JSON data points |

---

## 7. Artisan Commands

| Command | Deskripsi | Schedule |
|---------|-----------|----------|
| `olt:poll` | Poll semua OLT via SSH, update status ONU | Setiap 15 menit |
| `billing:process` | Generate tagihan bulanan + kirim WA reminder | Tiap tanggal 1 |
| `sync-voucher-mikrotik` | Sinkron voucher ke hotspot MikroTik | Manual |
| `app:check-offline-onus` | Kirim notifikasi ONU offline > X jam | Perlu diaktifkan |

---

## 8. Environment & Deployment

### 8.1 Vercel
- **Runtime:** `vercel-php@0.9.0`
- **Bootstrap:** `api/index.php` — `migrate --force` tiap cold request
- **Framework:** `null` (bukan Laravel Vite)
- **Output:** `public/` directory

### 8.2 Env Variables (vercel.json)
```json
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rabegnet.vercel.app
DB_CONNECTION=mysql
DB_HOST=mysql-2b9ccfa-ranggamar.e.aivencloud.com
DB_PORT=15501
DB_DATABASE=defaultdb
DB_USERNAME=avnadmin
DB_PASSWORD=**REMOVED**
SESSION_DRIVER=cookie
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
FONNTE_TOKEN=6uvwBd14g3QGDZmQyzff
```

### 8.3 SSL Database
- Aiven MySQL requires SSL
- Config: `config/database.php` → `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` = `false` via env

### 8.4 GitHub Actions
- Branch `main` push → auto-deploy to Vercel
- Workflow: `.github/workflows/deploy.yml`

---

## 9. Testing

| Detail | Value |
|--------|-------|
| Framework | PHPUnit 11 + Mockery |
| Database | SQLite `:memory:` |
| Suites | `tests/Unit` + `tests/Feature` |
| Run | `php artisan test` or `vendor/bin/phpunit` |
| Total tests | 53 (semua pass) |
| Coverage | Auth, Customer, Invoice, Package, Distribution, Example |

---

## 10. Constraints & Catatan

| # | Constraint | Detail |
|---|-----------|--------|
| 1 | PHP 8.5 di Vercel | `fake()` unavailable — jangan pakai Factory di production path |
| 2 | Cold start | `migrate` per request — hindari `db:seed` |
| 3 | Multi-tenant | Semua model pakai `BelongsToUser` — scope per `user_id` |
| 4 | SSH ke OLT | Wajib koneksi internet keluar dari Vercel ke IP OLT |
| 5 | Rx power threshold | `< -27 dBm` = merah (sinyal terlalu lemah) |
| 6 | Aiven SSL | `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=false` |
| 7 | No file sessions | Cookie session di Vercel (file system readonly) |
| 8 | Queue sync | `QUEUE_CONNECTION=sync` — blocking, tidak async |

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

## 12. File Structure — Key Files

```
app/
├── Console/Commands/
│   ├── BillingProcess.php
│   ├── PollOlt.php
│   └── SyncVoucherMikrotik.php
├── Http/
│   ├── Controllers/
│   │   ├── Auth/LoginController.php
│   │   ├── Auth/RegisterController.php
│   │   ├── Auth/SocialiteController.php
│   │   ├── Api/OdpruteController.php
│   │   ├── BackupController.php
│   │   ├── CustomerController.php
│   │   ├── DashboardController.php
│   │   ├── DistributionController.php
│   │   ├── ExportController.php
│   │   ├── InvoiceController.php
│   │   ├── LogController.php
│   │   ├── MidtransController.php
│   │   ├── MikrotikController.php
│   │   ├── OltController.php
│   │   ├── PackageController.php
│   │   ├── PaymentController.php
│   │   ├── PortalController.php
│   │   ├── ReportController.php
│   │   ├── SettingController.php
│   │   └── VoucherController.php
│   └── Middleware/
│       ├── IsAdmin.php
│       └── IsTeknisiOrAdmin.php
├── Mail/
│   ├── InvoiceReminder.php
│   └── PaymentConfirmation.php
├── Models/
│   ├── ActivityLog.php
│   ├── Customer.php
│   ├── Invoice.php
│   ├── Odc.php
│   ├── OdpPoint.php
│   ├── OdpRoute.php
│   ├── Olt.php
│   ├── OltPort.php
│   ├── Onu.php
│   ├── Package.php
│   ├── Payment.php
│   ├── Setting.php
│   ├── User.php
│   ├── Voucher.php
│   └── Traits/BelongsToUser.php
├── Services/
│   ├── MikrotikService.php
│   ├── MidtransService.php
│   └── Olt/
│       ├── Contracts/OltConnector.php
│       ├── Drivers/
│       │   ├── HuaweiConnector.php
│       │   ├── ZteConnector.php
│       │   └── FiberHomeConnector.php
│       └── Factory/OltConnectorFactory.php
database/migrations/     (25 files)
resources/views/         (45 files)
routes/web.php
bootstrap/app.php
config/database.php
vercel.json
api/index.php
```

---

## 13. Glossary

| Istilah | Definisi |
|---------|----------|
| **OLT** | Optical Line Terminal — perangkat induk fiber optik di ISP |
| **ONU** | Optical Network Unit — perangkat di rumah pelanggan |
| **ODC** | Optical Distribution Cabinet — kabinet distribusi fiber |
| **ODP** | Optical Distribution Point — titik distribusi fiber ke pelanggan |
| **GPON** | Gigabit Passive Optical Network — standar fiber optik |
| **Rx Power** | Receive power — kekuatan sinyal yang diterima ONU |
| **Tx Power** | Transmit power — kekuatan sinyal yang dikirim ONU |
| **Fonnte** | WhatsApp gateway service Indonesia |
| **Midtrans** | Payment gateway Indonesia |
| **MikroTik** | RouterOS — manajemen bandwidth & hotspot |
