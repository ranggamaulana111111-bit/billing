# Business Rules — RabegNet ISP Billing System

---

| # | Rule | Deskripsi | Implementasi |
|---|------|-----------|--------------|
| BR-01 | **Satu pelanggan, satu layanan aktif** | Setiap customer memiliki satu paket aktif. Ganti paket = update data customer | `Customer.package_id` — update langsung, tidak ada histori |
| BR-02 | **Invoice auto-generate** | Invoice dibuat otomatis setiap bulan untuk customer aktif | Console command `billing:process` — dailyAt('08:00') |
| BR-03 | **Invoice terkait customer** | Setiap invoice memiliki `customer_id`; amount diambil dari `package.price` | Foreign key + invoice.amount copy dari package.price saat create |
| BR-04 | **Invoice pertama auto-create** | Saat customer baru dibuat, invoice pertama langsung terbit | `CustomerController@store` → `Invoice::create()` |
| BR-05 | **Pembayaran hanya untuk invoice valid** | Payment hanya bisa dicatat untuk invoice yang exist | Validasi foreign key + controller validation |
| BR-06 | **Pembayaran update invoice** | Setiap payment, invoice status auto-update. Jika total >= amount, invoice = paid | `PaymentController@store` → update invoice payment_status & paid_at |
| BR-07 | **Pembayaran auto-activate** | Jika customer suspended dan bayar → auto-activate | Cek di `PaymentController` → `CustomerController@activate` |
| BR-08 | **Status layanan** | `active` = bisa akses internet. `suspended` = isolir. `inactive` = non-aktif | Enum kolom `status` di customers |
| BR-09 | **Isolir otomatis** | Customer dengan invoice unpaid > grace period akan auto-suspended jam 00:30 | `customer:auto-isolir` command + setting `grace_days` |
| BR-10 | **ODP port unique** | Satu ODP port hanya untuk satu customer | Unique constraint di `customers.odp_port_id` |
| BR-11 | **ODP port release** | Saat customer di-delete atau ganti ODP, port lama balik ke 'available' | `CustomerController@destroy` dan `@update` |
| BR-12 | **Voucher sekali pakai** | Voucher dengan status 'active' bisa dipakai. Setelah login hotspot → status jadi 'used' | Event-driven API callback `POST /api/v1/mikrotik/hotspot-login` |
| BR-13 | **Voucher expired otomatis** | Voucher yang lewat `expires_at` auto-status 'expired' | `Voucher::where('expires_at','<',now())->update(['status'=>'expired'])` |
| BR-14 | **Voucher push ke MikroTik** | Setiap generate voucher, langsung push hotspot user ke MikroTik | `generateAndPush()` → `MikrotikService::addHotspotUser()` |
| BR-15 | **Multi-tenant data isolation** | Setiap tenant hanya bisa melihat data sendiri | Global scope `BelongsToTenant::addGlobalScope()` |
| BR-16 | **Role-based access** | Admin bisa semua. Teknisi read-only untuk beberapa modul | Middleware `IsAdmin` + `IsTeknisiOrAdmin` |
| BR-17 | **Denda keterlambatan** | Jika setting late_fee diaktifkan, invoice lewat due_date + grace_days akan kena denda | Setting key: `late_fee_amount`, `grace_days` |
| BR-18 | **Proteksi delete** | Paket dengan customer tidak bisa dihapus. ODC dengan ODP tidak bisa dihapus. Route dengan point tidak bisa dihapus | Validasi count related records di controller |
| BR-19 | **OLT password encrypted** | Password OLT di-encrypt di database (berbeda dengan MikroTik) | `protected $casts = ['password' => 'encrypted']` |
| BR-20 | **Paket non-aktif** | Paket dengan `is_active = false` tidak muncul di dropdown customer | Scope `where('is_active', true)` di form |
