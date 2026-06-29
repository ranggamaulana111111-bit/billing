# PRD.md — RabegNet ISP Billing System

> **Product Requirement Document**
> Versi: 1.1 | Status: Production Active

---

## 1. Executive Summary

**Latar Belakang**

ISP skala kecil-menengah di Indonesia umumnya masih mengelola operasional secara semi-manual: catatan pelanggan di spreadsheet, tagihan dibuat satu per satu, pembayaran dicatat manual, monitoring jaringan terbatas, dan data infrastruktur tidak terdokumentasi dengan baik. RabegNet hadir untuk menjawab kebutuhan tersebut.

**Masalah yang Diselesaikan**

- Pengelolaan pelanggan masih tersebar dan tidak terpusat
- Billing dilakukan manual per pelanggan setiap bulan
- Monitoring jaringan OLT dan MikroTik tidak terintegrasi
- Data infrastruktur fiber (ODC/ODP) tidak terdokumentasi
- Pembayaran tidak terintegrasi dengan payment gateway
- Pelanggan tidak bisa cek tagihan/bayar secara mandiri
- Tidak ada sistem pengingat tagihan otomatis
- Penanganan pelanggan telat bayar masih manual

**Tujuan Produk**

Menyediakan satu platform terintegrasi untuk seluruh operasional ISP — dari manajemen pelanggan, billing otomatis, pembayaran online, monitoring jaringan, hingga dokumentasi infrastruktur fiber.

**Nilai Utama Produk**

- **Efisiensi** — Otomatisasi billing, reminder, dan isolir
- **Integrasi** — Menyatukan OLT, MikroTik, payment gateway dalam satu sistem
- **Transparansi** — Pelanggan bisa cek tagihan dan bayar mandiri
- **Dokumentasi** — Infrastruktur fiber terdokumentasi dengan peta interaktif

---

## 2. Product Vision

**Visi**

Menjadi sistem billing ISP open-source yang paling mudah diadopsi untuk ISP kecil dan menengah di Indonesia.

**Misi**

- Mengotomatiskan seluruh operasional billing ISP
- Mendukung multi-brand perangkat jaringan (OLT, MikroTik)
- Menyediakan self-service portal untuk pelanggan
- Open-source dan mudah dikustomisasi
- Dibangun dengan teknologi yang familiar (Laravel, Bootstrap)

**Sasaran Pengembangan**

| Fase | Target |
|------|--------|
| v1.x | Stabilisasi, security hardening, produksi |
| v2.0 | Refactoring arsitektur, API publik, testing coverage |
| v3.0 | Smart ISP Operations, auto-diagnosis jaringan |
| v4.0 | Network Management System (NMS) |
| v5.0 | Business Intelligence & advanced analytics |
| v6.0 | Multi-tenant enterprise |
| v7.0 | Ecosystem & marketplace |
| v8.0 | Enterprise readiness |

**Target Pengguna**

- Owner/Management ISP kecil-menengah
- Administrator/Admin ISP
- Teknisi jaringan ISP
- Pelanggan ISP (end-user)

---

## 3. Product Scope

### Core Module

| Modul | Prioritas | Status |
|-------|-----------|--------|
| Customer Management | P0 | ✅ Selesai |
| Package Management | P0 | ✅ Selesai |
| Billing (Invoice) | P0 | ✅ Selesai |
| Payment | P0 | ✅ Selesai |
| Portal Publik | P0 | ✅ Selesai |
| MikroTik Management | P0 | ✅ Selesai |
| OLT Management | P0 | ✅ Selesai |
| Distribution (ODC/ODP) | P1 | ✅ Selesai |
| Voucher WiFi | P1 | ✅ Selesai |
| Reporting | P1 | ✅ Selesai |

### Supporting Module

| Modul | Prioritas | Status |
|-------|-----------|--------|
| Authentication & RBAC | P0 | ✅ Selesai |
| User Management | P0 | ✅ Selesai |
| Settings | P0 | ✅ Selesai |
| Activity Log | P1 | ✅ Selesai |
| Backup & Export | P1 | ✅ Selesai |

