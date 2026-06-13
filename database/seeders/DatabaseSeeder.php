<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! User::where('email', 'admin@rabegnet.id')->exists()) {
            User::create([
                'name' => 'Admin',
                'email' => 'admin@rabegnet.id',
                'password' => bcrypt('admin123'),
            ]);
        }

        if (! User::where('email', 'test@example.com')->exists()) {
            User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        $this->call([
            OdpRouteSeeder::class,
            BillingSeeder::class,
        ]);
    }
}
