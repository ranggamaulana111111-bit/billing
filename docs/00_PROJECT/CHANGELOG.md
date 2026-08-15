# Changelog — ALKONEK ISP Billing System

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

### Pembaruan Lanjutan (Juli–Agustus 2026)
- **Modul NOC** — 14 controller di `app/Http/Controllers/Noc/` di bawah route `/noc/*` + middleware `IsNoc` (Automation, ConfigModule, ConfigRepository, Dashboard, Features, Genieacs, InterfaceCenter, InternetService, MikrotikDashboard, MikrotikDevice, NetworkConfig, SecurityPolicy, SyncDashboard, TrafficEngineering)
- **GenieACS (TR-069)** — modul `app/Modules/GenieACS/` + `Noc\GenieacsController`
- **Incidents & SLA** — `Incident`, `IncidentNotification`, `IncidentNotificationService`, `incident:check-sla`
- **Automation engine** — `AutomationJob/Trigger/Log` + `app/Services/Automation/` (`automation:scheduler`, `automation:worker --once`)
- **Network metrics & QoS** — `NetworkMetric`, `app/Services/SmartQos/SmartQosService.php`, `qos:*`, `network:data-collect`
- **RouterOS config sync** — `routeros:sync-config` (setiap 15 menit) ke tabel sync DB
- **Inventory** — `InventoryItem`, `InventoryTransaction`, `InventoryController`
- **Payment abstraction** — `app/Services/Payment/` (`PaymentGatewayInterface`, `MidtransGateway`, `XenditGateway`, `PaymentService`) + integrasi **Xendit** (`XenditController`, `xendit_invoice_id`)
- **ONU monitoring history & Ping** — `OnuMonitoringHistory`, `PingResult`
- **Voucher print templates** — `VoucherPrintTemplateController` + tabel `voucher_print_templates`
- **MikroTik device fields** — connection mode, local IP/port, user stats, `ssh_port`

### Agustus 2026 — Notifikasi & Toolbar FTTH Map
- **Fonnte/WhatsApp gateway dihapus total** — `FonnteService`, `SendWhatsAppNotification`, config `services.fonnte`, setting `fonnte_token`, route `/invoice/send-wa` & `/invoice/reminder/{id}` dihapus. Notifikasi pelanggan ke depan via aplikasi Android (in-development); `IncidentNotification` tetap dibuat berstatus `pending` sebagai data aplikasi tersebut.
- **Pengaturan notifikasi di peta FTTH** — tombol Notifikasi (bell) di toolbar map: dropdown "Pengaturan WhatsApp" & "Pengaturan Telegram" (enable + URL/API key/nomor atau bot token/chat id). Simpan ke tabel `settings` key `wa_*` / `telegram_*` via `FeaturesController@notifSave` (route `POST /noc/features/map/notif/save`, baca `GET /noc/features/map/notif/config`).
- **Toolbar FTTH map** — vis card toggle animasi master + toggle notifikasi; tombol 26×26, search kecil, aksesibilitas.

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
- OLT management multi-brand (Huawei, ZTE, FiberHome, CData)
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
- 142 test methods (17 file: 7 Feature + 10 Unit) — **suite saat ini RED** (±88 errors + 3 failures) karena migrasi tenant memakai `DROP CONSTRAINT IF EXISTS` yang gagal di SQLite
