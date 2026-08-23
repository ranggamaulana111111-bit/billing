# AGENTS.md — ALKONEK / PT Alkonek Network Access — ISP Billing System v1.2

## Stack

- **Framework:** Laravel 12 (PHP ^8.2, installed v12.61)
- **Database:** MySQL locally (`.env` `DB_CONNECTION=mysql`, db `e_billing`); Aiven MySQL in prod (Vercel)
- **CSS Framework:** **Bootstrap 5.3 via npm + Vite** (not CDN, not Tailwind). `resources/js/app.js` imports `bootstrap` + `bootstrap/dist/css/bootstrap.min.css`. **Tailwind tidak terpasang di source** (hanya artefak compiled di `public/hotspot/templates/*/assets/style.css`) — jangan pakai class Tailwind. Custom design system di `resources/css/app.css` (~5.3k baris). Font Awesome 6.4 via cdnjs CDN di layout; Chart.js/Leaflet juga via CDN per-halaman (walaupun ada di `package.json`). **Kecuali halaman portal** (`portal/index`, `portal/invoices`, `portal/pay`) **& `customer/print-a4`** yang load Bootstrap 5.3.0 CDN langsung (di luar layout app).
- **Per-page JS:** halaman yang butuh Chart.js/Leaflet load sendiri (CDN, `defer`) + `@push('scripts')` → `@stack('scripts')` di `layouts/app.blade.php`. **Jangan tambah ke bundle global** — Vite hanya entry `resources/css/app.css` + `resources/js/app.js`.
- **QR Code:** `simplesoftwareio/simple-qrcode` v4.2 (inline SVG, no external API)
- **Notifikasi pelanggan:** direncanakan via aplikasi Android pelanggan (in-development) — **Fonnte/WhatsApp sudah dihapus dari codebase**, jangan dihidupkan kembali
- **Code style:** Laravel Pint (default rules, no local `pint.json`)
- **Testing:** PHPUnit 11 + Mockery — SQLite `:memory:` (see `phpunit.xml`)
- **Deployment:** Vercel (`vercel-php@0.9.0`, `api/index.php`) + Railway.app backup
- **CI:** `.github/workflows/deploy.yml` — `npm ci && npm run build` lalu `vercel deploy --prebuilt --prod` on push ke `main`/`master`

## PHP CLI (Windows)

`php` **tidak ada di PATH**. Selalu gunakan path lengkap ke Laragon's PHP 8.3:
```
"C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe" artisan {command}
```
> Folder PHP 8.3 bernama persis `php-8.3.31-Win32-vs16-x64 (1)` (termasuk spasi & kurung) — jangan hapus tanda kutip.

## Commands

| Command | Runs |
|---|---|
| `composer setup` | `composer install`, copy `.env`, `key:generate`, `migrate --force`, `npm install && npm run build` |
| `composer dev` | concurrently: `artisan serve`, `queue:listen --tries=1`, `pail`, `npm run dev` |
| `composer test` | `artisan config:clear --ansi && artisan test` |
| `npm run build` / `npm run dev` | `vite build` / `vite` dev server |
| `./vendor/bin/pint` | Auto-format (Laravel default rules) |
| **Artisan/Test via CLI** | `"C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe" artisan {cmd}` / `vendor/bin/phpunit` |

## Scheduled Commands (`routes/console.php`, 17 schedules)

| Command | Schedule | Fungsi |
|---|---|---|
| `billing:process` | `dailyAt('08:00')` | Generate invoice bulanan |
| `invoices:purge-paid` | `dailyAt('08:30')` | Purge invoice lunas |
| `olt:poll` | `hourly()` | Poll OLT via SSH, update ONU status |
| `customers:onu-sync` | `hourly()` | Sync ONU dari data PPPoE MikroTik |
| `network:data-collect` | `everyFiveMinutes()` | Collect metrics; `--aggregate` hourly, `--prune` 6h |
| `qos:optimize` | `everyFiveMinutes()` | Optimasi QoS |
| `customer:auto-isolir` | `dailyAt('00:30')` | Auto-suspend overdue + PPP Profile isolir + address-list |
| `customer:sync-isolir-ips` | `everyFiveMinutes()` | Sync IP suspended ke firewall address-list |
| `routeros:sync-config` | `everyFifteenMinutes()` | Sync config MikroTik ke DB |
| `customers:backup` | `dailyAt('03:00')` | Backup pelanggan PPPoE/Hotspot ke JSON |
| `incident:check-sla` | `hourly()` | Cek SLA incident |
| `incidents:purge` / `incidents:purge-auto` | monthly / everyMinute | Purge riwayat incident |
| `automation:scheduler` / `automation:worker --once` | everyMinute | Engine automation |

