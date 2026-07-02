# Database Schema — RabegNet ISP Billing System

> 28 Tables | Definisi kolom per tabel (tipe, nullable, default, FK, index)

---

## Core / System (6 tables)

### `tenants`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| name | varchar(255) | NO | — | Nama tenant |
| address | varchar(255) | YES | NULL | Alamat |
| phone | varchar(255) | YES | NULL | Telepon |
| email | varchar(255) | YES | NULL | Email |
| logo | varchar(255) | YES | NULL | Path logo |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `users`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id |
| name | varchar(255) | NO | — | |
| email | varchar(255) | NO | — | **UNIQUE** |
| email_verified_at | timestamp | YES | NULL | |
| password | varchar(255) | YES | NULL | Nullable untuk OAuth users |
| remember_token | varchar(100) | YES | NULL | |
| provider | varchar(255) | YES | NULL | OAuth provider |
| provider_id | varchar(255) | YES | NULL | OAuth provider ID, **UNIQUE** (provider, provider_id) |
| avatar | varchar(255) | YES | NULL | URL avatar |
| role | varchar(255) | NO | 'teknisi' | admin / teknisi |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `sessions`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | varchar(255) | NO | | **Primary Key** |
| user_id | bigint unsigned | YES | NULL | FK → users.id, **INDEX** |
| ip_address | varchar(45) | YES | NULL | |
| user_agent | text | YES | NULL | |
| payload | longtext | NO | — | |
| last_activity | int | NO | — | **INDEX** |

### `password_reset_tokens`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| email | varchar(255) | NO | — | **Primary Key** |
| token | varchar(255) | NO | — | |
| created_at | timestamp | YES | NULL | |

### `cache`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| key | varchar(255) | NO | — | **Primary Key** |
| value | mediumtext | NO | — | |
| expiration | int | NO | — | |

### `jobs`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| queue | varchar(255) | NO | — | **INDEX** |
| payload | longtext | NO | — | |
| attempts | tinyint unsigned | NO | — | |
| reserved_at | int unsigned | YES | NULL | |
| available_at | int unsigned | NO | — | |
| created_at | int unsigned | NO | — | |

---

## Billing (4 tables)

### `packages`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| name | varchar(255) | NO | — | |
| speed | varchar(255) | NO | — | |
| description | text | YES | NULL | |
| price | decimal(12,2) | NO | — | |
| billing_cycle | varchar(255) | NO | 'monthly' | |
| mikrotik_profile | varchar(255) | YES | NULL | |
| is_active | tinyint(1) | NO | 1 | boolean |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `customers`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| name | varchar(255) | NO | — | |
| location | varchar(255) | YES | NULL | |
| phone | varchar(255) | YES | NULL | |
| email | varchar(255) | YES | NULL | |
| package_id | bigint unsigned | NO | — | FK → packages.id |
| odp_id | bigint unsigned | YES | NULL | FK → odps.id |
| odp_port_id | bigint unsigned | YES | NULL | FK → odp_ports.id, **UNIQUE** |
| odp_point_id | bigint unsigned | YES | NULL | FK → odp_points.id (legacy) |
| pppoe_username | varchar(255) | YES | NULL | |
| original_ppp_profile | varchar(255) | YES | NULL | |
| due_date | date | YES | NULL | |
| status | varchar(255) | NO | 'active' | active/suspended/inactive |
| suspended_at | timestamp | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `invoices`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| customer_id | bigint unsigned | NO | — | FK → customers.id |
| invoice_code | varchar(255) | NO | — | **UNIQUE** |
| amount | decimal(12,2) | NO | — | |
| payment_status | tinyint(1) | NO | 'unpaid' | enum: unpaid/paid/cancelled |
| billing_period | char(7) | YES | NULL | Format YYYY-MM |
| paid_at | timestamp | YES | NULL | |
| payment_method | varchar(50) | YES | NULL | |
| midtrans_order_id | varchar(255) | YES | NULL | Midtrans order ID |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `payments`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| invoice_id | bigint unsigned | NO | — | FK → invoices.id |
| amount | decimal(12,2) | NO | — | |
| payment_method | varchar(50) | NO | 'cash' | cash/transfer/qris/midtrans |
| payment_date | date | NO | — | |
| notes | text | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

---

## Infrastructure (8 tables)

