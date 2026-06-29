# RabegNet — ISP Billing System v1.1

Sistem billing ISP terintegrasi untuk manajemen pelanggan, tagihan, pembayaran online, inventaris, dan monitoring infrastruktur jaringan (OLT multi-brand, MikroTik, ODP/ODC).

**Stack:** Laravel 12 · PHP ^8.2 · Bootstrap 5.3 · Leaflet.js · Chart.js · MySQL (Aiven/Laragon)

---

## Fitur Utama

| Fitur | Detail |
|-------|--------|
| **Manajemen Pelanggan** | CRUD, suspend/activate, sync PPPoE, relasi ODP & ONU |
| **Tagihan & Pembayaran** | Auto-generate invoice, Midtrans (QRIS/VA), WA reminder (Fonnte), PDF |
| **Monitoring OLT** | Multi-brand (Huawei/ZTE/FiberHome/C-Data), SSH polling, Rx power, sorting redaman |
| **MikroTik** | Hotspot, PPP, queues, backup, bandwidth monitoring, multi-router |
| **Distribusi Fiber** | ODC/ODP management dengan peta Leaflet interaktif |
| **Voucher WiFi** | Generate + print + QR code lokal + push ke hotspot MikroTik |
| **Isolir Subsystem** | Auto-suspend pelanggan overdue, firewall integration |
| **Event-Driven API** | `POST /api/v1/mikrotik/hotspot-login` — auto-mark voucher used |
| **Portal Pelanggan** | Self-service cek tagihan & bayar online |
| **Multi-Tenant** | Setiap tenant memiliki data terpisah |

---

## Instalasi

```bash
# Clone & masuk direktori
git clone https://github.com/ranggamaulana111111-bit/billing.git
cd billing

# Setup dependencies & environment
composer setup

# Atau manual:
composer install
cp .env.example .env
# Edit .env (DB, Midtrans, Fonnte, dll)
php artisan key:generate
php artisan migrate --force
npm install && npm run build
```

## Development

```bash
# Jalankan semua service (Vite + queue + pail)
composer dev
```

## Testing

```bash
composer test
# Atau
php artisan test --filter=Feature
```

## Production (Vercel)

Push ke `main` → auto-deploy ke Vercel. Database: Aiven MySQL (SSL).

---

## Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | Laravel 12, PHP ^8.2, phpseclib, Midtrans, Socialite, DomPDF |
| Frontend | Bootstrap 5.3, Leaflet 1.9.4, Chart.js, Alpine.js, Vite |
| Database | MySQL (Aiven prod / Laragon local), SQLite testing |
| Dev Tools | PHPUnit 11, Laravel Pint, Vite |
| Deployment | Vercel (`vercel-php@0.9.0`), Railway.app backup |

---

## Lisensi

[MIT](LICENSE)
