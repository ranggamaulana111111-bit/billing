# Modules — ALKONEK ISP Billing System

> 10 Core Modules + 5 Supporting Modules + 9 NOC/Operasional Modules

---

## Core Modules

| # | Modul | Controller | Prioritas | Status |
|---|-------|------------|-----------|--------|
| 1 | Customer Management | `CustomerController` | P0 | ✅ Selesai |
| 2 | Package Management | `PackageController` | P0 | ✅ Selesai |
| 3 | Invoice (Billing) | `InvoiceController` | P0 | ✅ Selesai |
| 4 | Payment | `PaymentController`, `MidtransController`, `XenditController` | P0 | ✅ Selesai |
| 5 | Portal Publik | `PortalController` | P0 | ✅ Selesai |
| 6 | MikroTik Management | `MikrotikController`, `MikrotikRouterController` | P0 | ✅ Selesai |
| 7 | OLT Management | `OltController` | P0 | ✅ Selesai |
| 8 | Distribution (ODC/ODP) | `DistributionController`, `OdcController`, `OdpController` | P1 | ✅ Selesai |
| 9 | Voucher WiFi | `VoucherController`, `PublicVoucherController`, `VoucherPrintTemplateController` | P1 | ✅ Selesai |
| 10 | Reporting | `ReportController`, `VoucherReportController` | P1 | ✅ Selesai |

---

## Supporting Modules

| # | Modul | Controller | Prioritas | Status |
|---|-------|------------|-----------|--------|
| 1 | Authentication & RBAC | `LoginController`, `RegisterController`, `SocialiteController` | P0 | ✅ Selesai |
| 2 | User Management | `UserController` | P0 | ✅ Selesai |
| 3 | Settings | `SettingController`, `IntegrationController` | P0 | ✅ Selesai |
| 4 | Activity Log | `LogController` | P1 | ✅ Selesai |
| 5 | Backup & Export | `BackupController`, `ExportController` | P1 | ✅ Selesai |

---

## NOC & Operasional Modules

| # | Modul | Controller | Prioritas | Status |
|---|-------|------------|-----------|--------|
| 1 | NOC Dashboard & Features Map | `Noc\DashboardController`, `Noc\FeaturesController`, `NocController` | P1 | ✅ Selesai |
| 2 | GenieACS (TR-069) | `Noc\GenieacsController` | P1 | ✅ Selesai |
| 3 | Automation Engine | `Noc\AutomationController` + `app/Services/Automation` | P1 | ✅ Selesai |
| 4 | Incidents & SLA | `IncidentController` | P1 | ✅ Selesai |
| 5 | Inventory Management | `InventoryController` | P1 | ✅ Selesai |
| 6 | Monitoring & QoS | `MonitoringController`, `OnuHealthController`, `QosHealthController`, `TeknisiController` | P1 | ✅ Selesai |
| 7 | RouterOS Sync | `Noc\SyncDashboardController`, `Noc\NetworkConfigController`, `Noc\InterfaceCenterController` | P1 | ✅ Selesai |
| 8 | Integrations | `IntegrationController` | P1 | ✅ Selesai |
| 9 | ONU Hotspot | `OnuHotspotController`, `HotspotCustomerController` | P1 | ✅ Selesai |

---

## Modul Detail

| Modul | File Controller | Baris | Model | Fitur Utama |
|-------|-----------------|-------|-------|-------------|
| Customer | `CustomerController.php` | 976 | Customer | CRUD, Suspend, Activate, Sync PPPoE/ONU, Print thermal/A4, Search API |
| Package | `PackageController.php` | 135 | Package | CRUD, Mass billing, Proteksi delete |
| Invoice | `InvoiceController.php` | 269 | Invoice | CRUD, Filter, Print, PDF, Email reminder |
| Payment | `PaymentController.php` | 71 | Payment | CRUD, History, Auto-update invoice |
| Xendit | `XenditController.php` | 125 | Payment | Pay online, Webhook notification, Finish, Gateway settings |
| OLT | `OltController.php` | 700 | Olt, OltPort, Onu | CRUD OLT, Scan, Reboot, Monitoring, Map |
| MikroTik | `MikrotikController.php` | 690 | MikrotikRouter | Dashboard, Hotspot, PPP, Queue, Monitoring, Hotspot Users, PPPoE Profiles, Queue Update |
| Voucher | `VoucherController.php` | 630 | Voucher | Generate, Print, Push, Sync, Report, Batch Print |
| Voucher Template | `VoucherPrintTemplateController.php` | 126 | VoucherPrintTemplate | CRUD template print, preview |
| Distribution | `DistributionController.php` | 259 | Odc, Odp, OdcPort, OdpPort | CRUD, Map Interaktif, Port Grid |
| Portal | `PortalController.php` | 110 | — | Cek Tagihan, Bayar Midtrans/Xendit |
| Voucher Template | `VoucherTemplateController.php` | 210 | VoucherTemplate | CRUD, Preview 6 halaman hotspot, templatePath() |
| Voucher Profile | `VoucherProfileController.php` | 410 | VoucherProfile | CRUD, Sync/Delete/Update MikroTik langsung |
| Router | `MikrotikRouterController.php` | 78 | MikrotikRouter | CRUD, Test koneksi, SSH port |
| Incident | `IncidentController.php` | 234 | Incident, IncidentNotification | CRUD incident, Investigating/Resolve/Close, SLA, Purge |
| Inventory | `InventoryController.php` | 212 | InventoryItem, InventoryTransaction | Items, Stok masuk/keluar, Laporan aset |
| Integration | `IntegrationController.php` | 324 | MikrotikRouter, Olt | CRUD MikroTik/OLT, Test & live koneksi |
| NOC Dashboard | `NocController.php` | 134 | — | Traffic analyzer, linux server, DNS, VPN, automation, dll |
| NOC Features | `Noc\FeaturesController.php` | 546 | — | Features map, capability showcase |
| GenieACS | `Noc\GenieacsController.php` | 222 | (modul `app/Modules/GenieACS`) | TR-069 device management |
| Automation | `Noc\AutomationController.php` | 186 | AutomationJob, AutomationJobLog, AutomationTrigger | Scheduler, worker, trigger |
| Monitoring | `MonitoringController.php` | 239 | NetworkMetric, PingResult, OnuMonitoringHistory | Health score, ping, diagnosis, speed test |
| Onu Health | `OnuHealthController.php` | 218 | Onu, NetworkMetric | Topologi OLT→ODC→ODP→ONU, health per ONU |
| QoS Health | `QosHealthController.php` | 152 | NetworkMetric | Optimasi & health QoS |
| Teknisi | `TeknisiController.php` | 44 | — | Halaman/action khusus teknisi |
| ONU Hotspot | `OnuHotspotController.php` | 130 | Onu, Olt | Manajemen ONU untuk hotspot |
| Hotspot Customer | `HotspotCustomerController.php` | 131 | Customer, Onu | Scan & create customer hotspot |
| Setting | `SettingController.php` | 76 | Setting | Key-value store |
| Report | `ReportController.php` | 70 | — | Revenue, Outstanding, Charts |
| Backup | `BackupController.php` | 79 | — | Download, Delete backup |
| Export | `ExportController.php` | 81 | — | CSV export |
| Log | `LogController.php` | 38 | ActivityLog | Filterable activity log |