Manual / legacy: `hotspot:import`, `mikrotik:setup-isolir`, `olt:batch-link`, `qos:setup`, `qos:sync`, `qos:health`, `voucher:sync-mikrotik` (non-aktif — digantikan event-driven API).

## Testing

- **142 test methods** across **17 files** (7 Feature + 10 Unit) — incl. Unit untuk `Services/Billing`, `Services/Payment` (Midtrans + Xendit), `Services/GenieACS`, `Services/Olt`
- SQLite `:memory:` — no external DB needed (see `phpunit.xml`)
- Run focused: `php artisan test --filter=CustomerTest`
- Run single suite: `php artisan test --testsuite=Unit`
- `RefreshDatabase` dipakai **eksplisit** per class — bukan default
- **⚠️ KNOWN ISSUE: suite saat ini RED (142 tests → 88 errors + 3 failures).** Jangan panik & jangan salahkan kode yang sedang dikerjakan:
  - Penyebab utama: migrasi `2026_06_22_000004_add_tenant_id_to_business_tables.php` memakai `DROP CONSTRAINT IF EXISTS` (sintaks MySQL/Postgres) yang **gagal di SQLite** → semua test `RefreshDatabase` error `near "CONSTRAINT"`.
  - Beberapa Unit test yang menyentuh DB tanpa `RefreshDatabase` (`MidtransGatewayTest`, `PaymentServiceTest`, `XenditGatewayTest`) → error `no such table`.
  - `InvoiceGeneratorTest::test_uses_current_date_when_no_period_given` expect format `INV-YYYYMM-` padahal `InvoiceGenerator::generate()` menghasilkan `INV-YYYYMMDD-` (pakai `now()->format('Ymd')`).

## Architecture

Monolith Laravel besar: 22 commands, 58 controllers (38 root + 3 Api + 3 Auth + 14 Noc), 40 models + 2 traits, 100 migrations (48 tables), 172 views, ~546 routes (web.php).

### Multi-Tenancy
- **`BelongsToTenant` trait** (`app/Models/Traits/`) — global scope `tenant_id` pada 31 model; `Tenant` sebagai root, `User` belongsTo `Tenant`
- `BelongsToUser` trait masih ada tapi **dead code**
- **Gotcha:** `OdcPort`, `OdpPort`, `IncidentNotification`, `InterfaceChangeLog`, `MikrotikInterfaceMetadata`, `OnuMonitoringHistory`, `PingResult` TIDAK punya tenant scope — potensi data leak (cek filter manual bila perlu)

### Key Patterns
- Monolithic Controller → Service → Model
- **Driver Pattern** OLT multi-brand (`app/Services/Olt/Drivers/`: Zte, Huawei, FiberHome, CData + JumpHost/Mikrotik SSH tunnel; factory)
- MikroTik: REST API dengan **SSH fallback** (`phpseclib3`) + connection pool (`app/Services/Mikrotik/`: `RouterConnectionPool`, `RouterConnectionManager`, dll)
- RouterOS config sync: `routeros:sync-config` → `app/Services/Mikrotik/Sync/`
- Event-driven voucher sync: `POST /api/v1/mikrotik/hotspot-login` (pengganti `voucher:sync-mikrotik`)
- **Topology:** `app/Services/Monitoring/FiberTopologyService::getTopologyData()` bangun graf OLT→ODC→ODP→ONU dari entitas Distribution (`Odc`, `Odp`, `OdpPort.customer_id`) — **bukan** `OdcPort.connected_to_odp_id`. Route `/onu-health/topology/graph`.

