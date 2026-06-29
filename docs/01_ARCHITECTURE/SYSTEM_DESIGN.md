# System Design — RabegNet ISP Billing System

---

## Arsitektur Overview

RabegNet menggunakan arsitektur **Monolithic dengan pemisahan Controller → Service → Model**, dibangun di atas Laravel 12. Aplikasi berjalan sebagai satu kesatuan yang menangani request HTTP, business logic, dan database access.

### Layer Arsitektur

```
┌──────────────────────────────────────────────────────┐
│                 Presentation Layer                    │
│   Blade Templates + Bootstrap 5.3 + Leaflet.js       │
│   Chart.js + Alpine.js + Vite                        │
├──────────────────────────────────────────────────────┤
│                  Routing Layer                        │
│   routes/web.php (~148 routes)                       │
│   routes/api.php (3 routes)                          │
│   routes/console.php (5 schedules)                   │
├──────────────────────────────────────────────────────┤
│                 Controller Layer                      │
│   34 Controllers (Auth, API, Backup, dll)            │
│   Middleware: IsAdmin, IsTeknisiOrAdmin               │
├──────────────────────────────────────────────────────┤
│                  Service Layer                        │
│   MidtransService, MikrotikService                   │
│   Olt/ (Driver Pattern)                              │
│     ├─ Drivers: Huawei, ZTE, FiberHome, C-Data      │
│     └─ Decorators: JumpHost, MikroTikProxy           │
├──────────────────────────────────────────────────────┤
│                   Model Layer                         │
│   19 Models + 2 Traits                               │
│   BelongsToTenant (Global Scope)                     │
├──────────────────────────────────────────────────────┤
│                 Database Layer                        │
│   MySQL (production), SQLite (testing)               │
│   28 tables, 46 migrations                           │
├──────────────────────────────────────────────────────┤
│          External Integration Layer                   │
│   Midtrans Snap API, MikroTik REST API,              │
│   Fonnte WA API, Google OAuth                        │
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
Tenant → User (admin/teknisi)
 ├── Membuat Pelanggan → auto-create Invoice pertama
 ├── Pelanggan bayar → Payment → Invoice status=paid
 ├── Generate Voucher → push ke MikroTik → callback API saat login
 ├── Scan/Poll OLT → update ONU status → deteksi redaman
 ├── Buat ODC → ODP → assign port ke Customer
 ├── Isolir: overdue → auto-suspend → MikroTik PPP Profile isolir
 └── Customer akses halaman isolir → bayar → auto-activate
```

### Pola Arsitektur

| Pattern | Penerapan |
|---------|-----------|
| Monolithic | Satu aplikasi Laravel, pemisahan Controller → Service → Model |
| Multi-tenant | `BelongsToTenant` trait — global scope `WHERE tenant_id = ?` |
| Driver Pattern | OLT multi-brand — factory memilih driver sesuai brand |
| Decorator Pattern | Jump Host SSH tunnel & MikroTik SSH Proxy — bungkus driver OLT |
| Scheduled Tasks | 5 console command berjalan otomatis (daily/hourly) |
| Event-driven API | Callback MikroTik hotspot → update status voucher |
| Job Queue | Polling OLT + WA notification (database queue) |

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
