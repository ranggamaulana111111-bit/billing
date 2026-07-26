<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Incident;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Services\IncidentNotificationService;
use Illuminate\Http\Request;

class OdpController extends Controller
{
    public function show(Odp $odp)
    {
        $odp->load([
            'ports.customer.package',
            'odc',
            'connectedOdcPort.odc',
        ]);

        return view('odp.show', compact('odp'));
    }

    public function toggleJalur(Request $request, Odp $odp)
    {
        $status = $request->input('status');

        $allowed = ['UP', 'GANGGUAN', 'DOWN_LINK_FAILURE'];
        if (! in_array($status, $allowed)) {
            return back()->with('error', 'Status tidak valid.');
        }

        $prevStatus = $odp->kondisi_jalur;
        $odp->update(['kondisi_jalur' => $status]);

        $label = match ($status) {
            'UP' => 'NORMAL',
            'GANGGUAN' => 'GANGGUAN',
            'DOWN_LINK_FAILURE' => 'PUTUS',
        };

        ActivityLog::log('Ubah Kondisi Jalur', "ODP {$odp->nama_odp} → {$label}");

        if (in_array($status, ['GANGGUAN', 'DOWN_LINK_FAILURE']) && $prevStatus === 'UP') {
            $this->createIncidentForManualToggle($odp, $status);
        }

        if ($status === 'UP' && $prevStatus !== 'UP') {
            $this->resolveOpenIncidents($odp);
        }

        return back()->with('success', "Kondisi jalur {$odp->nama_odp} diubah ke {$label}.");
    }

    private function createIncidentForManualToggle(Odp $odp, string $status): void
    {
        $severity = $status === 'DOWN_LINK_FAILURE' ? 'high' : 'medium';
        $title = $status === 'DOWN_LINK_FAILURE'
            ? "Kabel distribusi putus - ODP {$odp->nama_odp}"
            : "Gangguan jalur ODP {$odp->nama_odp}";

        $customerIds = Customer::where('odp_id', $odp->id)
            ->where('status', 'active')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->pluck('id')
            ->values()
            ->all();

        $odcName = $odp->odc?->nama_odc ?? '-';
        $tube = $odp->kabel_tube_color ?? '-';
        $core = $odp->kabel_core_number ?? '-';
        $offlineCount = count($customerIds);

        $incident = Incident::create([
            'tenant_id' => $odp->tenant_id,
            'title' => $title,
            'description' => "Ditetapkan manual oleh admin. ODC: {$odcName}, Tube: {$tube}, Core: {$core}. {$offlineCount} pelanggan terdampak.",
            'type' => 'manual',
            'source' => 'admin',
            'severity' => $severity,
            'status' => 'open',
            'odp_id' => $odp->id,
            'odc_id' => $odp->odc_id,
            'created_by' => auth()->id(),
            'detected_at' => now(),
            'sla_deadline' => now()->addHours(Incident::slaHoursForSeverity($severity)),
            'sla_status' => 'on_track',
            'notifiable_customer_ids' => $customerIds,
        ]);

        (new IncidentNotificationService($odp->tenant_id))->notifyCreated($incident, $customerIds);
    }

    private function resolveOpenIncidents(Odp $odp): void
    {
        $incidents = Incident::where('odp_id', $odp->id)
            ->whereIn('status', ['open', 'investigating'])
            ->get();

        foreach ($incidents as $incident) {
            $incident->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'sla_status' => $incident->sla_deadline && now()->lte($incident->sla_deadline) ? 'met' : 'breached',
            ]);

            (new IncidentNotificationService($incident->tenant_id))->notifyStatusChange($incident, 'resolved');
        }

        if ($odp->kondisi_jalur === 'UP') {
            OdpPort::withoutGlobalScopes()
                ->where('odp_id', $odp->id)
                ->where('status', 'broken')
                ->update(['status' => 'used']);
        }
    }
}
