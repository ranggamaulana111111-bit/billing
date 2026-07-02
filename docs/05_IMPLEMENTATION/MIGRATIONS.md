# Migrations — RabegNet ISP Billing System

> 48 Files | Kronologi perubahan skema database

---

## Format Migrasi

Daftar migrasi diurutkan berdasarkan timestamp. Kolom `Aksi` menunjukkan jenis perubahan.

| Aksi | Keterangan |
|------|------------|
| CREATE | Membuat tabel baru |
| ALTER | Mengubah struktur tabel |
| INDEX | Menambah index |
| DATA | Migrasi data antar tabel |

---

## Fase 1: Laravel Default (0001_01_01)

| # | File | Aksi | Tabel | Detail |
|---|------|------|-------|--------|
| 1 | `0001_01_01_000000_create_users_table` | CREATE | users, password_reset_tokens, sessions | Users standard Laravel |
| 2 | `0001_01_01_000001_create_cache_table` | CREATE | cache | Cache driver database |
| 3 | `0001_01_01_000002_create_jobs_table` | CREATE | jobs | Queue driver database |

## Fase 2: Distribusi Awal (2025_01)

| # | File | Aksi | Tabel | Detail |
|---|------|------|-------|--------|
| 4 | `2025_01_01_000001_create_odp_routes_table` | CREATE | odp_routes | Jalur fiber optik (legacy) |
| 5 | `2025_01_01_000002_create_odp_points_table` | CREATE | odp_points | Titik ODP di peta (legacy) |

## Fase 3: Billing & Customer (2026_06_09 — batch 1)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 6 | `2026_06_09_105114_create_packages_table` | CREATE | packages | name, speed, price (3 kolom awal) |
| 7 | `2026_06_09_105115_create_customers_table` | CREATE | customers | name, location, package_id, odp_point_id, pppoe_username, due_date |
| 8 | `2026_06_09_105116_create_invoices_table` | CREATE | invoices | invoice_code (unique), customer_id, amount, payment_status |
| 9 | `2026_06_09_105117_create_activity_logs_table` | CREATE | activity_logs | action, details |
| 10 | `2026_06_09_111448_add_phone_to_customers_table` | ALTER | customers +phone | Tambah kolom phone |
| 11 | `2026_06_09_114445_create_settings_table` | CREATE | settings | key (unique), value |
| 12 | `2026_06_09_120252_create_vouchers_table` | CREATE | vouchers | username (unique), password, duration_hours, status, used_at, expires_at |
| 13 | `2026_06_09_124543_add_status_to_customers` | ALTER | customers +status +suspended_at | Tambah status (enum: active/suspended/inactive), suspended_at |
| 14 | `2026_06_09_124544_create_payments_table` | CREATE | payments | invoice_id, amount, payment_method, payment_date, notes |
| | | ALTER | invoices +paid_at +payment_method | Tambah kolom paid_at, payment_method |
| 15 | `2026_06_09_133137_add_user_id_to_activity_logs_table` | ALTER | activity_logs +user_id | Tambah FK ke users |
| 16 | `2026_06_09_135109_add_email_to_customers_table` | ALTER | customers +email | Tambah kolom email |
| 17 | `2026_06_09_140152_add_midtrans_order_id_to_invoices_table` | ALTER | invoices +midtrans_order_id | Tambah kolom midtrans_order_id |
| 18 | `2026_06_09_141000_add_details_to_packages_table` | ALTER | packages +description +billing_cycle +mikrotik_profile +is_active | Perluasan kolom |

## Fase 4: ODC & Distribusi (2026_06_09 — batch 2)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 19 | `2026_06_09_142000_create_odcs_table_and_link_odp_routes` | CREATE | odcs | name (unique), address, lat, lng, status, capacity, notes |
| | | ALTER | odp_routes +odc_id | Tambah FK ke odcs |

## Fase 5: Multi-user (2026_06_12)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 20 | `2026_06_12_000001_add_user_id_to_business_tables` | ALTER | customers, packages, invoices, payments, vouchers, odcs, odp_routes, odp_points, settings, activity_logs, olts | Tambah user_id + index |
| 21 | `2026_06_12_162858_add_socialite_fields_to_users_table` | ALTER | users +provider +provider_id +avatar | OAuth support, password nullable, unique(provider, provider_id) |

## Fase 6: OLT & RBAC (2026_06_14)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 22 | `2026_06_14_000001_create_olts_table` | CREATE | olts | user_id, name, brand, model, ip, ssh, snmp, location, status, last_polled_at |
| 23 | `2026_06_14_000002_create_olt_ports_table` | CREATE | olt_ports | olt_id, slot_number, port_number, port_type, status, unique(slot, port) |
| 24 | `2026_06_14_000003_create_onus_table` | CREATE | onus | olt_port_id, customer_id, onu_id, sn, vendor, status, rx/tx power, distance, uptime |
| 25 | `2026_06_14_000004_add_role_to_users_table` | ALTER | users +role | Tambah kolom role (default 'teknisi') |

## Fase 7: ODC Route Fix & Paket (2026_06_15)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 26 | `2026_06_15_231106_add_odc_id_to_odp_routes_table` | ALTER | odp_routes +odc_id | Tambah FK odc_id (duplikat safety) |
| 27 | `2026_06_15_235017_add_details_to_packages_table` | ALTER | packages | (duplikat safety — cek exist) |
| 28 | `2026_06_15_235419_add_phone_and_email_to_customers_table` | ALTER | customers | (duplikat safety — cek exist) |
| 29 | `2026_06_15_235922_fix_missing_columns` | ALTER | customers +status +suspended_at (conditional) | Fix kolom yang mungkin belum ada |
| | | ALTER | users +provider +provider_id +avatar (conditional) | Fix kolom yang mungkin belum ada |

