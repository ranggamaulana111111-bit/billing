# UI Guideline — RabegNet ISP Billing System

---

## CSS Framework

- **Utama:** Bootstrap 5.3
- **Custom:** `resources/css/app.css` (~1570 baris)
- **Tailwind CSS v4:** Di-import tapi TIDAK digunakan

---

## Design Tokens (Custom Properties)

Semua custom properties didefinisikan di `:root` dalam `app.css`:

```css
:root {
    --primary: #2563eb;        /* Biru utama */
    --primary-dark: #1d4ed8;   /* Biru hover */
    --secondary: #64748b;      /* Abu-abu */
    --success: #22c55e;        /* Hijau success */
    --danger: #ef4444;         /* Merah danger */
    --warning: #eab308;        /* Kuning warning */
    --bg-body: #f8fafc;        /* Background body */
    --card-shadow: 0 1px 3px rgba(0,0,0,0.06);
    --border-radius: 16px;     /* Border radius cards */
}
```

---

## Komponen

### Cards
```blade
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white">
        <!-- judul -->
    </div>
    <div class="card-body">
        <!-- konten -->
    </div>
</div>
```

### Stat Cards (Dashboard)
```blade
<div class="stat-card">
    <div class="stat-icon" style="background:rgba(34,197,94,0.15);color:#22c55e;">
        <i class="fa-solid fa-plug"></i>
    </div>
    <div class="stat-info">
        <div class="stat-value">42</div>
        <div class="stat-label">Label</div>
    </div>
</div>
```

### Port Grid (ODC/ODP)
```blade
<div class="port-grid">
    <div class="port-item port-available">
        <span class="port-number">1</span>
    </div>
    <div class="port-item port-used">
        <span class="port-number">2</span>
        <span class="port-customer-name">Budi</span>
    </div>
    <div class="port-item port-broken">
        <span class="port-number">3</span>
        <span class="port-broken-label">RUSAK</span>
    </div>
</div>
```

### Page Header
```blade
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="fa-solid fa-icon me-2"></i>Title</h2>
        <p class="section-subtitle mb-0 mt-1">Subtitle</p>
    </div>
    <div class="page-actions d-flex gap-2">
        <!-- action buttons -->
    </div>
</div>
```

---

## Warna Status

| Status | Warna | Background |
|--------|-------|------------|
| Available | Hijau (`#16a34a`) | `#f0fdf4` + `border: #86efac` |
| Used | Merah (`#dc2626`) | `#fef2f2` + `border: #fca5a5` |
| Broken | Abu gelap (`#94a3b8`) | `#1e293b` + animasi blink |

---

## Icons

Menggunakan **Font Awesome 6** (via CDN atau Bootstrap Icons fallback).

---

## Responsive

Bootstrap 5.3 grid system:
- `col-md-3` untuk 4 kolom stat cards
- `col-md-4` untuk 3 kolom
- `table-responsive` untuk tabel
- `port-grid` dengan `grid-template-columns: repeat(auto-fill, minmax(130px, 1fr))`

---

## JavaScript

- **Alpine.js** — untuk interaktivitas ringan (modal, toggle)
- **Chart.js** — untuk grafik (revenue bar, donut payment)
- **Leaflet.js** — untuk peta interaktif (ODC/ODP/OLT)
- **Bootstrap JS** — untuk komponen Bootstrap (tooltip, modal)
- **Vanilla JS** — untuk polling realtime (fetch + setInterval)
- Semua script di `@push('scripts')` — wrapped dalam `DOMContentLoaded`
