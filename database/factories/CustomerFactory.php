<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '08'.fake()->numerify('##########'),
            'location' => fake()->address(),
            'email' => fake()->email(),
            'package_id' => Package::factory(),
            'status' => 'active',
            'due_date' => now()->day(5)->format('Y-m-d'),
        ];
    }
}
