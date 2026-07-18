<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Olt;
use App\Models\Onu;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HotspotCustomerController extends Controller
{
    public function index(Request $request)
    {
        $scanDone = $request->boolean('scanned');

        $onus = Onu::with(['oltPort.olt'])
            ->whereNull('customer_id')
            ->latest('last_seen_at')
            ->get();

        $oltList = Olt::where('status', 'active')->orderBy('name')->get();

        $stats = [
            'unlinked' => $onus->count(),
            'online' => $onus->where('status', 'online')->count(),
            'offline' => $onus->where('status', 'offline')->count(),
        ];

        return view('hotspot-customers.index', compact('onus', 'oltList', 'stats', 'scanDone'));
    }

    public function scan(Request $request, Olt $olt)
    {
        try {
            $connector = OltConnectorFactory::make($olt->brand, $olt);
            $connected = $connector->connect(
                $olt->ip_address,
                $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            if (! $connected) {
                return back()->with('error', "SSH login ditolak oleh {$olt->ip_address}.");
            }

            $ports = $olt->ports()->get();
            $totalFound = 0;

            foreach ($ports as $port) {
                try {
                    $onuList = $connector->getOnuList($port->slot_number, $port->port_number);
                    if (! is_array($onuList)) {
                        continue;
                    }

                    foreach ($onuList as $onuData) {
                        $serial = $onuData['sn'] ?? null;
                        $onuId = $onuData['onu_id'] ?? $onuData['id'] ?? null;

                        $existing = Onu::where('olt_port_id', $port->id)
                            ->where('onu_id', $onuId)
                            ->first();

                        if ($existing) {
                            try {
                                $optical = $connector->getOpticalPower($existing->onu_id);
                            } catch (\Exception $e) {
                                $optical = ['rx_power' => null, 'tx_power' => null];
                            }

                            $existing->update([
                                'serial_number' => $serial ?? $existing->serial_number,
                                'status' => $onuData['status'] ?? $existing->status,
                                'rx_power' => $optical['rx_power'] ?? $existing->rx_power,
                                'tx_power' => $optical['tx_power'] ?? $existing->tx_power,
                                'caller_id' => $onuData['caller_id'] ?? $existing->caller_id,
                                'last_seen_at' => now(),
                            ]);
                        } else {
                            Onu::create([
                                'olt_port_id' => $port->id,
                                'onu_id' => $onuId,
                                'serial_number' => $serial,
                                'caller_id' => $onuData['caller_id'] ?? null,
                                'vendor' => $onuData['vendor'] ?? null,
                                'model' => $onuData['model'] ?? null,
                                'mac_address' => $onuData['mac_address'] ?? null,
                                'status' => $onuData['status'] ?? 'unknown',
                                'slot_number' => $port->slot_number,
                                'port_number' => $port->port_number,
                                'last_seen_at' => now(),
                            ]);
                        }

                        $totalFound++;
                    }
                } catch (\Exception $e) {
                    Log::warning("Scan port {$port->slot_number}/{$port->port_number} gagal: {$e->getMessage()}");
                }
            }

            $connector->disconnect();
            $olt->update(['last_polled_at' => now()]);

            return redirect()->route('hotspot-customers.index', ['scanned' => 1])
                ->with('success', "Scan {$olt->name} selesai. {$totalFound} ONU ditemukan.");
        } catch (\Exception $e) {
            Log::error("OLT scan failed for {$olt->name}: {$e->getMessage()}");

            return back()->with('error', "Gagal scan {$olt->name}: {$e->getMessage()}");
        }
    }

    public function create(Request $request)
    {
        $onuId = $request->input('onu_id');
        $onu = $onuId ? Onu::with(['oltPort.olt'])->find($onuId) : null;

        return view('hotspot-customers.create', compact('onu'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'location' => 'nullable|string|max:255',
            'onu_id' => 'nullable|exists:onus,id',
        ]);

        $customer = Customer::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'location' => $validated['location'] ?? null,
            'type' => 'hotspot',
        ]);

        if (! empty($validated['onu_id'])) {
            $onu = Onu::find($validated['onu_id']);
            if ($onu && ! $onu->customer_id) {
                $onu->update(['customer_id' => $customer->id]);
                if ($onu->serial_number) {
                    $customer->update(['serial_number' => $onu->serial_number]);
                }
            }
        }

        ActivityLog::log('Tambah Pelanggan Hotspot', 'Menambahkan pelanggan hotspot: '.$customer->name);

        return redirect()->route('hotspot-customers.index')
            ->with('success', 'Pelanggan hotspot '.$customer->name.' berhasil ditambahkan!');
    }
}
