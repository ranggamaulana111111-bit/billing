<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Odc;
use App\Models\OdpPoint;
use App\Models\OdpRoute;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistributionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->admin()->create();
    }

    public function test_index_requires_auth(): void
    {
        $this->get('/distribution')->assertRedirect('/login');
    }

    public function test_index_page_is_accessible(): void
    {
        $this->actingAs($this->user)->get('/distribution')->assertStatus(200);
    }

    public function test_store_odc(): void
    {
        $this->actingAs($this->user)->post('/distribution/odcs', $this->odcPayload([
            'name' => 'ODC Barat',
        ]))->assertRedirect();

        $this->assertDatabaseHas('odcs', ['name' => 'ODC Barat', 'status' => 'active']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Tambah ODC']);
    }

    public function test_store_odc_rejects_duplicate_name(): void
    {
        $this->createOdc();

        $this->actingAs($this->user)
            ->post('/distribution/odcs', $this->odcPayload(['name' => 'ODC Test']))
            ->assertSessionHasErrors('name');
    }

    public function test_update_odc(): void
    {
        $odc = $this->createOdc();

        $this->actingAs($this->user)->put("/distribution/odcs/{$odc->id}", $this->odcPayload([
            'name' => 'ODC Update',
            'status' => 'maintenance',
            'capacity' => 64,
        ]))->assertRedirect();

        $this->assertDatabaseHas('odcs', ['id' => $odc->id, 'name' => 'ODC Update', 'status' => 'maintenance', 'capacity' => 64]);
    }

    public function test_destroy_odc_without_routes(): void
    {
        $odc = $this->createOdc();

        $this->actingAs($this->user)->delete("/distribution/odcs/{$odc->id}")->assertRedirect();

        $this->assertDatabaseMissing('odcs', ['id' => $odc->id]);
    }

    public function test_destroy_odc_with_routes_is_blocked(): void
    {
        $odc = $this->createOdc();
        $this->createRoute(['odc_id' => $odc->id]);

        $this->actingAs($this->user)
            ->delete("/distribution/odcs/{$odc->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('odcs', ['id' => $odc->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Gagal Hapus ODC']);
    }

    public function test_store_route(): void
    {
        $this->actingAs($this->user)->post('/distribution/routes', [
            'name' => 'Route Barat',
            'description' => 'Area barat',
            'color' => '#2563eb',
            'coordinates' => '[[[-6.476,106.014],[-6.477,106.015]]]',
        ])->assertRedirect();

        $this->assertDatabaseHas('odp_routes', ['name' => 'Route Barat', 'color' => '#2563eb']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Tambah Route ODP']);
    }

    public function test_store_route_rejects_duplicate_name(): void
    {
        $this->createRoute();

        $this->actingAs($this->user)->post('/distribution/routes', [
            'name' => 'Route Test',
            'description' => 'Duplicate route',
            'color' => '#2563eb',
            'coordinates' => '[]',
        ])->assertSessionHasErrors('name');
    }

    public function test_update_route(): void
    {
        $route = OdpRoute::create([
            'user_id' => $this->user->id,
            'name' => 'Route Lama',
            'color' => '#2563eb',
            'coordinates' => [],
        ]);

        $this->actingAs($this->user)->put("/distribution/routes/{$route->id}", [
            'name' => 'Route Baru',
            'description' => 'Update route',
            'color' => '#059669',
            'coordinates' => '[]',
        ])->assertRedirect();

        $this->assertDatabaseHas('odp_routes', ['id' => $route->id, 'name' => 'Route Baru', 'color' => '#059669']);
    }

    public function test_destroy_route_without_points(): void
    {
        $route = OdpRoute::create([
            'user_id' => $this->user->id,
            'name' => 'Route Hapus',
            'color' => '#2563eb',
            'coordinates' => [],
        ]);

        $this->actingAs($this->user)->delete("/distribution/routes/{$route->id}")->assertRedirect();

        $this->assertDatabaseMissing('odp_routes', ['id' => $route->id]);
    }

    public function test_destroy_route_with_points_is_blocked(): void
    {
        $route = $this->createRoute();
        OdpPoint::create($this->pointPayload($route, ['user_id' => $this->user->id]));

        $this->actingAs($this->user)
            ->delete("/distribution/routes/{$route->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('odp_routes', ['id' => $route->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Gagal Hapus Route ODP']);
    }

    public function test_store_point(): void
    {
        $route = $this->createRoute();

        $this->actingAs($this->user)->post('/distribution/points', $this->pointPayload($route, [
            'name' => 'ODP-001',
        ]))->assertRedirect();

        $this->assertDatabaseHas('odp_points', ['name' => 'ODP-001', 'odp_route_id' => $route->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Tambah Titik ODP']);
    }

    public function test_store_point_rejects_duplicate_name(): void
    {
        $route = $this->createRoute();
        OdpPoint::create($this->pointPayload($route, ['user_id' => $this->user->id]));

        $this->actingAs($this->user)
            ->post('/distribution/points', $this->pointPayload($route))
            ->assertSessionHasErrors('name');
    }

    public function test_update_point(): void
    {
        $route = $this->createRoute();
        $point = OdpPoint::create($this->pointPayload($route, ['user_id' => $this->user->id]));

        $this->actingAs($this->user)->put("/distribution/points/{$point->id}", $this->pointPayload($route, [
            'name' => 'ODP-Update',
            'status' => 'maintenance',
            'port_capacity' => 16,
        ]))->assertRedirect();

        $this->assertDatabaseHas('odp_points', ['id' => $point->id, 'name' => 'ODP-Update', 'status' => 'maintenance', 'port_capacity' => 16]);
    }

    public function test_destroy_point_without_customers(): void
    {
        $route = $this->createRoute();
        $point = OdpPoint::create($this->pointPayload($route, ['user_id' => $this->user->id]));

        $this->actingAs($this->user)->delete("/distribution/points/{$point->id}")->assertRedirect();

        $this->assertDatabaseMissing('odp_points', ['id' => $point->id]);
    }

    public function test_destroy_point_with_customers_is_blocked(): void
    {
        $route = $this->createRoute();
        $point = OdpPoint::create($this->pointPayload($route, ['user_id' => $this->user->id]));
        $package = Package::factory()->create(['user_id' => $this->user->id]);
        $customer = Customer::factory()->create(['user_id' => $this->user->id, 'package_id' => $package->id, 'odp_point_id' => $point->id]);

        $this->actingAs($this->user)
            ->delete("/distribution/points/{$point->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('odp_points', ['id' => $point->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Gagal Hapus Titik ODP']);
    }

    private function createOdc(array $override = []): Odc
    {
        return Odc::create($this->odcPayload($override));
    }

    private function createRoute(array $override = []): OdpRoute
    {
        return OdpRoute::create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Route Test',
            'color' => '#2563eb',
            'coordinates' => [],
        ], $override));
    }

    private function odcPayload(array $override = []): array
    {
        return array_merge([
            'user_id' => $this->user->id,
            'name' => 'ODC Test',
            'address' => 'Kp. ODC',
            'latitude' => -6.476,
            'longitude' => 106.014,
            'status' => 'active',
            'capacity' => 32,
            'notes' => 'ODC test',
        ], $override);
    }

    private function pointPayload(OdpRoute $route, array $override = []): array
    {
        return array_merge([
            'user_id' => $this->user->id,
            'odp_route_id' => $route->id,
            'name' => 'ODP-Test',
            'address' => 'Kp. Test',
            'latitude' => -6.476,
            'longitude' => 106.014,
            'status' => 'active',
            'port_capacity' => 8,
            'port_used' => 0,
        ], $override);
    }
}
