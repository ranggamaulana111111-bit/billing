# Prompts — AI Agent Guide for RabegNet

> Kumpulan prompt template untuk AI Agent mengerjakan task di RabegNet.

---

## Memahami Kodebase

```
Baca dan pahami struktur folder docs/00_PROJECT/DESCRIPTION.md untuk 
memahami gambaran umum proyek RabegNet.
Gunakan docs/01_ARCHITECTURE/*.md untuk memahami arsitektur.
Gunakan docs/02_BUSINESS/*.md untuk memahami business rules.
```

---

## Menambahkan Fitur Baru

```
Saya ingin menambahkan fitur [nama fitur] ke RabegNet.
Tujuan: [deskripsi singkat]

Langkah:
1. Pahami struktur existing di docs/02_BUSINESS/MODULES.md
2. Ikuti coding standard di docs/03_DEVELOPMENT/CODING_STANDARD.md
3. Buat migration jika perlu tabel baru
4. Buat model, controller, view
5. Daftarkan route
6. Tulis test
7. Format dengan pint
```

---

## Debugging Issue

```
Saya mengalami issue di [modul/halaman].
Gejala: [deskripsi error/perilaku]
Error log: [paste error]

Langkah:
1. Cek log di storage/logs/laravel.log
2. Cek activity log di database
3. Cek relasi model di app/Models/
4. Cek controller logic
```

---

## Code Review

```
Lakukan code review untuk file berikut:
[file path]

Fokus:
- Keamanan (SQL injection, XSS, data leak)
- Performance (N+1 query, loop)
- Coding standard (docs/03_DEVELOPMENT/CODING_STANDARD.md)
- Error handling
- Business rules (docs/02_BUSINESS/BUSINESS_RULES.md)
```

---

## Membuat Migration

```
Buat migration untuk [deskripsi]:
- Tabel: [nama tabel]
- Kolom: [daftar kolom]
- Relasi: [foreign keys]
- Unique: [constraints]
```

---

## Menambahkan API Endpoint

```
Buat API endpoint baru:
- Method: GET/POST/PUT/DELETE
- URI: /api/v1/[path]
- Fungsi: [deskripsi]
- Request: [parameter]
- Response: [format JSON]
- Auth: none/auth/admin
```
