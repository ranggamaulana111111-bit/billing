# Panduan Link ONU GenieACS → Map FTTH

> Workflow agar ONU dari GenieACS ter-link ke tabel `onus` dan status **ACS Aktif** muncul
> di peta FTTH (FeaturesController map). Berlaku untuk pelanggan **hotspot** (bukan PPPoE).

## Alur End-to-End

```
OLT scan (olt:poll)
   │  PollOltJob::scanFromOlt → populate tabel onus
   │  (Onu::updateOrCreate per ONT, isi serial_number dari OLT fisik)
   ▼
tabel onus terisi (serial_number match dengan SN fisik ONU)
   │
Sync Device GenieACS (FeaturesController::genieacsSync)
   │  Baca daftar device dari GenieACS API
   │  Resolve serial: _deviceId._SerialNumber → TR-069 path → fallback _id suffix
   │  Match ke onus.serial_number (case-insensitive, trim)
   ▼
Onu.acs_device_id / acs_status / acs_last_inform ter-update
   │
Map FTTH: onu.acs_device_id ada → tampil "ACS Aktif" (map.blade.php)
```

## Command / Langkah

| Step | Command/Panel | Fungsi |
|---|---|---|
| 1 | `php artisan olt:poll` | Dispatch job scan OLT per device aktif |
| 2 | `php artisan queue:work --once` | Proses job poll (SSH ke OLT, scan ONT all port) |
| 3 | Cek tabel: `Onu::count()` | Pastikan record ONU + serial_number terisi |
| 4 | **Sync Device (GenieACS)** di panel / `FeaturesController::genieacsSync` | Match serial ke GenieACS, isi `acs_*` fields |

## Catatan Penting

- **`customers:onu-sync` HANYA untuk PPPoE** — filter `whereNotNull('pppoe_username')`.
  Pelanggan **hotspot tidak di-sync** oleh command ini; mereka masuk lewat scan OLT (`olt:poll`).
- **Serial fallback** (FeaturesController.php ~1506): jika `_deviceId._SerialNumber` & TR-069 path kosong,
  ambil bagian paling belakang `_id` GenieACS setelah `explode('-')` (mis. `3CFB5C-XPON88-FHTT5C1F9710` → `FHTT5C1F9710`).
- ONU yang belum punya `customer_id` tetap tampil di map (cust=N/A) selama ada di tabel `onus`.
- Sync GenieACS update field: `acs_device_id`, `acs_status`, `acs_last_inform`,
  `acs_manufacturer`, `acs_product_class`, `acs_software_version`, `acs_connection_request_url`.
- OLT pakai **single default port** bila belum ada `OltPort` (slot 0/port 1, C-Data).

## Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| "ACS Tidak Terdeteksi" di map | `onus.acs_device_id` kosong (tabel `onus` belum ada / belum di-sync) | Jalankan `olt:poll` lalu Sync GenieACS |
| `Class "App\Models\olt" not found` | Import lowercase di `SyncCustomerOnu.php` | Fix ke `use App\Models\Olt;` |
| Tabel `onus` 0 record | Scan OLT belum pernah jalan | `olt:poll` + `queue:work --once` |
| PollOltJob FAIL | OLT offline / SSH unreachable / command OLT beda | Cek `connection_status` OLT, koneksi SSH |
| C-Data `% Unknown command` untuk distance | Command OLT belum didukung | Aman — optical tetap di-parse (non-fatal) |