<?php

namespace App\Console\Commands;

use App\Models\Olt;
use App\Models\Onu;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollOlt extends Command
{
    protected $signature = 'olt:poll {--olt=}';

    protected $description = 'Poll all OLT devices and update ONU status';

    public function handle(): void
    {
        $olts = Olt::query();

        if ($oltId = $this->option('olt')) {
            $olts->where('id', $oltId);
        }

        $olts = $olts->where('status', 'active')->get();

        if ($olts->isEmpty()) {
            $this->warn('No active OLT devices found.');

            return;
        }

        foreach ($olts as $olt) {
            $this->line("Polling {$olt->name} ({$olt->ip_address})...");

            try {
                $connector = OltConnectorFactory::make($olt->brand);
                $connected = $connector->connect(
                    $olt->ip_address,
                    $olt->ssh_port,
                    $olt->username,
                    $olt->password
                );

                if (! $connected) {
                    $this->error("  Failed to connect to {$olt->name}");
                    Log::warning("OLT poll failed to connect: {$olt->name}");

                    continue;
                }

                $ports = $olt->ports;
                $totalOnus = 0;

                foreach ($ports as $port) {
                    $onus = $connector->getOnuList($port->slot_number, $port->port_number);

                    foreach ($onus as $onuData) {
                        $optical = $connector->getOpticalPower($onuData['onu_id']);

                        Onu::updateOrCreate(
                            [
                                'olt_port_id' => $port->id,
                                'onu_id' => $onuData['onu_id'],
                            ],
                            [
                                'serial_number' => $onuData['sn'] ?? null,
                                'status' => $onuData['status'] ?? 'unknown',
                                'rx_power' => $optical['rx_power'] ?? null,
                                'tx_power' => $optical['tx_power'] ?? null,
                                'slot_number' => $port->slot_number,
                                'port_number' => $port->port_number,
                                'last_seen_at' => now(),
                            ]
                        );

                        $totalOnus++;
                    }
                }

                $connector->disconnect();
                $olt->update(['last_polled_at' => now()]);

                $this->info("  Done: {$totalOnus} ONU found");
            } catch (\Exception $e) {
                $this->error("  Error polling {$olt->name}: {$e->getMessage()}");
                Log::error("OLT poll error {$olt->name}: {$e->getMessage()}");
            }
        }
    }
}
