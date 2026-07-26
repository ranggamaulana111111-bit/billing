<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupCustomers extends Command
{
    protected $signature = 'customers:backup
        {--tenant= : ID tenant tertentu. Kosongkan untuk semua tenant.}
        {--download : Tampilkan path file hasil backup.}';

    protected $description = 'Backup otomatis seluruh pelanggan PPPoE & Hotspot ke file JSON';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $backupDir = storage_path('app/backups/customers');

        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $query = Customer::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $customers = $query->with(['package', 'odp'])->get();

        $pppoe = [];
        $hotspot = [];

        foreach ($customers as $c) {
            $row = [
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'type' => $c->type,
                'phone' => $c->phone,
                'email' => $c->email,
                'nik' => $c->nik,
                'location' => $c->location,
                'package' => $c->package?->name,
                'pppoe_username' => $c->pppoe_username,
                'pppoe_password' => $c->pppoe_password,
                'serial_number' => $c->serial_number,
                'mac_address' => $c->mac_address,
                'original_ppp_profile' => $c->original_ppp_profile,
                'odp' => $c->odp?->nama_odp,
                'odc' => $c->odp?->odc?->nama_odc,
                'due_date' => $c->due_date,
                'status' => $c->status,
                'created_at' => $c->created_at?->format('Y-m-d H:i:s'),
            ];

            if ($c->type === 'hotspot') {
                $hotspot[] = $row;
            } else {
                $pppoe[] = $row;
            }
        }

        $timestamp = now()->format('Ymd-His');
        $filename = "customers-{$timestamp}.json";
        $data = [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'tenant_id' => $tenantId ?: 'all',
            'counts' => [
                'pppoe' => count($pppoe),
                'hotspot' => count($hotspot),
                'total' => $customers->count(),
            ],
            'pppoe' => $pppoe,
            'hotspot' => $hotspot,
        ];

        $path = "{$backupDir}/{$filename}";
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Backup pelanggan selesai: {$filename}");
        $this->info('PPPoE: '.count($pppoe).', Hotspot: '.count($hotspot).', Total: '.$customers->count());

        if ($this->option('download')) {
            $this->line("Path: {$path}");
        }

        return self::SUCCESS;
    }
}