## Fase 8: Voucher System (2026_06_16)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 30 | `2026_06_16_003400_create_mikrotik_routers_table` | CREATE | mikrotik_routers | user_id, name, host, port, username, password (plaintext), hotspot_server, is_active |
| 31 | `2026_06_16_003415_create_voucher_profiles_table` | CREATE | voucher_profiles | user_id, name, speed, price, time_limit, quota_limit, validity_days, shared_users |
| | | ALTER | vouchers +voucher_profile_id +price +prefix +speed +quota_limit +validity_days +shared_users +printed_count +downloaded +uploaded +total_traffic +ip_address +mac_address +last_login_at +router_id | Perluasan kolom vouchers |
| 32 | `2026_06_16_010000_create_voucher_templates_table` | CREATE | voucher_templates | user_id, name, content, is_active |
| | | ALTER | vouchers +voucher_template_id | Tambah FK ke voucher_templates |
| 33 | `2026_06_16_012824_add_logout_page_to_voucher_templates` | ALTER | voucher_templates +logout_page | Tambah halaman hotspot |
| 34 | `2026_06_16_020000_add_hotspot_pages_to_voucher_templates` | ALTER | voucher_templates +status_page +redirect_page +error_page +alive_page | 4 halaman hotspot |
| 35 | `2026_06_16_190645_add_jump_host_to_olts_table` | ALTER | olts +jump_host +jump_port +jump_username +jump_password | SSH tunnel |

## Fase 9: Indexes (2026_06_22 — batch 1)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 36 | `2026_06_22_000001_add_indexes_to_business_tables` | INDEX | customers, packages, invoices, payments, vouchers, odcs, odp_routes, odp_points, settings, olts, activity_logs, mikrotik_routers, voucher_profiles, voucher_templates | Tambah index user_id |
| 37 | `2026_06_22_000002_add_user_id_to_olt_ports_and_onus` | ALTER | olt_ports +user_id, onus +user_id | Tambah FK + index + backfill data |

## Fase 10: Multi-Tenancy (2026_06_22 — batch 2)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 38 | `2026_06_22_000003_create_tenants_table` | CREATE | tenants | Root multi-tenancy |
| | | ALTER | users +tenant_id | Tambah FK ke tenants, backfill default tenant |
| 39 | `2026_06_22_000004_add_tenant_id_to_business_tables` | ALTER | 15 tabel business | Tambah tenant_id, copy dari user_id, drop user_id |
| | | | activity_logs | Khusus: keep user_id untuk audit trail |
| 40 | `2026_06_22_000005_add_original_ppp_profile_to_customers_table` | ALTER | customers +original_ppp_profile | Untuk restore profil PPP setelah isolir |

## Fase 11: ODP Refactor (2026_06_22 — batch 3)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 41 | `2026_06_22_010000_create_odps_table` | CREATE | odps | tenant_id, odc_id, nama_odp, koordinat, kapasitas_port, kabel_tube_color, kabel_core_number, kondisi_jalur |
| 42 | `2026_06_22_020000_create_odc_ports_table` | CREATE | odc_ports | odc_id, port_number, port_type, status, connected_to_odp_id |
| 43 | `2026_06_22_030000_create_odp_ports_table` | CREATE | odp_ports | odp_id, port_number, status |
| 44 | `2026_06_22_040000_alter_odcs_table` | ALTER | odcs | Rename name→nama_odc, capacity→kapasitas_port, drop address/lat/lng/status/notes, add koordinat |
| 45 | `2026_06_22_050000_add_odp_to_customers_table` | ALTER | customers +odp_id +odp_port_id | Tambah relasi ke tabel odps/odp_ports yang baru |
| 46 | `2026_06_22_060000_migrate_odp_data` | DATA | odp_points → odps + odp_ports | Migrasi data legacy ke tabel baru, assign port ke customer |

## Fase 12: Tambahan (2026_06_27, 2026_06_30)

| # | File | Aksi | Tabel/Kolom | Detail |
|---|------|------|-------------|--------|
| 47 | `2026_06_27_000001_add_caller_id_to_onus_table` | ALTER | onus +caller_id | Caller-ID dari PPPoE session |
| 48 | `2026_06_30_093143_add_billing_period_to_invoices_table` | ALTER | invoices +billing_period | char(7) format YYYY-MM, backfill dari created_at |

---

## Ringkasan Timeline

| Fase | Tanggal | Jumlah Migrasi | Fokus |
|------|---------|----------------|-------|
| 1 | 0001-01-01 | 3 | Laravel default |
| 2 | 2025-01 | 2 | Distribusi awal (legacy) |
| 3 | 2026-06-09 (batch 1) | 13 | Billing & customer |
| 4 | 2026-06-09 (batch 2) | 1 | ODC |
| 5 | 2026-06-12 | 2 | Multi-user |
| 6 | 2026-06-14 | 4 | OLT & RBAC |
| 7 | 2026-06-15 | 4 | Fix & safety |
| 8 | 2026-06-16 | 6 | Voucher system |
| 9 | 2026-06-22 (batch 1) | 2 | Indexes |
| 10 | 2026-06-22 (batch 2) | 3 | Multi-tenancy |
| 11 | 2026-06-22 (batch 3) | 6 | ODP refactor |
| 12 | 2026-06-27/30 | 2 | Tambahan |
| | **Total** | **48** | |
