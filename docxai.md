# docxai.md — Dokumentasi Arsitektur Sistem

**Sistem:** Alkonek / PT Alkonek Network Access — ISP Billing & NOC Platform
**Versi:** v1.2
**Stack Utama:** Laravel 12 (PHP ^8.2 / 8.3), MySQL, Bootstrap 5.3 (npm + Vite), Leaflet, Font Awesome 6, Chart.js (per-halaman), Simple-QRCode.

---

## 1. Business Requirements

1. **Billing & Invoicing** — Generasi invoice bulanan otomatis untuk pelanggan PPPoE & Hotspot, dengan purge invoice lunas dan auto-isolir pelanggan overdue.
2. **Manajemen Jaringan FTTH** — Pemetaan visual topologi fiber (OLT → ODC → ODP → ONU) pada peta satelit/normal, termasuk status online/offline perangkat.
3. **Monitoring & Kesehatan Jaringan** — Poll OLT via SSH, sync ONU dari MikroTik PPPoE, pengumpulan metric jaringan, optimasi QoS, dan pengecekan SLA incident.
4. **Otomasi Operasional** — Engine automation (scheduler + worker + trigger) untuk tindakan berbasis event (isolir, notifikasi, sinkronisasi).
5. **Multi-Tenancy** — Setiap tenant (ISP/afiliasi) memiliki isolasi data per `tenant_id` via global scope.
6. **Payment Gateway** — Integrasi Midtrans & Xendit untuk pembayaran online.
7. **Self-Service Pelanggan** — Portal pelanggan (invoice, pembayaran, status) dan rencana notifikasi via aplikasi Android pelanggan (in-development).
8. **Manajemen User & Hak Akses** — Role (noc, superadmin, admin, teknisi, sales) dengan permission granular per fitur.

---

## 2. Functional Requirements

- **Map Screen (NOC FTTH):** Tambah/edit/hapus perangkat (OLT, ODC, ODP, OTB, Closure, HTB, ONU, Custom) pada peta; kalkulator redaman; visibility layer; ukur jarak/OTDR; edit jalur kabel; tabel ONU; tabel perangkat; card Sync Mikrotik/OLT/GenieACS; backup/restore; users.
- **Device Markers:** Marker diambil dari `Device` (source=`device`) dengan koordinat lat/lon; status diselesaikan dengan walk-up parent chain ke OLT.
- **OLT Multi-Brand:** Driver pattern untuk ZTE, Huawei, FiberHome, CData (+ JumpHost/Mikrotik SSH tunnel) via factory.
- **MikroTik Integration:** REST API dengan SSH fallback (phpseclib3) dan connection pool.
- **GenieACS (TR-069):** Manajemen ONU via GenieACS module (`app/Modules/GenieACS/`).
- **Billing Engine:** `billing:process` (harian 08:00), `invoices:purge-paid` (08:30), `customer:auto-isolir` (00:30), `customer:sync-isolir-ips` (5 menit).
- **Customer Isolation:** Set PPP Profile "Isolir" + tambah IP ke firewall address-list saat overdue.
- **Payment:** `PaymentGatewayInterface` dengan `MidtransGateway`, `XenditGateway`, dan `PaymentService`; webhook/local callback.
- **Incident & SLA:** `Incident`, `IncidentNotification`, `incident:check-sla`.
- **Automation:** `AutomationJob`, `AutomationTrigger`, `AutomationLog` + `automation:scheduler`/`automation:worker`.
- **I18N:** Antarmuka peta mendukung ID/EN (default **Bahasa Indonesia**).
- **Import/Export:** Excel import/export perangkat & pelanggan; import KML/KMZ.

---

## 3. Non-functional Requirements

- **Skalabilitas:** Monolith Laravel dengan cache marker (45s) dan auto-refresh tiap 10 detik; cache ping OLT (30s).
- **Performance:** Map markers di-cache di localStorage (stale-while-revalidate, 10 menit) agar render instan.
- **Keandalan (Reliability):** 17 perintah terjadwal untuk自愈 jaringan (poll, sync, purge, backup).
- **Keamanan:** Password MikroTik tidak di-encrypt; OLT credential di-encrypt (cast `encrypted`); MikroTik REST pakai `withoutVerifying()` (SSL disabled).
- **Maintainability:** PSR-4, Laravel Pint, pattern Controller → Service → Model, driver pattern untuk OLT.
- **Portability:** Deployment Vercel (prebuilt) + Railway backup; CI via GitHub Actions.
- **Observability:** Status bar PPPoE/ONU online/offline, traffic chart, health score, log automasi.
- **Compatibility:** Browser modern dengan WebGL (Leaflet), JavaScript esbuild via Vite.

