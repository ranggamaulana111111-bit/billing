# Contributing — RabegNet ISP Billing System

---

## Development Setup

### Requirements
- PHP ^8.2
- Composer
- Node.js + NPM
- MySQL 8.0+ (Laragon recommended)
- Git

### Local Setup

```bash
# Clone repository
git clone <repo-url> e-billing
cd e-billing

# Install dependencies
composer install
npm install

# Environment
cp .env.example .env
# Edit .env: set DB_DATABASE, DB_USERNAME, DB_PASSWORD

# Generate key & migrate
php artisan key:generate
php artisan migrate --seed

# Build frontend
npm run build

# Run dev server
php artisan serve
```

**Note:** PHP CLI default = 8.1 (tidak cukup). Gunakan Laragon's PHP 8.2:
```
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan {command}
```

---

## Development Workflow

```mermaid
git checkout -b feature/nama-fitur
  → Buat migration (jika perlu)
  → Buat/update model
  → Buat/update controller
  → Buat/update view
  → Tambah route
  → Tulis test
  → php artisan test
  → ./vendor/bin/pint
  → Commit & Push
  → Buat Pull Request
```

---

## Coding Standard

- **PSR-4** untuk autoloading
- **Laravel Pint** (default rules) untuk formatting — jalankan `./vendor/bin/pint`
- Ikuti **Coding Convention** di `docs/03_DEVELOPMENT/CODING_STANDARD.md`

---

## Commit Convention

Gunakan prefix untuk commit message:

| Prefix | Contoh |
|--------|--------|
| `feat:` | `feat: add realtime port polling for ODP` |
| `fix:` | `fix: double API prefix in route registration` |
| `docs:` | `docs: restructure documentation folder` |
| `refactor:` | `refactor: extract generateAndPush method` |
| `test:` | `test: add customer suspend test` |
| `security:` | `security: encrypt mikrotik passwords` |
| `style:` | `style: format code with pint` |

---

## Branch Convention

| Branch | Digunakan Untuk |
|--------|-----------------|
| `main` | Production |
| `develop` | Development |
| `feature/*` | Fitur baru (`feature/realtime-port`) |
| `fix/*` | Bug fix (`fix/double-api-prefix`) |
| `docs/*` | Dokumentasi (`docs/folder-restructure`) |

---

## Pull Request Checklist

- [ ] Semua test passing (`php artisan test`)
- [ ] Code sudah di-format (`./vendor/bin/pint`)
- [ ] Tidak ada `dd()`, `dump()`, atau debug code
- [ ] Migration sudah di-test (fresh migrate)
- [ ] Tidak ada credentials atau sensitive data
- [ ] UI sudah di-test di browser
- [ ] Dokumentasi di-update (jika perlu)

---

## Testing Sebelum Push

```bash
# Full test suite
php artisan test

# Format code
./vendor/bin/pint

# Build frontend (pastikan tidak error)
npm run build
```
