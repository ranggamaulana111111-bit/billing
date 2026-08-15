# Seeders — ALKONEK ISP Billing System

> 5 Files | `Database\Seeders\` namespace

---

## Urutan Eksekusi

```
DatabaseSeeder
├── User (admin + teknisi)
├── OdpRouteSeeder
│   ├── Odc (1)
│   ├── OdpRoute (5)
│   └── OdpPoint (11)
└── BillingSeeder
    ├── Package (5)
    ├── Customer (10)
    ├── Invoice (14)
    └── ActivityLog (5)
```

Dua seeder **tidak dipanggil** oleh `DatabaseSeeder`:
- `SettingSeeder` — harus dijalankan manual atau via `db:seed --class=SettingSeeder`
- `VoucherProfileSeeder` — harus dijalankan manual

---

## DatabaseSeeder

| Key | Value |
|-----|-------|
| Class | `DatabaseSeeder` |
| Idempotent | Ya — `User::where('email', ...)->exists()` check |
| Data | 2 user accounts |
| Memanggil | `OdpRouteSeeder`, `BillingSeeder` |

**Data yang dibuat:**
| Email | Name | Password | Role |
|-------|------|----------|------|
| admin@alkonek.net | Admin | admin123 | admin |
| test@example.com | Test User | password | teknisi |

---

## OdpRouteSeeder

| Key | Value |
|-----|-------|
| Class | `OdpRouteSeeder` |
| Idempotent | Ya — `updateOrCreate` |
| Data | 1 ODC, 5 route, 11 ODP point |

**Data:**
- **ODC:** ODC Bendungan (kapasitas 48 port)
- **Routes:** Kumpay Timur, Kumpay Barat, Kumpay Raya, Kumpay Selatan, Kumpay Lapangan
- **Points:** 11 ODP dengan kapasitas 8-16 port, lat/lng real, port_used bervariasi
- **Cleanup:** Hapus ODC default lain jika ada (`ODC Kumpay Utama`, `ODC Kumpay Barat`)

---

## BillingSeeder

| Key | Value |
|-----|-------|
| Class | `BillingSeeder` |
| Idempotent | Tidak — `Package::create()` langsung tanpa check |
| Data | 5 packages, 10 customers, 14 invoices, 5 activity logs |

**Packages:**
| Name | Speed | Price |
|------|-------|-------|
| Home 10 | 10 | 150,000 |
| Home 20 | 20 | 250,000 |
| Biz 30 | 30 | 400,000 |
| Biz 50 | 50 | 650,000 |
| Ultra 100 | 100 | 1,000,000 |

**Customers:** 10 pelanggan (Ahmad Fauzi s/d Fitri Handayani) dengan ODP point rotating, PPPoE username, due_date Juni 2026.

**Invoices:** Masing-masing customer mendapat 1 invoice (Juni). Customer 1-4 (Ahmad-Dewi) mendapat invoice tambahan bulan Mei. 4 customer status 'paid', 6 customer status 'unpaid'.

**Activity Logs:** 5 log statis (Login, Tambah Pelanggan, Pembayaran, Generate Invoice, Update ODP).

---

## SettingSeeder

| Key | Value |
|-----|-------|
| Class | `SettingSeeder` |
| Idempotent | Ya — `firstOrCreate` |
| Data | 10 settings |

**Default settings:**
| Key | Value |
|-----|-------|
| company_name | ALKONEKbill |
| company_short_name | ALKONEKbill |
| company_address | Jl. Raya Rabeg No. 1 |
| company_phone | 08123456789 |
| bank_name | Bank BCA |
| bank_account | 1234567890 |
| bank_holder | ALKONEKbill |
| invoice_footer | Terima kasih atas kepercayaan Anda. |
| admin_phone | (kosong) |
| admin_name | Admin |

---

## VoucherProfileSeeder

| Key | Value |
|-----|-------|
| Class | `VoucherProfileSeeder` |
| Idempotent | Tidak — `VoucherProfile::create()` langsung |
| Data | 8 voucher profiles |

**Profiles:**
| Name | Speed | Price | Time (h) | Quota (MB) | Validity | Shared |
|------|-------|-------|----------|------------|----------|--------|
| 5GB - 30 Hari | 5Mbps | 50,000 | — | 5120 | 30 | 1 |
| 10GB - 30 Hari | 10Mbps | 75,000 | — | 10240 | 30 | 1 |
| 20GB - 30 Hari | 20Mbps | 100,000 | — | 20480 | 30 | 2 |
| Unlimited - 7 Hari | 10Mbps | 35,000 | 168 | — | 7 | 1 |
| Unlimited - 30 Hari | 10Mbps | 120,000 | 720 | — | 30 | 2 |
| 1 Jam | 5Mbps | 5,000 | 1 | — | 1 | 1 |
| 3 Jam | 5Mbps | 10,000 | 3 | — | 1 | 1 |
| 12 Jam | 10Mbps | 20,000 | 12 | — | 1 | 1 |

Semua `is_active = true`, `description = ''`.

---

## Cara Re-seed

```bash
# Full reseed (hapus semua data + migrasi ulang)
"C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe" artisan migrate:fresh --seed

# Seeder spesifik
"C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe" artisan db:seed --class=SettingSeeder

# Tanpa seeder (hanya migrasi)
"C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe" artisan migrate:fresh
```

---

## Catatan Penting

1. `BillingSeeder` dan `VoucherProfileSeeder` **tidak idempotent** — akan duplikasi data jika dijalankan ulang
2. `DatabaseSeeder` memanggil `OdpRouteSeeder` dan `BillingSeeder` — urutan penting karena BillingSeeder membutuhkan OdpPoint
3. `SettingSeeder` dan `VoucherProfileSeeder` harus dijalankan manual
4. Data seeder mengasumsikan **single tenant** (tenant_id tidak diisi — BelongsToTenant trait akan auto-fill dari user yang login)
