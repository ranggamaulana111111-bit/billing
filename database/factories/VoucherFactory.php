<?php

namespace Database\Factories;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->bothify('????####'),
            'password' => fake()->bothify('######'),
            'duration_minutes' => fake()->randomElement([60, 120, 480, 1440, 43200]),
            'status' => 'active',
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'used',
            'used_at' => now(),
        ]);
    }
}
