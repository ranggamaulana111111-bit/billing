<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageTest extends TestCase
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
        $this->get('/packages')->assertRedirect('/login');
    }

    public function test_index_page_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/packages')->assertStatus(200);
    }

    public function test_store_creates_package(): void
    {
        $response = $this->actingAs($this->user)->post('/packages', [
            'name' => 'Paket 20Mbps',
            'speed' => 20,
            'price' => 200000,
            'description' => 'Unlimited rumahan',
            'billing_cycle' => 'monthly',
            'mikrotik_profile' => '20M',
            'is_active' => true,
        ]);

        $response->assertRedirect('/packages');
        $this->assertDatabaseHas('packages', [
            'name' => 'Paket 20Mbps',
            'speed' => 20,
            'price' => 200000,
            'description' => 'Unlimited rumahan',
            'billing_cycle' => 'monthly',
            'mikrotik_profile' => '20M',
            'is_active' => true,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/packages', [])->assertSessionHasErrors(['name', 'speed', 'price']);
    }

    public function test_update_package(): void
    {
        $package = Package::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->user)->put("/packages/{$package->id}", [
            'name' => 'New Name',
            'speed' => 50,
            'price' => 300000,
            'billing_cycle' => 'weekly',
            'is_active' => false,
        ])->assertRedirect('/packages');

        $this->assertDatabaseHas('packages', ['name' => 'New Name', 'billing_cycle' => 'weekly', 'is_active' => false]);
    }

    public function test_index_can_search_and_filter_packages(): void
    {
        Package::factory()->create(['name' => 'Home Fiber 10', 'is_active' => true]);
        Package::factory()->create(['name' => 'Legacy Radio', 'is_active' => false]);

        $this->actingAs($this->user)
            ->get('/packages?search=Home&status=active')
            ->assertStatus(200)
            ->assertSee('Home Fiber 10')
            ->assertDontSee('Legacy Radio');
    }

    public function test_destroy_package(): void
    {
        $package = Package::factory()->create();

        $this->actingAs($this->user)->delete("/packages/{$package->id}")->assertRedirect('/packages');
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
    }

    public function test_destroy_package_in_use_is_blocked(): void
    {
        $package = Package::factory()->create();
        Customer::factory()->create(['package_id' => $package->id]);

        $this->actingAs($this->user)
            ->delete("/packages/{$package->id}")
            ->assertRedirect('/packages')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('packages', ['id' => $package->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Gagal Hapus Paket']);
    }
}
