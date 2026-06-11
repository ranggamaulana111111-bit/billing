<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
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
        $this->get('/invoices')->assertRedirect('/login');
    }

    public function test_index_page_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/invoices')->assertStatus(200);
    }

    public function test_create_page_is_accessible(): void
    {
        Package::factory()->create();
        $this->actingAs($this->user)->get('/invoices/create')->assertStatus(200);
    }

    public function test_store_creates_invoice(): void
    {
        $package = Package::factory()->create(['price' => 200000]);
        $customer = Customer::factory()->create(['package_id' => $package->id]);

        $response = $this->actingAs($this->user)->post('/invoices', [
            'customer_id' => $customer->id,
            'amount' => 200000,
        ]);

        $response->assertRedirect('/invoices');
        $this->assertDatabaseHas('invoices', ['customer_id' => $customer->id, 'amount' => 200000]);
    }

    public function test_mark_invoice_as_paid(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->user)->get("/invoice/paid/{$invoice->id}")->assertRedirect();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'payment_status' => 'paid']);
    }

    public function test_destroy_invoice(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->user)->delete("/invoice/{$invoice->id}")->assertRedirect('/invoices');
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_print_page_is_accessible(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->user)->get("/invoice/print/{$invoice->id}")->assertStatus(200);
    }
}
