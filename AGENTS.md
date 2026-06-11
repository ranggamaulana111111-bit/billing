# AGENTS.md — e-billing

## Stack

- **Framework:** Laravel 12 (PHP ^8.2)
- **Database:** SQLite — `database/database.sqlite` already exists, tracked in git
- **Frontend:** Tailwind CSS v4 (no config file — uses `@import 'tailwindcss'` in CSS) + Vite via `laravel-vite-plugin`
- **Code style:** Laravel Pint (default rules, no local `pint.json`)
- **Testing:** PHPUnit 11 + Mockery — SQLite `:memory:` in tests (see `phpunit.xml`)
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

## Testing

- Two suites: `tests/Unit` (plain PHPUnit) and `tests/Feature` (Laravel HTTP tests)
- SQLite `:memory:` — no external DB needed
- Example test checks `GET /` returns 200
- Run focused: `php artisan test --filter=ExampleTest`
- Run single suite: `php artisan test --testsuite=Unit`

## Architecture (as-is)

This is a **stock Laravel skeleton** — no custom app code beyond defaults:
- `routes/web.php` — single `GET /` route returning `welcome` view
- `routes/console.php` — single `inspire` command
- `app/Models/User.php` — default authentication model
- `app/Http/Controllers/Controller.php` — empty base controller
- `app/Providers/AppServiceProvider.php` — empty register/boot
- 3 migrations: `users` (with password_reset_tokens + sessions), `cache`, `jobs`
- Database-driven sessions, cache, queue — all backed by SQLite

## Conventions

- PSR-4: `App\` → `app/`, `Database\Factories\` → `database/factories/`, `Database\Seeders\` → `database/seeders/`, `Tests\` → `tests/`
- `RefreshDatabase` is **not** used in tests by default — add it explicitly for feature tests that touch the DB
- `.env` is gitignored — copy `.env.example` and set `APP_KEY` on fresh clone
- Only significant local `.env` variance from default: `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`
