<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OdpPoint;
use App\Models\OdpRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_register_page_is_accessible(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_access_dashboard_with_odp_data(): void
    {
        $user = User::factory()->create();
        $route = OdpRoute::create([
            'user_id' => $user->id,
            'name' => 'Route A',
            'color' => '#2563eb',
            'coordinates' => [[-6.2, 106.8]],
        ]);
        $odp = OdpPoint::create([
            'user_id' => $user->id,
            'odp_route_id' => $route->id,
            'name' => 'ODP-01',
            'address' => 'Kp. Kumpay',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'port_capacity' => 8,
            'port_used' => 1,
        ]);
        Customer::factory()->create(['user_id' => $user->id, 'odp_point_id' => $odp->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('ODP-01');
        $response->assertSee('1/8');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