---

## 4. System Context

Sistem digunakan oleh:
- **NOC Engineer / Superadmin** — operasi peta, sync, monitoring.
- **Admin / Teknisi / Sales** — manajemen perangkat, user, import/export.
- **Pelanggan** — portal pembayaran & status (web); notifikasi via aplikasi Android (rencana).
- **Sistem Eksternal:** MikroTik Router (REST/SSH), OLT vendor (SSH), GenieACS (TR-069), Midtrans/Xendit (payment), Aiven MySQL (prod DB), Android App (notifikasi).

Alur utama: Pengguna → NOC Map Screen → Laravel API → Service Layer → MikroTik/OLT/GenieACS → DB (Device/ONU/Customer). Pembayaran: Pelanggan → Payment Gateway → Webhook → PaymentService → Invoice.

---

## 5. Container Architecture

Monolith tunggal (Laravel app) yang berisi:
- **Web/Routing Layer** (`routes/web.php`, `routes/api.php`, `routes/console.php`).
- **HTTP Controllers** (58 root + Api + Auth + 14 Noc).
- **Service Layer** (`app/Services/`): Olt/Drivers, Mikrotik, Monitoring, Payment, SmartQos, Automation.
- **Module Layer** (`app/Modules/GenieACS/`).
- **Model Layer** (40 models + traits `BelongsToTenant`).
- **Frontend** (`resources/views/noc/features/map.blade.php` + Vite bundle).

Tidak ada pemisahan container mikroservice; semua dalam satu deploy Vercel (`api/index.php` via vercel-php).

---

## 6. Component Architecture

```
┌─────────────────────────────────────────────┐
│                  Browser (NOC Map)            │
│  Leaflet + Bootstrap + Chart.js + FontAwesome │
└───────────────┬─────────────────────────────┘
                │ HTTPS / JSON (mtApi)
┌───────────────▼─────────────────────────────┐
│            Laravel App (Vercel)               │
│  Controllers → Services → Models             │
│  ├─ FeaturesController (map markers, devices) │
│  ├─ OltService / Drivers (ZTE/Huawei/...)    │
│  ├─ MikrotikService (REST+SSH, Pool)         │
│  ├─ GenieACS Module                          │
│  ├─ PaymentService (Midtrans/Xendit)         │
│  ├─ Monitoring (FiberTopology, HealthScore)  │
│  └─ Automation Engine                        │
└───┬──────────┬──────────┬──────────┬────────┘
    │          │          │          │
 MySQL    MikroTik    OLT      GenieACS   Payment GW
(Aiven)   (REST/SSH) (SSH)    (TR-069)   (Midtrans/Xendit)
```

Komponen utama:
- **FTTH Map Screen** — `map.blade.php`, ~11.8k baris, mengelola marker, kabel, calculator, visibility.
- **OLT Driver Factory** — `app/Services/Olt/Drivers/`.
- **Mikrotik Connection Pool** — `app/Services/Mikrotik/` (`RouterConnectionPool`, `RouterConnectionManager`).
- **Fiber Topology Service** — `app/Services/Monitoring/FiberTopologyService::getTopologyData()`.
- **SmartQoS** — `app/Services/SmartQos/SmartQosService.php`.
- **Automation Engine** — `app/Services/Automation/`.

---

## 7. Data Model

~48 tabel, 40 models, 31 models dengan trait `BelongsToTenant` (global scope `tenant_id`).

Entitas inti:
- **Tenant** — root multi-tenancy; `User` belongsTo Tenant.
- **User** — role + permissions JSON.
- **Device** — perangkat peta (type: olt/odc/odp/otb/closure/htb/onu/custom), lat/lon, attributes JSON (induk, port_plc, rasio, cable_*, warna_core, pppoe_user).
- **Olt** — model OLT (connection_status, ip_address).
- **Odp / OdpPort** — distribusi; `OdpPort.customer_id`.
- **Onu** — `onus` terkait customer & oltPort.
- **Customer** — PPPoE/Hotspot, `odp_port_id`, `customer_code`, `pppoe_username`.
- **Invoice** — billing bulanan.
- **Incident / IncidentNotification / IncidentNotificationService**.
- **AutomationJob / AutomationTrigger / AutomationLog**.
- **NetworkMetric** — metric jaringan periodik.
- **MikrotikRouter** — `is_active`, host (dari Setting DB, bukan hardcoded).

