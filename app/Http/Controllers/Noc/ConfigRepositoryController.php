<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\Mikrotik\Config\ConfigRepositoryService;
use App\Services\Mikrotik\Sync\ConfigSyncModuleRegistry;
use Illuminate\Http\Request;

class ConfigRepositoryController extends Controller
{
    /**
     * Repository overview — stats + recent changes.
     */
    public function index(Request $request)
    {
        $routerId = $request->input('router_id');
        $stats = ConfigRepositoryService::getStats($routerId);
        $recentChanges = ConfigRepositoryService::getRecentChanges($routerId, limit: 20);
        $changedItems = ConfigRepositoryService::getChangedItems($routerId, limit: 15);
        $moduleSummary = ConfigRepositoryService::getModuleChangeSummary($routerId);

        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $modules = ConfigSyncModuleRegistry::all();

        return view('noc.repo.index', compact(
            'stats',
            'recentChanges',
            'changedItems',
            'moduleSummary',
            'routers',
            'modules',
            'routerId',
        ));
    }

    /**
     * Version history for a specific item.
     */
    public function itemHistory(Request $request, int $routerId, string $module, string $itemId)
    {
        $versions = ConfigRepositoryService::getItemVersions($routerId, $module, $itemId);
        $latest = ConfigRepositoryService::getLatestVersion($routerId, $module, $itemId);
        $router = MikrotikRouter::find($routerId);
        $moduleDef = ConfigSyncModuleRegistry::get($module);

        abort_unless($router, 404);

        return view('noc.repo.item-history', compact(
            'versions',
            'latest',
            'router',
            'module',
            'moduleDef',
            'itemId',
        ));
    }

    /**
     * Compare two versions side-by-side.
     */
    public function compare(Request $request)
    {
        $fromId = $request->input('from');
        $toId = $request->input('to');

        if (! $fromId || ! $toId) {
            return redirect()->route('noc.repo.index')
                ->with('warning', 'Please select two versions to compare');
        }

        $comparison = ConfigRepositoryService::compareVersions((int) $fromId, (int) $toId);

        return view('noc.repo.compare', $comparison);
    }

    /**
     * Show a single version detail with full config snapshot.
     */
    public function show(int $id)
    {
        $version = ConfigRepositoryService::getVersionById($id);
        abort_unless($version, 404);

        return view('noc.repo.show', compact('version'));
    }

    /**
     * Recent changes — full list with filters.
     */
    public function changes(Request $request)
    {
        $changes = ConfigRepositoryService::getRecentChanges(
            routerId: $request->input('router_id'),
            module: $request->input('module'),
            source: $request->input('source'),
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
            limit: 30,
        );

        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $modules = ConfigSyncModuleRegistry::all();
        $sources = ['sync', 'manual', 'api', 'script'];

        return view('noc.repo.changes', compact(
            'changes',
            'routers',
            'modules',
            'sources',
        ));
    }
}
