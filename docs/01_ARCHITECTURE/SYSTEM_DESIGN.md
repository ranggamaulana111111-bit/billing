# System Design — ALKONEK ISP Billing System v1.2

---

## Arsitektur Overview

ALKONEK menggunakan arsitektur **Monolithic dengan pemisahan Controller → Service → Model**, dibangun di atas Laravel 12 (PHP ^8.2). Aplikasi berjalan sebagai satu kesatuan yang menangani request HTTP, business logic, dan database access.

### Layer Arsitektur

```
┌──────────────────────────────────────────────────────┐
│                 Presentation Layer                    │
│   Blade Templates + Bootstrap 5.3 (Vite) + Leaflet   │
│   Chart.js (CDN, per-halaman) + app.css custom       │
├──────────────────────────────────────────────────────┤
│                  Routing Layer                        │
│   routes/web.php (~510 routes)                       │
│   routes/api.php (3 routes)                          │
│   routes/console.php (17 schedules)                  │
├──────────────────────────────────────────────────────┤
│                 Controller Layer                      │
│   59 Controllers (39 root + 3 Api + 3 Auth + 14 Noc)│
│   Middleware: IsAdmin, IsTeknisiOrAdmin, IsNoc       │
├──────────────────────────────────────────────────────┤
│                  Service Layer                        │
│   Payment/ (MidtransGateway, XenditGateway)          │
│   Mikrotik/ (connection pool + RouterOSApiService)   │
│   Olt/ (Driver Pattern)                              │
│     ├─ Drivers: Huawei, ZTE, FiberHome, C-Data,      │
│     │          ChineseOlt, Global, Hioso, Hsgq, VSOL │
│     └─ Decorators: JumpHost, MikroTikProxy, SshTunnel│
│   Automation/ + SmartQos/ + Monitoring/              │
├──────────────────────────────────────────────────────┤
│                   Model Layer                         │
│   39 Models + 2 Traits                               │
│   BelongsToTenant (Global Scope)                     │
├──────────────────────────────────────────────────────┤
│                 Database Layer                        │
│   MySQL (local/prod Aiven), SQLite (testing)         │
│   98 migrations, ~47 tabel                           │
├──────────────────────────────────────────────────────┤
│          External Integration Layer                   │
│   Midtrans Snap, Xendit, MikroTik REST/SSH,          │
│   Google OAuth, GenieACS (TR-069)                     │
└──────────────────────────────────────────────────────┘
```

### Alur Request

```
User → Browser → Vite (dev) / public/build (prod)
              → Laravel Route
              → Middleware (auth → teknisi/admin)
              → Controller
              → Service (optional)
              → Model/ORM (auto-filter tenant_id)
              → Database (MySQL)
              → View (Blade) → Response HTML
```

### Alur Data Utama

```
Tenant → User (admin/teknisi/noc)
 ├── Membuat Pelanggan → auto-create Invoice pertama
 ├── Pelanggan bayar → Payment → Invoice status=paid
 ├── Generate Voucher → push ke MikroTik → callback API saat login
 ├── Scan/Poll OLT → update ONU status → deteksi redaman
 ├── Buat ODC → ODP → assign port ke Customer
 ├── Isolir: overdue → auto-suspend (command) → MikroTik PPP Profile isolir
 └── Bayar → status aktif kembali (auto-activate via CustomerController)
```

### Pola Arsitektur

| Pattern | Penerapan |
|---------|-----------|
| Monolithic | Satu aplikasi Laravel, pemisahan Controller → Service → Model |
| Multi-tenant | `BelongsToTenant` trait — global scope `WHERE tenant_id = ?` |
| Driver Pattern | OLT multi-brand (9 brand) — factory memilih driver sesuai brand |
| Decorator Pattern | Jump Host SSH tunnel & MikroTik SSH Proxy — bungkus driver OLT |
| Scheduled Tasks | 17 console command berjalan otomatis (daily/hourly/everyMinute) |
| Event-driven API | Callback MikroTik hotspot → update status voucher |
| Job Queue | Polling OLT & job async (database queue) |
| Connection Pool | `RouterConnectionPool`/`RouterConnectionManager` — reuse koneksi REST/SSH MikroTik |
| REST + SSH Fallback | MikroTik REST API → gagal → fallback SSH (phpseclib3) |
| Module Monolith | `app/Modules/GenieACS/` untuk domain TR-069 terpisah |

### Arsitektur Data Multi-Tenant

```
Database
└── tenants
    └── tenant_1
        ├── users (admin, teknisi)
        ├── customers
        ├── invoices
        ├── payments
        ├── odcs, odps
        ├── olts, onus
        └── settings
    └── tenant_2
        └── ... (data terisolasi)
```

Setiap query ke model utama otomatis difilter dengan `WHERE tenant_id = ?` via Global Scope (`BelongsToTenant` trait).
