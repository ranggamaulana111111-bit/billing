# Modules — RabegNet ISP Billing System

> 10 Core Modules + 5 Supporting Modules

---

## Core Modules

| # | Modul | Controller | Prioritas | Status |
|---|-------|------------|-----------|--------|
| 1 | Customer Management | `CustomerController` | P0 | ✅ Selesai |
| 2 | Package Management | `PackageController` | P0 | ✅ Selesai |
| 3 | Invoice (Billing) | `InvoiceController` | P0 | ✅ Selesai |
| 4 | Payment | `PaymentController`, `MidtransController` | P0 | ✅ Selesai |
| 5 | Portal Publik | `PortalController` | P0 | ✅ Selesai |
| 6 | MikroTik Management | `MikrotikController` | P0 | ✅ Selesai |
| 7 | OLT Management | `OltController` | P0 | ✅ Selesai |
| 8 | Distribution (ODC/ODP) | `DistributionController`, `OdcController`, `OdpController` | P1 | ✅ Selesai |
| 9 | Voucher WiFi | `VoucherController`, `PublicVoucherController` | P1 | ✅ Selesai |
| 10 | Reporting | `ReportController`, `VoucherReportController` | P1 | ✅ Selesai |

---

## Supporting Modules

| # | Modul | Controller | Prioritas | Status |
|---|-------|------------|-----------|--------|
| 1 | Authentication & RBAC | `LoginController`, `RegisterController`, `SocialiteController` | P0 | ✅ Selesai |
| 2 | User Management | (via auth controllers) | P0 | ✅ Selesai |
| 3 | Settings | `SettingController` | P0 | ✅ Selesai |
| 4 | Activity Log | `LogController` | P1 | ✅ Selesai |
| 5 | Backup & Export | `BackupController`, `ExportController` | P1 | ✅ Selesai |

---

## Modul Detail

| Modul | File Controller | Baris | Model | Fitur Utama |
|-------|-----------------|-------|-------|-------------|
| Customer | `CustomerController.php` | 376 | Customer | CRUD, Suspend, Activate, Sync PPPoE/ONU |
| Package | `PackageController.php` | 135 | Package | CRUD, Mass billing, Proteksi delete |
| Invoice | `InvoiceController.php` | 269 | Invoice | CRUD, Filter, Print, PDF, WA/Email reminder |
| Payment | `PaymentController.php` | 71 | Payment | CRUD, History, Auto-update invoice |
| OLT | `OltController.php` | 650 | Olt, OltPort, Onu | CRUD OLT, Scan, Reboot, Monitoring, Map |
| MikroTik | `MikrotikController.php` | — | MikrotikRouter | Dashboard, Hotspot, PPP, Queue, Monitoring |
| Voucher | `VoucherController.php` | 355 | Voucher | Generate, Print, Push, Sync, Report |
| Distribution | `DistributionController.php` | 259 | Odc, Odp, OdcPort, OdpPort | CRUD, Map Interaktif, Port Grid |
| Portal | `PortalController.php` | 110 | — | Cek Tagihan, Bayar Midtrans |
| Voucher Template | `VoucherTemplateController.php` | 96 | VoucherTemplate | CRUD, Preview 6 halaman hotspot |
| Voucher Profile | `VoucherProfileController.php` | 75 | VoucherProfile | CRUD, Proteksi delete |
| Router | `MikrotikRouterController.php` | 92 | MikrotikRouter | CRUD, Test koneksi |
| Isolir | `IsolirController.php` | — | — | Halaman publik redirect pelanggan isolir |
| Setting | `SettingController.php` | 76 | Setting | Key-value store |
| Report | `ReportController.php` | 70 | — | Revenue, Outstanding, Charts |
| Backup | `BackupController.php` | 79 | — | Download, Delete backup |
| Export | `ExportController.php` | 81 | — | CSV export |
| Log | `LogController.php` | 38 | ActivityLog | Filterable activity log |
