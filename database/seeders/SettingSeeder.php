<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'company_name' => 'RabegNet',
            'company_address' => 'Jl. Raya Rabeg No. 1',
            'company_phone' => '08123456789',
            'bank_name' => 'Bank BCA',
            'bank_account' => '1234567890',
            'bank_holder' => 'RabegNet',
            'invoice_footer' => 'Terima kasih atas kepercayaan Anda.',
            'admin_phone' => '',
            'admin_name' => 'Admin',
            'isolir_title' => 'Internet Terisolir',
            'isolir_subtitle' => 'Akun Anda sedang ditangguhkan.',
            'isolir_message' => 'Silakan lakukan pembayaran untuk mengaktifkan kembali koneksi internet Anda.',
            'isolir_bg_start' => '#0f172a',
            'isolir_bg_end' => '#1e293b',
            'isolir_card_bg' => '#ffffff',
            'isolir_title_color' => '#dc2626',
            'isolir_accent' => '#25D366',
            'isolir_footer_text' => 'Layanan Internet Cepat & Stabil',
            'isolir_show_invoice' => '1',
            'isolir_show_wa' => '1',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
