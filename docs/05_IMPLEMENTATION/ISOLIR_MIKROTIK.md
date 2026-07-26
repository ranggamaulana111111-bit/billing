# Panduan Isolir & Redirect Landing Page (MikroTik)

Sistem isolir otomatis akan memutus akses internet pelanggan yang menunggak, lalu
**me-redirect semua traffic HTTP/HTTPS mereka ke halaman landing page isolir** (`/isolir`)
yang berisi info tagihan & tombol konfirmasi WhatsApp.

## Alur End-to-End

```
Pelanggan telat bayar
   │  (cron customer:auto-isolir @ 00:30)
   ▼
Customer.status = 'suspended'
   │  + set PPP secret → Profile-Isolir (auto address-list: isolir-users)
   │  + tambahkan IP PPP aktif ke firewall address-list isolir-users
   │  + disconnect sesi PPP (force reconnect pakai profile isolir)
   ▼
MikroTik: address-list "isolir-users" terisi IP pelanggan
   │  DST-NAT rule: src=isolir-users, dst-port 80/443 → IP_SERVER:80
   ▼
Pelanggan buka web apa pun → diarahkan ke http://IP_SERVER/isolir
   │  IsolirController::byIp() deteksi customer dari IP PPP aktif
   ▼
Tampil halaman isolir (template editor) + tombol WA admin
```

Cron pendukung:
- `customer:auto-isolir` — `dailyAt('00:30')` : isolir pelanggan overdue.
- `customer:sync-isolir-ips` — `everyFiveMinutes()` : sync ulang IP suspended → address-list
  (jaga-jaga kalau pelanggan reconnect / IP berubah).

## 1. Set IP Server Landing Page

Buka **Settings → tab Isolir**, isi field **IP Server Landing Page Isolir** dengan
IP publik server e-billing (tempat Laravel jalan, port 80). Mis. `103.xy.zw.ab`.

> IP ini wajib diisi. Tanpa ini MikroTik tidak tahu ke mana me-redirect traffic pelanggan.

## 2. Terapkan Konfigurasi ke MikroTik

Masih di tab Isolir, klik tombol **Terapkan Konfigurasi ke MikroTik**.
Di balik layar menjalankan `php artisan mikrotik:setup-isolir --redirect-ip=IP_SERVER`.

Atau via CLI (jalankan rutin saat setup router baru):
```powershell
& "C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe" artisan mikrotik:setup-isolir --redirect-ip=103.x.x.x
```

Command akan membuat di MikroTik:
1. **PPP Profile** `Profile-Isolir` (auto masukkan ke address-list `isolir-users`).
2. **Filter DROP** `BLOCK-ISOLIR` — blokir semua traffic selain redirect.
3. **Filter ACCEPT** `ISOLIR-ACCEPT-REDIRECT` — izinkan traffic ke server landing page.
4. **NAT DST-NAT** `isolir-http-redirect-80` → redirect port 80 ke `IP_SERVER:80`.
5. **NAT DST-NAT** `isolir-http-redirect-443` → redirect port 443 ke `IP_SERVER:80`
   (port 443 di-redirect ke 80 karena landing page hanya HTTP statis; browser akan
   menampilkan halaman tanpa SSL error fatal pada redirect).

Untuk mencabut (jika salah konfigurasi):
```powershell
& "C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe" artisan mikrotik:setup-isolir --remove
```

## 3. Verifikasi di MikroTik (WinBox/CLI)

- **PPP → Profiles**: ada `Profile-Isolir` dengan `Address List = isolir-users`.
- **IP → Firewall → NAT**: rule `isolir-http-redirect-80` & `isolir-http-redirect-443`
  (chain=dstnat, src-address-list=isolir-users, action=dst-nat,
  to-addresses=IP_SERVER, to-ports=80).
- **IP → Firewall → Filter Rules**: `ISOLIR-ACCEPT-REDIRECT` (accept) di atas
  `BLOCK-ISOLIR` (drop), chain=forward, src-address-list=isolir-users.
- **IP → Firewall → Address Lists**: `isolir-users` terisi IP pelanggan suspended
  (diisi otomatis oleh `customer:sync-isolir-ips`).

## 4. Uji Coba

1. Suspended-kan satu customer lewat menu Customer (atau tunggu cron auto-isolir).
2. Pelanggan reconnect PPPoE → otomatis pakai `Profile-Isolir`.
3. Buka browser di perangkat pelanggan → otomatis diarahkan ke halaman isolir.
4. Klik tombol WhatsApp → chat ke admin untuk konfirmasi bayar.
5. Setelah lunas, set Customer status = `active` & kembalikan PPP profile → redirect lepas.

## Catatan Teknis / Troubleshooting

- Landing page di-render oleh `IsolirController::byIp()` yang membaca IP client, lalu
  mencocokan dengan sesi PPP aktif (`getPppActive()`) untuk menemukan customer.
  Jika IP tidak ditemukan, tampil halaman "Akses Dibatasi" generik.
- `SyncIsolirIps` hanya sync customer yang punya sesi PPP **aktif**. Pastikan pelanggan
  sudah reconnect setelah diisolir agar IP masuk address-list.
- HTTPS (443) di-redirect ke port 80 server. Pastikan web server Laravel listen di port 80
  dan route `/isolir` dapat diakses publik (tanpa auth).
- Jika tunnel MikroTik mati (cURL error 6/7/28), setting `mikrotik_host` bisa diganti
  sementara ke IP langsung router.
