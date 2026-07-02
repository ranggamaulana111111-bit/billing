# Models — RabegNet ISP Billing System

> 19 Models + 2 Traits | `App\Models\` namespace

---

## Traits

### BelongsToTenant (`app/Models/Traits/BelongsToTenant.php`)

| Aspek | Detail |
|-------|--------|
| Fungsi | Global scope `WHERE tenant_id = ?`, auto-fill saat create |
| Boot | `bootBelongsToTenant()` — registered via `addGlobalScope` |
| Scope | `forTenant($id)`, `allTenants()` (without global scope) |
| Relasi | `tenant()`: `belongsTo(Tenant::class)` |
| Model | 15 model menggunakannya (lihat tabel di bawah) |

**Tidak menggunakan:** `OdcPort`, `OdpPort`, `Tenant` (root), `User` (Authenticatable)

### BelongsToUser (`app/Models/Traits/BelongsToUser.php`)

**Dead code.** Legacy trait — superseded by `BelongsToTenant`. Masih ada tapi tidak dipakai.

---

## Models

### Tenant

| Key | Value |
|-----|-------|
| Traits | — (root model, no trait) |
| `$fillable` | `name`, `address`, `phone`, `email`, `logo` |
| `$casts` | — |
| Relationships | `users()`, `customers()`, `invoices()`, `payments()`, `packages()`, `vouchers()`, `odcs()`, `odpRoutes()`, `odpPoints()`, `settings()`, `activityLogs()` — semua `HasMany` |

### User

| Key | Value |
|-----|-------|
| Traits | `HasFactory`, `Notifiable` |
| `$fillable` | `tenant_id`, `name`, `email`, `password`, `provider`, `provider_id`, `avatar`, `role` |
| `$hidden` | `password`, `remember_token` |
| `$casts` | `email_verified_at` → `datetime`, `password` → `hashed` |
| Relationships | `tenant()`: `BelongsTo(Tenant)`, `customers()`/`invoices()`/etc (hasMany through tenant_id) |

### Customer

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant`, `HasFactory` |
| `$fillable` | `tenant_id`, `name`, `location`, `phone`, `email`, `package_id`, `odp_point_id`, `odp_id`, `odp_port_id`, `pppoe_username`, `original_ppp_profile`, `due_date`, `status`, `suspended_at` |
| `$casts` | — |
| Relationships | `package()`: `belongsTo(Package)`, `odp()`: `belongsTo(Odp)`, `odpPort()`: `belongsTo(OdpPort)`, `invoices()`: `hasMany(Invoice)`, `onus()`: `hasMany(Onu)` |

### Package

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant`, `HasFactory` |
| `$fillable` | `tenant_id`, `name`, `speed`, `description`, `price`, `billing_cycle`, `mikrotik_profile`, `is_active` |
| `$casts` | `price` → `decimal:2`, `is_active` → `boolean` |
| Relationships | `customers()`: `hasMany(Customer)` |

### Invoice

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant`, `HasFactory` |
| `$fillable` | `tenant_id`, `invoice_code`, `customer_id`, `amount`, `payment_status`, `billing_period`, `paid_at`, `payment_method`, `midtrans_order_id` |
| `$casts` | `paid_at` → `datetime`, `amount` → `decimal:2` |
| Relationships | `customer()`: `belongsTo(Customer)`, `payments()`: `hasMany(Payment)` |

### Payment

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `invoice_id`, `amount`, `payment_method`, `payment_date`, `notes` |
| `$casts` | `payment_date` → `date`, `amount` → `decimal:2` |
| Relationships | `invoice()`: `belongsTo(Invoice)` |

### Olt

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `name`, `brand`, `model`, `ip_address`, `ssh_port`, `username`, `password`, `jump_host`, `jump_port`, `jump_username`, `jump_password`, `snmp_community`, `snmp_version`, `snmp_port`, `location`, `latitude`, `longitude`, `status`, `notes`, `last_polled_at` |
| `$casts` | `password`/`jump_password` → `encrypted`, `last_polled_at` → `datetime`, `latitude`/`longitude` → `decimal:7` |
| Methods | `hasJumpHost()`: bool, `usesMikrotikProxy()`: bool |
| Relationships | `ports()`: `hasMany(OltPort)` |

