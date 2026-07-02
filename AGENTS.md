# AGENTS.md — e-billing (ALKONEK / PT Alkonek Network Access — ISP Billing System v1.1)

## Stack

- **Framework:** Laravel 12 (PHP ^8.2)
- **Database:** MySQL via Laragon (local `.env` uses `DB_CONNECTION=mysql`), Aiven MySQL (Vercel prod)
- **Frontend:** Bootstrap 5.3 + custom CSS (~1570 baris `resources/css/app.css`) + Tailwind CSS v4 (import saja, tidak aktif) + Vite via `laravel-vite-plugin`
- **CSS Framework Utama:** **Bootstrap 5.3** (bukan Tailwind) — custom design system dengan CSS custom properties, gradient, glassmorphism. Tailwind di-import tapi tidak digunakan.
- **Asset JS:** Chart.js (via NPM + Vite), Leaflet 1.9.4 + MarkerCluster, Alpine.js, Bootstrap JS
- **QR Code:** `simplesoftwareio/simple-qrcode` v4.2 (inline SVG, no external API)
- **WA Gateway:** Fonnte (via `App\Services\FonnteService`) — phone number auto-cleaned (strip `0`/`62` prefix), response validated & logged
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

- **55 test methods** across 8 files (7 Feature + 1 Unit)
- **5 feature test classes** use `RefreshDatabase`: Auth, Customer, Distribution, Invoice, Package
- SQLite `:memory:` — no external DB needed
- Two suites: `tests/Unit` (plain PHPUnit) and `tests/Feature` (Laravel HTTP tests)
- Run focused: `php artisan test --filter=ExampleTest`
- Run single suite: `php artisan test --testsuite=Unit`
- Coverage: Auth (login/register/logout/dashboard), Customer (CRUD/suspend/activate), Invoice (CRUD/paid/print), Package (CRUD/destroy protection), Distribution (ODC/Route/Point CRUD + cascade protection), Sitemap

## Architecture

**ALKONEK (PT Alkonek Network Access)** adalah sistem billing ISP lengkap dengan ~80 file PHP di `app/`, 46 migrations, 28 tabel database, dan ~151 route.

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

### WA Gateway (Fonnte)
- **`FonnteService`** — centralized service di `app/Services/FonnteService.php`
  - `cleanPhone()` — strip non-digit, hapus prefix `0`/`62`, kirim subscriber number saja
  - `send()` — kirim via Fonnte API, validasi response, log error jika gagal
- **5 call sites:** `InvoiceController::sendReminder()` (manual), `InvoiceController::sendWaNotification()` (lunas), `BillingProcess::sendWa()` (auto cron), `SendWhatsAppNotification` (job), `PollOltJob` (alert teknisi)
- **Token** via `Setting::get('fonnte_token')` atau fallback `config('services.fonnte.token')`
- **Message templates** menggunakan branding `ALKONEK BILLING` / `PT Alkonek Network Access`
- **Nomor telepon** disimpan dalam format lokal (`08xx`), dibersihkan otomatis sebelum dikirim ke API

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
- **Fonnte token** juga di Settings DB — token production jangan bocor via screenshot/log
- `checker.md` juga mengandung sensitive tokens — jangan commit ke public repo
- Password MikroTik router **tidak di-encrypt** (beda dengan OLT yang pakai `encrypted` cast)
- `OdcPort` dan `OdpPort` model **tidak punya** `BelongsToTenant` scope — potensi data leak
- SSL verification disabled untuk koneksi MikroTik REST API (`withoutVerifying()`)

## File Structure (key files only)

```
app/
├── Console/Commands/       # 8 commands (billing, olt, voucher, isolir, dll)
├── Http/
│   ├── Controllers/        # 34 files (Auth, API, Backup, Customer, Dashboard, dll)
│   ├── Controllers/Api/    # OdpruteController, MikrotikHotspotController
│   └── Middleware/          # IsAdmin, IsTeknisiOrAdmin
├── Jobs/                   # PollOltJob, SendWhatsAppNotification
├── Mail/                   # InvoiceReminder, PaymentConfirmation
├── Models/                 # 19 models + 2 traits (BelongsToTenant, BelongsToUser legacy)
└── Services/               # FonnteService, MidtransService, MikrotikService, Olt/ (drivers, factory, SSH tunnel)
database/
├── migrations/             # 46 files (28 tables)
├── factories/              # 5 factories
└── seeders/                # 5 seeders (DatabaseSeeder, BillingSeeder, SettingSeeder, dll)
resources/views/            # 58 blade files + 1 orphan backup
routes/
├── web.php                 # ~148 routes
├── api.php                 # POST /api/v1/mikrotik/hotspot-login
└── console.php             # 5 scheduled commands
```
