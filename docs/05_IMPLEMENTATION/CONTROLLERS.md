# Controllers — RabegNet ISP Billing System

> 36 Controllers | 149 Methods | `App\Http\Controllers\` namespace

---

## Auth (3 controllers, 7 methods)

### LoginController

| Method | Route | Middleware | Flow |
|--------|-------|------------|------|
| `showLoginForm` | GET /login | guest | Tampilkan form login |
| `login` | POST /login | guest | Validasi email+password, redirect ke dashboard |
| `logout` | POST /logout | auth | Logout, redirect ke /login |

### RegisterController

| Method | Route | Middleware | Flow |
|--------|-------|------------|------|
| `showRegistrationForm` | GET /register | guest | Tampilkan form register |
| `register` | POST /register | guest | Validasi, create user, auto-login |

### SocialiteController

| Method | Route | Flow |
|--------|-------|------|
| `redirect` | GET /auth/{provider} | Redirect ke Google OAuth |
| `callback` | GET /auth/{provider}/callback | Handle callback, find/create user, login |

---

## API (3 controllers, 5 methods)

| Controller | Method | Route | Flow |
|------------|--------|-------|------|
| `MikrotikHotspotController` | `hotspotLogin` | POST /api/v1/mikrotik/hotspot-login | Validasi voucher → update status → return JSON |
| `OdpruteController` | `routes` | GET /api/odp-routes | Return all routes + points untuk Leaflet |
| | `points` | GET /api/odp-points | Return all points untuk Leaflet |
| `PortController` | `odpPorts` | GET /api/v1/odp/{odp}/ports | Return port ODP + customer detail |
| | `odcPorts` | GET /api/v1/odc/{odc}/ports | Return port ODC + ODP connection |

---

## Core Modules (10 controllers, 100+ methods)

### DashboardController (1 method)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /dashboard | Aggregate statistics: total customer, active/suspended, revenue, chart data, OLT + MikroTik live data |

### CustomerController (12 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /customers | Filter/search/paginate, eager load package+odp |
| `create` | GET /customers/create | Load packages + ODPs for form |
| `store` | POST /customers | Validasi, create customer, assign ODP port, create first invoice |
| `show` | GET /customers/{customer} | Detail + invoices + ONU |
| `edit` | GET /customers/{customer}/edit | Load packages + ODPs |
| `update` | PUT /customers/{customer} | Validasi, update, sync PPPoE |
| `destroy` | DELETE /customers/{customer} | Hapus + release ODP port |
| `suspend` | POST /customers/{customer}/suspend | Set status suspended, MikroTik disable PPP |
| `activate` | POST /customers/{customer}/activate | Set status active, MikroTik enable PPP, restore profile |
| `syncPppoe` | POST /customers/{customer}/sync-pppoe | Sync PPPoE username ke MikroTik |
| `syncOnu` | POST /customers/{customer}/sync-onu | Sync ONU assignment ke OLT |
| `getOdpPorts` | GET /customers/odp-ports | Ajax: return available ports for selected ODP |

### PackageController (5 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /packages | List all packages |
| `create` | GET /packages/create | Show form |
| `store` | POST /packages | Validasi, create |
| `edit` | GET /packages/{package}/edit | Show form |
| `update` | PUT /packages/{package} | Validasi, update |
| `destroy` | DELETE /packages/{package} | **Protected** — cek relasi customer |

### InvoiceController (14 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /invoices | Filter (status, search, date range), paginate, billing period check |
| `create` | GET /invoices/create | Load customers |
| `store` | POST /invoices | Validasi, create, log activity |
| `show` | GET /invoices/{invoice} | Load customer + payments |
| `edit` | GET /invoices/{invoice}/edit | Edit form |
| `update` | PUT /invoices/{invoice} | Validasi, update |
| `destroy` | DELETE /invoices/{invoice} | Hapus + release related |
| `markPaid` | POST /invoices/{invoice}/paid | Set paid, payment method, create Payment record, auto-activate customer |
| `print` | GET /invoices/{invoice}/print | Load view printable |
| `pdf` | GET /invoices/{invoice}/pdf | Generate PDF via DomPDF |
| `sendEmail` | POST /invoices/{invoice}/send-email | Kirim email reminder via Mail facade |
| `sendWhatsApp` | POST /invoices/{invoice}/send-wa | Kirim WA reminder via Fonnte API |
| `bulkCreate` | POST /invoices/bulk | Generate invoice massal untuk semua customer aktif |
| `getCustomerInvoices` | GET /invoices/by-customer/{customer} | Ajax: return invoices for customer |

### PaymentController (4 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /payments | List + filter |
| `create` | GET /payments/create | Load invoices |
| `store` | POST /payments | Validasi, create payment, update invoice status |
| `history` | GET /payments/history | Filterable history |

### OltController (20 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /olts | List all OLTs |
| `create` | GET /olts/create | Show form |
| `store` | POST /olts | Validasi, create |
| `show` | GET /olts/{olt} | Detail + ports + ONU list |
| `edit` | GET /olts/{olt}/edit | Show form |
| `update` | PUT /olts/{olt} | Validasi, update |
| `destroy` | DELETE /olts/{olt} | Hapus OLT + ports + onus |
| `testConnection` | POST /olts/{olt}/test | Test SSH connection |
| `scan` | POST /olts/{olt}/scan | Manual scan ONU via OLT driver |
| `provision` | POST /olts/{olt}/provision | Provision ONU baru |
| `removeOnu` | POST /olts/onu/{onu}/remove | Remote ONU from OLT |
| `rebootOnu` | POST /olts/onu/{onu}/reboot | Reboot ONU |
| `getOnuDetail` | GET /olts/onu/{onu} | Detail ONU |
| `getPortStatus` | GET /olts/{olt}/ports/{port} | Status port OLT |
| `getOpticalPower` | GET /olts/onu/{onu}/optical | Daya optik |
| `live` | GET /olts/{olt}/live | Ajax: live data |
| `map` | GET /olts/map | Peta OLT |
| `ports` | GET /olts/{olt}/ports | Manajemen port |
| `storePort` | POST /olts/{olt}/ports | Tambah port |
| `destroyPort` | DELETE /olts/ports/{port} | Hapus port |

### MikrotikController (17 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /mikrotik | Dashboard MikroTik |
| `testConnection` | POST /mikrotik/test | Test REST API |
| `hotspot` | GET /mikrotik/hotspot | Manajemen hotspot |
| `hotspotUsers` | GET /mikrotik/hotspot/users | List user hotspot |
| `addHotspotUser` | POST /mikrotik/hotspot/users | Tambah user |
| `removeHotspotUser` | POST /mikrotik/hotspot/users/remove | Hapus user |
| `hotspotActive` | GET /mikrotik/hotspot/active | Sesi aktif |
| `disconnectHotspot` | POST /mikrotik/hotspot/disconnect | Putus sesi |
| `ppp` | GET /mikrotik/ppp | Manajemen PPP |
| `pppSecrets` | GET /mikrotik/ppp/secrets | List secrets |
| `pppActive` | GET /mikrotik/ppp/active | Sesi PPP aktif |
| `addPppSecret` | POST /mikrotik/ppp/secrets | Tambah secret |
| `disablePpp` | POST /mikrotik/ppp/disable | Disable secret |
| `enablePpp` | POST /mikrotik/ppp/enable | Enable secret |
| `queues` | GET /mikrotik/queues | Simple queues |
| `live` | GET /mikrotik/live | Ajax: live monitoring data |
| `backup` | POST /mikrotik/backup | Backup konfigurasi |

### DistributionController (11 methods)

| Key | Value |
|-----|-------|
| Module | ODC/ODP Management |
| Fitur | CRUD ODC, ODP, Port management, Map interaktif |

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /distribution | Overview map + list |
| `odcCreate` | GET /distribution/odc/create | |
| `odcStore` | POST /distribution/odc | Validasi, create ODC |
| `odcEdit` | GET /distribution/odc/{odc}/edit | |
| `odcUpdate` | PUT /distribution/odc/{odc} | |
| `odcDestroy` | DELETE /distribution/odc/{odc} | **Protected** — cek relasi ODP |
| `odpCreate` | GET /distribution/odp/create | Load ODCs |
| `odpStore` | POST /distribution/odp | Validasi, create ODP + generate ODP ports |
| `odpEdit` | GET /distribution/odp/{odp}/edit | |
| `odpUpdate` | PUT /distribution/odp/{odp} | |
| `odpDestroy` | DELETE /distribution/odp/{odp} | **Protected** — cek relasi customer |

### MikrotikRouterController (5 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /mikrotik-routers | List routers |
| `create` | GET /mikrotik-routers/create | |
| `store` | POST /mikrotik-routers | Validasi, create |
| `edit` | GET /mikrotik-routers/{router}/edit | |
| `update` | PUT /mikrotik-routers/{router} | Validasi, update |
| `testConnection` | POST /mikrotik-routers/{router}/test | Test REST API |

### VoucherController (9 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /vouchers | List + filter + search |
| `create` | GET /vouchers/create | Load profiles + templates + routers |
| `store` | POST /vouchers | Generate vouchers (massal) |
| `show` | GET /vouchers/{voucher} | Detail voucher + print |
| `destroy` | DELETE /vouchers/{voucher} | Hapus |
| `print` | GET /vouchers/{voucher}/print | Print QR code + credentials |
| `printBulk` | POST /vouchers/print-bulk | Print multiple |
| `generate` | POST /vouchers/generate | Generate + push ke MikroTik |
| `pushToMikrotik` | POST /vouchers/{voucher}/push | Push single ke MikroTik |

### VoucherProfileController (4 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /voucher-profiles | List |
| `create` | GET /voucher-profiles/create | |
| `store` | POST /voucher-profiles | Validasi, create |
| `edit` | GET /voucher-profiles/{profile}/edit | |
| `update` | PUT /voucher-profiles/{profile} | Validasi, update |
| `destroy` | DELETE /voucher-profiles/{profile} | **Protected** — cek relasi voucher |

### VoucherTemplateController (5 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /voucher-templates | List |
| `create` | GET /voucher-templates/create | |
| `store` | POST /voucher-templates | Validasi HTML content, create |
| `edit` | GET /voucher-templates/{template}/edit | |
| `update` | PUT /voucher-templates/{template} | Validasi, update |
| `destroy` | DELETE /voucher-templates/{template} | Hapus |
| `preview` | GET /voucher-templates/{template}/preview/{page} | Preview halaman hotspot (login/status/redirect/error/alive/logout) |

---

## Supporting Modules (7 controllers, 20+ methods)

### PortalController (4 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET / | Landing page |
| `checkInvoice` | POST /check | Cek tagihan by phone/ID |
| `showInvoice` | GET /invoice/{invoice} | Detail tagihan publik |
| `payMidtrans` | POST /pay/midtrans/{invoice} | Init Midtrans Snap |

### PublicVoucherController (4 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /voucher | Halaman cek voucher |
| `check` | POST /voucher/check | Validasi voucher |
| `detail` | GET /voucher/{voucher} | Detail voucher publik |
| `buy` | POST /voucher/buy | Pembelian voucher |

### IsolirController (2 methods)

| Method | Route | Flow |
|--------|-------|------|
| `byIp` | GET /isolir | Auto-detect IP, cari customer |
| `show` | GET /isolir/{customer} | Info pembayaran + link bayar |

### SettingController (3 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /settings | List all settings |
| `update` | PUT /settings | Validasi, update massal |
| `get` | GET /settings/{key} | Ajax: get single setting |

### ReportController (1 method)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /reports | Revenue chart, outstanding, traffic stats |

### BackupController (4 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /backup | List backup files |
| `create` | POST /backup | Create backup (database dump) |
| `download` | GET /backup/{filename} | Download file |
| `destroy` | DELETE /backup/{filename} | Hapus file |

### ExportController (2 methods)

| Method | Route | Flow |
|--------|-------|------|
| `customers` | GET /export/customers | CSV export pelanggan |
| `invoices` | GET /export/invoices | CSV export invoice |

### LogController (1 method)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /logs | Filterable activity log |

### TeknisiController (1 method)

| Method | Route | Flow |
|--------|-------|------|
| `dashboard` | GET /teknisi | Dashboard khusus teknisi |

### UserController (4 methods)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /users | List users |
| `create` | GET /users/create | |
| `store` | POST /users | Validasi, create |
| `edit` | GET /users/{user}/edit | |
| `update` | PUT /users/{user} | Validasi, update |

### SitemapController (1 method)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /sitemap.xml | Dynamic sitemap |

### OdcController (1 method)

| Method | Route | Flow |
|--------|-------|------|
| `ports` | GET /odc/{odc}/ports | Ajax: return port list + usage |

### OdpController (1 method)

| Method | Route | Flow |
|--------|-------|------|
| `ports` | GET /odp/{odp}/ports | Ajax: return port list + customer |

### VoucherReportController (1 method)

| Method | Route | Flow |
|--------|-------|------|
| `index` | GET /voucher-reports | Laporan penjualan voucher |

### MidtransController (3 methods)

| Method | Route | Flow |
|--------|-------|------|
| `notification` | POST /midtrans/notification | Handle Midtrans callback |
| `finish` | GET /midtrans/finish | Redirect setelah bayar |
| `unfinish` | GET /midtrans/unfinish | Redirect jika batal |

### CronController (1 method)

| Method | Route | Flow |
|--------|-------|------|
| `run` | GET /cron/{token} | Manual trigger scheduler via HTTP |
