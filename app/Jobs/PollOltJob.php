<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Incident;
use App\Models\OdpPort;
use App\Models\Olt;
use App\Models\OltPort;
use App\Models\Onu;
use App\Services\IncidentNotificationService;
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

    public int $timeout = 120;

    public int $tries = 2;

    public function __construct(public Olt $olt) {}

    public function handle(): void
    {
        $totalOnus = $this->scanFromOlt();
        if ($totalOnus === 0) {
            Log::info("PollOltJob: OLT scan 0 ONU, fallback sync dari MikroTik untuk {$this->olt->name}");
            $this->syncFromMikrotik();
        } else {
            $this->linkOnusToCustomers();
            $this->runRca();
        }

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

                // Fetch distances for this port (C-Data only, returns [] for other brands)
                $distances = [];
                if (method_exists($connector, 'getOnuDistances')) {
                    try {
                        $distances = $connector->getOnuDistances($port->slot_number, $port->port_number);
                    } catch (\Exception $e) {
                        Log::warning("PollOltJob distance fetch gagal untuk port {$port->slot_number}/{$port->port_number}: {$e->getMessage()}");
                    }
                }

                foreach ($onus as $onuData) {
                    try {
                        $optical = $connector->getOpticalPower($onuData['onu_id']);
                    } catch (\Exception $e) {
                        Log::warning("PollOltJob optical power gagal untuk {$onuData['onu_id']}: {$e->getMessage()}");
                        $optical = ['rx_power' => null, 'tx_power' => null];
                    }

                    $rxVal = $optical['rx_power'] ?? null;
                    $txVal = $optical['tx_power'] ?? null;

                    if ($rxVal === null || $txVal === null) {
                        Log::info("PollOltJob optical-null ONU {$onuData['onu_id']}: RX=".
                            ($rxVal ?? 'null').' TX='.($txVal ?? 'null').' SN='.($onuData['sn'] ?? '?'));
                    }

                    // Extract ONT ID from "slot/port/ontId" format
                    $parts = explode('/', $onuData['onu_id']);
                    $ontIdx = (int) ($parts[2] ?? 0);
                    $distanceVal = $distances[$ontIdx] ?? null;

                    Onu::updateOrCreate(
    [
        'tenant_id'   => $this->olt->tenant_id,
        'olt_port_id' => $port->id,
        'onu_id'      => $onuData['onu_id'],
    ],
    [
        'tenant_id'    => $this->olt->tenant_id,
        'serial_number'=> $onuData['sn'] ?? null,
        'caller_id'    => $onuData['caller_id'] ?? $onuData['mac'] ?? null,
        'status'       => $onuData['status'] ?? 'unknown',
        'rx_power'     => $optical['rx_power'] ?? null,
        'tx_power'     => $optical['tx_power'] ?? null,
        'distance'     => $distanceVal,
        'slot_number'  => $port->slot_number,
        'port_number'  => $port->port_number,
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

    private function linkOnusToCustomers(): void
    {
        $unlinkedOnus = Onu::where('olt_port_id', $this->olt->ports()->pluck('id'))
            ->whereNull('customer_id')
            ->whereNotNull('serial_number')
            ->get();

        if ($unlinkedOnus->isEmpty()) {
            return;
        }

        $linked = 0;

        foreach ($unlinkedOnus as $onu) {
            $customer = Customer::where('serial_number', $onu->serial_number)
                ->where('status', 'active')
                ->first();

            if (! $customer) {
                continue;
            }

            $alreadyHasOlt = Onu::where('customer_id', $customer->id)
                ->whereNotNull('serial_number')
                ->where('id', '!=', $onu->id)
                ->exists();

            if ($alreadyHasOlt) {
                Log::info("PollOltJob skip link: {$customer->name} sudah punya OLT ONU lain (SN prevented)");

                continue;
            }

            $onu->update(['customer_id' => $customer->id]);
            $linked++;
        }

        if ($linked > 0) {
            Log::info("PollOltJob linkOnusToCustomers: {$linked} ONU ditautkan via serial_number untuk {$this->olt->name}");
        }
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

            if ($pct >= 80 && $odp->kondisi_jalur !== 'DOWN_LINK_FAILURE') {
                Log::info("PollOltJob RCA: ODP {$odp->nama_odp} - {$offlineCount}/{$totalUsed} offline ({$pct}%), kabel distribusi putus.");

                DB::transaction(function () use ($odp, $onus) {
                    $odp->update(['kondisi_jalur' => 'DOWN_LINK_FAILURE']);

                    $customerIds = $onus->pluck('customer.id')->filter()->values()->all();
                    OdpPort::where('odp_id', $odp->id)
                        ->where('status', 'used')
                        ->whereHas('customer', fn ($q) => $q->whereIn('id', $customerIds))
                        ->update(['status' => 'broken']);

                    $odcName = $odp->odc?->nama_odc ?? '-';
                    $tube = $odp->kabel_tube_color ?? '-';
                    $core = $odp->kabel_core_number ?? '-';

                    $incident = Incident::create([
                        'tenant_id' => $odp->tenant_id,
                        'title' => "Kabel distribusi putus - ODP {$odp->nama_odp}",
                        'description' => "Kabel distribusi terdeteksi putus. ODC: {$odcName}, Tube: {$tube}, Core: {$core}. ".$onus->count().' pelanggan offline.',
                        'type' => 'auto',
                        'source' => 'olt_rca',
                        'severity' => 'high',
                        'status' => 'open',
                        'odp_id' => $odp->id,
                        'odc_id' => $odp->odc_id,
                        'created_by' => 1,
                        'detected_at' => now(),
                        'sla_deadline' => now()->addHours(Incident::slaHoursForSeverity('high')),
                        'sla_status' => 'on_track',
                        'notifiable_customer_ids' => $customerIds,
                    ]);

                    (new IncidentNotificationService($odp->tenant_id))->notifyCreated($incident, $customerIds);
                });
            } elseif ($pct >= 50 && $odp->kondisi_jalur === 'UP') {
                Log::info("PollOltJob RCA: ODP {$odp->nama_odp} - {$offlineCount}/{$totalUsed} offline ({$pct}%), jalur gangguan.");

                $customerIds = $onus->pluck('customer.id')->filter()->values()->all();
                $odcName = $odp->odc?->nama_odc ?? '-';
                $tube = $odp->kabel_tube_color ?? '-';
                $core = $odp->kabel_core_number ?? '-';

                DB::transaction(function () use ($odp, $offlineCount, $totalUsed, $pct, $customerIds, $odcName, $tube, $core) {
                    $odp->update(['kondisi_jalur' => 'GANGGUAN']);

                    $incident = Incident::create([
                        'tenant_id' => $odp->tenant_id,
                        'title' => "Gangguan jalur ODP {$odp->nama_odp}",
                        'description' => "Jalur distribusi terdeteksi gangguan. ODC: {$odcName}, Tube: {$tube}, Core: {$core}. {$offlineCount}/{$totalUsed} pelanggan offline ({$pct}%).",
                        'type' => 'auto',
                        'source' => 'olt_rca',
                        'severity' => 'medium',
                        'status' => 'open',
                        'odp_id' => $odp->id,
                        'odc_id' => $odp->odc_id,
                        'created_by' => 1,
                        'detected_at' => now(),
                        'sla_deadline' => now()->addHours(Incident::slaHoursForSeverity('medium')),
                        'sla_status' => 'on_track',
                        'notifiable_customer_ids' => $customerIds,
                    ]);

                    (new IncidentNotificationService($odp->tenant_id))->notifyCreated($incident, $customerIds);
                });
            } elseif ($pct < 50 && $odp->kondisi_jalur !== 'UP') {
                Log::info("PollOltJob RCA: ODP {$odp->nama_odp} - {$offlineCount}/{$totalUsed} offline ({$pct}%), jalur recovery -> NORMAL.");
                $odp->update(['kondisi_jalur' => 'UP']);

                $resolvedIncident = Incident::where('odp_id', $odp->id)
                    ->whereIn('status', ['open', 'investigating'])
                    ->first();

                if ($resolvedIncident) {
                    DB::transaction(function () use ($resolvedIncident) {
                        $resolvedIncident->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                            'sla_status' => $resolvedIncident->sla_deadline && now()->lte($resolvedIncident->sla_deadline) ? 'met' : 'breached',
                        ]);

                        (new IncidentNotificationService($resolvedIncident->tenant_id))->notifyStatusChange($resolvedIncident, 'resolved');
                    });
                }
            } elseif ($odp->kondisi_jalur !== 'UP'
                && ! Incident::where('odp_id', $odp->id)->whereIn('status', ['open', 'investigating'])->exists()
            ) {
                $severity = $odp->kondisi_jalur === 'DOWN_LINK_FAILURE' ? 'high' : 'medium';
                $customerIds = $onus->pluck('customer.id')->filter()->values()->all();
                $odcName = $odp->odc?->nama_odc ?? '-';
                $tube = $odp->kabel_tube_color ?? '-';
                $core = $odp->kabel_core_number ?? '-';

                $incident = Incident::create([
                    'tenant_id' => $odp->tenant_id,
                    'title' => "Gangguan jalur ODP {$odp->nama_odp}",
                    'description' => "ODP terdeteksi {$odp->kondisi_jalur}. ODC: {$odcName}, Tube: {$tube}, Core: {$core}. {$offlineCount}/{$totalUsed} pelanggan offline ({$pct}%).",
                    'type' => 'auto',
                    'source' => 'olt_rca',
                    'severity' => $severity,
                    'status' => 'open',
                    'odp_id' => $odp->id,
                    'odc_id' => $odp->odc_id,
                    'created_by' => 1,
                    'detected_at' => now(),
                    'sla_deadline' => now()->addHours(Incident::slaHoursForSeverity($severity)),
                    'sla_status' => 'on_track',
                    'notifiable_customer_ids' => $customerIds,
                ]);

                (new IncidentNotificationService($odp->tenant_id))->notifyCreated($incident, $customerIds);
            } else {
                continue;
            }
        }

        $this->resolveRecoveredOdps();
    }

    private function resolveRecoveredOdps(): void
    {
        $offlineStatuses = ['offline', 'LOS', 'dying-gasp', 'unknown'];

        $problemOdps = Odp::withoutGlobalScopes()
            ->whereIn('kondisi_jalur', ['GANGGUAN', 'DOWN_LINK_FAILURE'])
            ->with('ports.customer.onus')
            ->get();

        foreach ($problemOdps as $odp) {
            $usedPorts = $odp->ports->where('status', 'used');
            if ($usedPorts->isEmpty()) {
                continue;
            }

            $customersWithOnu = $usedPorts->pluck('customer')->filter();
            if ($customersWithOnu->isEmpty()) {
                continue;
            }

            $offlineOnuCount = 0;
            foreach ($customersWithOnu as $customer) {
                $offlineOnuCount += $customer->onus->whereIn('status', $offlineStatuses)
                    ->where('last_seen_at', '>=', now()->subHours(2))
                    ->count();
            }

            if ($offlineOnuCount > 0) {
                continue;
            }

            Log::info("PollOltJob RCA recovery: ODP {$odp->nama_odp} - semua ONU online, auto-resolve.");

            $wasDown = $odp->kondisi_jalur === 'DOWN_LINK_FAILURE';
            $odp->update(['kondisi_jalur' => 'UP']);

            if ($wasDown) {
                OdpPort::where('odp_id', $odp->id)->where('status', 'broken')->update(['status' => 'used']);
            }

            $openIncidents = Incident::where('odp_id', $odp->id)
                ->whereIn('status', ['open', 'investigating'])
                ->get();

            foreach ($openIncidents as $incident) {
                $incident->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'sla_status' => $incident->sla_deadline && now()->lte($incident->sla_deadline) ? 'met' : 'breached',
                ]);

                (new IncidentNotificationService($incident->tenant_id))->notifyStatusChange($incident, 'resolved');
            }
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
                    'port_number' => 1,
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
        'tenant_id'   => $this->olt->tenant_id,
        'olt_port_id' => $firstPort->id,
        'onu_id'      => 'mikrotik-'.$customer->id,
    ],
    [
    'tenant_id'   => $this->olt->tenant_id,
    'customer_id' => $customer->id,
    'caller_id'   => $mac ?: 'PPPoE-'.$username,
    'status'      => 'online',
    'slot_number' => $firstPort->slot_number,
    'port_number' => $firstPort->port_number,
    'last_seen_at'=> now(),
]
);

                if ($customer->serial_number && ! $customer->onus()->whereNotNull('serial_number')->exists()) {
                    $oltOnu = Onu::where('serial_number', $customer->serial_number)
                        ->whereNull('customer_id')
                        ->where('olt_port_id', $firstPort->id)
                        ->first();

                    if ($oltOnu) {
                        $oltOnu->update(['customer_id' => $customer->id]);
                    }
                }

                $synced++;
            }

            Log::info("PollOltJob syncFromMikrotik: {$synced} ONU untuk {$this->olt->name}");
        } catch (\Exception $e) {
            Log::error("PollOltJob syncFromMikrotik gagal: {$e->getMessage()}");
        }
    }
}