Relasi topologi: `Device.parent` (field `induk` = "TYPE — Name") → walk-up chain ke OLT.

---

## 8. Integration Architecture

| Sistem Eksternal | Protokol | Library | Catatan |
|---|---|---|---|
| MikroTik Router | REST API + SSH fallback | `RouterCommandService` (phpseclib3) | Connection pool; tunnel via `mikrotik_host` di Setting DB |
| OLT Vendor | SSH | OLT Drivers (factory) | ZTE/Huawei/FiberHome/CData/JumpHost |
| GenieACS | TR-069 / NBI | `app/Modules/GenieACS/` | Manajemen ONU |
| Midtrans | HTTPS REST | `MidtransGateway` | Pembayaran |
| Xendit | HTTPS REST | `XenditGateway` | Pembayaran |
| Aiven MySQL | TCP/3306 | Laravel DB | Prod DB (Vercel) |
| Android App | Push/API | (rencana) | Notifikasi pelanggan (Fonnte/WA dihapus) |
| Nominatim | HTTPS | `fetch` | Reverse geocode di add-device |

Catatan keamanan integrasi: MikroTik REST pakai `withoutVerifying()` (SSL verification disabled). cURL error 6/7/28 = tunnel MikroTik offline.

---

## 9. Security Architecture

- **Multi-Tenancy Isolation:** Global scope `tenant_id` pada 31 model; `Tenant` sebagai root.
- **Auth:** Laravel Auth; role case-insensitive saat redirect login.
- **Permissions:** JSON per-user, dicek via `ftthCan(perm)` di UI & `FTTH_PERM_MAP`.
- **Credentials:** Password MikroTik **tidak di-encrypt**; OLT credential di-encrypt (cast `encrypted`).
- **Secrets:** `.env`, `vercel.json` (berisi plaintext prod credential) — **gitignored**, tidak boleh di-commit.
- **Payment:** Abstraksi `PaymentGatewayInterface`; webhook diproses via controller terpisah (XenditController).
- **Deleted Integrations:** Fonnte/WhatsApp dihapus seluruhnya dari codebase (jangan dihidupkan kembali).
- **Known Data-Leak Risk:** `OdcPort`, `OdpPort`, `IncidentNotification`, `InterfaceChangeLog`, `MikrotikInterfaceMetadata`, `OnuMonitoringHistory`, `PingResult` **tidak** punya tenant scope — butuh filter manual.

---

## 10. Deployment Architecture

- **Platform:** Vercel dengan `vercel-php@0.9.0`, `api/index.php` sebagai entry, outputDirectory `public`.
- **Backup:** Railway.app.
- **CI/CD:** `.github/workflows/deploy.yml` — `npm ci && npm run build` lalu `vercel deploy --prebuilt --prod` pada push ke `main`/`master`.
- **PHP:** Laravel 12 (PHP ^8.2); lokal dev pakai PHP 8.3 (Laragon).
- **DB:** MySQL lokal (`e_billing`); prod Aiven MySQL.
- **Cache/Session:** `QUEUE_CONNECTION=database`, `DB_CONNECTION=mysql`, session/cache `file` (lokal).
- **Frontend Build:** Bootstrap 5.3 + `resources/js/app.js` via Vite; Chart.js/Leaflet di-load per-halaman (CDN, `defer`) + `@push('scripts')`.

---

## 11. Observability

- **Status Bar:** PPPoE Online/Offline, ONU Online/Offline (di map screen).
- **Traffic Charts:** WAN-ISP (MikroTik), PON (OLT), Live Traffic ONU (Chart.js).
- **Health Score:** `app/Services/Monitoring/HealthScore.php`.
- **Ping Monitor / Diagnosis / SpeedTest:** `app/Services/Monitoring/`.
- **Fiber Topology Graph:** `/onu-health/topology/graph`.
- **Logs:** `AutomationLog`, `IncidentNotification`, `InterfaceChangeLog`, `OnuMonitoringHistory`, `PingResult`.
- **Queue:** database queue (`QUEUE_CONNECTION=database`), diproses via `queue:listen`.

---

## 12. Failure Scenarios