### OltPort

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `olt_id`, `slot_number`, `port_number`, `port_type`, `status`, `description` |
| `$casts` | — |
| Relationships | `olt()`: `belongsTo(Olt)`, `onus()`: `hasMany(Onu)` |

### Onu

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `olt_port_id`, `customer_id`, `onu_id`, `serial_number`, `caller_id`, `vendor`, `model`, `mac_address`, `status`, `rx_power`, `tx_power`, `distance`, `uptime`, `slot_number`, `port_number`, `notes`, `last_seen_at` |
| `$casts` | `last_seen_at` → `datetime`, `uptime` → `integer`, `rx_power`/`tx_power` → `float`, `distance` → `integer` |
| Relationships | `oltPort()`: `belongsTo(OltPort)`, `customer()`: `belongsTo(Customer)` |

### Odc

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `nama_odc`, `koordinat`, `kapasitas_port` |
| `$appends` | `name`, `capacity`, `latitude`, `longitude` |
| Accessors | `getNameAttribute` → `nama_odc`, `getCapacityAttribute` → `kapasitas_port`, `getLatitudeAttribute`/`getLongitudeAttribute` → parse `koordinat` (comma-separated) |
| Relationships | `routes()`: `hasMany(OdpRoute)`, `ports()`: `hasMany(OdcPort)`, `odps()`: `hasMany(Odp)` |

### OdcPort

| Key | Value |
|-----|-------|
| Traits | ⚠️ **TIDAK menggunakan BelongsToTenant** — potensi data leak |
| `$fillable` | `odc_id`, `port_number`, `port_type`, `status`, `connected_to_odp_id` |
| `$casts` | — |
| Relationships | `odc()`: `belongsTo(Odc)`, `connectedOdp()`: `belongsTo(Odp, 'connected_to_odp_id')` |

### Odp

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `odc_id`, `nama_odp`, `koordinat`, `kapasitas_port`, `kabel_tube_color`, `kabel_core_number`, `kondisi_jalur` |
| `$appends` | `name`, `address`, `latitude`, `longitude` |
| Accessors | `getNameAttribute` → `nama_odp`, `getAddressAttribute` → `koordinat`, `getLatitudeAttribute`/`getLongitudeAttribute` → parse `koordinat` |
| Methods | `availablePortsCount()`, `usedPortsCount()`, `brokenPortsCount()` |
| Relationships | `odc()`: `belongsTo(Odc)`, `connectedOdcPort()`: `hasOne(OdcPort)`, `ports()`: `hasMany(OdpPort)`, `customers()`: `hasMany(Customer)` |

### OdpPort

| Key | Value |
|-----|-------|
| Traits | ⚠️ **TIDAK menggunakan BelongsToTenant** — potensi data leak |
| `$fillable` | `odp_id`, `port_number`, `status` |
| `$casts` | — |
| Relationships | `odp()`: `belongsTo(Odp)`, `customer()`: `hasOne(Customer, 'odp_port_id')` |

### OdpRoute (Legacy)

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `odc_id`, `name`, `description`, `color`, `coordinates` |
| `$casts` | `coordinates` → `array` (JSON) |
| Relationships | `points()`: `hasMany(OdpPoint)`, `odc()`: `belongsTo(Odc)` |

### OdpPoint (Legacy)

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `odp_route_id`, `name`, `address`, `latitude`, `longitude`, `status`, `port_capacity`, `port_used` |
| `$casts` | — |
| Relationships | `route()`: `belongsTo(OdpRoute)`, `customers()`: `hasMany(Customer, 'odp_point_id')` |

### MikrotikRouter

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `name`, `host`, `port`, `username`, `password`, `hotspot_server`, `is_active` |
| `$casts` | `is_active` → `boolean` |
| ⚠️ | `password` disimpan **plaintext** (tidak di-encrypt) |
| Relationships | `vouchers()`: `hasMany(Voucher, 'router_id')` |

