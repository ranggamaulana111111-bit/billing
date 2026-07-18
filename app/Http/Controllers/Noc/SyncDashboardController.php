<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Models\RouterosSyncedConfig;
use App\Models\RouterosSyncLog;
use App\Services\Mikrotik\Sync\ConfigSyncModuleRegistry;
use App\Services\Mikrotik\Sync\RouterosConfigSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncDashboardController extends Controller
{
    /**
     * Sync dashboard — overview of all routers' sync status.
     */
    public function dashboard()
    {
        $summary = RouterosConfigSyncService::getSyncSummary();
        $recentLogs = RouterosConfigSyncService::getRecentLogs(10);
        $modules = ConfigSyncModuleRegistry::all();

        return view('noc.sync.dashboard', compact('summary', 'recentLogs', 'modules'));
    }

    /**
     * Sync logs — full history with filtering.
     */
    public function logs(Request $request)
    {
        $query = RouterosSyncLog::with('router')->latest('started_at');

        if ($request->filled('router_id')) {
            $query->where('mikrotik_router_id', $request->input('router_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('sync_type')) {
            $query->where('sync_type', $request->input('sync_type'));
        }
        if ($request->filled('date_from')) {
            $query->where('started_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('started_at', '<=', $request->input('date_to').' 23:59:59');
        }

        $logs = $query->paginate(25)->withQueryString();
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.sync.logs', compact('logs', 'routers'));
    }

    /**
     * Sync a specific router manually.
     */
    public function syncNow(Request $request)
    {
        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
        ]);

        $router = MikrotikRouter::findOrFail($request->input('router_id'));

        $service = new RouterosConfigSyncService;
        $result = $service->syncRouter($router, 'manual', $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        $status = $result['success'] ? 'success' : 'warning';
        $message = $result['success']
            ? "Sync completed for {$result['router_name']}: {$result['stats']['total']} items ({$result['stats']['new']} new, {$result['stats']['updated']} updated)"
            : "Sync failed for {$result['router_name']}: {$result['status']}";

        return redirect()->route('noc.sync.dashboard')
            ->with($status, $message);
    }

    /**
     * Sync all active routers manually.
     */
    public function syncAll(Request $request)
    {
        $service = new RouterosConfigSyncService;
        $result = $service->sync(null, null, 'manual', $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->route('noc.sync.dashboard')
            ->with('success', $result['message']);
    }

    /**
     * Synced configs — browse current stored configs per router/module.
     */
    public function configs(Request $request)
    {
        $query = RouterosSyncedConfig::with('router')->latest('last_synced_at');

        if ($request->filled('router_id')) {
            $query->where('mikrotik_router_id', $request->input('router_id'));
        }
        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('item_id', 'like', "%{$search}%");
            });
        }

        $configs = $query->paginate(50)->withQueryString();
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $modules = ConfigSyncModuleRegistry::all();

        return view('noc.sync.configs', compact('configs', 'routers', 'modules'));
    }

    /**
     * Live API endpoint for auto-refresh.
     */
    public function liveApi(): JsonResponse
    {
        $summary = RouterosConfigSyncService::getSyncSummary();

        return response()->json([
            'router_statuses' => $summary['router_statuses'],
            'conflict_count' => $summary['conflict_count'],
            'total_synced_items' => $summary['total_synced_items'],
            'recent_failures_24h' => $summary['recent_failures_24h'],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
