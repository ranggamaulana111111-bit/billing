# AGENTS.md — ALKONEK / PT Alkonek Network Access — ISP Billing System v1.2

## Stack

- **Framework:** Laravel 12 (PHP ^8.2)
- **Database:** MySQL locally (`.env` `DB_CONNECTION=mysql`, db `e_billing`); Aiven MySQL in prod (Vercel)
- **CSS Framework:** **Bootstrap 5.3 via npm + Vite** (not CDN, not Tailwind). `resources/js/app.js` imports `bootstrap` + `bootstrap/dist/css/bootstrap.min.css`. Tailwind CSS is imported but unused. Custom design system in `resources/css/app.css` (~5.2k baris). Font Awesome via jsDelivr CDN in layout; beberapa Chart.js juga via CDN per-halaman.
- **Per-page JS:** halaman yang butuh Chart.js/Leaflet load sendiri (CDN, `defer`) + `@push('scripts')` → `@stack('scripts')` di `layouts/app.blade.php`. **Jangan tambah ke bundle global** — Vite hanya entry `resources/css/app.css` + `resources/js/app.js`.
- **QR Code:** `simplesoftwareio/simple-qrcode` v4.2 (inline SVG, no external API)
- **WA Gateway:** Fonnte via `App\Services\FonnteService`
- **Code style:** Laravel Pint (default rules, no local `pint.json`)
- **Testing:** PHPUnit 11 + Mockery — SQLite `:memory:` (see `phpunit.xml`)
- **Deployment:** Vercel (`vercel-php@0.9.0`, `api/index.php`) + Railway.app backup
- **CI:** `.github/workflows/deploy.yml` auto-deploy ke Vercel on push ke `main`/`master`

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

## Scheduled Commands (`routes/console.php`, ~19 schedules)

| Command | Schedule | Fungsi |
|---|---|---|
| `billing:process` | `dailyAt('08:00')` | Generate invoice bulanan + WA reminder |
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

Manual / legacy: `hotspot:import`, `mikrotik:setup-isolir`, `olt:batch-link`, `qos:setup`, `qos:sync`, `voucher:sync-mikrotik` (non-aktif — digantikan event-driven API).

## Testing

- **~124 test methods** across **15 files** (7 Feature + 8 Unit) — incl. Unit untuk `Services/Billing`, `Services/Payment`, dan `Modules/GenieACS`
- SQLite `:memory:` — no external DB needed
- Run focused: `php artisan test --filter=CustomerTest`
- Run single suite: `php artisan test --testsuite=Unit`
- `RefreshDatabase` dipakai **eksplisit** per class (Auth, Customer, Distribution, Invoice, Package, CustomerCodeGeneratorTest) — bukan default

## Architecture

Monolith Laravel besar: ~22 commands, ~55 controllers, ~39 models + 2 traits, ~88 migrations, ~167 views, ~490 routes (web.php).

### Multi-Tenancy
- **`BelongsToTenant` trait** — global scope `tenant_id` pada semua model utama; `Tenant` sebagai root, `User` belongsTo `Tenant`
- `BelongsToUser` trait masih ada tapi **dead code**
- **Gotcha:** `OdcPort` & `OdpPort` TIDAK punya tenant scope — potensi data leak

### Key Patterns
- Monolithic Controller → Service → Model
- **Driver Pattern** OLT multi-brand (`app/Services/Olt/Drivers/`, factory, SSH tunnel)
- MikroTik: REST API dengan **SSH fallback** (`phpseclib3`) + connection pool (`app/Services/Mikrotik/`)
- RouterOS config sync: `routeros:sync-config` → `app/Services/Mikrotik/Sync/`
- Event-driven voucher sync: `POST /api/v1/mikrotik/hotspot-login` (pengganti `voucher:sync-mikrotik`)
- **Topology:** `FiberTopologyService::getTopologyData()` bangun graf OLT→ODC→ODP→ONU dari entitas Distribution (`Odc`, `Odp`, `OdpPort.customer_id`) — **bukan** `OdcPort.connected_to_odp_id`. Route `/onu-health/topology/graph`.

