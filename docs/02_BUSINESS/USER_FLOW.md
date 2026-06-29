# User Flow — RabegNet ISP Billing System

---

## Customer Flow

```
START: Menerima tagihan (WA/Email)
  │
  ├─ 1. Buka portal /portal
  ├─ 2. Input nomor telepon
  ├─ 3. Lihat daftar tagihan unpaid
  │
  ├─ [Pilih Bayar]
  │   ├─ Redirect ke Midtrans
  │   ├─ Bayar via QRIS/VA/Convenience Store
  │   └─ Konfirmasi pembayaran real-time
  │
  ├─ [Cek Voucher]
  │   ├─ Buka /vouchers/check
  │   ├─ Input username + password
  │   └─ Lihat status voucher
  │
  └─ [Beli Voucher]
      ├─ Buka /vouchers/public
      ├─ Pilih profile
      └─ Generate + dapatkan QR code
```

---

## Administrator Flow

```
START: Login ke dashboard
  │
  ├─ 1. Dashboard
  │   ├─ Lihat statistik (customer, revenue, unpaid)
  │   ├─ Lihat grafik (revenue 6 bulan, payment donut)
  │   ├─ Lihat aktivitas terbaru
  │   └─ Lihat map ODP
  │
  ├─ 2. Customer Management
  │   ├─ Tambah pelanggan baru
  │   │   ├─ Pilih paket
  │   │   ├─ Isi data
  │   │   ├─ Pilih ODP & port (opsional)
  │   │   └─ Submit → auto-create invoice pertama
  │   ├─ Aktivasi pelanggan
  │   │   └─ Push PPPoE ke MikroTik
  │   ├─ Edit pelanggan
  │   ├─ Suspend/Activate
  │   └─ Cari/filter pelanggan
  │
  ├─ 3. Billing
  │   ├─ Lihat daftar invoice (filter by status, date)
  │   ├─ Buat invoice manual
  │   ├─ Mark paid
  │   ├─ Print/PDF invoice
  │   ├─ Kirim WA/Email reminder
  │   └─ Mass billing
  │
  ├─ 4. Payment
  │   ├─ Catat pembayaran manual (cash/transfer/QRIS)
  │   ├─ Lihat history pembayaran
  │   └─ Hapus pembayaran
  │
  ├─ 5. OLT Management
  │   ├─ Tambah/edit OLT
  │   ├─ Test koneksi SSH
  │   ├─ Scan ONU
  │   ├─ Reboot/Remove ONU
  │   ├─ Monitoring (sort by Rx power)
  │   ├─ Map OLT
  │   └─ Export CSV
  │
  ├─ 6. MikroTik Management
  │   ├─ Dashboard (system health, uptime)
  │   ├─ Hotspot profiles & users
  │   ├─ PPP secrets & active sessions
  │   ├─ Simple queues
  │   ├─ Bandwidth monitoring
  │   └─ Backup
  │
  ├─ 7. Voucher
  │   ├─ Generate voucher (pilih profile, count, router)
  │   ├─ Print batch
  │   ├─ Sync status ke MikroTik
  │   └─ Report voucher
  │
  ├─ 8. Distribution
  │   ├─ Map interaktif ODC/ODP
  │   ├─ CRUD ODC
  │   ├─ CRUD ODP & port
  │   └─ Detail ODC/ODP dengan port grid
  │
  ├─ 9. Reports
  │   ├─ Revenue bulanan
  │   ├─ Outstanding total
  │   ├─ Chart 12 bulan
  │   └─ Top unpaid customers
  │
  ├─ 10. Settings
  │   ├─ Company info
  │   ├─ Bank account
  │   ├─ Midtrans config
  │   ├─ MikroTik config
  │   └─ Fonnte token
  │
  └─ 11. Backup & Export
      ├─ Download backup database
      ├─ Export CSV invoices
      └─ Export CSV payments

END: Logout
```

---

## Teknisi Flow

```
START: Login ke dashboard
  │
  ├─ 1. Dashboard (ringkasan)
  │
  ├─ 2. OLT Monitoring
  │   ├─ Lihat daftar OLT
  │   ├─ Detail OLT → scan ONU
  │   ├─ Cek Rx power → sort weakest signal
  │   ├─ Reboot ONU customer
  │   └─ Link ONU ke customer
  │
  ├─ 3. OLT Monitoring Page
  │   └─ Semua ONU dengan redaman, dari sinyal terlemah
  │
  ├─ 4. MikroTik (read-only)
  │   ├─ Cek active sessions
  │   ├─ Cek PPP active
  │   └─ Disconnect session (jika perlu)
  │
  ├─ 5. Distribution (read-only)
  │   ├─ Map interaktif ODC/ODP
  │   └─ Detail ODC/ODP
  │
  ├─ 6. Customer
  │   ├─ Cari customer
  │   ├─ Lihat detail (ODP, port, invoice)
  │   └─ Edit customer (jika perlu)
  │
  ├─ 7. Voucher (read-only)
  │   ├─ Lihat daftar voucher
  │   └─ Cek status
  │
  └─ 8. Logs
      └─ Lihat activity log

END: Logout
```

---

## Owner/Management Flow

```
START: Login sebagai admin
  │
  ├─ 1. Dashboard
  │   ├─ Total customer & growth
  │   ├─ Revenue bulan ini
  │   ├─ Outstanding (piutang)
  │   └─ Grafik tren
  │
  ├─ 2. Reports
  │   ├─ Revenue per bulan
  │   ├─ Outstanding per periode
  │   ├─ Top unpaid
  │   ├─ Chart 12 bulan
  │   └─ Payment method breakdown
  │
  └─ 3. Export
      └─ Export data ke CSV

END: Logout
```
