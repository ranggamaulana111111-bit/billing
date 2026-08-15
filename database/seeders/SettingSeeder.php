<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'company_name' => 'ALKONEKbill',
            'company_short_name' => 'ALKONEKbill',
            'company_address' => 'Jl. Raya Rabeg No. 1',
            'company_phone' => '08123456789',
            'bank_name' => 'Bank BCA',
            'bank_account' => '1234567890',
            'bank_holder' => 'ALKONEKbill',
            'invoice_footer' => 'Terima kasih atas kepercayaan Anda.',
            'admin_phone' => '',
            'admin_name' => 'Admin',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