---

## 4. Problem Statement

| # | Problem | Dampak | Modul Terkait |
|---|---------|--------|---------------|
| 1 | Pengelolaan pelanggan masih tersebar (spreadsheet, catatan manual) | Data tidak akurat, sulit tracking riwayat pelanggan | Customer |
| 2 | Billing dilakukan manual per pelanggan setiap bulan | Memakan waktu, rawan human error, keterlambatan tagihan | Invoice |
| 3 | Pembayaran tidak terintegrasi dengan payment gateway | Pelanggan harus transfer manual, admin harus cek mutasi | Payment, Midtrans |
| 4 | Tidak ada sistem reminder tagihan otomatis | Banyak pelanggan lupa bayar, piutang membengkak | Invoice, Fonnte |
| 5 | Monitoring jaringan OLT tidak terpusat | Teknisi harus SSH satu per satu ke OLT | OLT |
| 6 | Data infrastruktur fiber tidak terdokumentasi | Sulit tracking port yang terpakai, trouble investigation lambat | Distribution |
| 7 | Penanganan pelanggan telat bayar masih manual | Perlu cek satu per satu, suspend/isolir tidak konsisten | Isolir |
| 8 | Tidak ada self-service untuk pelanggan | Pelanggan selalu hubungi admin untuk cek tagihan & bayar | Portal |
| 9 | Voucher WiFi dikelola manual | Proses generate, cetak, dan push ke MikroTik lambat | Voucher |

---

## 5. Product Goals

| # | Goal | Metrik | Modul |
|---|------|--------|-------|
| 1 | Memusatkan operasional ISP dalam satu aplikasi | Semua data pelanggan, tagihan, infrastruktur di satu tempat | All |
| 2 | Mengurangi pekerjaan manual billing | 100% invoice ter-generate otomatis sesuai siklus | Invoice |
| 3 | Meningkatkan akurasi data pelanggan | Tidak ada duplikasi data, semua relasi terdokumentasi | Customer |
| 4 | Mempercepat proses pembayaran | Pembayaran online via Midtrans, real-time update status | Payment, Midtrans |
| 5 | Mempermudah monitoring jaringan | Semua OLT terpantau dari satu dashboard | OLT |
| 6 | Mendokumentasi infrastruktur fiber | Semua ODC, ODP, port, dan customer mapping ter-record | Distribution |
| 7 | Otomatisasi isolir pelanggan overdue | Isolir berjalan otomatis tanpa campur tangan admin | Isolir |
| 8 | Self-service pelanggan | Pelanggan bisa cek tagihan & bayar tanpa hubungi admin | Portal |
| 9 | Voucher otomatis | Generate, cetak, dan push ke MikroTik dalam 1 klik | Voucher |

---

## 6. Stakeholders

| Stakeholder | Peran | Kebutuhan Utama |
|-------------|-------|-----------------|
| **Owner/Management** | Pemilik bisnis, pengambil keputusan | Laporan keuangan, revenue, outstanding, pertumbuhan pelanggan |
| **Administrator** | Admin operasional sehari-hari | CRUD pelanggan, invoice, payment, setting, management OLT & MikroTik |
| **Teknisi** | Teknisi jaringan lapangan | Monitoring OLT, scan ONU, troubleshooting, cek distribution |
| **Customer** | Pelanggan ISP | Cek tagihan, bayar, status layanan, beli voucher |
| **Finance** | (Opsional — bisa dirangkap admin) | Laporan pembayaran, rekonsiliasi, export data |

---

## 7. Functional Requirements

### FR-001: Customer Management

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Mengelola data seluruh pelanggan ISP dalam satu tempat |
| **Deskripsi** | CRUD pelanggan, aktivasi/suspend, koneksi ke ODP port, PPPoE management, auto-create invoice pertama |
| **Prioritas** | P0 |
| **Modul Terkait** | Package, Invoice, OLT, MikroTik, Distribution |
| **Kriteria Keberhasilan** | Admin bisa create/update/delete pelanggan; saat create otomatis buat invoice pertama; saat activate otomatis push PPPoE ke MikroTik; saat suspend otomatis nonaktifkan PPPoE |

