# Business Process — RabegNet ISP Billing System

---

## 1. Alur Pelanggan Baru

```
1. Admin create customer
   ├── Pilih paket internet
   ├── Isi data pelanggan (nama, telepon, lokasi, email)
   ├── Input PPPoE username
   ├── Pilih ODP & port (opsional saat create)
   └── Submit

2. System:
   ├── Customer.create (status: inactive)
   ├── Invoice.create (pertama, langsung jatuh tempo)
   └── ActivityLog.log('Create Customer')

3. Admin activate customer:
   ├── CustomerController@activate
   │   ├── MikrotikService.addPppSecret (create PPPoE di MikroTik)
   │   ├── Onu.create (auto-create ONU record)
   │   ├── Customer.update(status: 'active')
   │   └── ActivityLog.log('Activate Customer')
   └── Customer aktif, internet menyala
```

---

## 2. Alur Billing Bulanan

```
Schedule: setiap hari jam 08:00

1. billing:process command jalan
2. Loop setiap tenant:
   ├── Loop setiap customer active:
   │   ├── Cek: sudah ada invoice bulan ini?
   │   ├── Jika belum:
   │   │   ├── Invoice.create(amount = package.price)
   │   │   └── Dispatch SendWhatsAppNotification (WA reminder)
   │   └── Jika sudah: skip
   └── Selesai

3. WA reminder terkirim ke pelanggan
```

---

## 3. Alur Pembayaran

### Pembayaran Online (Midtrans)

```
1. Pelanggan buka portal /portal
2. Input nomor telepon
3. Lihat daftar tagihan unpaid
4. Klik "Bayar"
5. Redirect ke Midtrans (QRIS/VA/Convenience Store)
6. Pelanggan bayar di Midtrans
7. Midtrans callback POST /midtrans/notification
   ├── MidtransController@notification
   │   ├── Validasi signature
   │   ├── Invoice.update(payment_status='paid', paid_at=now)
   │   ├── Payment.create
   │   └── Jika customer suspended: auto-activate
   └── Selesai
```

### Pembayaran Manual (Cash/Transfer/QRIS)

```
1. Admin buka invoice → Klik "Catat Pembayaran"
2. Pilih metode (cash/transfer/qris)
3. Input jumlah
4. Submit
   ├── PaymentController@store
   │   ├── Payment.create
   │   ├── Invoice.update(payment_status, paid_at)
   │   ├── Jika total bayar >= amount: invoice lunas
   │   ├── Jika customer suspended: auto-activate
   │   └── ActivityLog.log('Create Payment')
   └── Selesai
```

---

## 4. Alur Aktivasi Layanan (Post-Isolir)

```
1. Customer suspended karena overdue
2. Customer bayar tagihan (online atau manual)
3. System auto-detect:
   ├── Cek: customer.status === 'suspended'
   ├── Jika ya: auto-activate
   │   ├── MikrotikService: remove PPP active → add PPP secret
   │   ├── Customer.update(status: 'active', suspended_at: null)
   │   └── ActivityLog.log('Auto Activate Customer')
   └── Selesai — internet menyala kembali
```

---

## 5. Alur Monitoring Jaringan

### Polling OLT (Hourly)

```
1. olt:poll command jalan
2. Dispatch PollOltJob per OLT aktif
3. Setiap job:
   ├── SSH ke OLT
   ├── Scan ONU per port (slot/port)
   ├── Update status ONU (online/offline)
   ├── Update Rx power
   ├── Deteksi ONU baru
   └── Log hasil

4. Jika scan result 0 ONU:
   └── Fallback: sync dari data PPPoE MikroTik
```

### Sync ONU dari MikroTik (Hourly)

```
1. customers:onu-sync command jalan
2. Ambil semua PPPoE active session dari MikroTik
3. Cocokkan dengan customer PPPoE username
4. Untuk setiap customer aktif tanpa ONU:
   └── Create ONU record
```

### Auto-Isolir (Daily 00:30)

```
1. customer:auto-isolir command jalan
2. Cari customer dengan:
   ├── status = 'active'
   └── invoice unpaid > grace_period (dari setting)
3. Untuk setiap customer:
   ├── MikroTik: set PPP Profile = "Isolir" (speed throttle/drop)
   ├── MikroTik: add IP ke firewall address-list
   ├── Customer.update(status: 'suspended', suspended_at: now)
   └── ActivityLog.log('Auto Isolir Customer')
```

### Sync IP Isolir ke Firewall (Every 5 Minutes)

```
1. customer:sync-isolir-ips command jalan
2. Ambil semua customer suspended
3. Sync IP list ke MikroTik firewall address-list
4. Hapus IP yang sudah active dari address-list
```
