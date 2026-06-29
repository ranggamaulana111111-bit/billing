# Changelog — RabegNet ISP Billing System

---

## v1.1 (Current)

**Tanggal:** 2026-06-30

### Added
- Realtime port status (polling 15 detik) di halaman ODP & ODC detail
- API endpoint `GET /api/v1/odp/{odp}/ports` untuk data realtime port ODP
- API endpoint `GET /api/v1/odc/{odc}/ports` untuk data realtime port ODC
- Toggle layer Satelit/Street di semua peta Leaflet
- Esri World Imagery sebagai default tile layer
- Dokumentasi DESCRIPTION.md lengkap (18 seksi)
- Dokumentasi PRD.md lengkap (12 seksi)
- Struktur folder `docs/` terorganisir

### Changed
- Tile layer peta dari OpenStreetMap ke Esri Satellite (bisa toggle)
- ODP show: menampilkan info customer (nama + package) di setiap port
- ODP show: menampilkan port ODC tujuan di jalur distribusi
- ODC show: menampilkan jumlah pelanggan per port ODC

### Security
- Catatan keamanan didokumentasikan di DESCRIPTION.md

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