### `olts`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| name | varchar(255) | NO | — | |
| brand | varchar(255) | NO | — | huawei/zte/fiberhome/cdata |
| model | varchar(255) | YES | NULL | |
| ip_address | varchar(255) | NO | — | |
| ssh_port | int | NO | 22 | |
| username | varchar(255) | YES | NULL | |
| password | text | YES | NULL | **Encrypted** cast |
| jump_host | varchar(255) | YES | NULL | SSH tunnel host |
| jump_port | int | NO | 22 | |
| jump_username | varchar(255) | YES | NULL | |
| jump_password | text | YES | NULL | **Encrypted** cast |
| snmp_community | varchar(255) | YES | NULL | |
| snmp_version | varchar(255) | YES | NULL | v1/v2c/v3 |
| snmp_port | int | NO | 161 | |
| location | varchar(255) | YES | NULL | |
| latitude | decimal(10,7) | YES | NULL | |
| longitude | decimal(10,7) | YES | NULL | |
| status | varchar(255) | NO | 'active' | active/maintenance/inactive |
| notes | text | YES | NULL | |
| last_polled_at | timestamp | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `olt_ports`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| olt_id | bigint unsigned | NO | — | FK → olts.id |
| slot_number | int | NO | — | |
| port_number | int | NO | — | |
| port_type | varchar(255) | NO | 'gpon' | gpon/xgspon/epon |
| status | varchar(255) | NO | 'active' | active/inactive/blocked |
| description | text | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |
| **UNIQUE** | (olt_id, slot_number, port_number) | | | |

### `onus`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| olt_port_id | bigint unsigned | NO | — | FK → olt_ports.id |
| customer_id | bigint unsigned | YES | NULL | FK → customers.id |
| onu_id | varchar(255) | NO | — | ID dari OLT |
| serial_number | varchar(255) | YES | NULL | |
| caller_id | varchar(255) | YES | NULL | | |
| vendor | varchar(255) | YES | NULL | |
| model | varchar(255) | YES | NULL | |
| mac_address | varchar(255) | YES | NULL | |
| status | varchar(255) | NO | 'offline' | online/offline/active/inactive |
| rx_power | double | YES | NULL | |
| tx_power | double | YES | NULL | |
| distance | int | YES | NULL | |
| uptime | int | YES | NULL | Detik |
| slot_number | int | YES | NULL | |
| port_number | int | YES | NULL | |
| notes | text | YES | NULL | |
| last_seen_at | timestamp | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |
| **UNIQUE** | (olt_port_id, onu_id) | | | |

### `odcs`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| nama_odc | varchar(255) | NO | — | |
| koordinat | varchar(255) | YES | NULL | "lat,lng" |
| kapasitas_port | int | NO | — | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `odc_ports`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| odc_id | bigint unsigned | NO | — | FK → odcs.id |
| port_number | int | NO | — | |
| port_type | varchar(255) | NO | — | inlet/outlet |
| status | varchar(255) | NO | 'available' | available/used/broken |
| connected_to_odp_id | bigint unsigned | YES | NULL | FK → odps.id |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |
| ⚠️ | **Tidak ada tenant_id** — potensi data leak |
| **UNIQUE** | (odc_id, port_number) | | | |

### `odps`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| odc_id | bigint unsigned | YES | NULL | FK → odcs.id |
| nama_odp | varchar(255) | NO | — | |
| koordinat | varchar(255) | YES | NULL | "lat,lng" |
| kapasitas_port | int | NO | — | |
| kabel_tube_color | varchar(255) | NO | — | |
| kabel_core_number | int | NO | — | |
| kondisi_jalur | varchar(255) | NO | 'UP' | UP/DOWN_LINK_FAILURE |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `odp_ports`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| odp_id | bigint unsigned | NO | — | FK → odps.id |
| port_number | int | NO | — | |
| status | varchar(255) | NO | 'available' | available/used/broken |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |
| ⚠️ | **Tidak ada tenant_id** — potensi data leak |
| **UNIQUE** | (odp_id, port_number) | | | |

---

## Voucher (4 tables)

