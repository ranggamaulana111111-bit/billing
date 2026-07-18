<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Models\RouterosSyncedConfig;
use App\Models\RouterosSyncLog;
use App\Services\Mikrotik\Config\ConfigModuleRegistry;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use App\Services\Mikrotik\Sync\RouterosConfigSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigModuleController extends Controller
{
    /**
     * Configuration Center — all modules overview.
     */
    public function modules()
    {
        $moduleGroups = ConfigModuleRegistry::getByCategory();
        $categories = ConfigModuleRegistry::categories();

        $routers = MikrotikRouter::where('is_active', true)->get();
        $lastSync = RouterosSyncLog::latest('completed_at')->first();

        return view('noc.config.modules', compact('moduleGroups', 'categories', 'routers', 'lastSync'));
    }

    /**
     * Module data listing — fetch live data from a specific router.
     */
    public function index(Request $request, string $module)
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        abort_unless($moduleDef, 404);

        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        $items = [];
        $error = null;
        $syncedCount = RouterosSyncedConfig::where('mikrotik_router_id', $router->id)
            ->where('module', $module)
            ->where('status', 'active')
            ->count();

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($moduleDef['path']));

            if ($result->isSuccess()) {
                $items = $result->toArray();
                if (! is_array($items)) {
                    $items = [];
                }
                if ($moduleDef['keyField'] === '__singleton__') {
                    $items = [$items];
                }
            } else {
                $error = $result->getMessage();
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $recentLogs = RouterosSyncLog::where('mikrotik_router_id', $router->id)
            ->latest('started_at')
            ->limit(5)
            ->get();

        return view('noc.config.index', [
            'module' => $module,
            'moduleDef' => $moduleDef,
            'router' => $router,
            'items' => $items,
            'error' => $error,
            'routers' => $routers,
            'syncedCount' => $syncedCount,
            'recentLogs' => $recentLogs,
        ]);
    }

    /**
     * Module item detail — single item configuration from router.
     */
    public function detail(Request $request, string $module)
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        abort_unless($moduleDef, 404);

        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        $itemId = $request->input('item_id');
        $isSingleton = ($moduleDef['keyField'] ?? '') === '__singleton__';

        $item = null;
        $error = null;

        try {
            $service = new RouterConnectionService($router);
            $path = $isSingleton ? $moduleDef['path'] : $moduleDef['path'].'/'.$itemId;
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($path));

            if ($result->isSuccess()) {
                $data = $result->toArray();
                if (is_array($data)) {
                    $item = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;
                } else {
                    $item = $data;
                }
            } else {
                $error = $result->getMessage();
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $syncedConfig = null;
        if ($itemId) {
            $syncedConfig = RouterosSyncedConfig::where('mikrotik_router_id', $router->id)
                ->where('module', $module)
                ->where('item_id', $itemId)
                ->first();
        }

        return view('noc.config.detail', [
            'module' => $module,
            'moduleDef' => $moduleDef,
            'router' => $router,
            'item' => $item,
            'itemId' => $itemId ?? $module,
            'error' => $error,
            'syncedConfig' => $syncedConfig,
        ]);
    }

    /**
     * Show create form.
     */
    public function create(Request $request, string $module)
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        abort_unless($moduleDef, 404);
        abort_unless(ConfigModuleRegistry::isWritable($module), 403);

        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        $fields = ConfigModuleRegistry::getCreateFields($module);

        return view('noc.config.create', [
            'module' => $module,
            'moduleDef' => $moduleDef,
            'router' => $router,
            'fields' => $fields,
        ]);
    }

    /**
     * Store new item on the router.
     */
    public function store(Request $request, string $module)
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        abort_unless($moduleDef, 404);
        abort_unless(ConfigModuleRegistry::isWritable($module), 403);

        $fields = ConfigModuleRegistry::getCreateFields($module);
        $rules = [];
        foreach ($fields as $field) {
            if ($field['required'] ?? false) {
                $rules[$field['name']] = 'required';
            }
        }
        $request->validate($rules);

        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        $data = $this->filterFields($request, $fields);

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPut($moduleDef['path'], $data));

            if ($result->isSuccess()) {
                return redirect()->route('noc.config.module', ['module' => $module, 'router_id' => $router->id])
                    ->with('success', 'Item berhasil ditambahkan ke router.');
            }

            return back()->withInput()
                ->with('error', 'Gagal membuat item: '.$result->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Error: '.$e->getMessage());
        }
    }

    /**
     * Show edit form.
     */
    public function edit(Request $request, string $module)
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        abort_unless($moduleDef, 404);
        abort_unless(ConfigModuleRegistry::isWritable($module), 403);

        $itemId = $request->input('item_id');
        abort_unless($itemId, 404);

        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        $item = null;
        $error = null;

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($moduleDef['path'].'/'.$itemId));

            if ($result->isSuccess()) {
                $data = $result->toArray();
                $item = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data[0] : $data;
            } else {
                $error = $result->getMessage();
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $fields = ConfigModuleRegistry::getCreateFields($module);

        return view('noc.config.edit', [
            'module' => $module,
            'moduleDef' => $moduleDef,
            'router' => $router,
            'item' => $item,
            'itemId' => $itemId,
            'fields' => $fields,
            'error' => $error,
        ]);
    }

    /**
     * Update existing item on the router.
     */
    public function update(Request $request, string $module)
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        abort_unless($moduleDef, 404);
        abort_unless(ConfigModuleRegistry::isWritable($module), 403);

        $itemId = $request->input('item_id');
        abort_unless($itemId, 404);

        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        $fields = ConfigModuleRegistry::getCreateFields($module);
        $data = $this->filterFields($request, $fields);

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawPatch($moduleDef['path'].'/'.$itemId, $data));

            if ($result->isSuccess()) {
                return redirect()->route('noc.config.detail', ['module' => $module, 'item_id' => $itemId, 'router_id' => $router->id])
                    ->with('success', 'Item berhasil diupdate di router.');
            }

            return back()->withInput()
                ->with('error', 'Gagal update item: '.$result->getMessage());
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('error', 'Error: '.$e->getMessage());
        }
    }

    /**
     * Delete item from the router.
     */
    public function destroy(Request $request, string $module)
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        abort_unless($moduleDef, 404);
        abort_unless(ConfigModuleRegistry::isWritable($module), 403);

        $itemId = $request->input('item_id');
        abort_unless($itemId, 404);

        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawDelete($moduleDef['path'].'/'.$itemId));

            if ($result->isSuccess()) {
                return redirect()->route('noc.config.module', ['module' => $module, 'router_id' => $router->id])
                    ->with('success', 'Item berhasil dihapus dari router.');
            }

            return back()->with('error', 'Gagal menghapus item: '.$result->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Error: '.$e->getMessage());
        }
    }

    /**
     * Live API — JSON endpoint for auto-refresh.
     */
    public function liveApi(Request $request, string $module): JsonResponse
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        if (! $moduleDef) {
            return response()->json(['error' => 'Module not found'], 404);
        }

        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();

        if (! $router) {
            return response()->json(['error' => 'No active router', 'items' => []]);
        }

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet($moduleDef['path']));

            if ($result->isSuccess()) {
                $items = $result->toArray();
                if (! is_array($items)) {
                    $items = [];
                }
                if ($moduleDef['keyField'] === '__singleton__') {
                    $items = [$items];
                }

                return response()->json([
                    'items' => $items,
                    'count' => count($items),
                    'router' => $router->display_identity,
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            return response()->json([
                'error' => $result->getMessage(),
                'items' => [],
                'router' => $router->display_identity,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'items' => []]);
        }
    }

    /**
     * Sync a specific module for a router.
     */
    public function syncModule(Request $request, string $module)
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        abort_unless($moduleDef, 404);

        $request->validate(['router_id' => 'required|exists:mikrotik_routers,id']);
        $router = MikrotikRouter::findOrFail($request->input('router_id'));

        $service = new RouterosConfigSyncService;
        $result = $service->syncRouter($router, 'manual', $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        $status = $result['success'] ? 'success' : 'warning';

        return redirect()->route('noc.config.module', ['module' => $module, 'router_id' => $router->id])
            ->with($status, "Module {$moduleDef['label']} synced: {$result['stats']['total']} items");
    }

    /**
     * Sync all modules for a specific router.
     */
    public function syncAll(Request $request)
    {
        $request->validate(['router_id' => 'required|exists:mikrotik_routers,id']);
        $router = MikrotikRouter::findOrFail($request->input('router_id'));

        $service = new RouterosConfigSyncService;
        $result = $service->syncRouter($router, 'manual', $request->user()->id);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->route('noc.sync.dashboard')
            ->with($result['success'] ? 'success' : 'warning', $result['status'].': '.$result['stats']['total'].' items synced');
    }

    /**
     * Change history for a module on a router.
     */
    public function history(Request $request, string $module)
    {
        $moduleDef = ConfigModuleRegistry::get($module);
        abort_unless($moduleDef, 404);

        $routerId = $request->input('router_id');
        $logs = RouterosSyncLog::with('router')
            ->when($routerId, fn ($q) => $q->where('mikrotik_router_id', $routerId))
            ->latest('started_at')
            ->paginate(25);

        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.config.history', [
            'module' => $module,
            'moduleDef' => $moduleDef,
            'logs' => $logs,
            'routers' => $routers,
        ]);
    }

    /**
     * Filter request data to only include defined fields.
     */
    private function filterFields(Request $request, array $fields): array
    {
        $data = [];
        foreach ($fields as $field) {
            $value = $request->input($field['name']);
            if ($value !== null && $value !== '') {
                $data[$field['name']] = $value;
            }
        }

        return $data;
    }
}
