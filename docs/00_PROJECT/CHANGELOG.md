# Changelog — RabegNet ISP Billing System

---

## v1.2 (Current)

**Tanggal:** 2026-07-07

### Added
- **SSH fallback** untuk koneksi MikroTik — auto-switch dari REST API ke SSH via `phpseclib3` jika REST gagal
- **MikrotikSshService** — service baru untuk koneksi SSH langsung ke MikroTik, 18 method mapping
- **Hotspot Users management** — halaman CRUD + sync user hotspot dari MikroTik
- **PPPoE Profiles management** — halaman CRUD + sync PPP profile dari MikroTik
- **Queue management** — update & sync queue simple queue MikroTik
- **Hotspot Profiles update** — update profile hotspot via UI
- **Voucher improvement:**
  - Username numeric-only option
  - Password same-as-username option
  - Custom name/password length per generate
  - `description` & `hotspot_server` field pada voucher
  - `expires_at` diisi saat pertama dipakai (bukan saat generate)
- **Voucher Profile sync** — sync/delete/update profile langsung ke MikroTik
- **Hotspot template serving** — route publik `GET /hotspot/templates/{template}/{path}` untuk serve file hotspot template
- **Welcome page** — menampilkan daftar paket aktif dari database
- **Migrations baru:**
  - `bandwidth_profiles` table
  - `ssh_port` field di `mikrotik_routers`
  - `description` & `hotspot_server` field di `vouchers`
  - Fields tambahan di `voucher_profiles`

### Changed
- `MikrotikService::safeGet()` — auto-fallback ke SSH jika REST gagal, dengan method mapping 15+ endpoint
- `Voucher::generate()` — lebih fleksibel: opsi numeric, password same-as-username, custom length
- `VoucherProfileController` — dari CRUD lokal jadi sync langsung ke MikroTik
- `MikrotikController` — 690 baris (dari 298), +12 method baru (hotspot users, ppp profiles, queue update, dll)
- `resources/css/app.css` — bertambah 494 baris (styling baru)
- `settings/index.blade.php` — redesign besar (731 baris)
- Voucher views redesign — create, index, print-batch dirombak total
- Hotspot HTML pages — login, error, status, redirect, logout di-refactor

### Security
- Proteksi path traversal pada route `hotspot/templates/{template}/{path}`

---

## v1.0

**Tanggal:** Production Active

### Added
- Initial production release
- Authentication (login/register/Google OAuth)
- Role-based access (admin/teknisi)
- Customer management CRUD + Suspend/Activate
- Package management
- Invoice auto-generate + print + PDF
- Payment manual + Midtrans integration
- OLT management multi-brand (Huawei, ZTE, FiberHome, C-Data)
- MikroTik management (hotspot, PPP, queue, monitoring)
- Distribution ODC/ODP dengan peta Leaflet
- Voucher WiFi generate + print + push MikroTik
- Portal publik (cek tagihan & bayar)
- Isolir subsystem (auto-suspend + firewall)
- Settings management
- Activity log
- Backup & export
- Reporting
- Multi-tenant (BelongsToTenant)
- Scheduler untuk billing, polling, isolir
- 55 test methods
