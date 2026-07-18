<?php

namespace App\Services\Monitoring;

use App\Models\Odc;
use App\Models\Olt;
use App\Models\OltPort;
use App\Models\Onu;
use Illuminate\Support\Facades\Auth;

class FiberTopologyService
{
    public function getTopologyData(): array
    {
        $tenantId = Auth::user()->tenant_id;

        $olts = Olt::where('tenant_id', $tenantId)->get();
        $oltPorts = OltPort::whereHas('olt', fn ($q) => $q->where('tenant_id', $tenantId))->get();
        $onus = Onu::whereHas('oltPort.olt', fn ($q) => $q->where('tenant_id', $tenantId))->with('customer')->get();

        $nodes = [];
        $edges = [];

        $nodes[] = [
            'id' => 'internet',
            'label' => 'Internet',
            'type' => 'internet',
            'status' => 'online',
            'icon' => 'fa-solid fa-globe',
        ];

        $nodes[] = [
            'id' => 'core-router',
            'label' => 'Core Router',
            'type' => 'router',
            'status' => 'online',
            'icon' => 'fa-solid fa-network-wired',
        ];

        $edges[] = ['from' => 'internet', 'to' => 'core-router'];

        foreach ($olts as $olt) {
            $oltOnline = $oltPorts->where('olt_id', $olt->id)->sum(function ($p) use ($onus) {
                return $onus->where('olt_port_id', $p->id)->where('status', 'online')->count();
            });
            $oltTotal = $onus->where('olt_port_id', $oltPorts->where('olt_id', $olt->id)->pluck('id'))->count();
            $oltStatus = match (true) {
                $oltOnline == 0 && $oltTotal > 0 => 'offline',
                $oltOnline < $oltTotal * 0.8 => 'warning',
                default => 'online',
            };

            $nodes[] = [
                'id' => "olt-{$olt->id}",
                'label' => $olt->name,
                'type' => 'olt',
                'status' => $oltStatus,
                'icon' => 'fa-solid fa-server',
                'detail' => "{$oltOnline}/{$oltTotal} ONU online",
                'olt_id' => $olt->id,
            ];

            $edges[] = ['from' => 'core-router', 'to' => "olt-{$olt->id}"];

            $portIds = $oltPorts->where('olt_id', $olt->id)->pluck('id');
            $portOnus = $onus->whereIn('olt_port_id', $portIds);

            $portGroups = $portOnus->groupBy('olt_port_id');
            foreach ($portGroups as $portId => $portOnuList) {
                $port = $oltPorts->firstWhere('id', $portId);
                $ponOnline = $portOnuList->where('status', 'online')->count();
                $ponTotal = $portOnuList->count();
                $ponStatus = match (true) {
                    $ponOnline == 0 && $ponTotal > 0 => 'offline',
                    $ponOnline < $ponTotal * 0.8 => 'warning',
                    default => 'online',
                };

                $ponId = "pon-{$portId}";
                $nodes[] = [
                    'id' => $ponId,
                    'label' => "PON {$port->slot_number}/{$port->port_number}",
                    'type' => 'pon',
                    'status' => $ponStatus,
                    'icon' => 'fa-solid fa-ethernet',
                    'detail' => "{$ponOnline}/{$ponTotal} online",
                ];

                $edges[] = ['from' => "olt-{$olt->id}", 'to' => $ponId];
            }

            $odcs = Odc::where('tenant_id', $tenantId)->with(['odps' => fn ($q) => $q->with('ports.customer')])->get();

            $odpNodeByCustomer = [];

            foreach ($odcs as $odc) {
                $odcId = "odc-{$odc->id}";
                $odcStatus = $odc->ports()->where('status', 'available')->count() === $odc->kapasitas_port && $odc->kapasitas_port > 0
                    ? 'offline'
                    : 'online';

                if (! in_array($odcId, array_column($nodes, 'id'))) {
                    $nodes[] = [
                        'id' => $odcId,
                        'label' => $odc->nama_odc,
                        'type' => 'odc',
                        'status' => $odcStatus,
                        'icon' => 'fa-solid fa-plug',
                        'detail' => "{$odc->ports()->where('status', 'used')->count()}/{$odc->kapasitas_port} port terpakai",
                    ];
                }

                $edges[] = ['from' => "olt-{$olt->id}", 'to' => $odcId];

                foreach ($odc->odps as $odp) {
                    $odpId = "odp-{$odp->id}";
                    $usedPorts = $odp->ports->where('status', 'used')->count();
                    $odpStatus = match ($odp->kondisi_jalur) {
                        'DOWN_LINK_FAILURE' => 'offline',
                        'GANGGUAN' => 'warning',
                        default => $usedPorts > 0 ? 'online' : 'offline',
                    };

                    if (! in_array($odpId, array_column($nodes, 'id'))) {
                        $nodes[] = [
                            'id' => $odpId,
                            'label' => $odp->nama_odp,
                            'type' => 'odp',
                            'status' => $odpStatus,
                            'icon' => 'fa-solid fa-ethernet',
                            'detail' => "{$usedPorts}/{$odp->kapasitas_port} port terpakai",
                        ];
                    }

                    $edges[] = ['from' => $odcId, 'to' => $odpId];

                    foreach ($odp->ports as $port) {
                        if ($port->customer_id) {
                            $odpNodeByCustomer[$port->customer_id] = $odpId;
                        }
                    }
                }
            }
        }

        foreach ($onus as $onu) {
            $customerName = $onu->customer->name ?? 'Unlinked';
            $onuId = "onu-{$onu->id}";
            $nodes[] = [
                'id' => $onuId,
                'label' => $onu->onu_id,
                'type' => 'onu',
                'status' => $onu->status ?? 'unknown',
                'icon' => 'fa-solid fa-tower-broadcast',
                'detail' => $customerName,
                'onu_id' => $onu->id,
                'rx_power' => $onu->rx_power,
            ];

            $ponId = "pon-{$onu->olt_port_id}";
            if (in_array($ponId, array_column($nodes, 'id'))) {
                $edges[] = ['from' => $ponId, 'to' => $onuId];
            }

            if ($onu->customer && isset($odpNodeByCustomer[$onu->customer_id])) {
                $edges[] = ['from' => $odpNodeByCustomer[$onu->customer_id], 'to' => $onuId];
            }

            if ($onu->customer) {
                $custId = "cust-{$onu->customer_id}";
                if (! in_array($custId, array_column($nodes, 'id'))) {
                    $nodes[] = [
                        'id' => $custId,
                        'label' => $onu->customer->name,
                        'type' => 'customer',
                        'status' => 'online',
                        'icon' => 'fa-solid fa-user',
                    ];
                }
                $edges[] = ['from' => $onuId, 'to' => $custId];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }
}
