<?php

namespace App\Jobs;

use App\Models\Olt;
use App\Models\Onu;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollOltJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(public Olt $olt) {}

    public function handle(): void
    {
        try {
            $connector = OltConnectorFactory::make($this->olt->brand);
            $connected = $connector->connect(
                $this->olt->ip_address,
                $this->olt->ssh_port,
                $this->olt->username,
                $this->olt->password
            );

            if (! $connected) {
                Log::warning("PollOltJob: Failed to connect to {$this->olt->name}");
                return;
            }

            $ports = $this->olt->ports;
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
            $this->olt->update(['last_polled_at' => now()]);

            Log::info("PollOltJob: {$this->olt->name} done — {$totalOnus} ONU found");
        } catch (\Exception $e) {
            Log::error("PollOltJob error {$this->olt->name}: {$e->getMessage()}");
            $this->fail($e);
        }
    }
}