### Voucher

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant`, `HasFactory` |
| `$fillable` | `tenant_id`, `voucher_profile_id`, `voucher_template_id`, `username`, `password`, `duration_hours`, `price`, `prefix`, `speed`, `quota_limit`, `validity_days`, `shared_users`, `printed_count`, `downloaded`, `uploaded`, `total_traffic`, `ip_address`, `mac_address`, `last_login_at`, `router_id`, `status`, `used_at`, `expires_at` |
| `$casts` | `expires_at`/`used_at`/`last_login_at` → `datetime`, `price` → `decimal:2` |
| Static | `generate(int $durationHours, int $count = 1, ?array $extra = null): array` — generate voucher dengan unique username |
| Relationships | `profile()`: `belongsTo(VoucherProfile)`, `router()`: `belongsTo(MikrotikRouter)`, `template()`: `belongsTo(VoucherTemplate)` |

### VoucherProfile

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `name`, `speed`, `price`, `time_limit`, `quota_limit`, `validity_days`, `shared_users`, `description`, `is_active` |
| `$casts` | `price` → `decimal:2`, `is_active` → `boolean` |
| Relationships | `vouchers()`: `hasMany(Voucher, 'voucher_profile_id')` |

### VoucherTemplate

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `name`, `content`, `status_page`, `redirect_page`, `error_page`, `alive_page`, `logout_page`, `is_active` |
| `$casts` | `is_active` → `boolean` |
| Methods | `getPage(string $type): ?string` — ambil konten halaman hotspot, `writeFiles()` — tulis ke public/hotspot/ (tidak dipakai otomatis di Vercel) |
| Relationships | `vouchers()`: `hasMany(Voucher, 'voucher_template_id')` |

### ActivityLog

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `user_id`, `action`, `details` |
| `$casts` | — |
| Static | `log(string $action, ?string $details = null): self` — create log dengan user_id dari Auth |
| Relationships | `user()`: `belongsTo(User)` |

### Setting

| Key | Value |
|-----|-------|
| Traits | `BelongsToTenant` |
| `$fillable` | `tenant_id`, `key`, `value` |
| `$casts` | — |
| Static | `get(string $key, ?string $default, ?int $tenantId): ?string` |
| Static | `set(string $key, ?string $value, ?int $tenantId): void` — `updateOrCreate` |
| Static | `getByUser(int $userId, string $key, ?string $default): ?string` |

---

## Relasi Matrix

| Model | Tenant | User | Customer | Package | Invoice | Payment | Olt | OltPort | Onu | Odc | Odp | OdcPort | OdpPort | Route | Point | Router | Voucher | VProfile | VTemplate | Activity |
|-------|--------|------|----------|---------|---------|---------|-----|---------|-----|-----|-----|---------|---------|-------|-------|--------|---------|----------|-----------|----------|
| **Tenant** | — | HM | HM | HM | HM | HM | — | — | — | HM | — | — | — | HM | HM | — | HM | — | — | HM |
| **User** | BT | — | HM* | HM* | HM* | HM* | — | — | — | HM* | — | — | — | HM* | HM* | — | HM* | — | — | HM* |
| **Customer** | — | — | — | BT | HM | — | — | — | HM | — | BT | — | BT | — | — | — | — | — | — | — |
| **Invoice** | — | — | BT | — | — | HM | — | — | — | — | — | — | — | — | — | — | — | — | — | — |
| **Payment** | — | — | — | — | BT | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — |
| **Olt** | — | — | — | — | — | — | — | HM | — | — | — | — | — | — | — | — | — | — | — | — |
| **OltPort** | — | — | — | — | — | — | BT | — | HM | — | — | — | — | — | — | — | — | — | — | — |
| **Onu** | — | — | BT | — | — | — | — | BT | — | — | — | — | — | — | — | — | — | — | — | — |
| **Odc** | — | — | — | — | — | — | — | — | — | — | HM | HM | — | HM | — | — | — | — | — | — |
| **Odp** | — | — | HM | — | — | — | — | — | — | BT | — | — | HM | — | — | — | — | — | — | — |
| **OdcPort** | — | — | — | — | — | — | — | — | — | BT | — | — | — | — | — | — | — | — | — | — |
| **OdpPort** | — | — | HO | — | — | — | — | — | — | — | BT | — | — | — | — | — | — | — | — | — |
| **OdpRoute** | — | — | — | — | — | — | — | — | — | BT | — | — | — | — | HM | — | — | — | — | — |
| **OdpPoint** | — | — | HM | — | — | — | — | — | — | — | — | — | — | BT | — | — | — | — | — | — |
| **ActivityLog** | — | BT | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — | — |

**Legend:** BT = BelongsTo, HM = HasMany, HO = HasOne, HM* = through tenant_id, — = no direct relationship
