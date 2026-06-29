<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Odc;
use App\Models\Odp;

class PortController extends Controller
{
    public function odpPorts(Odp $odp)
    {
        $odp->load([
            'ports.customer.package',
            'connectedOdcPort.odc',
            'odc',
        ]);

        $ports = $odp->ports->map(function ($port) use ($odp) {
            return [
                'id' => $port->id,
                'port_number' => $port->port_number,
                'status' => $port->status,
                'customer' => $port->customer ? [
                    'id' => $port->customer->id,
                    'name' => $port->customer->name,
                    'phone' => $port->customer->phone,
                    'package' => $port->customer->package?->name,
                    'status' => $port->customer->status,
                ] : null,
            ];
        });

        return response()->json([
            'odp' => [
                'id' => $odp->id,
                'nama_odp' => $odp->nama_odp,
                'kapasitas_port' => $odp->kapasitas_port,
                'available' => $odp->availablePortsCount(),
                'used' => $odp->usedPortsCount(),
                'broken' => $odp->brokenPortsCount(),
                'kondisi_jalur' => $odp->kondisi_jalur,
            ],
            'odc' => $odp->odc ? [
                'id' => $odp->odc->id,
                'nama_odc' => $odp->odc->nama_odc,
                'port_odc' => $odp->connectedOdcPort ? [
                    'port_number' => $odp->connectedOdcPort->port_number,
                    'port_type' => $odp->connectedOdcPort->port_type,
                ] : null,
            ] : null,
            'ports' => $ports,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function odcPorts(Odc $odc)
    {
        $odc->load([
            'ports.connectedOdp.ports.customer.package',
            'odps',
        ]);

        $ports = $odc->ports->map(function ($port) {
            $odp = $port->connectedOdp;
            $customers = $odp ? $odp->ports->filter(fn ($p) => $p->status === 'used' && $p->customer) : collect();

            return [
                'id' => $port->id,
                'port_number' => $port->port_number,
                'port_type' => $port->port_type,
                'status' => $port->status,
                'connected_odp' => $odp ? [
                    'id' => $odp->id,
                    'nama_odp' => $odp->nama_odp,
                    'customer_count' => $customers->count(),
                    'port_used' => $odp->usedPortsCount(),
                    'port_total' => $odp->kapasitas_port,
                    'kondisi_jalur' => $odp->kondisi_jalur,
                ] : null,
            ];
        });

        return response()->json([
            'odc' => [
                'id' => $odc->id,
                'nama_odc' => $odc->nama_odc,
                'kapasitas_port' => $odc->kapasitas_port,
                'odp_count' => $odc->odps->count(),
            ],
            'ports' => $ports,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
