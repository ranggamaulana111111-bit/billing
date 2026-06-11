<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word().' Package',
            'speed' => fake()->randomElement([10, 20, 30, 50, 100]),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomElement([150000, 200000, 350000, 500000]),
            'billing_cycle' => 'monthly',
            'mikrotik_profile' => fake()->optional()->word(),
            'is_active' => true,
        ];
    }
}
