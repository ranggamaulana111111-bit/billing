# Testing — RabegNet ISP Billing System

---

## Framework

- **PHPUnit 11** + Mockery
- **Database:** SQLite `:memory:` (no external DB needed)
- **RefreshDatabase:** Digunakan oleh 5 dari 7 feature test classes

---

## Test Structure

```
tests/
├── Feature/           # 7 classes, 49 methods
│   ├── AuthTest.php
│   ├── CustomerTest.php
│   ├── DistributionTest.php
│   ├── ExampleTest.php
│   ├── InvoiceTest.php
│   ├── PackageTest.php
│   └── SitemapTest.php
│
└── Unit/              # 1 class, 1 method
    └── ExampleTest.php
```

---

## Coverage

| Test Class | Methods | Coverage |
|------------|---------|----------|
| `AuthTest` | 9 | Login, register, logout, dashboard redirect, ODP data |
| `CustomerTest` | 10 | CRUD, suspend, activate, validation, auto-create invoice |
| `DistributionTest` | 17 | ODC/Route/Point CRUD + cascade protection |
| `InvoiceTest` | 7 | CRUD, mark paid, print, destroy |
| `PackageTest` | 8 | CRUD, search, status filter, destroy protection |
| `SitemapTest` | 2 | Sitemap XML |
| `ExampleTest` (Feature) | 1 | Basic response |
| `ExampleTest` (Unit) | 1 | Basic assertion |

**Total:** 55 test methods, 135 assertions ✅

---

## Running Tests

```bash
# Full test suite
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe vendor/bin/phpunit

# Via Artisan
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan test

# Focused test
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan test --filter=CustomerTest

# Single suite
C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe artisan test --testsuite=Unit
```

---

## Key Testing Patterns

### RefreshDatabase (5 classes)
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
