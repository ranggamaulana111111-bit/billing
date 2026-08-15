# Testing — ALKONEK ISP Billing System

---

## Framework

- **PHPUnit 11** + Mockery
- **Database:** SQLite `:memory:` (no external DB needed — see `phpunit.xml`)
- **RefreshDatabase:** Dipakai **eksplisit** per class (bukan default) — 5 dari 7 Feature class + 1 Unit class (`CustomerCodeGeneratorTest`)

---

## Test Structure

```
tests/
├── Feature/           # 7 classes, 54 methods
│   ├── AuthTest.php
│   ├── CustomerTest.php
│   ├── DistributionTest.php
│   ├── ExampleTest.php
│   ├── InvoiceTest.php
│   ├── PackageTest.php
│   └── SitemapTest.php
│
└── Unit/              # 10 classes, 88 methods
    ├── ExampleTest.php
    └── Services/
        ├── Billing/
        │   ├── CustomerCodeGeneratorTest.php
        │   └── InvoiceGeneratorTest.php
        ├── GenieACS/
        │   ├── GenieACSClientTest.php
        │   ├── GenieacsDTOTest.php
        │   └── GenieacsRepositoryTest.php
        ├── Olt/
        │   └── ChineseOltParserTest.php
        └── Payment/
            ├── MidtransGatewayTest.php
            ├── PaymentServiceTest.php
            └── XenditGatewayTest.php
```

---

## Coverage

### Feature (7 classes, 54 methods)

| Test Class | Methods | Coverage |
|------------|---------|----------|
| `AuthTest` | 9 | Login, register, logout, dashboard redirect, ODP data |
| `CustomerTest` | 10 | CRUD, suspend, activate, validation, auto-create invoice |
| `DistributionTest` | 17 | ODC/Route/Point CRUD + cascade protection |
| `InvoiceTest` | 7 | CRUD, mark paid, print, destroy |
| `PackageTest` | 8 | CRUD, search, status filter, destroy protection |
| `SitemapTest` | 2 | Sitemap XML |
| `ExampleTest` (Feature) | 1 | Basic response |

### Unit (10 classes, 88 methods)

| Test Class | Methods | Coverage |
|------------|---------|----------|
| `ExampleTest` (Unit) | 1 | Basic assertion |
| `CustomerCodeGeneratorTest` | 5 | Generator kode pelanggan |
| `InvoiceGeneratorTest` | 7 | Generator kode invoice (`INV-YYYYMMDD-...`) |
| `GenieACSClientTest` | 23 | Client HTTP TR-069 (parameter, reboot, provisioning) |
| `GenieacsDTOTest` | 10 | Data transfer object GenieACS |
| `GenieacsRepositoryTest` | 12 | Repository GenieACS |
| `ChineseOltParserTest` | 8 | Parser output OLT CData/Hioso (multi-brand) |
| `MidtransGatewayTest` | 6 | Implementasi `PaymentGatewayInterface` (Midtrans) |
| `PaymentServiceTest` | 6 | Orchestrator payment gateway |
| `XenditGatewayTest` | 10 | Implementasi `PaymentGatewayInterface` (Xendit) |

**Total:** 142 test methods di 17 file (7 Feature + 10 Unit)

---

## ⚠️ Known Issue — Suite Saat Ini RED

Suite belum green: **142 tests → ±88 errors + 3 failures**. Penyebab (bukan regresi dari pekerjaan aktif):

1. **Migrasi `2026_06_22_000004_add_tenant_id_to_business_tables.php`** memakai `DROP CONSTRAINT IF EXISTS` (sintaks MySQL/Postgres) yang **gagal di SQLite** → semua test `RefreshDatabase` error `near "CONSTRAINT"`.
2. **Unit test yang menyentuh DB tanpa `RefreshDatabase`** (`MidtransGatewayTest`, `PaymentServiceTest`, `XenditGatewayTest`) → error `no such table`.
3. **`InvoiceGeneratorTest::test_uses_current_date_when_no_period_given`** mengharap format `INV-YYYYMM-` padahal `InvoiceGenerator::generate()` menghasilkan `INV-YYYYMMDD-` (pakai `now()->format('Ymd')`).

Jangan panik & jangan salahkan kode yang sedang dikerjakan ketika melihat error ini.

---

## Running Tests

```bash
# Via Artisan (pakai full path PHP Laragon)
C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe artisan test

# Focused test
C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe artisan test --filter=CustomerTest

# Single suite
C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe artisan test --testsuite=Unit

# Direct PHPUnit
C:\laragon\bin\php\php-8.3.31-Win32-vs16-x64 (1)\php.exe vendor/bin/phpunit
```

---

## Key Testing Patterns

### RefreshDatabase (eksplisit per class)
```php
use RefreshDatabase;

public function test_create_customer(): void
{
    $response = $this->actingAs($user)->post('/customer', [...]);
    $response->assertRedirect();
    $this->assertDatabaseHas('customers', ['name' => 'Test']);
}
```

### HTTP Tests
```php
// Auth
$response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);
$response->assertRedirect('/dashboard');

// CRUD
$response = $this->actingAs($user)->get('/customers');
$response->assertStatus(200);

// Protection
$response = $this->actingAs($user)->delete("/packages/{$package->id}");
$response->assertSessionHasErrors();
```

### Cascade Protection (Distribution)
```php
// ODC with ODP routes cannot be deleted
$response = $this->actingAs($user)->delete("/distribution/odcs/{$odc->id}");
$response->assertSessionHasErrors();

// ODP Route with points cannot be deleted
$response = $this->actingAs($user)->delete("/distribution/routes/{$route->id}");
$response->assertSessionHasErrors();
```