**Detail Fitur:**
- Create customer: nama, lokasi, telepon, email, paket, PPPoE username, ODP/port
- Edit customer: update data, ganti paket, ganti ODP port
- Delete customer: hapus data + release ODP port
- Suspend: disable PPPoE MikroTik, set status suspended
- Activate: enable PPPoE MikTik + create ONU record, set status active
- Sync PPPoE: push semua customer ke MikroTik
- Sync ONU: sync single atau all ONU dari data PPPoE MikroTik

---

### FR-002: Package Management

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Mengelola paket internet yang ditawarkan ke pelanggan |
| **Deskripsi** | CRUD paket, proteksi delete jika ada customer, mass billing |
| **Prioritas** | P0 |
| **Modul Terkait** | Customer, Invoice |
| **Kriteria Keberhasilan** | Admin bisa buat/update/delete paket; paket dengan customer aktif tidak bisa dihapus; mass billing generate invoice untuk semua customer di paket tertentu |

**Field paket:** name, speed, description, price, billing_cycle, mikrotik_profile, is_active

---

### FR-003: Invoice (Billing)

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Mengelola tagihan pelanggan secara otomatis dan terstruktur |
| **Deskripsi** | CRUD invoice, auto-generate via scheduler, mark paid, print, PDF, reminder WA/email, filter by status/date |
| **Prioritas** | P0 |
| **Modul Terkait** | Customer, Payment, Package |
| **Kriteria Keberhasilan** | Invoice auto-generate setiap bulan jam 08:00; WA reminder otomatis; print & PDF berfungsi; filter & search akurat |

**Alur Billing Otomatis:**
1. Scheduler `billing:process` jalan setiap hari jam 08:00
2. Untuk setiap customer aktif per tenant:
   - Cek apakah invoice bulan ini sudah ada
   - Jika belum: create invoice (amount = package.price)
   - Dispatch WA notification job

**Format kode invoice:** `INV/{tenant}/{month}/{year}/{customer_id}` — sequential

---

### FR-004: Payment

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Mencatat pembayaran dari pelanggan |
| **Deskripsi** | Catat pembayaran manual (cash/transfer/QRIS), integrasi Midtrans (QRIS/VA), history, auto-update invoice status |
| **Prioritas** | P0 |
| **Modul Terkait** | Invoice, Midtrans, Isolir |
| **Kriteria Keberhasilan** | Pembayaran tercatat dan otomatis update status invoice; jika customer sedang isolir, auto-activate setelah bayar; Midtrans callback real-time |

**Metode pembayaran:** cash, transfer, qris, midtrans

---

### FR-005: Voucher WiFi

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Mengelola voucher akses WiFi hotspot |
| **Deskripsi** | Generate voucher (random user/pass), print QR code, push ke MikroTik, report, event-driven callback saat login |
| **Prioritas** | P1 |
| **Modul Terkait** | MikroTik, VoucherProfile, VoucherTemplate |
| **Kriteria Keberhasilan** | Voucher tergenerate dengan username/password unik; QR code tercetak; push ke MikroTik otomatis; status terupdate saat digunakan via callback API |

**Template hotspot:** 6 halaman kustom (login, status, redirect, error, alive, logout) — bisa diedit via admin

---

### FR-006: MikroTik Management

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Mengelola perangkat MikroTik dari satu dashboard |
| **Deskripsi** | Dashboard system resource, hotspot management, PPP secret, queue, monitoring bandwidth, backup, live data |
| **Prioritas** | P0 |
| **Modul Terkait** | Customer (PPPoE), Voucher (hotspot), Isolir (firewall) |
| **Kriteria Keberhasilan** | Koneksi ke MikroTik via REST API; hotspot user/profile CRUD; PPP secret CRUD; queue management; active session monitoring + disconnect; bandwidth monitoring chart |

