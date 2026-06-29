# AI Workflow — Panduan untuk AI Agent

> Dokumen ini memandu AI Agent dalam bekerja dengan kodebase RabegNet secara efektif.

---

## Sebelum Mulai

1. **Baca DESCRIPTION.md** — pahami gambaran proyek, arsitektur, modul, dan business rules
2. **Baca AGENTS.md** — pahami stack, commands, conventions
3. **Baca PRD.md** — pahami requirements dan goals

---

## Saat Mengerjakan Task

### 1. Pahami Context

```
docs/00_PROJECT/
├── DESCRIPTION.md      → Gambaran umum, arsitektur, modul
├── PRD.md              → Requirements, goals, business rules
├── ROADMAP.md          → Arah pengembangan
└── CHANGELOG.md        → Riwayat perubahan
```

### 2. Explore Codebase

Gunakan search tools untuk memahami existing code sebelum membuat perubahan:

1. Cari model → `app/Models/`
2. Cari controller → `app/Http/Controllers/`
3. Cari view → `resources/views/`
4. Cari route → `routes/web.php`
5. Cari migration → `database/migrations/`

### 3. Ikuti Convention

- **Naming** — lihat `docs/03_DEVELOPMENT/CODING_STANDARD.md`
- **Route** — tambah di `routes/web.php` dengan middleware sesuai role
- **Controller** — resource controller untuk CRUD, private method untuk shared logic
- **View** — Blade dengan layout `layouts.app`
- **Model** — `BelongsToTenant` untuk multi-tenant

### 4. Tulis Test

- Test di `tests/Feature/` untuk integration test
- Test di `tests/Unit/` untuk unit test
- `RefreshDatabase` untuk test yang butuh database

### 5. Format & Verify

```bash
./vendor/bin/pint          # Format code
php artisan test           # Run all tests
npm run build              # Build frontend (jika ada perubahan asset)
```

---

## Checklist Sebelum Selesai

- [ ] Semua test passing
- [ ] Code sudah di-format dengan pint
- [ ] Tidak ada debug code (`dd()`, `dump()`, `var_dump()`)
- [ ] Tidak ada credentials atau sensitive data
- [ ] Route sudah terdaftar dengan middleware yang tepat
- [ ] Migration sudah di-test (fresh migrate)
- [ ] UI sudah berfungsi (tidak ada JS error)
- [ ] Dokumentasi di-update jika perlu

---

## File yang Tidak Boleh Dimodifikasi

- `.env` — environment config
- `vercel.json` — deployment config (berisi credentials)
- `checker.md` — berisi sensitive tokens

---

## File yang Wajib Dibaca

| File | Kenapa |
|------|--------|
| `routes/web.php` | Semua route aplikasi |
| `routes/api.php` | API routes |
| `routes/console.php` | Scheduled commands |
| `app/Providers/AppServiceProvider.php` | Bootstrap 5 pagination |
| `bootstrap/app.php` | Middleware configuration |
| `phpunit.xml` | Test configuration |
