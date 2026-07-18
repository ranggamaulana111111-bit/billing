<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use App\Services\Mikrotik\TrafficEngineering\TrafficEngineeringManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrafficEngineeringController extends Controller
{
    public function dashboard(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $stats = TrafficEngineeringManager::getDashboardStats($router);
        $recommendations = TrafficEngineeringManager::validatePolicies($router);
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.traffic-engineering.dashboard', compact('router', 'stats', 'recommendations', 'routers'));
    }

    // ── Simple Queue ──

    public function simpleQueues(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $result = TrafficEngineeringManager::list($router, 'simple_queue');
        $queueTypes = TrafficEngineeringManager::list($router, 'queue_type');
        $extra = ['queueTypes' => $queueTypes['items'] ?? []];

        return $this->resourceView('noc.traffic-engineering.simple-queue', $router, $result, $extra);
    }

    public function simpleQueueStore(Request $request): RedirectResponse
    {
        return $this->storeAction($request, 'simple_queue', 'noc.traffic_eng.simple-queue');
    }

    public function simpleQueueUpdate(Request $request, string $itemId): RedirectResponse
    {
        return $this->updateAction($request, 'simple_queue', $itemId, 'noc.traffic_eng.simple-queue');
    }

    public function simpleQueueDestroy(Request $request, string $itemId): RedirectResponse
    {
        return $this->destroyAction($request, 'simple_queue', $itemId);
    }

    public function simpleQueueToggle(Request $request, string $itemId): RedirectResponse
    {
        return $this->toggleAction($request, 'simple_queue', $itemId);
    }

    public function simpleQueueMove(Request $request, string $itemId): RedirectResponse
    {
        return $this->moveAction($request, 'simple_queue', $itemId);
    }

    public function simpleQueueCopy(Request $request, string $itemId): RedirectResponse
    {
        return $this->copyAction($request, 'simple_queue', $itemId);
    }

    public function simpleQueueBulk(Request $request): RedirectResponse
    {
        return $this->bulkAction($request, 'simple_queue');
    }

    // ── Queue Tree ──

    public function queueTrees(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $result = TrafficEngineeringManager::list($router, 'queue_tree');
        $queueTypes = TrafficEngineeringManager::list($router, 'queue_type');
        $extra = ['queueTypes' => $queueTypes['items'] ?? []];

        return $this->resourceView('noc.traffic-engineering.queue-tree', $router, $result, $extra);
    }

    public function queueTreeStore(Request $request): RedirectResponse
    {
        return $this->storeAction($request, 'queue_tree', 'noc.traffic_eng.queue-tree');
    }

    public function queueTreeUpdate(Request $request, string $itemId): RedirectResponse
    {
        return $this->updateAction($request, 'queue_tree', $itemId, 'noc.traffic_eng.queue-tree');
    }

    public function queueTreeDestroy(Request $request, string $itemId): RedirectResponse
    {
        return $this->destroyAction($request, 'queue_tree', $itemId);
    }

    public function queueTreeToggle(Request $request, string $itemId): RedirectResponse
    {
        return $this->toggleAction($request, 'queue_tree', $itemId);
    }

    public function queueTreeBulk(Request $request): RedirectResponse
    {
        return $this->bulkAction($request, 'queue_tree');
    }

    // ── Queue Type ──

    public function queueTypes(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $result = TrafficEngineeringManager::list($router, 'queue_type');

        return $this->resourceView('noc.traffic-engineering.queue-type', $router, $result);
    }

    public function queueTypeStore(Request $request): RedirectResponse
    {
        return $this->storeAction($request, 'queue_type', 'noc.traffic_eng.queue-type');
    }

    public function queueTypeUpdate(Request $request, string $itemId): RedirectResponse
    {
        return $this->updateAction($request, 'queue_type', $itemId, 'noc.traffic_eng.queue-type');
    }

    public function queueTypeDestroy(Request $request, string $itemId): RedirectResponse
    {
        return $this->destroyAction($request, 'queue_type', $itemId);
    }

    public function queueTypeBulk(Request $request): RedirectResponse
    {
        return $this->bulkAction($request, 'queue_type');
    }

    // ── Traffic Classification ──

    public function trafficClassification(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $mangleResult = TrafficEngineeringManager::list($router, 'simple_queue');
        $mangleItems = [];
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ip/firewall/mangle'));
            if ($result->isSuccess()) {
                $mangleItems = is_array($result->toArray()) ? $result->toArray() : [];
            }
        } catch (\Exception $e) {
            // ignore
        }

        $packetMarks = [];
        $connMarks = [];
        $routingMarks = [];
        $dscpCounts = [];
        foreach ($mangleItems as $item) {
            $pm = $item['new-packet-marks'] ?? '';
            if (! empty($pm)) {
                $packetMarks[$pm] = ($packetMarks[$pm] ?? 0) + 1;
            }
            $cm = $item['new-connection-mark'] ?? '';
            if (! empty($cm)) {
                $connMarks[$cm] = ($connMarks[$cm] ?? 0) + 1;
            }
            $rm = $item['new-routing-mark'] ?? '';
            if (! empty($rm)) {
                $routingMarks[$rm] = ($routingMarks[$rm] ?? 0) + 1;
            }
            $dscp = $item['dscp'] ?? '';
            if (! empty($dscp)) {
                $dscpCounts[$dscp] = ($dscpCounts[$dscp] ?? 0) + 1;
            }
        }

        $classification = compact('mangleItems', 'packetMarks', 'connMarks', 'routingMarks', 'dscpCounts');
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.traffic-engineering.traffic-classification', compact('router', 'routers', 'classification'));
    }

    // ── QoS Policy ──

    public function qosPolicy(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $queues = TrafficEngineeringManager::list($router, 'simple_queue');
        $queueTrees = TrafficEngineeringManager::list($router, 'queue_tree');
        $queueTypes = TrafficEngineeringManager::list($router, 'queue_type');

        $interfaces = [];
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/interface'));
            if ($result->isSuccess()) {
                $interfaces = is_array($result->toArray()) ? $result->toArray() : [];
            }
        } catch (\Exception $e) {
            // ignore
        }

        $pppProfiles = [];
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ppp/profile'));
            if ($result->isSuccess()) {
                $pppProfiles = is_array($result->toArray()) ? $result->toArray() : [];
            }
        } catch (\Exception $e) {
            // ignore
        }

        $hotspotProfiles = [];
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ip/hotspot/profile'));
            if ($result->isSuccess()) {
                $hotspotProfiles = is_array($result->toArray()) ? $result->toArray() : [];
            }
        } catch (\Exception $e) {
            // ignore
        }

        $policies = [
            'queues' => $queues['items'] ?? [],
            'queue_trees' => $queueTrees['items'] ?? [],
            'queue_types' => $queueTypes['items'] ?? [],
            'interfaces' => $interfaces,
            'ppp_profiles' => $pppProfiles,
            'hotspot_profiles' => $hotspotProfiles,
        ];

        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.traffic-engineering.qos-policy', compact('router', 'routers', 'policies'));
    }

    // ── Bufferbloat Analyzer ──

    public function bufferbloat(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $queues = TrafficEngineeringManager::list($router, 'simple_queue');
        $queueTypes = TrafficEngineeringManager::list($router, 'queue_type');
        $interfaces = [];

        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/interface'));
            if ($result->isSuccess()) {
                $interfaces = is_array($result->toArray()) ? $result->toArray() : [];
            }
        } catch (\Exception $e) {
            // ignore
        }

        $analysis = [
            'queues' => $queues['items'] ?? [],
            'queue_types' => $queueTypes['items'] ?? [],
            'interfaces' => $interfaces,
            'has_cake' => collect($queueTypes['items'] ?? [])->contains('kind', 'cake'),
            'has_fq_codel' => collect($queueTypes['items'] ?? [])->contains('kind', 'fq-codel'),
        ];

        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.traffic-engineering.bufferbloat', compact('router', 'routers', 'analysis'));
    }

    // ── Traffic Analytics ──

    public function analytics(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $queues = TrafficEngineeringManager::list($router, 'simple_queue');
        $queueTrees = TrafficEngineeringManager::list($router, 'queue_tree');

        $interfaces = [];
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/interface'));
            if ($result->isSuccess()) {
                $interfaces = is_array($result->toArray()) ? $result->toArray() : [];
            }
        } catch (\Exception $e) {
            // ignore
        }

        $mangleItems = [];
        try {
            $service = new RouterConnectionService($router);
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ip/firewall/mangle'));
            if ($result->isSuccess()) {
                $mangleItems = is_array($result->toArray()) ? $result->toArray() : [];
            }
        } catch (\Exception $e) {
            // ignore
        }

        $topQueues = collect($queues['items'] ?? [])
            ->sortByDesc(fn ($q) => self::parseRate($q['rate'] ?? '0'))
            ->take(10)
            ->values()
            ->all();

        $topTrees = collect($queueTrees['items'] ?? [])
            ->sortByDesc(fn ($t) => self::parseRate($t['rate'] ?? '0'))
            ->take(10)
            ->values()
            ->all();

        $topInterfaces = collect($interfaces)
            ->sortByDesc(fn ($i) => self::parseRate($i['rate'] ?? '0'))
            ->take(10)
            ->values()
            ->all();

        $packetMarkUsage = [];
        foreach ($mangleItems as $item) {
            $pm = $item['new-packet-marks'] ?? '';
            if (! empty($pm)) {
                $packets = (int) ($item['packets'] ?? '0');
                $bytes = (int) ($item['bytes'] ?? '0');
                if (! isset($packetMarkUsage[$pm])) {
                    $packetMarkUsage[$pm] = ['packets' => 0, 'bytes' => 0, 'count' => 0];
                }
                $packetMarkUsage[$pm]['packets'] += $packets;
                $packetMarkUsage[$pm]['bytes'] += $bytes;
                $packetMarkUsage[$pm]['count']++;
            }
        }

        $analytics = compact('topQueues', 'topTrees', 'topInterfaces', 'packetMarkUsage');
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.traffic-engineering.analytics', compact('router', 'routers', 'analytics'));
    }

    // ── Audit ──

    public function auditLogs(Request $request): View
    {
        $logs = TrafficEngineeringManager::getAuditLogs(
            routerId: $request->input('router_id'),
            resourceType: $request->input('resource_type'),
            action: $request->input('action'),
            limit: 30,
        );
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $resourceDefs = TrafficEngineeringManager::getResourceDefs();

        return view('noc.traffic-engineering.audit', compact('logs', 'routers', 'resourceDefs'));
    }

    // ── Shared Helpers ──

    private function resolveRouter(Request $request): MikrotikRouter
    {
        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        return $router;
    }

    private function resourceView(string $view, MikrotikRouter $router, array $result, array $extra = []): View
    {
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view($view, array_merge([
            'router' => $router,
            'routers' => $routers,
            'items' => $result['items'] ?? [],
            'error' => $result['error'] ?? null,
        ], $extra));
    }

    private function storeAction(Request $request, string $resource, string $view): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $data = $request->except(['router_id', '_token']);
        $userId = $request->user()->id;

        $result = TrafficEngineeringManager::create($router, $resource, $data, $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Created successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function updateAction(Request $request, string $resource, string $itemId, string $view): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $data = $request->except(['router_id', '_token', '_method']);
        $userId = $request->user()->id;

        $result = TrafficEngineeringManager::update($router, $resource, $itemId, $data, $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Updated successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function destroyAction(Request $request, string $resource, string $itemId): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $userId = $request->user()->id;

        $result = TrafficEngineeringManager::delete($router, $resource, $itemId, $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Deleted successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function toggleAction(Request $request, string $resource, string $itemId): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $disable = $request->boolean('disable', true);
        $userId = $request->user()->id;

        $result = TrafficEngineeringManager::toggle($router, $resource, $itemId, $disable, $userId);

        return back()->with($result['success'] ? 'success' : 'danger', $result['success'] ? ($disable ? 'Disabled' : 'Enabled').' successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function moveAction(Request $request, string $resource, string $itemId): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $putBefore = $request->input('put_before', '');
        $userId = $request->user()->id;

        $result = TrafficEngineeringManager::move($router, $resource, $itemId, $putBefore, $userId);

        return back()->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Rule moved successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function copyAction(Request $request, string $resource, string $itemId): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $userId = $request->user()->id;

        $result = TrafficEngineeringManager::copy($router, $resource, $itemId, $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Copied successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function bulkAction(Request $request, string $resource): RedirectResponse
    {
        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
            'action' => 'required|in:enable,disable,delete',
            'item_ids' => 'required|array|min:1',
        ]);

        $router = MikrotikRouter::find($request->input('router_id'));
        $userId = $request->user()->id;

        $result = TrafficEngineeringManager::bulkOperation($router, $resource, $request->input('action'), $request->input('item_ids'), $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['failed'] > 0 ? 'warning' : 'success', "{$result['success']} succeeded, {$result['failed']} failed");
    }

    private function resourceToRoute(string $resource): string
    {
        return match ($resource) {
            'simple_queue' => 'noc.traffic_eng.simple-queue',
            'queue_tree' => 'noc.traffic_eng.queue-tree',
            'queue_type' => 'noc.traffic_eng.queue-type',
            default => 'noc.traffic_eng.dashboard',
        };
    }

    private static function parseRate(string $rate): float
    {
        $rate = trim($rate);
        if ($rate === '' || $rate === '0') {
            return 0;
        }
        $multipliers = ['k' => 1000, 'm' => 1000000, 'g' => 1000000000];
        $unit = strtolower(substr($rate, -1));
        if (isset($multipliers[$unit])) {
            return (float) substr($rate, 0, -1) * $multipliers[$unit];
        }

        return (float) $rate;
    }
}
