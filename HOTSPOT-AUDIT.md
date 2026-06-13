# Hotspot / Voucher — Audit & Action Plan

> **Versi:** 1.0  
> **Dibuat:** 14 Juni 2026  
> **Tujuan:** Daftar semua yang sudah jadi, kurang, dan bermasalah pada fitur voucher & hotspot login

---

## A. ✅ Yang Sudah Jadi

| Fitur | Detail |
|-------|--------|
| Generate voucher | Username 8 char + Password 6 char (bisa diatur di Settings) |
| CRUD voucher | Index, create, mark used, delete |
| Print individual | Layout premium branded bisa print |
| Print batch | Pilih checkbox → cetak banyak sekaligus |
| Quick print | Generate + langsung print tanpa simpan |
| Push ke MikroTik | Create/quick-print auto push hotspot user ke MikroTik |
| Sync MikroTik | Sinkron status voucher dari router (cek active session) |
| Schedule auto-sync | `voucher:sync-mikrotik` tiap 5 menit |
| Settings panjang kredensial | Bisa ubah 4-20 char username & password |
| Log aktivitas | Setiap generate, hapus, mark used, sync tercatat |
| Role-based | Bisa diakses admin & teknisi |

---

## B. ❌ Yang Kurang

| # | Item | Prioritas | Detail |
|---|------|-----------|--------|
| 1 | **Halaman login hotspot branded** | 🔴 **HIGH** | 6 file HTML statis untuk MikroTik captive portal: `login.html`, `status.html`, `error.html`, `logout.html`, `alive.html`, `redirect.html` — harus self-contained (inline CSS), disimpan di `public/hotspot/`, diupload ke MikroTik via SCP/FTP |
| 2 | **Tidak ada kolom `price`** | 🟡 MEDIUM | Voucher dijual ke pelanggan tapi tidak ada harga — tidak bisa laporan penjualan voucher. Perlu migration + update form create + index view + export |
| 3 | **Tidak bisa pilih profile/server hotspot** | 🟡 MEDIUM | `store()` hardcode server `'all'` — padahal MikroTik bisa punya multiple hotspot server. Perlu dropdown di form create |
| 4 | **Tidak ada export CSV voucher** | 🟢 LOW | Export tagihan & pembayaran sudah ada, voucher belum. Tambah method export di VoucherController + route + tombol di index |
| 5 | **Tidak ada filter/sort di index** | 🟢 LOW | Sort by `expires_at`, filter by durasi, search lebih advanced (cari juga berdasarkan status) |
| 6 | **Halaman cek voucher publik** | 🟢 LOW | Seperti `/portal` untuk tagihan — pelanggan cek validitas voucher tanpa login ke sistem |

---

## C. 🔴 Yang Rusak / Bermasalah

| # | Item | Severity | Detail |
|---|------|----------|--------|
| 1 | **VoucherFactory pakai `duration_minutes`** | 🔴 **ERROR** | `database/factories/VoucherFactory.php` line 17: `'duration_minutes' => ...` — kolom tabel sebenarnya `duration_hours`. Akan error `MassAssignmentException` kalau factory dipakai (`duration_minutes` tidak ada di `$fillable`) |
| 2 | **`user_id` di tabel vouchers — KONFIRMASI AMAN** | ✅ **AMAN** | Migration `2026_06_12_000001_add_user_id_to_business_tables.php` (line 27-29) sudah include `vouchers`. Kolom `user_id` ada di production (Aiven MySQL). Di local SQLite, tabel `vouchers` belum di-migrate — jalanin `php artisan migrate` |
| 3 | **Expired auto-handle boros query** | 🟢 **LOW** | Setiap akses `index()` menjalankan `Voucher::where('status', 'active')->where('expires_at', '<', now())->update(...)`. Sebaiknya dipindah ke scheduled command `voucher:expire` biar tidak dieksekusi tiap request |

---

## D. Prioritas Eksekusi

```
Phase 1 — Fix Critical
├── 🔴 Perbaiki VoucherFactory (duration_minutes → duration_hours)
├── 🟡 Cek & perbaiki user_id migration untuk vouchers
└── ✅ Run migration + test

Phase 2 — Hotspot Pages
├── 🔴 Buat 6 file HTML statis di public/hotspot/
│   ├── login.html       → Form input username + password
│   ├── status.html      → Login berhasil + sisa waktu
│   ├── error.html       → Kredensial salah
│   ├── logout.html      → Logout sukses
│   ├── alive.html       → 1x1 pixel tracking (wajib MikroTik)
│   └── redirect.html    → Redirect URL asal
├── 🔴 Buat instruksi upload ke MikroTik

Phase 3 — Enhancements
├── 🟡 Tambah kolom price + migration
├── 🟡 Tambah profile/server selector di form create
├── 🟢 Tambah export CSV voucher
├── 🟢 Tambah sort/filter di index
└── 🟢 Halaman cek voucher publik

Phase 4 — Refactor
├── 🟢 Pindah expired auto-handle ke scheduled command
└── 🟢 Bersihkan code duplicate (print-batch vs quick-print)
```

---

## E. Lampiran: Spesifikasi 6 File Hotspot Statis

Semua file harus:
- **Self-contained** — tidak ada external CSS/JS (inline semua)
- **Branded RabegNet** — logo, warna biru/indigo (#2563eb, #6366f1)
- **Font** — system-ui / sans-serif (tidak boleh external Google Fonts)
- **Ukuran** — < 20 KB per file
- **Method** — POST ke `http://<mikrotik-ip>/hotspot/login` (standar MikroTik)

| File | Fungsi | Elemen Wajib |
|------|--------|-------------|
| `login.html` | Halaman utama saat connect WiFi | Form username + password, tombol submit, logo, "Powered by RabegNet" |
| `status.html` | Setelah login sukses | "Selamat datang {username}", sisa waktu/durasi, tombol logout, IP address |
| `error.html` | Kredensial salah | Pesan error, "Coba lagi", link balik ke login |
| `logout.html` | Setelah klik logout | "Anda telah logout", "Terima kasih", link untuk login lagi |
| `alive.html` | Tracking MikroTik | 1x1 transparan pixel (format: `data:image/gif;base64,...`) |
| `redirect.html` | Redirect ke URL asal | JS redirect ke `$(link-orig)` atau meta refresh |

---

## F. Catatan Teknis

| # | Item | Detail |
|---|------|--------|
| 1 | **Route publik** | Halaman `/voucher-login` harus di luar middleware auth |
| 2 | **CSS inline** | File hotspot harus self-contained biar gampang di-SCP ke MikroTik |
| 3 | **SCP ke MikroTik** | `scp public/hotspot/* admin@10.0.0.1:/hotspot/` atau via Files menu di WinBox |
| 4 | **Konfigurasi MikroTik** | `/ip hotspot walled-garden` + `/ip hotspot profile` set `html-directory` |
| 5 | **User ID migration cek** | ✅ SUDAH DI-FIX. `user_id` ditambahkan ke `vouchers` via script. Aman. |

---

## G. Status Fix Lokal (SQLite)

| # | Item | Sebelum | Sesudah |
|---|------|---------|---------|
| 1 | Tabel `vouchers` di SQLite | ❌ Hilang (migration record ada, tabel tidak) | ✅ Dibikin ulang via `artisan migrate` |
| 2 | Kolom `user_id` di `vouchers` | ❌ Tidak ada | ✅ Ditambahkan manual via Schema Builder |
| 3 | Factory `duration_minutes` | ❌ Masih salah | ⏳ Belum diperbaiki |
| 4 | Tests | ✅ 53 pass | ✅ 53 pass
