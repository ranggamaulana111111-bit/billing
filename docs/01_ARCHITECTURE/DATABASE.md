# Database — RabegNet ISP Billing System

> 28 Tables | 46 Migrations | MySQL (Prod) / SQLite (Test)

---

## Entity Relationship

```
tenants
 └── users ──┬── customers ──┬── invoices ──── payments
              │               └── onus ──────── olt_ports ── olts
              │               └── odp_ports ─── odps ──────── odc_ports ── odcs
              ├── packages
              ├── vouchers ──── voucher_profiles
              │              └── mikrotik_routers
              │              └── voucher_templates
              ├── settings
              ├── activity_logs
              ├── olts ──────── olt_ports ──── onus
              ├── odcs ──────── odp_routes ──── odp_points
              └── odcs ──────── odps ────────── odp_ports ──── customers
```

---

## Tabel per Grup

### Core / System

| Tabel | Kolom Utama | Catatan |
|-------|-------------|---------|
| `tenants` | id, name, domain | Root multi-tenancy |
| `users` | id, tenant_id, name, email, password, role | role: admin / teknisi |
| `settings` | id, tenant_id, key, value | Key-value store per tenant |
| `sessions` | — | Database session driver |
| `jobs` | — | Database queue driver |
| `cache` | — | Database cache driver |

### Billing

| Tabel | Kolom Utama | Catatan |
|-------|-------------|---------|
| `customers` | id, tenant_id, name, phone, email, package_id, odp_id, odp_port_id, pppoe_username, status, due_date | status: active/suspended/inactive |
| `packages` | id, tenant_id, name, speed, price, billing_cycle, is_active | |
| `invoices` | id, tenant_id, customer_id, invoice_code, amount, payment_status, paid_at, due_date | payment_status: unpaid/paid/cancelled |
| `payments` | id, tenant_id, invoice_id, amount, payment_method, payment_date | method: cash/transfer/qris/midtrans |

### Infrastructure

| Tabel | Kolom Utama | Catatan |
|-------|-------------|---------|
| `olts` | id, tenant_id, name, brand, ip_address, username, password (encrypted) | brand: huawei/zte/fiberhome/cdata |
| `olt_ports` | id, tenant_id, olt_id, slot_number, port_number | |
| `onus` | id, tenant_id, olt_port_id, customer_id, onu_id, sn, status, rx_power | |
| `odcs` | id, tenant_id, nama_odc, koordinat, kapasitas_port | koordinat: "lat,lng" |
| `odc_ports` | id, odc_id, port_number, port_type, status, connected_to_odp_id | ⚠️ No tenant scope |
| `odps` | id, tenant_id, odc_id, nama_odp, koordinat, kapasitas_port, kabel_tube_color, kabel_core_number, kondisi_jalur | |
| `odp_ports` | id, odp_id, port_number, status | ⚠️ No tenant scope |
| `odp_routes` | id, tenant_id, odc_id, name, color, coordinates (JSON) | Legacy |
| `odp_points` | id, tenant_id, odp_route_id, name, lat, lng, port_capacity | Legacy |

### Voucher

| Tabel | Kolom Utama | Catatan |
|-------|-------------|---------|
| `vouchers` | id, tenant_id, username, password, duration_hours, status, profile_id, router_id | status: active/used/expired |
| `voucher_profiles` | id, tenant_id, name, speed, price, time_limit, quota_limit | |
| `voucher_templates` | id, tenant_id, name, content, status_page, ..., is_active | 6 halaman hotspot |
| `mikrotik_routers` | id, tenant_id, name, host, port, username, password (plaintext⚠️), hotspot_server | |

### Activity

| Tabel | Kolom Utama |
|-------|-------------|
| `activity_logs` | id, tenant_id, user_id, action, description, data (JSON) |

---

## Relasi Detail

### ODC → ODP → Customer

```
odcs
 └── hasMany → odc_ports (odc_id)
 │               └── port_number, port_type (inlet/outlet), status (available/used/broken)
 │               └── connected_to_odp_id → odps.id
 │
 └── hasMany → odps (odc_id)
                └── hasMany → odp_ports (odp_id)
                │               └── port_number, status (available/used/broken)
                │               └── hasOne → customers (odp_port_id)
                └── hasMany → customers (odp_id)
```

### OLT → ONU → Customer

```
olts
 └── hasMany → olt_ports (olt_id)
                └── hasMany → onus (olt_port_id)
                               └── belongsTo → customers (customer_id)
```

---

## Catatan Penting

1. **`OdcPort` & `OdpPort`** — TIDAK menggunakan `BelongsToTenant` (potensi data leak antar tenant)
2. **`mikrotik_routers.password`** — disimpan plaintext (tidak di-encrypt)
3. **`olts.password`** — di-encrypt (menggunakan `encrypted` cast Laravel)
4. **`BelongsToUser` trait** — masih ada tapi dead code, sudah digantikan `BelongsToTenant`
5. **`odp_points`** — legacy table, data sudah dimigrasi ke `odps`
6. **`kondisi_jalur`** di `odps` — string biasa (`UP`/`DOWN_LINK_FAILURE`), bukan enum
7. **Database driver session/cache/queue** — semuanya menggunakan database, bukan file/redis
