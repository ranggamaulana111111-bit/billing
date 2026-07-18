<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\Mikrotik\Config\NetworkConfigService;
use App\Services\Mikrotik\Sync\RouterosConfigSyncService;
use Illuminate\Http\Request;

class NetworkConfigController extends Controller
{
    // ── Dashboard ──

    public function dashboard(Request $request)
    {
        $routerId = $request->input('router_id');
        $stats = NetworkConfigService::getDashboardStats($routerId);
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.netconfig.dashboard', compact('stats', 'routers', 'routerId'));
    }

    // ── Generic Resource Listing ──

    public function index(Request $request, string $resource)
    {
        $def = NetworkConfigService::getResourceDef($resource);
        abort_unless($def, 404);

        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        $items = [];
        $error = null;

        try {
            $result = NetworkConfigService::list($router, $resource);
            $items = $result['items'];
            $error = $result['error'];
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $auditLogs = NetworkConfigService::getAuditLogs($resource, $router->id, limit: 5);

        return view('noc.netconfig.index', compact(
            'resource', 'def', 'router', 'items', 'error', 'routers', 'auditLogs',
        ));
    }

    // ── Generic CRUD ──

    public function store(Request $request, string $resource)
    {
        $def = NetworkConfigService::getResourceDef($resource);
        abort_unless($def, 404);

        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
        ]);

        $router = MikrotikRouter::find($request->input('router_id'));
        $data = $request->except(['router_id', '_token']);
        $userId = $request->user()->id;

        $result = NetworkConfigService::create($router, $resource, $data, $userId);

        $status = $result['success'] ? 'success' : 'danger';
        $message = $result['success']
            ? "Created {$def['label']} successfully"
            : "Failed: {$result['error']}";

        if ($request->expectsJson()) {
            return response()->json($result, $result['success'] ? 201 : 422);
        }

        return redirect()->route('noc.netconfig.index', ['resource' => $resource, 'router_id' => $router->id])
            ->with($status, $message);
    }

    public function update(Request $request, string $resource, string $itemId)
    {
        $def = NetworkConfigService::getResourceDef($resource);
        abort_unless($def, 404);

        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
        ]);

        $router = MikrotikRouter::find($request->input('router_id'));
        $data = $request->except(['router_id', '_token', '_method']);
        $userId = $request->user()->id;

        $result = NetworkConfigService::update($router, $resource, $itemId, $data, $userId);

        $status = $result['success'] ? 'success' : 'danger';
        $message = $result['success']
            ? "Updated {$def['label']} successfully"
            : "Failed: {$result['error']}";

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->route('noc.netconfig.index', ['resource' => $resource, 'router_id' => $router->id])
            ->with($status, $message);
    }

    public function destroy(Request $request, string $resource, string $itemId)
    {
        $def = NetworkConfigService::getResourceDef($resource);
        abort_unless($def, 404);

        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
        ]);

        $router = MikrotikRouter::find($request->input('router_id'));
        $userId = $request->user()->id;

        $result = NetworkConfigService::delete($router, $resource, $itemId, $userId);

        $status = $result['success'] ? 'success' : 'danger';
        $message = $result['success']
            ? "Deleted {$def['label']} successfully"
            : "Failed: {$result['error']}";

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->route('noc.netconfig.index', ['resource' => $resource, 'router_id' => $router->id])
            ->with($status, $message);
    }

    public function toggle(Request $request, string $resource, string $itemId)
    {
        $def = NetworkConfigService::getResourceDef($resource);
        abort_unless($def, 404);

        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
            'disable' => 'required|boolean',
        ]);

        $router = MikrotikRouter::find($request->input('router_id'));
        $disable = $request->boolean('disable');
        $userId = $request->user()->id;

        $result = NetworkConfigService::toggle($router, $resource, $itemId, $disable, $userId);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        $action = $disable ? 'Disabled' : 'Enabled';

        return back()->with($result['success'] ? 'success' : 'danger',
            $result['success'] ? "{$action} successfully" : "Failed: {$result['error']}");
    }

    // ── Bulk Operations ──

    public function bulk(Request $request, string $resource)
    {
        $def = NetworkConfigService::getResourceDef($resource);
        abort_unless($def, 404);

        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
            'action' => 'required|in:enable,disable,delete',
            'item_ids' => 'required|array|min:1',
        ]);

        $router = MikrotikRouter::find($request->input('router_id'));
        $userId = $request->user()->id;

        $result = NetworkConfigService::bulkOperation(
            $router,
            $resource,
            $request->input('action'),
            $request->input('item_ids'),
            null,
            $userId,
        );

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        $message = "{$result['success']} succeeded, {$result['failed']} failed";

        return redirect()->route('noc.netconfig.index', ['resource' => $resource, 'router_id' => $router->id])
            ->with($result['failed'] > 0 ? 'warning' : 'success', $message);
    }

    // ── Sync ──

    public function sync(Request $request, string $resource)
    {
        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
        ]);

        $router = MikrotikRouter::find($request->input('router_id'));

        $syncService = new RouterosConfigSyncService;
        $moduleMap = ['bridge' => 'bridge', 'vlan' => 'vlan', 'ip_address' => 'ip_address'];
        $result = $syncService->sync($router, [$moduleMap[$resource] ?? $resource], 'manual', $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with($result['success'] ? 'success' : 'warning', $result['message']);
    }

    // ── Audit Logs ──

    public function auditLogs(Request $request)
    {
        $logs = NetworkConfigService::getAuditLogs(
            resourceType: $request->input('resource_type'),
            routerId: $request->input('router_id'),
            action: $request->input('action'),
            limit: 30,
        );

        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.netconfig.audit', compact('logs', 'routers'));
    }
}