### Modul Baru (tidak ada di docs lama)
- **GenieACS (TR-069):** `app/Modules/GenieACS/` (Services, Repositories, DTO, ServiceProvider) + `Noc\GenieacsController` — routes di bawah `/noc/genieacs`
- **Incidents & SLA:** `Incident`, `IncidentNotification`, `IncidentNotificationService`, `incident:check-sla`
- **Automation engine:** `AutomationJob/Trigger/Log` + `app/Services/Automation/` (scheduler + worker + trigger)
- **Network metrics & QoS:** `NetworkMetric`, `SmartQosService`, `qos:*`, `app/Services/Monitoring/` (HealthScore, PingMonitor, Diagnosis, SpeedTest)
- **Payment abstraction:** `app/Services/Payment/` (`PaymentGatewayInterface`, `MidtransGateway`, `PaymentService`) — legacy `MidtransService` juga masih dipakai

### WA Gateway (Fonnte)
- **`FonnteService`** centralized (`app/Services/FonnteService.php`)
  - `cleanPhone()` — strip non-digit, hapus prefix `0`/`62`
  - `send()` — validasi response, log error jika gagal
- **Token:** `Setting::get('fonnte_token')` atau fallback `config('services.fonnte.token')`
- **5 call sites:** `InvoiceController::sendReminder()` (manual), `InvoiceController::sendWaNotification()` (lunas), `BillingProcess::sendWa()` (cron), `SendWhatsAppNotification` (job), `PollOltJob` (alert teknisi)
- Nomor disimpan format lokal (`08xx`), dibersihkan sebelum dikirim

### Isolir Subsystem
- `customer:auto-isolir` — suspend otomatis, set PPP Profile "Isolir", tambah IP ke address-list
- `customer:sync-isolir-ips` — sync IP suspended ke firewall address-list tiap 5 menit
- `mikrotik:setup-isolir` — setup awal firewall di MikroTik (manual)
- `IsolirController` + `GET /isolir/{customer}` — halaman publik untuk pelanggan di-isolir (redirect DNS)

## Conventions

- PSR-4: `App\` → `app/`, `Database\Factories\`, `Database\Seeders\`, `Tests\`; **modul tambahan di `app/Modules/`** (mis. GenieACS)
- `.env` gitignored — copy `.env.example` + `key:generate` pada fresh clone
- Local `.env` variance dari default: `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `DB_CONNECTION=mysql`
- **Docs:** `docs/` (lihat `docs/INDEX.md`, termasuk `docs/04_AI/AGENTS.md`) punya detail arsitektur, tapi **angka & tabelnya ketinggalan zaman** (contoh: menyebut 54 migrations / 55 tests) — percaya kode di atas angka di docs.

## Security Notes

- **JANGAN commit** `.env`, `vercel.json`, `checker.md`, `_check*.php` — semua sudah gitignored. `vercel.json` berisi **plaintext prod credentials** (Aiven DB password, APP_KEY, FONNTE_TOKEN).
- **`reset_data.php` destructive dan masih ter-track di git** — jangan dijalankan; hapus/proteksi sebelum production.
- Password MikroTik router **tidak di-encrypt** (beda OLT yang pakai `encrypted` cast)
- MikroTik REST API pakai `withoutVerifying()` (SSL verification disabled)
- Fonnte token juga ada di Settings DB — jangan bocor via screenshot/log

## MikroTik Tunnel Connection
- Tunnel host: `cloud10.tunnel.id:3069`
- cURL error 6/7/28 = tunnel client di MikroTik offline → restart MikroTik onsite
- Jika tunnel mati, `mikrotik_host` bisa diganti sementara ke IP langsung MikroTik
