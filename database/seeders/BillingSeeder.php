<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OdpPoint;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $paket1 = Package::create(['name' => 'Home 10', 'speed' => '10', 'price' => 150000]);
        $paket2 = Package::create(['name' => 'Home 20', 'speed' => '20', 'price' => 250000]);
        $paket3 = Package::create(['name' => 'Biz 30', 'speed' => '30', 'price' => 400000]);
        $paket4 = Package::create(['name' => 'Biz 50', 'speed' => '50', 'price' => 650000]);
        $paket5 = Package::create(['name' => 'Ultra 100', 'speed' => '100', 'price' => 1000000]);

        $odps = OdpPoint::all();

        $customerData = [
            ['name' => 'Ahmad Fauzi', 'location' => 'Kp. Kumpay RT 01', 'phone' => '081234567890', 'package_id' => $paket1->id, 'pppoe_username' => 'ahmad10', 'due_date' => '2026-06-15'],
            ['name' => 'Siti Nurhaliza', 'location' => 'Kp. Kumpay RT 02', 'phone' => '081234567891', 'package_id' => $paket2->id, 'pppoe_username' => 'siti20', 'due_date' => '2026-06-10'],
            ['name' => 'Budi Santoso', 'location' => 'Kp. Kumpay RT 03', 'phone' => '081234567892', 'package_id' => $paket1->id, 'pppoe_username' => 'budi10', 'due_date' => '2026-06-20'],
            ['name' => 'Dewi Lestari', 'location' => 'Kp. Kumpay RT 04', 'phone' => '081234567893', 'package_id' => $paket3->id, 'pppoe_username' => 'dewi30', 'due_date' => '2026-06-05'],
            ['name' => 'Rudi Hartono', 'location' => 'Kp. Kumpay RT 05', 'phone' => '081234567894', 'package_id' => $paket2->id, 'pppoe_username' => 'rudi20', 'due_date' => '2026-06-25'],
            ['name' => 'Mega Wati', 'location' => 'Kp. Kumpay RT 01', 'phone' => '081234567895', 'package_id' => $paket4->id, 'pppoe_username' => 'mega50', 'due_date' => '2026-06-12'],
            ['name' => 'Agus Prasetyo', 'location' => 'Kp. Kumpay RT 02', 'phone' => '081234567896', 'package_id' => $paket1->id, 'pppoe_username' => 'agus10', 'due_date' => '2026-06-18'],
            ['name' => 'Rina Melati', 'location' => 'Kp. Kumpay RT 03', 'phone' => '081234567897', 'package_id' => $paket3->id, 'pppoe_username' => 'rina30', 'due_date' => '2026-06-08'],
            ['name' => 'Hendra Gunawan', 'location' => 'Kp. Kumpay RT 04', 'phone' => '081234567898', 'package_id' => $paket2->id, 'pppoe_username' => 'hendra20', 'due_date' => '2026-06-22'],
            ['name' => 'Fitri Handayani', 'location' => 'Kp. Kumpay RT 05', 'phone' => '081234567899', 'package_id' => $paket5->id, 'pppoe_username' => 'fitri100', 'due_date' => '2026-06-03'],
        ];

        foreach ($customerData as $i => $data) {
            $data['odp_point_id'] = $odps[$i % $odps->count()]->id;
            $customer = Customer::create($data);

            Invoice::create([
                'invoice_code' => 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-06',
                'customer_id' => $customer->id,
                'amount' => $customer->package->price,
                'payment_status' => $i < 4 ? 'paid' : 'unpaid',
                'created_at' => Carbon::now()->subDays($i),
            ]);

            if ($i < 4) {
                Invoice::create([
                    'invoice_code' => 'INV-'.str_pad($customer->id, 4, '0', STR_PAD_LEFT).'-05',
                    'customer_id' => $customer->id,
                    'amount' => $customer->package->price,
                    'payment_status' => 'paid',
                    'created_at' => Carbon::now()->subMonth()->addDays($i),
                ]);
            }
        }

        $logs = [
            ['action' => 'Login', 'details' => 'Admin login ke sistem'],
            ['action' => 'Tambah Pelanggan', 'details' => 'Menambahkan pelanggan baru: Fitri Handayani'],
            ['action' => 'Pembayaran', 'details' => 'Pembayaran dari Ahmad Fauzi - Rp 150.000'],
            ['action' => 'Generate Invoice', 'details' => 'Membuat tagihan untuk 10 pelanggan'],
            ['action' => 'Update ODP', 'details' => 'Update kapasitas ODP-10 Kumpay Lapangan'],
        ];

        foreach ($logs as $log) {
            ActivityLog::create($log);
        }
    }
}