### Modul Baru (tidak ada di docs lama)
- **Noc controllers:** 14 controller di `app/Http/Controllers/Noc/` (namespace `Noc\`) — GenieACS, Automation, MikrotikDashboard, TrafficEngineering, dll; routes di bawah `/noc/*`
- **GenieACS (TR-069):** `app/Modules/GenieACS/` (Contracts, Exceptions, Repositories, Services, Support — termasuk `Support/GenieacsServiceProvider`) + `Noc\GenieacsController` — routes di bawah `/noc/genieacs`
- **Incidents & SLA:** `Incident`, `IncidentNotification`, `IncidentNotificationService`, `incident:check-sla`
- **Automation engine:** `AutomationJob/Trigger/Log` + `app/Services/Automation/` (scheduler + worker + trigger)
- **Network metrics & QoS:** `NetworkMetric`, `app/Services/SmartQos/SmartQosService.php`, `qos:*`, `app/Services/Monitoring/` (HealthScore, PingMonitor, Diagnosis, SpeedTest, FiberTopology)
- **Payment abstraction:** `app/Services/Payment/` (`PaymentGatewayInterface`, `MidtransGateway`, `XenditGateway`, `PaymentService`) + `XenditController` — legacy `MidtransService` juga masih dipakai

### Notifikasi Pelanggan (Android app — in-development)
- **Fonnte/WhatsApp dihapus total** dari codebase (`FonnteService`, `SendWhatsAppNotification`, config `services.fonnte`, `fonnte_token` di settings). Referensi WA yang tersisa hanya **link kontak/chat** (mis. tombol WA di map NOC & "No. WA:" di portal) — bukan gateway.
- **`IncidentNotification`** masih dibuat (status `'pending'`) sebagai data untuk aplikasi Android pelanggan nanti — jangan hapus.
- Rencana: aplikasi Android pelanggan akan menerima notifikasi pengingat pembayaran/isolir langsung di app, bukan via WhatsApp.

### Isolir Subsystem
- `customer:auto-isolir` — suspend otomatis, set PPP Profile "Isolir", tambah IP ke address-list
- `customer:sync-isolir-ips` — sync IP suspended ke firewall address-list tiap 5 menit
- `mikrotik:setup-isolir` — setup awal firewall di MikroTik (manual)
- **Tidak ada halaman publik `/isolir/{customer}` lagi** — semua aksi isolir lewat command + status `suspended` di `CustomerController`

## Conventions

- PSR-4: `App\` → `app/`, `Database\Factories\`, `Database\Seeders\`, `Tests\`; **modul tambahan di `app/Modules/`** (mis. GenieACS)
- `.env` gitignored — copy `.env.example` + `key:generate` pada fresh clone
- Local `.env` variance dari default: `QUEUE_CONNECTION=database`, `DB_CONNECTION=mysql` (session/cache pakai `file`)
- **Docs:** `docs/` (lihat `docs/INDEX.md`) punya detail arsitektur, tapi **sebagian angka & tabelnya ketinggalan zaman** — percaya kode & root `AGENTS.md` ini di atas angka di docs.

## Security Notes

- **JANGAN commit** `.env`, `vercel.json`, `checker.md`, `_check*.php` — semua sudah gitignored. `vercel.json` berisi **plaintext prod credentials** (Aiven DB password, APP_KEY).
- Password MikroTik router **tidak di-encrypt** (beda OLT yang pakai `encrypted` cast)
- MikroTik REST API pakai `withoutVerifying()` (SSL verification disabled)

## MikroTik Tunnel Connection
- Host router MikroTik dikonfigurasi via **Setting DB** (`mikrotik_host`) — bisa diganti runtime tanpa deploy; **tidak ada default hardcoded di code/seeders** (nilai saat ini di DB: tunnel `cloud10.tunnel.id:3069`)
- cURL error 6/7/28 = tunnel client di MikroTik offline → restart MikroTik onsite
- Jika tunnel mati, `mikrotik_host` bisa diganti sementara ke IP langsung MikroTik
