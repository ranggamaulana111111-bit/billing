<?php

namespace Database\Seeders;

use App\Models\VoucherProfile;
use Illuminate\Database\Seeder;

class VoucherProfileSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            ['name' => 'Paket 5GB - 30 Hari', 'speed' => '5Mbps', 'price' => 50000, 'time_limit' => null, 'quota_limit' => 5120, 'validity_days' => 30, 'shared_users' => 1],
            ['name' => 'Paket 10GB - 30 Hari', 'speed' => '10Mbps', 'price' => 75000, 'time_limit' => null, 'quota_limit' => 10240, 'validity_days' => 30, 'shared_users' => 1],
            ['name' => 'Paket 20GB - 30 Hari', 'speed' => '20Mbps', 'price' => 100000, 'time_limit' => null, 'quota_limit' => 20480, 'validity_days' => 30, 'shared_users' => 2],
            ['name' => 'Paket Unlimited - 7 Hari', 'speed' => '10Mbps', 'price' => 35000, 'time_limit' => 168, 'quota_limit' => null, 'validity_days' => 7, 'shared_users' => 1],
            ['name' => 'Paket Unlimited - 30 Hari', 'speed' => '10Mbps', 'price' => 120000, 'time_limit' => 720, 'quota_limit' => null, 'validity_days' => 30, 'shared_users' => 2],
            ['name' => 'Paket 1 Jam', 'speed' => '5Mbps', 'price' => 5000, 'time_limit' => 1, 'quota_limit' => null, 'validity_days' => 1, 'shared_users' => 1],
            ['name' => 'Paket 3 Jam', 'speed' => '5Mbps', 'price' => 10000, 'time_limit' => 3, 'quota_limit' => null, 'validity_days' => 1, 'shared_users' => 1],
            ['name' => 'Paket 12 Jam', 'speed' => '10Mbps', 'price' => 20000, 'time_limit' => 12, 'quota_limit' => null, 'validity_days' => 1, 'shared_users' => 1],
        ];

        foreach ($profiles as $profile) {
            VoucherProfile::create($profile + ['is_active' => true, 'description' => '']);
        }
    }
}