### `mikrotik_routers`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| name | varchar(255) | NO | — | |
| host | varchar(255) | NO | — | |
| port | int | NO | 80 | |
| username | varchar(255) | NO | — | |
| password | text | NO | — | ⚠️ **Plaintext** |
| hotspot_server | varchar(255) | NO | 'all' | |
| is_active | tinyint(1) | NO | 1 | boolean |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `voucher_profiles`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| name | varchar(255) | NO | — | |
| speed | varchar(255) | YES | NULL | |
| price | decimal(12,2) | NO | 0 | |
| time_limit | int | YES | NULL | Jam |
| quota_limit | bigint | YES | NULL | MB |
| validity_days | int | YES | NULL | Hari |
| shared_users | int | NO | 1 | |
| description | text | YES | NULL | |
| is_active | tinyint(1) | NO | 1 | boolean |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `voucher_templates`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| name | varchar(255) | NO | — | |
| content | text | YES | NULL | login.html |
| status_page | text | YES | NULL | |
| redirect_page | text | YES | NULL | |
| error_page | text | YES | NULL | |
| alive_page | text | YES | NULL | |
| logout_page | text | YES | NULL | |
| is_active | tinyint(1) | NO | 1 | boolean |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `vouchers`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| voucher_profile_id | bigint unsigned | YES | NULL | FK → voucher_profiles.id |
| voucher_template_id | bigint unsigned | YES | NULL | FK → voucher_templates.id |
| username | varchar(20) | NO | — | **UNIQUE** |
| password | varchar(20) | NO | — | |
| duration_hours | int | NO | — | |
| price | decimal(12,2) | YES | NULL | |
| prefix | varchar(255) | YES | NULL | |
| speed | varchar(255) | YES | NULL | |
| quota_limit | bigint | YES | NULL | MB |
| validity_days | int | YES | NULL | Hari |
| shared_users | int | NO | 1 | |
| printed_count | int | NO | 0 | |
| downloaded | bigint | NO | 0 | |
| uploaded | bigint | NO | 0 | |
| total_traffic | bigint | NO | 0 | |
| ip_address | varchar(255) | YES | NULL | |
| mac_address | varchar(255) | YES | NULL | |
| last_login_at | timestamp | YES | NULL | |
| router_id | bigint unsigned | YES | NULL | FK → mikrotik_routers.id |
| status | varchar(255) | NO | 'active' | active/used/expired |
| used_at | timestamp | YES | NULL | |
| expires_at | timestamp | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

---

## Activity (1 table)

### `activity_logs`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| user_id | bigint unsigned | YES | NULL | FK → users.id |
| action | varchar(255) | NO | — | |
| details | text | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

---

## Key-value (1 table)

### `settings`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| key | varchar(255) | NO | — | |
| value | text | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

---

## Legacy Tables (2 tables — data telah dimigrasi ke `odps`/`odp_ports`)

### `odp_routes`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| odc_id | bigint unsigned | YES | NULL | FK → odcs.id |
| name | varchar(255) | NO | — | |
| description | varchar(255) | YES | NULL | |
| color | varchar(7) | NO | — | Hex color |
| coordinates | json | NO | — | Array koordinat |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

### `odp_points`

| Kolom | Tipe | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| id | bigint unsigned | NO | auto_increment | Primary Key |
| tenant_id | bigint unsigned | YES | NULL | FK → tenants.id, **INDEX** |
| odp_route_id | bigint unsigned | NO | — | FK → odp_routes.id |
| name | varchar(255) | NO | — | |
| address | varchar(255) | YES | NULL | |
| latitude | decimal(10,7) | NO | — | |
| longitude | decimal(10,7) | NO | — | |
| status | varchar(255) | NO | 'active' | |
| port_capacity | int | NO | 8 | |
| port_used | int | NO | 0 | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

---

## Ringkasan

| Grup | Tabel | Jumlah |
|------|-------|--------|
| Core / System | tenants, users, sessions, password_reset_tokens, cache, jobs | 6 |
| Billing | packages, customers, invoices, payments | 4 |
| Infrastructure | olts, olt_ports, onus, odcs, odc_ports, odps, odp_ports | 7 |
| Voucher | mikrotik_routers, voucher_profiles, voucher_templates, vouchers | 4 |
| Activity | activity_logs | 1 |
| Key-value | settings | 1 |
| Legacy | odp_routes, odp_points | 2 |
| **Total** | | **25 + 3 Laravel (sessions, cache, password_reset_tokens)** = 28 |