**Endpoint REST API MikroTik:**
- `/ip/hotspot/user` — hotspot users
- `/ip/hotspot/user/profile` — hotspot profiles
- `/ppp/secret` — PPP secrets
- `/ppp/active` — PPP active sessions
- `/queue/simple` — simple queues
- `/ip/hotspot/active` — active hotspot sessions
- `/system/resource` — system health
- `/interface` — interfaces + traffic

---

### FR-007: OLT Management

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Monitoring dan manajemen perangkat OLT multi-brand |
| **Deskripsi** | CRUD OLT, scan ONU per port, reboot/remove ONU, link ke customer, monitoring Rx power, map OLT, export CSV |
| **Prioritas** | P0 |
| **Modul Terkait** | Customer (link ONU), MikroTik (sync ONU) |
| **Kriteria Keberhasilan** | Koneksi SSH ke OLT berhasil; scan ONU semua port; deteksi ONU online/offline; Rx power monitoring; sorting by weakest signal; map OLT dengan posisi geografis |

**Brand OLT yang didukung:**
- Huawei (CLI: `system-view` → `display ont info`)
- ZTE (CLI: `enable` → `configure terminal` → `show onu unquiet`)
- FiberHome (CLI: `show ont list slot/port`)
- C-Data (CLI: `enable` → `config` → `show ont info slot/port`)

**Decorator pattern:** JumpHost SSH tunnel + MikroTik SSH Proxy

---

### FR-008: Distribution (ODC/ODP)

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Mendokumentasi infrastruktur fiber optik dari ODC ke ODP ke Customer |
| **Deskripsi** | CRUD ODC, ODP, ODC Port, ODP Port; peta interaktif Leaflet; port grid realtime; relasi ODC-ODP-Customer |
| **Prioritas** | P1 |
| **Modul Terkait** | Customer (ODP port assignment) |
| **Kriteria Keberhasilan** | Semua ODC dan ODP terdaftar; port mapping akurat; peta interaktif menampilkan semua titik; realtime status port (available/used/broken); customer ter-link ke port ODP yang benar |

**Struktur hierarki:**
```
ODC → ODC Port (inlet/outlet, connected_to_odp_id)
  └── ODP → ODP Port (available/used/broken)
       └── Customer (via odp_port_id)
```

**Realtime:** Polling setiap 15 detik via API endpoint `/api/v1/odp/{id}/ports` dan `/api/v1/odc/{id}/ports`

---

### FR-009: Reporting

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Menyediakan laporan keuangan dan operasional |
| **Deskripsi** | Revenue bulanan, outstanding, chart 12 bulan, metode pembayaran, top unpaid |
| **Prioritas** | P1 |
| **Modul Terkait** | Invoice, Payment, Customer |
| **Kriteria Keberhasilan** | Admin bisa melihat revenue per bulan; outstanding total; grafik tren 12 bulan; breakdown metode pembayaran; daftar pelanggan dengan tunggakan terbesar |

---

### FR-010: Portal Publik

| Aspek | Detail |
|-------|--------|
| **Tujuan** | Self-service untuk pelanggan cek tagihan dan bayar |
| **Deskripsi** | Cek tagihan by nomor telepon, bayar via Midtrans, selesai pembayaran |
| **Prioritas** | P0 |
| **Modul Terkait** | Invoice, Midtrans |
| **Kriteria Keberhasilan** | Pelanggan input nomor telepon → lihat daftar tagihan → pilih bayar → redirect ke Midtrans → selesai |

---

## 8. Non-Functional Requirements

