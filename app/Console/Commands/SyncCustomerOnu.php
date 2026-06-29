<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\olt;
use App\Models\OltPort;
use App\Models\Onu;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCustomerOnu extends Command
{
    protected $signature = 'customers:onu-sync {--olt= : ID OLT tertentu}';

    protected $description = 'Sync semua pelanggan aktif ke ONU di OLT';

    public function handle(): void
    {
        $olts = olt::where('status', 'active')->when($this->option('olt'), fn ($q, $id) => $q->where('id', $id))->get();

        if ($olts->isEmpty()) {
            $this->warn('Tidak ada OLT aktif.');

            return;
        }

        $mikrotik = new MikrotikService;

        try {
            $mikrotik->isConfigured();
            $active = $mikrotik->getPppActive();
            $sessions = collect($active)->keyBy('name');
        } catch (\Exception $e) {
            $this->warn('MikroTik tidak reachable, ONU tetap dibuat tanpa MAC.');
            $sessions = collect();
        }

        $total = 0;

        foreach ($olts as $olt) {
            $port = $olt->ports()->first();
            if (! $port) {
                $port = OltPort::create([
                    'olt_id' => $olt->id,
                    'slot_number' => 0,
                    'port_number' => 0,
                    'port_type' => 'gpon',
                    'status' => 'active',
                ]);
            }

            $customers = Customer::where('status', 'active')
                ->whereNotNull('pppoe_username')
                ->where('pppoe_username', '!=', '')
                ->get();

            foreach ($customers as $customer) {
                $session = $sessions->get($customer->pppoe_username);
                $mac = $session['caller-id'] ?? '';

                Onu::updateOrCreate(
                    [
                        'olt_port_id' => $port->id,
                        'customer_id' => $customer->id,
                    ],
                    [
                        'onu_id' => 'mikrotik-'.$customer->id,
                        'caller_id' => $mac ?: 'PPPoE-'.$customer->pppoe_username,
                        'status' => $session ? 'online' : 'offline',
                        'slot_number' => $port->slot_number,
                        'port_number' => $port->port_number,
                        'last_seen_at' => $session ? now() : null,
                    ]
                );

                $total++;
            }

            $olt->update(['last_polled_at' => now()]);
        }

        $this->info("Selesai. {$total} ONU tersinkron.");
        Log::info("customers:onu-sync selesai — {$total} ONU");
    }
}
