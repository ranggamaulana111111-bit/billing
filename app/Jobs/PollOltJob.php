<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\Olt;
use App\Models\OltPort;
use App\Models\Onu;
use App\Models\Setting;
use App\Services\FonnteService;
use App\Services\MikrotikService;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PollOltJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(public Olt $olt) {}

    public function handle(): void
    {
        $totalOnus = $this->scanFromOlt();
        if ($totalOnus === 0) {
            Log::info("PollOltJob: OLT scan 0 ONU, fallback sync dari MikroTik untuk {$this->olt->name}");
            $this->syncFromMikrotik();

            return;
        }

        $this->runRca();

        $this->olt->update(['last_polled_at' => now()]);
    }

    private function scanFromOlt(): int
    {
        if (! $this->olt->usesMikrotikProxy()) {
            $sock = @fsockopen($this->olt->ip_address, $this->olt->ssh_port, $errno, $errstr, 5);
            if (! $sock) {
                return 0;
            }
            fclose($sock);
        }

        $connector = OltConnectorFactory::make($this->olt->brand, $this->olt);
        $connected = $connector->connect(
            $this->olt->ip_address,
            $this->olt->ssh_port,
            $this->olt->username,
            $this->olt->password
        );

        if (! $connected) {
            return 0;
        }

        $ports = $this->olt->ports;
        $totalOnus = 0;

        foreach ($ports as $port) {
            try {
                $onus = $connector->getOnuList($port->slot_number, $port->port_number);

                foreach ($onus as $onuData) {
                    try {
                        $optical = $connector->getOpticalPower($onuData['onu_id']);
                    } catch (\Exception $e) {
                        $optical = ['rx_power' => null, 'tx_power' => null];
                    }

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
            } catch (\Exception $e) {
                Log::error("PollOlt port {$port->slot_number}/{$port->port_number} gagal: {$e->getMessage()}");
            }
        }

        $connector->disconnect();

        return $totalOnus;
    }

    private function runRca(): void
    {
        $offlineStatuses = ['offline', 'LOS', 'dying-gasp', 'unknown'];

        $offlineOnus = Onu::whereIn('status', $offlineStatuses)
            ->where('last_seen_at', '>=', now()->subHours(2))
            ->whereHas('customer', fn ($q) => $q->whereNotNull('odp_id'))
            ->with('customer.odp.ports', 'customer.odp.odc')
            ->get()
            ->groupBy(fn ($onu) => $onu->customer?->odp_id);

        foreach ($offlineOnus as $odpId => $onus) {
            $odp = $onus->first()->customer->odp;
            if (! $odp) {
                continue;
            }

            $totalUsed = $odp->ports->where('status', 'used')->count();
            if ($totalUsed === 0) {
                continue;
            }

            $offlineCount = $onus->count();
            $pct = ($offlineCount / $totalUsed) * 100;

            if ($pct < 80) {
                continue;
            }

            Log::info("PollOltJob RCA: ODP {$odp->nama_odp} - {$offlineCount}/{$totalUsed} offline ({$pct}%), kabel distribusi putus.");

            DB::transaction(function () use ($odp, $onus) {
                $odp->update(['kondisi_jalur' => 'DOWN_LINK_FAILURE']);

                $customerIds = $onus->pluck('customer.id')->filter();
                OdpPort::where('odp_id', $odp->id)
                    ->where('status', 'used')
                    ->whereHas('customer', fn ($q) => $q->whereIn('id', $customerIds))
                    ->update(['status' => 'broken']);

                $this->notifyTechnician($odp);
            });
        }
    }

    private function notifyTechnician(Odp $odp): void
    {
        try {
            $tenantId = $odp->tenant_id;
            $token = Setting::get('fonnte_token', null, $tenantId);
            $phone = Setting::get('notif_phone_teknisikoordinator', null, $tenantId)
                  ?? Setting::get('admin_phone', null, $tenantId)
                  ?? '';

            if (! $token || ! $phone) {
                return;
            }

            $koordinat = $odp->koordinat ?: '-';
            $odcName = $odp->odc?->nama_odc ?: '-';

            $message = "⚠️ *ALERT: KABEL DISTRIBUSI PUTUS* ⚠️\n\n"
                ."ODP: {$odp->nama_odp}\n"
                ."Koordinat: {$koordinat}\n"
                ."Jalur: {$odcName} ➔ Tube: {$odp->kabel_tube_color} ➔ Core: {$odp->kabel_core_number}\n\n"
                .'Kabel distribusi ini terdeteksi putus. Segera lakukan pengecekan dan splicing ulang.';

            (new FonnteService($tenantId))->send($phone, $message);
        } catch (\Exception $e) {
            Log::error("PollOltJob RCA gagal kirim WA: {$e->getMessage()}");
        }
    }

    private function syncFromMikrotik(): void
    {
        try {
            $mikrotik = new MikrotikService;
            if (! $mikrotik->isConfigured()) {
                return;
            }

            $active = $mikrotik->getPppActive();
            $firstPort = $this->olt->ports()->first();
            if (! $firstPort) {
                $firstPort = OltPort::create([
                    'olt_id' => $this->olt->id,
                    'slot_number' => 0,
                    'port_number' => 0,
                    'port_type' => 'gpon',
                    'status' => 'active',
                ]);
            }

            $synced = 0;
            foreach ($active as $session) {
                $username = $session['name'] ?? '';
                if (! $username) {
                    continue;
                }

                $customer = Customer::where('pppoe_username', $username)->first();
                if (! $customer) {
                    continue;
                }

                $mac = $session['caller-id'] ?? '';

                Onu::updateOrCreate(
                    [
                        'olt_port_id' => $firstPort->id,
                        'customer_id' => $customer->id,
                    ],
                    [
                        'onu_id' => 'mikrotik-'.$customer->id,
                        'caller_id' => $mac ?: 'PPPoE-'.$username,
                        'status' => 'online',
                        'slot_number' => $firstPort->slot_number,
                        'port_number' => $firstPort->port_number,
                        'last_seen_at' => now(),
                    ]
                );

                $synced++;
            }

            Log::info("PollOltJob syncFromMikrotik: {$synced} ONU untuk {$this->olt->name}");
        } catch (\Exception $e) {
            Log::error("PollOltJob syncFromMikrotik gagal: {$e->getMessage()}");
        }
    }
}
