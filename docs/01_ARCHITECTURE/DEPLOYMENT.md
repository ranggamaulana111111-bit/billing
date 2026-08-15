# Deployment — ALKONEK ISP Billing System v1.2

---

## Requirement Server

| Komponen | Versi Minimal |
|----------|---------------|
| PHP | ^8.2 (local Laragon PHP 8.3.31; Vercel runtime PHP 8.5) |
| Composer | 2.x |
| Node.js | 18.x+ |
| NPM | 10.x+ |
| MySQL | 8.0+ (local) / Aiven MySQL (prod) |
| Extensions | PDO, MySQL, BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML |

---

## Environment Variables

Key `.env` variance dari default Laravel:

```
DB_CONNECTION=mysql
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Midtrans
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false

# Xendit
XENDIT_SECRET_KEY=
XENDIT_WEBHOOK_VERIFICATION_TOKEN=

# MikroTik (default)
MIKROTIK_HOST=
MIKROTIK_USER=admin
MIKROTIK_PASSWORD=

# Cron trigger (Vercel — tidak ada cron server)
CRON_TOKEN=
```

> ⚠️ `vercel.json` berisi **plaintext prod credentials** (Aiven DB password, APP_KEY) dan sudah gitignored. Jangan pernah commit file ini.

---

## Deployment ke Vercel (Primary)

### Struktur

```
vercel.json:
  - builder: vercel-php@0.9.0
  - entry: api/index.php
  - runtime: php 8.5
```

### Langkah (manual)

```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 2. Setup environment
cp .env.example .env
# Edit .env: isi DB credentials (Aiven MySQL), APP_KEY, dll

# 3. Build frontend
npm run build

# 4. Deploy (prebuilt)
vercel deploy --prebuilt --prod
```

### CI/CD (`.github/workflows/deploy.yml`)

Auto-deploy saat push ke `main`/`master`:
1. `npm ci && npm run build`
2. `vercel deploy --prebuilt --prod`

### Catatan Vercel

- **No persistent storage** — gunakan Aiven MySQL (cloud)
- **No cron jobs** — trigger scheduler via external cron service hitting `/api/cron/run?token=xxx` (CronController → `artisan schedule:run`)
- **Read-only filesystem** — template hotspot tidak bisa write file, gunakan route `/hotspot/{page}` dari database
- **PHP 8.5 di Vercel** — `fake()` tidak tersedia & tidak ada Factory di production path; pastikan path produksi tidak memakai helper test

---

## Deployment ke Railway.app (Backup)

```
railway.json:
  - builder: Nixpacks
  - start: php artisan serve --host=0.0.0.0 --port=$PORT
```

---

## Post-Deployment

```bash
# Migration database
php artisan migrate --force

# Generate APP_KEY (jika first deploy)
php artisan key:generate

# Cache config
php artisan config:cache

# Setup scheduler (di server dengan cron)
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

# Atau via HTTP trigger (Vercel)
# External cron service → GET /api/cron/run?token=xxx
```

---

## Queue Worker

```bash
# Production
php artisan queue:work --tries=3 --timeout=60

# Local development
php artisan queue:listen --tries=3 --timeout=60
```
