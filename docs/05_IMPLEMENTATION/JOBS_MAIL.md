# Jobs & Mail — RabegNet ISP Billing System

> 2 Jobs | 2 Mail | Queue + Notifikasi

---

## Jobs

### PollOltJob (`app/Jobs/PollOltJob.php`)

| Key | Value |
|-----|-------|
| Queue | `default` (database) |
| Timeout | 60 detik |
| Retry | 3x |
| Trigger | Scheduler `olt:poll` (hourly) |
| Constructor | `Olt $olt` |

**Flow `handle()`:**
```
handle()
├── scanFromOlt()
│   ├── Test koneksi SSH (fsockopen)
│   ├── OltConnectorFactory::make(brand, olt)
│   ├── connector->connect()
│   ├── Loop setiap port → getOnuList() + getOpticalPower()
│   ├── Onu::updateOrCreate() setiap ONU
│   └── connector->disconnect()
│
├── Jika scan = 0 ONU → fallback syncFromMikrotik()
│   ├── MikrotikService::getPppActive()
│   ├── Loop session → cari Customer by pppoe_username
│   └── Onu::updateOrCreate()
│
├── runRca() → RCA Analysis
│   ├── Cari ONU offline dalam 2 jam terakhir
│   ├── Group by ODP
│   ├── Jika >80% port offline → tandai DOWN_LINK_FAILURE
│   ├── Update ODP kondisi_jalur = 'DOWN_LINK_FAILURE'
│   ├── Set ODP Ports → 'broken'
│   └── notifyTechnician() via Fonnte WA API
│
└── update last_polled_at
```

**RCA Threshold:**
- ODP dengan >80% port offline → kabel distribusi putus
- Notifikasi WA dikirim ke `notif_phone_teknisikoordinator` atau `admin_phone`

### SendWhatsAppNotification (`app/Jobs/SendWhatsAppNotification.php`)

| Key | Value |
|-----|-------|
| Queue | `default` (database) |
| Timeout | 30 detik |
| Retry | 3x |
| Trigger | Scheduler `billing:process` |

| Parameter | Type | Deskripsi |
|-----------|------|-----------|
| `$userId` | `int` | User ID untuk lookup Fonnte token |
| `$phone` | `string` | Nomor tujuan |
| `$message` | `string` | Pesan teks |

**Flow:**
```
handle()
├── Setting::get('fonnte_token', null, userId)
├── POST https://api.fonnte.com/send
│   ├── headers: Authorization = token
│   ├── body: target, message, countryCode=62
└── (no return check — fire and forget)
```

---

## Mail

### InvoiceReminder (`app/Mail/InvoiceReminder.php`)

| Key | Value |
|-----|-------|
| Queueable | Yes |
| Constructor | `Invoice $invoice` |
| Subject | `Reminder Pembayaran - {invoice_code}` |
| View | `emails.invoice-reminder` |
| Data | `$invoice`, `$settings` (all settings as array) |

**Trigger:** `InvoiceController@sendEmail` / `billing:process`

### PaymentConfirmation (`app/Mail/PaymentConfirmation.php`)

| Key | Value |
|-----|-------|
| Queueable | Yes |
| Constructor | `Invoice $invoice` |
| Subject | `Pembayaran Diterima - {invoice_code}` |
| View | `emails.payment-confirmation` |
| Data | `$invoice`, `$settings` (all settings as array) |

**Trigger:** `InvoiceController@markPaid` / `billing:process`

---

## Queue Configuration

| Item | Config |
|------|--------|
| Connection | `database` (MySQL `jobs` table) |
| Failed jobs | `failed_jobs` table |
| Worker | `php artisan queue:work --queue=default` |
| Restart | `php artisan queue:restart` setelah deploy |