| Skenario | Dampak | Mitigasi |
|---|---|---|
| Tunnel MikroTik offline (cURL 6/7/28) | Sync/ping gagal | Ganti `mikrotik_host` ke IP langsung; restart onsite |
| OLT mati (fisik) | `connection_status=offline` → kaskade offline ke anak | `refreshAllOltRealStatus` (ICMP dari MikroTik); jangan paksa offline dari ping edge yang tak reliabel |
| Marker API 500 (TypeError, dsb) | Semua device hilang di peta | `buildMapMarkers` di-cache 45s; periksa exception di Service |
| Payment gateway down | Pembayaran gagal | Fallback gateway lain (Midtrans/Xendit) |
| DB connection lost | Seluruh fitur mati | Aiven HA + Railway backup |
| Vercel build gagal | Deploy terhenti | CI gagal → rollback otomatis ke versi sebelumnya |

---

## 13. Disaster Recovery

- **Backup DB:** `customers:backup` (JSON PPPoE/Hotspot), `backup/restore` card (Gmail auto-backup), Railway snapshot.
- **Restore:** Import JSON/Excel via backup card; `routes/console.php` `olt:batch-link`, `qos:setup`, dsb untuk rebuild.
- **RTO/RPO:** Tergantung schedule (backup harian 03:00); RPO ~1 hari untuk data pelanggan.
- **Vercel:** Stateless (prebuilt artifact); redeploy instan dari Git.
- **Aiven:** Managed HA dengan replica.

---

## 14. Cost Model

- **Vercel:** Plan hosting PHP (bayar per request/GB-hour); relatif murah untuk trafik NOC moderat.
- **Railway:** Backup app (biaya kecil).
- **Aiven MySQL:** Managed DB (biaya berdasar storage + throughput).
- **GitHub Actions:** CI gratis (public) / menit terbatas (private).
- **Payment Gateway:** Biaya transaksi per success (Midtrans/Xendit).
- **External API:** Nominatim (gratis, rate-limited); GenieACS self-hosted.
- **Lokal Dev:** Laragon + MySQL (tanpa biaya).

---

## 15. Architecture Decision Records (ADR)

**ADR-001 — Monolith Laravel (bukan microservice).**
Context: Tim kecil, fitur padat. Decision: Monolith dengan Service/Module layer. Consequence: Sederhana di-deploy, namun coupling tinggi.

**ADR-002 — OLT Driver Pattern.**
Context: Multi-vendor OLT. Decision: Factory + driver per brand (SSH). Consequence: Mudah tambah brand, tapi tiap driver perlu pemeliharaan.

**ADR-003 — MikroTik REST + SSH fallback.**
Context: API REST tidak selalu tersedia. Decision: REST utama, SSH fallback via phpseclib3 + connection pool. Consequence: Resilien, tapi SSL verification dimatikan (`withoutVerifying()`) — risiko MITM.

**ADR-004 — Device Markers dari `Device` (bukan customer).**
Context: Sebelumnya marker `source=customer` (lokasi dari ODP). Decision: Semua marker dari `Device` dengan lat/lon. Consequence: Konsisten, tapi perangkat tanpa koordinat tidak tampil.

**ADR-005 — OLT status "selalu ONLINE" by default.**
Context: Reachability SSH mgmt IP OLT tidak relevan (trafik via MikroTik). Decision: Jangan paksa offline dari ping edge yang tak reliabel. Consequence: Offline detection kurang akurat, namun menghindari false-offline kaskade.

**ADR-006 — Hapus Fonnte/WhatsApp.**
Context: Migrasi ke notifikasi aplikasi Android pelanggan. Decision: Hapus seluruh kode Fonnte/WA. Consequence: `IncidentNotification` tetap ada (status pending) untuk konsumsi app nanti.

**ADR-007 — Default Bahasa Indonesia.**
Context: Pengguna dominan lokal. Decision: `FTTH_LANG = 'id'` default, toggle tetap ada untuk sesi. Consequence: Selalu mulai Indonesian saat reload.

**ADR-008 — Vercel + prebuilt artifact.**
Context: Hosting PHP di serverless. Decision: `vercel-php` + `npm run build` + `vercel deploy --prebuilt`. Consequence: Deploy cepat, namun `vercel.json` berisi credential plaintext (gitignored).

**ADR-009 — Map marker cache (localStorage + server 45s).**
Context: Ribuan marker, load lambat. Decision: Stale-while-revalidate + localStorage. Consequence: Render instan, tapi data bisa staleness 10 menit di client.
