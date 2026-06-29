# Coding Standard — RabegNet ISP Billing System

> Berdasarkan Laravel Pint (default rules) — tidak ada `pint.json` lokal.

---

## Naming Convention

| Elemen | Convention | Contoh |
|--------|------------|--------|
| **Namespace** | PSR-4, PascalCase | `App\Models`, `App\Http\Controllers` |
| **Class** | PascalCase | `CustomerController`, `MikrotikService` |
| **Method** | camelCase | `generateAndPush()`, `assignOdpPort()` |
| **Variable** | camelCase | `$dueDate`, `$paidAt`, `$filterStatus` |
| **Property** | snake_case (protected) | `protected $host;` |
| **Table** | snake_case, plural | `customers`, `odc_ports`, `activity_logs` |
| **Column** | snake_case | `nama_odc`, `kabel_tube_color`, `connected_to_odp_id` |
| **Model** | Singular, PascalCase | `Customer`, `Invoice`, `OdcPort` |
| **Controller** | PascalCase + `Controller` suffix | `CustomerController`, `OltController` |
| **Migration** | `YYYY_MM_DD_HHMMSS_create_{table}_table` | `2026_06_22_010000_create_odps_table.php` |
| **Route** | snake_case, dot notation | `customers.index`, `invoice.paid` |
| **View** | snake_case, kebab file | `customer/index.blade.php`, `odc/show.blade.php` |

---

## Route Convention

| Method | URI Pattern | Middleware | Contoh |
|--------|-------------|-----------|--------|
| GET | `/resource` | auth + teknisi | `/customers` |
| GET | `/resource/create` | auth + admin | `/vouchers/create` |
| POST | `/resource` | auth + admin | `/vouchers` |
| GET | `/resource/{id}/edit` | auth + teknisi | `/customer/{id}/edit` |
| PUT | `/resource/{id}` | auth + teknisi | `/customer/{id}` |
| DELETE | `/resource/{id}` | auth + admin | `/voucher/{id}` |
| GET | `/resource/{id}/action` | auth + sesuai role | `/invoice/paid/{id}` |

### Route Grouping

```php
// Public — no middleware
Route::get('/portal', [PortalController::class, 'index']);

// Teknisi & Admin (authenticated + role: teknisi/admin)
Route::middleware(['auth', 'teknisi'])->group(function () {
    Route::get('/customers', [CustomerController::class, 'index']);
    // ...
});

// Admin only (authenticated + role: admin)
Route::middleware(['auth', 'teknisi', 'admin'])->group(function () {
    Route::post('/vouchers', [VoucherController::class, 'store']);
    // ...
});
```

---

## Controller Convention

- **Resource controller** untuk CRUD standar (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`)
- **Private method** untuk logic yang digunakan >1 method dalam satu controller
- **Service class** untuk business logic kompleks yang dipakai lintas controller
- **Validasi** inline di method controller (tidak pakai Form Request terpisah)

```php
class VoucherController extends Controller
{
    public function store(Request $request)
    {
        // validation inline
        $validated = $request->validate([...]);
        
        // delegation to private method
        $vouchers = $this->generateAndPush($validated);
        
        return redirect()->route('vouchers.index');
    }
    
    private function generateAndPush(array $data): Collection
    {
        // shared logic for store() and quickPrint()
    }
}
```

---

## View Convention

- **Layout:** `layouts.app` sebagai base template (Bootstrap 5.3 + sidebar)
- **Section:** `@section('title')`, `@section('content')`, `@push('scripts')`
- **CSS:** Bootstrap classes + custom CSS di `resources/css/app.css`
- **JavaScript** di `@push('scripts')` — wrapped dalam `DOMContentLoaded`
- **Formatting:** Laravel Blade `{{ }}` untuk escaping

```blade
@extends('layouts.app')

@section('title', 'Daftar Customer')

@section('content')
    <!-- content here -->
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // JS here
});
</script>
@endpush
```

---

## Database Convention

- **Timestamps:** `created_at`, `updated_at` otomatis (Laravel default)
- **Soft deletes:** Tidak digunakan — hard delete + activity log
- **Foreign keys:** `{table}_id` (e.g., `customer_id`, `package_id`)
- **Enum:** VARCHAR + validasi di controller/model (bukan enum MySQL)
- **Index:** Untuk kolom yang sering di-query (tenant_id, status, dll)

---

## Code Style (Laravel Pint)

Default Laravel rules — no custom `pint.json`.

```bash
./vendor/bin/pint         # check & fix all files
./vendor/bin/pint --test  # dry run
```
