<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Incident;
use App\Models\Odp;
use App\Services\IncidentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $query = Incident::with(['odp', 'odc', 'assignee', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        $incidents = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => Incident::count(),
            'open' => Incident::where('status', 'open')->count(),
            'investigating' => Incident::where('status', 'investigating')->count(),
            'breached' => Incident::where('sla_status', 'breached')->count(),
        ];

        return view('incidents.index', compact('incidents', 'stats'));
    }

    public function create()
    {
        $odps = Odp::with(['customers' => function ($q) {
            $q->where('status', 'active')->whereNotNull('phone')->where('phone', '!=', '');
        }, 'odc'])->orderBy('nama_odp')->get();

        return view('incidents.create', compact('odps'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'severity' => 'required|in:low,medium,high,critical',
            'odp_id' => 'nullable|exists:odps,id',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        $odp = $validated['odp_id'] ? Odp::withoutGlobalScopes()->find($validated['odp_id']) : null;

        $incident = Incident::create([
            'tenant_id' => auth()->user()->tenant_id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => 'manual',
            'source' => 'admin',
            'severity' => $validated['severity'],
            'status' => 'open',
            'odp_id' => $validated['odp_id'] ?? null,
            'odc_id' => $odp?->odc_id,
            'created_by' => auth()->id(),
            'detected_at' => now(),
            'sla_deadline' => now()->addHours(Incident::slaHoursForSeverity($validated['severity'])),
            'sla_status' => 'on_track',
            'notifiable_customer_ids' => ! empty($validated['customer_ids']) ? $validated['customer_ids'] : null,
        ]);

        $customerIds = $incident->notifiable_customer_ids;

        Log::info('IncidentController@store', [
            'incident_id' => $incident->id,
            'odp_id' => $incident->odp_id,
            'validated_customer_ids' => $validated['customer_ids'] ?? null,
            'notifiable_customer_ids_saved' => $customerIds,
        ]);

        (new IncidentNotificationService)->notifyCreated($incident, $customerIds);

        ActivityLog::log('Incident Dibuat', "Incident #{$incident->id}: {$incident->title}");

        return redirect()->route('incidents.show', $incident)
            ->with('success', "Incident #{$incident->id} berhasil dibuat. Notifikasi terkirim.");
    }

    public function show(Incident $incident)
    {
        $incident->load(['odp', 'odc', 'assignee', 'creator', 'notifications' => function ($q) {
            $q->orderBy('created_at', 'desc');
        }]);

        $timeline = $this->buildTimeline($incident);

        return view('incidents.show', compact('incident', 'timeline'));
    }

    public function update(Request $request, Incident $incident)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'severity' => 'required|in:low,medium,high,critical',
            'odp_id' => 'nullable|exists:odps,id',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $odp = $validated['odp_id'] ? Odp::withoutGlobalScopes()->find($validated['odp_id']) : null;

        $incident->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'severity' => $validated['severity'],
            'odp_id' => $validated['odp_id'] ?? null,
            'odc_id' => $odp?->odc_id,
            'assigned_to' => $validated['assigned_to'] ?? null,
        ]);

        ActivityLog::log('Incident Diupdate', "Incident #{$incident->id}: {$incident->title}");

        return back()->with('success', 'Incident berhasil diupdate.');
    }

    public function investigating(Incident $incident)
    {
        $incident->update([
            'status' => 'investigating',
            'acknowledged_at' => now(),
        ]);

        (new IncidentNotificationService)->notifyStatusChange($incident, 'investigating');

        ActivityLog::log('Incident Investigating', "Incident #{$incident->id}: {$incident->title}");

        return back()->with('success', 'Status diubah ke Investigating. Notifikasi terkirim ke pelanggan.');
    }

    public function resolve(Incident $incident)
    {
        $incident->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'sla_status' => $incident->sla_deadline && now()->lte($incident->sla_deadline) ? 'met' : 'breached',
        ]);

        (new IncidentNotificationService)->notifyStatusChange($incident, 'resolved');

        ActivityLog::log('Incident Resolved', "Incident #{$incident->id}: {$incident->title}");

        return back()->with('success', 'Incident berhasil diselesaikan. Notifikasi resolved terkirim ke pelanggan.');
    }

    public function close(Incident $incident)
    {
        $incident->update(['status' => 'closed']);

        ActivityLog::log('Incident Ditutup', "Incident #{$incident->id}: {$incident->title}");

        return back()->with('success', 'Incident berhasil ditutup.');
    }

    protected function buildTimeline(Incident $incident): array
    {
        $events = [];

        $events[] = [
            'icon' => 'fa-solid fa-flag',
            'color' => 'primary',
            'title' => 'Incident Dibuat',
            'time' => $incident->detected_at,
            'detail' => 'Dibuat oleh '.$incident->creator?->name,
        ];

        if ($incident->acknowledged_at) {
            $events[] = [
                'icon' => 'fa-solid fa-magnifying-glass',
                'color' => 'info',
                'title' => 'Mulai Ditangani',
                'time' => $incident->acknowledged_at,
                'detail' => $incident->assignee ? 'Ditangani oleh '.$incident->assignee->name : null,
            ];
        }

        if ($incident->resolved_at) {
            $events[] = [
                'icon' => 'fa-solid fa-check-circle',
                'color' => 'success',
                'title' => 'Selesai',
                'time' => $incident->resolved_at,
                'detail' => $incident->sla_status === 'met' ? 'SLA terpenuhi' : 'SLA breached',
            ];
        }

        if ($incident->status === 'closed') {
            $events[] = [
                'icon' => 'fa-solid fa-lock',
                'color' => 'secondary',
                'title' => 'Ditutup',
                'time' => $incident->updated_at,
                'detail' => null,
            ];
        }

        return $events;
    }
}
