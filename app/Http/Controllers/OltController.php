<?php

namespace App\Http\Controllers;

use App\Models\Olt;
use App\Models\OltPort;
use App\Models\Onu;
use App\Services\Olt\Factory\OltConnectorFactory;
use Illuminate\Http\Request;

class OltController extends Controller
{
    public function index()
    {
        $olts = Olt::orderBy('name')->get();
        $totalOnus = Onu::count();
        $onlineOnus = Onu::where('status', 'online')->count();
        $offlineOnus = Onu::where('status', 'offline')->count();

        return view('olt.index', compact('olts', 'totalOnus', 'onlineOnus', 'offlineOnus'));
    }

    public function create()
    {
        return view('olt.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|in:huawei,zte,fiberhome',
            'model' => 'nullable|string|max:255',
            'ip_address' => 'required|string|max:45',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'snmp_community' => 'nullable|string|max:255',
            'snmp_version' => 'nullable|in:v1,v2c,v3',
            'snmp_port' => 'required|integer|min:1|max:65535',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:active,maintenance,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        Olt::create($validated);

        return redirect()->route('olt.index')->with('success', 'OLT berhasil ditambahkan.');
    }

    public function show(Olt $olt)
    {
        $olt->load('ports.onus.customer');
        $totalPorts = $olt->ports->count();
        $totalOnus = Onu::whereIn('olt_port_id', $olt->ports->pluck('id'))->count();
        $onlineOnus = Onu::whereIn('olt_port_id', $olt->ports->pluck('id'))->where('status', 'online')->count();

        return view('olt.show', compact('olt', 'totalPorts', 'totalOnus', 'onlineOnus'));
    }

    public function edit(Olt $olt)
    {
        return view('olt.edit', compact('olt'));
    }

    public function update(Request $request, Olt $olt)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|in:huawei,zte,fiberhome',
            'model' => 'nullable|string|max:255',
            'ip_address' => 'required|string|max:45',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'snmp_community' => 'nullable|string|max:255',
            'snmp_version' => 'nullable|in:v1,v2c,v3',
            'snmp_port' => 'required|integer|min:1|max:65535',
            'location' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'required|in:active,maintenance,inactive',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (! $request->filled('password')) {
            unset($validated['password']);
        }

        $olt->update($validated);

        return redirect()->route('olt.index')->with('success', 'OLT berhasil diperbarui.');
    }

    public function destroy(Olt $olt)
    {
        $olt->delete();

        return redirect()->route('olt.index')->with('success', 'OLT berhasil dihapus.');
    }

    public function testConnection(Olt $olt)
    {
        try {
            $connector = OltConnectorFactory::make($olt->brand);
            $connected = $connector->connect(
                $olt->ip_address,
                $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            if (! $connected) {
                return back()->with('error', 'Gagal terhubung ke OLT.');
            }

            $result = $connector->testConnection();
            $connector->disconnect();

            if ($result['success']) {
                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Exception $e) {
            return back()->with('error', 'Koneksi gagal: '.$e->getMessage());
        }
    }

    public function scanOnus(Olt $olt)
    {
        try {
            $connector = OltConnectorFactory::make($olt->brand);
            $connector->connect(
                $olt->ip_address,
                $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            $ports = $olt->ports;
            $totalFound = 0;

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

                    $totalFound++;
                }
            }

            $connector->disconnect();
            $olt->update(['last_polled_at' => now()]);

            return back()->with('success', "Scan selesai. {$totalFound} ONU ditemukan.");
        } catch (\Exception $e) {
            return back()->with('error', 'Scan gagal: '.$e->getMessage());
        }
    }

    public function rebootOnu(Olt $olt, Onu $onu)
    {
        try {
            $connector = OltConnectorFactory::make($olt->brand);
            $connector->connect(
                $olt->ip_address,
                $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            $result = $connector->rebootOnu($onu->onu_id);
            $connector->disconnect();

            if ($result['success']) {
                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Exception $e) {
            return back()->with('error', 'Reboot gagal: '.$e->getMessage());
        }
    }

    public function removeOnu(Olt $olt, Onu $onu)
    {
        try {
            $connector = OltConnectorFactory::make($olt->brand);
            $connector->connect(
                $olt->ip_address,
                $olt->ssh_port,
                $olt->username,
                $olt->password
            );

            $result = $connector->removeOnu($onu->onu_id);
            $connector->disconnect();

            if ($result['success']) {
                $onu->delete();

                return back()->with('success', $result['message']);
            }

            return back()->with('error', $result['message']);
        } catch (\Exception $e) {
            return back()->with('error', 'Hapus ONU gagal: '.$e->getMessage());
        }
    }

    public function linkCustomer(Request $request, Onu $onu)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $onu->update(['customer_id' => $validated['customer_id']]);

        return back()->with('success', 'ONU berhasil ditautkan ke pelanggan.');
    }

    public function syncPorts(Olt $olt)
    {
        $request = request();
        $validated = $request->validate([
            'ports' => 'required|array',
            'ports.*.slot' => 'required|integer',
            'ports.*.port' => 'required|integer',
            'ports.*.type' => 'required|string',
        ]);

        foreach ($validated['ports'] as $portData) {
            OltPort::firstOrCreate(
                [
                    'olt_id' => $olt->id,
                    'slot_number' => $portData['slot'],
                    'port_number' => $portData['port'],
                ],
                [
                    'port_type' => $portData['type'] ?? 'gpon',
                    'status' => 'active',
                ]
            );
        }

        return back()->with('success', 'Port berhasil disinkronkan.');
    }
}