| # | Aspek | Requirement | Prioritas |
|---|-------|-------------|-----------|
| NFR-01 | **Security** | Password MikroTik harus di-encrypt di database; SSL verification untuk koneksi REST API; proteksi script destruktif; tidak ada credentials di file publik | P0 |
| NFR-02 | **Performance** | Halaman dashboard & list harus load < 3 detik; query database dioptimasi dengan index; polling OLT menggunakan queue agar tidak blocking | P1 |
| NFR-03 | **Scalability** | Mendukung multi-tenant dengan data terisolasi; database queue untuk job processing | P1 |
| NFR-04 | **Availability** | Sistem bisa di-deploy di Vercel + Railway (backup); scheduler bisa di-trigger via HTTP untuk lingkungan serverless | P0 |
| NFR-05 | **Maintainability** | Kode mengikuti PSR-4; Laravel Pint untuk formatting; testing dengan PHPUnit; dokumentasi di DESCRIPTION.md | P1 |
| NFR-06 | **Reliability** | Error handling di semua service layer; try-catch di koneksi eksternal (OLT, MikroTik, Midtrans); logging error ke Laravel log | P0 |
| NFR-07 | **Backup** | Backup database bisa di-download via admin panel | P1 |
| NFR-08 | **Logging** | Semua operasi CRUD penting tercatat di activity log; login/logout tercatat | P0 |
| NFR-09 | **Audit Trail** | Activity log mencatat user, action, detail, timestamp; filterable | P1 |
| NFR-10 | **Compatibility** | Browser modern (Chrome, Firefox, Edge, Safari); mobile-responsive via Bootstrap 5.3 | P1 |

---

## 9. User Journey

### Customer Journey

```
1. Menerima tagihan (WA/email notifikasi)
2. Buka portal /portal
3. Input nomor telepon
4. Lihat daftar tagihan (unpaid)
5. Pilih "Bayar" → redirect ke Midtrans
6. Bayar via QRIS/VA
7. Konfirmasi pembayaran real-time
8. (Optional) Cek status voucher di /vouchers/check
9. (Optional) Beli voucher di /vouchers/public
```

### Administrator Journey

```
1. Login ke dashboard
2. Dashboard: lihat statistik, revenue, unpaid, aktivitas terbaru
3. Tambah pelanggan baru → pilih paket → isi data
4. Aktivasi pelanggan → otomatis push PPPoE ke MikroTik
5. Generate invoice massal via mass billing
6. Catat pembayaran manual (jika ada)
7. Monitoring OLT: scan ONU, cek Rx power
8. Management MikroTik: cek PPP active, disconnect jika perlu
9. Manage voucher: generate, print batch
10. Cek laporan revenue & outstanding
11. Setting: update company info, Midtrans keys, MikroTik config
```

### Teknisi Journey

```
1. Login ke dashboard
2. Cek OLT monitoring: ONU online/offline, Rx power terendah
3. Trouble investigation:
   - Cek customer → cek ODP port → cek ODC port → cek OLT port/ONU
4. Scan ulang OLT untuk update status ONU
5. Reboot ONU customer jika perlu
6. Cek distribution map untuk tracking jalur fiber
7. Cek MikroTik: active sessions, bandwidth usage
```

### Owner Journey

```
1. (Admin login) Dashboard: revenue, total customer, outstanding
2. Reports: revenue bulanan, tren 12 bulan, top unpaid
3. Export data untuk akuntansi (CSV)
```

---

## 10. Business Rules

| # | Rule | Deskripsi | Implementasi |
|---|------|-----------|--------------|
| BR-01 | **Satu pelanggan, satu layanan aktif** | Setiap customer memiliki satu paket aktif. Jika ingin ganti paket, update data customer | Customer.package_id — update langsung |
| BR-02 | **Invoice auto-generate** | Invoice dibuat otomatis setiap bulan untuk customer aktif via scheduler `billing:process` | Console command, dailyAt('08:00') |
| BR-03 | **Invoice terkait langsung dengan customer** | Setiap invoice memiliki customer_id; amount = package.price | Foreign key + relasi |
| BR-04 | **Pembayaran hanya untuk invoice valid** | Payment hanya bisa dicatat untuk invoice yang exist; auto-update status invoice | Validasi di controller + trigger update |
| BR-05 | **Pembayaran bisa auto-activate** | Jika customer dalam status suspended dan melakukan pembayaran, status otomatis active | Cek di PaymentController |
| BR-06 | **Status layanan** | Status customer: active / suspended / inactive. Active = bisa akses internet. Suspended = isolir. Inactive = non-aktif | Enum di kolom `status` |
| BR-07 | **Isolir otomatis** | Customer dengan invoice unpaid > grace period akan auto-suspended jam 00:30 | `customer:auto-isolir` command |
| BR-08 | **ODP port assignment** | Satu ODP port hanya untuk satu customer (unique constraint `odp_port_id` di customers) | Database unique + release port saat delete customer |
| BR-09 | **Voucher sekali pakai** | Voucher dengan status 'active' bisa dipakai. Setelah login via hotspot, status berubah 'used' | Event-driven API callback |
| BR-10 | **Multi-tenant** | Setiap tenant (ISP) memiliki data terpisah. Admin/teknisi dalam satu tenant berbagi data | Global scope `tenant_id` |
| BR-11 | **Role-based access** | Admin bisa semua; teknisi dibatasi (read-only untuk beberapa modul) | Middleware IsAdmin + IsTeknisiOrAdmin |
| BR-12 | **Denda keterlambatan** | Jika setting late_fee diaktifkan, invoice yang melewati due_date + grace_days akan ditambahkan denda | Setting key: late_fee_amount, grace_days |

