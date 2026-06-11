<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/customers')->assertRedirect('/login');
    }

    public function test_index_page_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/customers')->assertStatus(200);
    }

    public function test_create_page_is_accessible(): void
    {
        Package::factory()->create();
        $this->actingAs($this->user)->get('/customer/create')->assertStatus(200);
    }

    public function test_store_creates_customer_and_invoice(): void
    {
        $package = Package::factory()->create(['price' => 150000]);

        $response = $this->actingAs($this->user)->post('/customer', [
            'name' => 'Budi Santoso',
            'phone' => '08123456789',
            'location' => 'Kp. Kumpay',
            'package_id' => $package->id,
        ]);

        $response->assertRedirect('/customers');
        $this->assertDatabaseHas('customers', ['name' => 'Budi Santoso', 'phone' => '08123456789']);
        $this->assertDatabaseHas('invoices', ['amount' => 150000]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/customer', [])->assertSessionHasErrors(['name', 'phone', 'package_id']);
    }

    public function test_edit_page_is_accessible(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($this->user)->get("/customer/{$customer->id}/edit")->assertStatus(200);
    }

    public function test_update_customer(): void
    {
        $customer = Customer::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->user)->put("/customer/{$customer->id}", [
            'name' => 'New Name',
            'phone' => $customer->phone,
            'package_id' => $customer->package_id,
        ])->assertRedirect('/customers');

        $this->assertDatabaseHas('customers', ['name' => 'New Name']);
    }

    public function test_destroy_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->user)->delete("/customer/{$customer->id}")->assertRedirect('/customers');
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    public function test_suspend_customer(): void
    {
        $customer = Customer::factory()->create(['status' => 'active']);

        $this->actingAs($this->user)->post("/customer/{$customer->id}/suspend")->assertRedirect();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'suspended']);
    }

    public function test_activate_customer(): void
    {
        $customer = Customer::factory()->create(['status' => 'suspended']);

        $this->actingAs($this->user)->post("/customer/{$customer->id}/activate")->assertRedirect();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'active']);
    }
}
