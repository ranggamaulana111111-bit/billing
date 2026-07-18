<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Odp;
use App\Models\Olt;
use App\Models\Onu;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OnuHotspotController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $statusFilter = $request->input('status');

        $onus = Onu::with(['customer', 'oltPort.olt'])
            ->whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))
            ->when($search, fn ($q) => $q->whereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('customer_code', 'like', "%{$search}%"))->orWhere('serial_number', 'like', "%{$search}%")->orWhere('caller_id', 'like', "%{$search}%"))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->latest('last_seen_at')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'total' => Onu::whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))->count(),
            'online' => Onu::whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))->where('status', 'online')->count(),
            'offline' => Onu::whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))->where('status', 'offline')->count(),
            'unknown' => Onu::whereHas('customer', fn ($q) => $q->where('type', 'hotspot'))->where('status', '!=', 'online')->where('status', '!=', 'offline')->count(),
        ];

        $odps = Odp::with('ports')->orderBy('nama_odp')->get();

        return view('onu-hotspot.index', compact('onus', 'stats', 'search', 'statusFilter', 'odps'));
    }

    public function show(Onu $onu)
    {
        $onu->load(['customer', 'oltPort.olt']);
        $customers = Customer::where('type', 'hotspot')->whereNull('id')->get();

        return view('onu-hotspot.show', compact('onu', 'customers'));
    }

    public function update(Request $request, Onu $onu)
    {
        $validated = $request->validate([
            'serial_number' => 'nullable|string|max:50',
            'caller_id' => 'nullable|string|max:50',
            'vendor' => 'nullable|string|max:50',
            'model' => 'nullable|string|max:50',
            'mac_address' => 'nullable|string|max:17',
            'odp_port_id' => 'nullable|exists:odp_ports,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $onu->update($validated);

        return back()->with('success', 'ONU berhasil diupdate.');
    }

    public function unlink(Onu $onu)
    {
        $onu->update(['customer_id' => null]);

        return back()->with('success', 'ONU berhasil di-unlink dari pelanggan.');
    }

    public function linkCustomer(Request $request, Onu $onu)
    {
        $request->validate(['customer_id' => 'required|exists:customers,id']);

        $customer = Customer::findOrFail($request->customer_id);
        if ($customer->type !== 'hotspot') {
            return back()->with('error', 'Hanya pelanggan hotspot yang bisa di-link.');
        }

        $onu->update(['customer_id' => $customer->id]);

        return back()->with('success', "ONU berhasil di-link ke {$customer->name}.");
    }

    public function syncFromOlt(Request $request)
    {
        $olts = Olt::where('status', 'active')->get();
        if ($olts->isEmpty()) {
            return back()->with('error', 'Tidak ada OLT aktif.');
        }

        $totalSynced = 0;
        foreach ($olts as $olt) {
            try {
                $connector = OltConnectorFactory::make($olt->brand, $olt);
                $ports = $olt->ports()->get();

                foreach ($ports as $port) {
                    $onuList = $connector->getOnuList($port->slot_number, $port->port_number);
                    if (! is_array($onuList)) {
                        continue;
                    }

                    foreach ($onuList as $onuData) {
                        $existing = Onu::where('olt_port_id', $port->id)
                            ->where('onu_id', $onuData['onu_id'] ?? $onuData['id'] ?? null)
                            ->first();

                        if ($existing) {
                            $existing->update([
                                'status' => $onuData['status'] ?? $existing->status,
                                'rx_power' => $onuData['rx_power'] ?? $existing->rx_power,
                                'tx_power' => $onuData['tx_power'] ?? $existing->tx_power,
                                'distance' => $onuData['distance'] ?? $existing->distance,
                                'last_seen_at' => now(),
                            ]);
                        } else {
                            $serial = $onuData['serial_number'] ?? $onuData['sn'] ?? null;
                            $customer = null;
                            if ($serial) {
                                $customer = Customer::where('type', 'hotspot')
                                    ->where('serial_number', $serial)
                                    ->first();
                            }

                            Onu::create([
                                'olt_port_id' => $port->id,
                                'customer_id' => $customer?->id,
                                'onu_id' => $onuData['onu_id'] ?? $onuData['id'] ?? null,
                                'serial_number' => $serial,
                                'caller_id' => $onuData['caller_id'] ?? null,
                                'vendor' => $onuData['vendor'] ?? null,
                                'model' => $onuData['model'] ?? null,
                                'mac_address' => $onuData['mac_address'] ?? null,
                                'status' => $onuData['status'] ?? 'unknown',
                                'rx_power' => $onuData['rx_power'] ?? null,
                                'tx_power' => $onuData['tx_power'] ?? null,
                                'distance' => $onuData['distance'] ?? null,
                                'slot_number' => $port->slot_number,
                                'port_number' => $port->port_number,
                                'last_seen_at' => now(),
                            ]);
                            $totalSynced++;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error("ONU Hotspot sync failed for OLT {$olt->name}: {$e->getMessage()}");
            }
        }

        return back()->with('success', "Sync selesai. {$totalSynced} ONU baru ditemukan.");
    }
}