---

## 11. Success Metrics

| # | Metrik | Target | Cara Ukur |
|---|--------|--------|-----------|
| 1 | Seluruh pelanggan tercatat dalam sistem | 100% | Count customer vs data operasional |
| 2 | Invoice terbit otomatis sesuai siklus | 100% | Schedule running tepat waktu, jumlah invoice = jumlah customer aktif |
| 3 | Pembayaran tercatat dan tervalidasi | 100% | Setiap payment update invoice status akurat |
| 4 | Monitoring OLT berjalan otomatis | 100% | Poll OLT tiap jam, data ONU terupdate |
| 5 | Laporan dapat dihasilkan dari data sistem | Revenue, outstanding, chart akurat | Report page menampilkan data real-time |
| 6 | Pelanggan bisa self-service | Portal bisa diakses, Midtrans integration working | Test flow portal → bayar |
| 7 | Voucher generate + push otomatis | Generate voucher → muncul di MikroTik hotspot user | Validasi di MikroTik |
| 8 | Isolir berjalan otomatis | Customer overdue > grace period → suspended tanpa manual | Check schedule + log |

---

## 12. Product Roadmap Summary

```
v1.x ─── Stabilization & Security
   ├── Encrypt password MikroTik
   ├── SSL verification enable (configurable)
   ├── BelongsToTenant ke OdcPort & OdpPort
   ├── Proteksi reset_data.php
   └── Hapus sensitive credentials dari git history

v2.0 ─── Architecture Refactoring
   ├── API publik untuk integrasi pihak ketiga
   ├── Testing coverage meningkat (>80%)
   ├── Code refactoring (clean up dead code BelongsToUser)
   └── Performance optimasi query

v3.0 ─── Smart ISP Operations
   ├── Auto-diagnosis jaringan (deteksi putus OLT/ODP)
   ├── Predictive maintenance (berdasarkan tren Rx power)
   ├── Smart scheduling untuk teknisi
   └── Customer communication automation

v4.0 ─── Network Management (NMS)
   ├── Topology mapping otomatis
   ├── Real-time network health dashboard
   ├── Alert & notification system
   └── Integration dengan SNMP devices

v5.0 ─── Business Intelligence
   ├── Advanced analytics & forecasting
   ├── Churn prediction
   ├── Customer segmentation
   └── Executive dashboard real-time

v6.0 ─── Multi-Tenant Enterprise
   ├── White-label untuk reseller ISP
   ├── Billing management untuk multiple ISP
   ├── Central monitoring console
   └── Revenue sharing & commission

v7.0 ─── Ecosystem Expansion
   ├── Plugin marketplace
   ├── Public API dengan dokumentasi
   ├── Integrasi dengan payment channel lain
   └── Mobile app (customer & teknisi)

v8.0 ─── Enterprise Readiness
   ├── High availability clustering
   ├── SLA management
   ├── Advanced security compliance
   └── Enterprise support & documentation
```
