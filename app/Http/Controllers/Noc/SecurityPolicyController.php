<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\Mikrotik\Security\SecurityPolicyManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityPolicyController extends Controller
{
    public function dashboard(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $stats = SecurityPolicyManager::getDashboardStats($router);
        $recommendations = SecurityPolicyManager::validatePolicies($router);
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.security.dashboard', compact('router', 'stats', 'recommendations', 'routers'));
    }

    // ── Firewall Filter ──

    public function firewallFilter(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $result = SecurityPolicyManager::list($router, 'firewall_filter');

        return $this->resourceView('noc.security.firewall-filter', $router, $result);
    }

    public function firewallFilterStore(Request $request): RedirectResponse
    {
        return $this->storeAction($request, 'firewall_filter', 'noc.security.firewall-filter');
    }

    public function firewallFilterUpdate(Request $request, string $itemId): RedirectResponse
    {
        return $this->updateAction($request, 'firewall_filter', $itemId, 'noc.security.firewall-filter');
    }

    public function firewallFilterDestroy(Request $request, string $itemId): RedirectResponse
    {
        return $this->destroyAction($request, 'firewall_filter', $itemId);
    }

    public function firewallFilterToggle(Request $request, string $itemId): RedirectResponse
    {
        return $this->toggleAction($request, 'firewall_filter', $itemId);
    }

    public function firewallFilterBulk(Request $request): RedirectResponse
    {
        return $this->bulkAction($request, 'firewall_filter');
    }

    public function firewallFilterMove(Request $request, string $itemId): RedirectResponse
    {
        return $this->moveAction($request, 'firewall_filter', $itemId);
    }

    public function firewallFilterCopy(Request $request, string $itemId): RedirectResponse
    {
        return $this->copyAction($request, 'firewall_filter', $itemId);
    }

    // ── NAT ──

    public function nat(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $result = SecurityPolicyManager::list($router, 'firewall_nat');

        return $this->resourceView('noc.security.nat', $router, $result);
    }

    public function natStore(Request $request): RedirectResponse
    {
        return $this->storeAction($request, 'firewall_nat', 'noc.security.nat');
    }

    public function natUpdate(Request $request, string $itemId): RedirectResponse
    {
        return $this->updateAction($request, 'firewall_nat', $itemId, 'noc.security.nat');
    }

    public function natDestroy(Request $request, string $itemId): RedirectResponse
    {
        return $this->destroyAction($request, 'firewall_nat', $itemId);
    }

    public function natToggle(Request $request, string $itemId): RedirectResponse
    {
        return $this->toggleAction($request, 'firewall_nat', $itemId);
    }

    public function natBulk(Request $request): RedirectResponse
    {
        return $this->bulkAction($request, 'firewall_nat');
    }

    public function natMove(Request $request, string $itemId): RedirectResponse
    {
        return $this->moveAction($request, 'firewall_nat', $itemId);
    }

    public function natCopy(Request $request, string $itemId): RedirectResponse
    {
        return $this->copyAction($request, 'firewall_nat', $itemId);
    }

    // ── Mangle ──

    public function mangle(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $result = SecurityPolicyManager::list($router, 'mangle');

        return $this->resourceView('noc.security.mangle', $router, $result);
    }

    public function mangleStore(Request $request): RedirectResponse
    {
        return $this->storeAction($request, 'mangle', 'noc.security.mangle');
    }

    public function mangleUpdate(Request $request, string $itemId): RedirectResponse
    {
        return $this->updateAction($request, 'mangle', $itemId, 'noc.security.mangle');
    }

    public function mangleDestroy(Request $request, string $itemId): RedirectResponse
    {
        return $this->destroyAction($request, 'mangle', $itemId);
    }

    public function mangleToggle(Request $request, string $itemId): RedirectResponse
    {
        return $this->toggleAction($request, 'mangle', $itemId);
    }

    public function mangleBulk(Request $request): RedirectResponse
    {
        return $this->bulkAction($request, 'mangle');
    }

    public function mangleMove(Request $request, string $itemId): RedirectResponse
    {
        return $this->moveAction($request, 'mangle', $itemId);
    }

    public function mangleCopy(Request $request, string $itemId): RedirectResponse
    {
        return $this->copyAction($request, 'mangle', $itemId);
    }

    // ── Address List ──

    public function addressList(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $result = SecurityPolicyManager::list($router, 'address_list');

        return $this->resourceView('noc.security.address-list', $router, $result);
    }

    public function addressListStore(Request $request): RedirectResponse
    {
        return $this->storeAction($request, 'address_list', 'noc.security.address-list');
    }

    public function addressListUpdate(Request $request, string $itemId): RedirectResponse
    {
        return $this->updateAction($request, 'address_list', $itemId, 'noc.security.address-list');
    }

    public function addressListDestroy(Request $request, string $itemId): RedirectResponse
    {
        return $this->destroyAction($request, 'address_list', $itemId);
    }

    public function addressListBulk(Request $request): RedirectResponse
    {
        return $this->bulkAction($request, 'address_list');
    }

    // ── Raw Firewall ──

    public function raw(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $result = SecurityPolicyManager::list($router, 'raw');

        return $this->resourceView('noc.security.raw', $router, $result);
    }

    public function rawStore(Request $request): RedirectResponse
    {
        return $this->storeAction($request, 'raw', 'noc.security.raw');
    }

    public function rawUpdate(Request $request, string $itemId): RedirectResponse
    {
        return $this->updateAction($request, 'raw', $itemId, 'noc.security.raw');
    }

    public function rawDestroy(Request $request, string $itemId): RedirectResponse
    {
        return $this->destroyAction($request, 'raw', $itemId);
    }

    public function rawToggle(Request $request, string $itemId): RedirectResponse
    {
        return $this->toggleAction($request, 'raw', $itemId);
    }

    public function rawBulk(Request $request): RedirectResponse
    {
        return $this->bulkAction($request, 'raw');
    }

    public function rawMove(Request $request, string $itemId): RedirectResponse
    {
        return $this->moveAction($request, 'raw', $itemId);
    }

    public function rawCopy(Request $request, string $itemId): RedirectResponse
    {
        return $this->copyAction($request, 'raw', $itemId);
    }

    // ── Layer7 ──

    public function layer7(Request $request): View
    {
        $router = $this->resolveRouter($request);
        $result = SecurityPolicyManager::list($router, 'layer7');

        return $this->resourceView('noc.security.layer7', $router, $result);
    }

    public function layer7Store(Request $request): RedirectResponse
    {
        return $this->storeAction($request, 'layer7', 'noc.security.layer7');
    }

    public function layer7Update(Request $request, string $itemId): RedirectResponse
    {
        return $this->updateAction($request, 'layer7', $itemId, 'noc.security.layer7');
    }

    public function layer7Destroy(Request $request, string $itemId): RedirectResponse
    {
        return $this->destroyAction($request, 'layer7', $itemId);
    }

    public function layer7Bulk(Request $request): RedirectResponse
    {
        return $this->bulkAction($request, 'layer7');
    }

    // ── Audit ──

    public function auditLogs(Request $request): View
    {
        $logs = SecurityPolicyManager::getAuditLogs(
            routerId: $request->input('router_id'),
            resourceType: $request->input('resource_type'),
            action: $request->input('action'),
            limit: 30,
        );
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $resourceDefs = SecurityPolicyManager::getResourceDefs();

        return view('noc.security.audit', compact('logs', 'routers', 'resourceDefs'));
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

        $result = SecurityPolicyManager::create($router, $resource, $data, $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Created successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function updateAction(Request $request, string $resource, string $itemId, string $view): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $data = $request->except(['router_id', '_token', '_method']);
        $userId = $request->user()->id;

        $result = SecurityPolicyManager::update($router, $resource, $itemId, $data, $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Updated successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function destroyAction(Request $request, string $resource, string $itemId): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $userId = $request->user()->id;

        $result = SecurityPolicyManager::delete($router, $resource, $itemId, $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Deleted successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function toggleAction(Request $request, string $resource, string $itemId): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $disable = $request->boolean('disable', true);
        $userId = $request->user()->id;

        $result = SecurityPolicyManager::toggle($router, $resource, $itemId, $disable, $userId);

        return back()->with($result['success'] ? 'success' : 'danger', $result['success'] ? ($disable ? 'Disabled' : 'Enabled').' successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function moveAction(Request $request, string $resource, string $itemId): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $putBefore = $request->input('put_before', '');
        $userId = $request->user()->id;

        $result = SecurityPolicyManager::move($router, $resource, $itemId, $putBefore, $userId);

        return back()->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Rule moved successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function copyAction(Request $request, string $resource, string $itemId): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $userId = $request->user()->id;

        $result = SecurityPolicyManager::copy($router, $resource, $itemId, $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Rule copied successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
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

        $result = SecurityPolicyManager::bulkOperation($router, $resource, $request->input('action'), $request->input('item_ids'), $userId);

        return redirect()->route($this->resourceToRoute($resource), ['router_id' => $router->id])
            ->with($result['failed'] > 0 ? 'warning' : 'success', "{$result['success']} succeeded, {$result['failed']} failed");
    }

    private function resourceToRoute(string $resource): string
    {
        return match ($resource) {
            'firewall_filter' => 'noc.security.firewall-filter',
            'firewall_nat' => 'noc.security.nat',
            'mangle' => 'noc.security.mangle',
            'address_list' => 'noc.security.address-list',
            'raw' => 'noc.security.raw',
            'layer7' => 'noc.security.layer7',
            default => 'noc.security.dashboard',
        };
    }
}
