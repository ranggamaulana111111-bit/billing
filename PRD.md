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
| 9.11 | Live data OLT | ✅ | `GET /olts/{olt}/live` — JSON实时 data |
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
