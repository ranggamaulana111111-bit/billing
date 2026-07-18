<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\Mikrotik\Internet\InternetServiceManager;
use App\Services\Mikrotik\RouterCommandService;
use App\Services\Mikrotik\RouterConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternetServiceController extends Controller
{
    /**
     * Internet Service Center — dashboard overview.
     */
    public function dashboard(Request $request)
    {
        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        $stats = InternetServiceManager::getEnhancedDashboardStats($router);
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $poolUsage = $router ? InternetServiceManager::getPoolUsageDetails($router) : [];

        return view('noc.internet.dashboard', compact('stats', 'routers', 'routerId', 'router', 'poolUsage'));
    }

    // ── IP Pool ──

    public function ipPools(Request $request)
    {
        $router = $this->resolveRouter($request);
        $result = InternetServiceManager::list($router, 'ip_pool');
        $poolUsage = InternetServiceManager::getPoolUsageDetails($router);

        return $this->managerView('noc.internet.ippool', $router, $result, [
            'poolUsage' => $poolUsage,
        ]);
    }

    public function ipPoolStore(Request $request)
    {
        return $this->storeAction($request, 'ip_pool', 'noc.internet.ippool');
    }

    public function ipPoolUpdate(Request $request, string $itemId)
    {
        return $this->updateAction($request, 'ip_pool', $itemId, 'noc.internet.ippool');
    }

    public function ipPoolDestroy(Request $request, string $itemId)
    {
        return $this->destroyAction($request, 'ip_pool', $itemId, 'noc.internet.ippool');
    }

    public function ipPoolToggle(Request $request, string $itemId)
    {
        return $this->toggleAction($request, 'ip_pool', $itemId);
    }

    public function ipPoolBulk(Request $request)
    {
        return $this->bulkAction($request, 'ip_pool', 'noc.internet.ippool');
    }

    // ── DHCP Server ──

    public function dhcpServers(Request $request)
    {
        return $this->managerView('noc.internet.dhcp', $this->resolveRouter($request), InternetServiceManager::list($this->resolveRouter($request), 'dhcp_server'));
    }

    public function dhcpStore(Request $request)
    {
        return $this->storeAction($request, 'dhcp_server', 'noc.internet.dhcp');
    }

    public function dhcpUpdate(Request $request, string $itemId)
    {
        return $this->updateAction($request, 'dhcp_server', $itemId, 'noc.internet.dhcp');
    }

    public function dhcpDestroy(Request $request, string $itemId)
    {
        return $this->destroyAction($request, 'dhcp_server', $itemId, 'noc.internet.dhcp');
    }

    public function dhcpToggle(Request $request, string $itemId)
    {
        return $this->toggleAction($request, 'dhcp_server', $itemId);
    }

    public function dhcpBulk(Request $request)
    {
        return $this->bulkAction($request, 'dhcp_server', 'noc.internet.dhcp');
    }

    // ── DHCP Lease ──

    public function dhcpLeases(Request $request)
    {
        $router = $this->resolveRouter($request);
        $result = InternetServiceManager::list($router, 'dhcp_lease');

        return $this->managerView('noc.internet.dhcplease', $router, $result);
    }

    public function dhcpLeaseStore(Request $request)
    {
        return $this->storeAction($request, 'dhcp_lease', 'noc.internet.dhcplease');
    }

    public function dhcpLeaseUpdate(Request $request, string $itemId)
    {
        return $this->updateAction($request, 'dhcp_lease', $itemId, 'noc.internet.dhcplease');
    }

    public function dhcpLeaseDestroy(Request $request, string $itemId)
    {
        return $this->destroyAction($request, 'dhcp_lease', $itemId, 'noc.internet.dhcplease');
    }

    public function dhcpLeaseMakeStatic(Request $request, string $itemId)
    {
        $router = $this->resolveRouter($request);
        $userId = $request->user()->id;

        $result = InternetServiceManager::update($router, 'dhcp_lease', $itemId, ['dynamic' => 'false'], $userId);

        $status = $result['success'] ? 'success' : 'danger';
        $message = $result['success'] ? 'Leash made static' : 'Failed: '.($result['error'] ?? 'Unknown');

        return back()->with($status, $message);
    }

    public function dhcpLeaseBulk(Request $request)
    {
        return $this->bulkAction($request, 'dhcp_lease', 'noc.internet.dhcplease');
    }

    // ── PPP Profile ──

    public function pppProfiles(Request $request)
    {
        return $this->managerView('noc.internet.pppprofile', $this->resolveRouter($request), InternetServiceManager::list($this->resolveRouter($request), 'ppp_profile'));
    }

    public function pppProfileStore(Request $request)
    {
        return $this->storeAction($request, 'ppp_profile', 'noc.internet.pppprofile');
    }

    public function pppProfileUpdate(Request $request, string $itemId)
    {
        return $this->updateAction($request, 'ppp_profile', $itemId, 'noc.internet.pppprofile');
    }

    public function pppProfileDestroy(Request $request, string $itemId)
    {
        return $this->destroyAction($request, 'ppp_profile', $itemId, 'noc.internet.pppprofile');
    }

    public function pppProfileBulk(Request $request)
    {
        return $this->bulkAction($request, 'ppp_profile', 'noc.internet.pppprofile');
    }

    // ── PPP Secret ──

    public function pppSecrets(Request $request)
    {
        $router = $this->resolveRouter($request);
        $result = InternetServiceManager::list($router, 'ppp_secret');
        $activeResult = InternetServiceManager::list($router, 'ppp_active');
        $activeUsernames = array_column($activeResult['items'] ?? [], 'name');

        return $this->managerView('noc.internet.pppsecret', $router, $result, [
            'activeUsernames' => $activeUsernames,
            'activeSessions' => $activeResult['items'] ?? [],
        ]);
    }

    public function pppSecretStore(Request $request)
    {
        return $this->storeAction($request, 'ppp_secret', 'noc.internet.pppsecret');
    }

    public function pppSecretUpdate(Request $request, string $itemId)
    {
        return $this->updateAction($request, 'ppp_secret', $itemId, 'noc.internet.pppsecret');
    }

    public function pppSecretDestroy(Request $request, string $itemId)
    {
        return $this->destroyAction($request, 'ppp_secret', $itemId, 'noc.internet.pppsecret');
    }

    public function pppSecretToggle(Request $request, string $itemId)
    {
        return $this->toggleAction($request, 'ppp_secret', $itemId);
    }

    public function pppSecretBulk(Request $request)
    {
        return $this->bulkAction($request, 'ppp_secret', 'noc.internet.pppsecret');
    }

    // ── Hotspot Server ──

    public function hotspotServers(Request $request)
    {
        $router = $this->resolveRouter($request);

        return $this->managerView('noc.internet.hotspot', $router, InternetServiceManager::list($router, 'hotspot_server'), [
            'hotspotUsers' => InternetServiceManager::list($router, 'hotspot_user')['items'] ?? [],
        ]);
    }

    public function hotspotServerStore(Request $request)
    {
        return $this->storeAction($request, 'hotspot_server', 'noc.internet.hotspot');
    }

    public function hotspotServerUpdate(Request $request, string $itemId)
    {
        return $this->updateAction($request, 'hotspot_server', $itemId, 'noc.internet.hotspot');
    }

    public function hotspotServerDestroy(Request $request, string $itemId)
    {
        return $this->destroyAction($request, 'hotspot_server', $itemId, 'noc.internet.hotspot');
    }

    public function hotspotServerToggle(Request $request, string $itemId)
    {
        return $this->toggleAction($request, 'hotspot_server', $itemId);
    }

    public function hotspotServerBulk(Request $request)
    {
        return $this->bulkAction($request, 'hotspot_server', 'noc.internet.hotspot');
    }

    // ── Hotspot User ──

    public function hotspotUsers(Request $request)
    {
        return $this->managerView('noc.internet.hotspotuser', $this->resolveRouter($request), InternetServiceManager::list($this->resolveRouter($request), 'hotspot_user'));
    }

    public function hotspotUserStore(Request $request)
    {
        return $this->storeAction($request, 'hotspot_user', 'noc.internet.hotspotuser');
    }

    public function hotspotUserUpdate(Request $request, string $itemId)
    {
        return $this->updateAction($request, 'hotspot_user', $itemId, 'noc.internet.hotspotuser');
    }

    public function hotspotUserDestroy(Request $request, string $itemId)
    {
        return $this->destroyAction($request, 'hotspot_user', $itemId, 'noc.internet.hotspotuser');
    }

    public function hotspotUserToggle(Request $request, string $itemId)
    {
        return $this->toggleAction($request, 'hotspot_user', $itemId);
    }

    public function hotspotUserBulk(Request $request)
    {
        return $this->bulkAction($request, 'hotspot_user', 'noc.internet.hotspotuser');
    }

    // ── Hotspot Profile ──

    public function hotspotProfiles(Request $request)
    {
        return $this->managerView('noc.internet.hotspotprofile', $this->resolveRouter($request), InternetServiceManager::list($this->resolveRouter($request), 'hotspot_profile'));
    }

    public function hotspotProfileStore(Request $request)
    {
        return $this->storeAction($request, 'hotspot_profile', 'noc.internet.hotspotprofile');
    }

    public function hotspotProfileUpdate(Request $request, string $itemId)
    {
        return $this->updateAction($request, 'hotspot_profile', $itemId, 'noc.internet.hotspotprofile');
    }

    public function hotspotProfileDestroy(Request $request, string $itemId)
    {
        return $this->destroyAction($request, 'hotspot_profile', $itemId, 'noc.internet.hotspotprofile');
    }

    // ── Active Sessions ──

    public function activeSessions(Request $request)
    {
        $router = $this->resolveRouter($request);
        $service = new RouterConnectionService($router);
        $pppActive = [];
        $hotspotActive = [];

        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ppp/active'));
            if ($result->isSuccess()) {
                $pppActive = $result->toArray();
                if (! is_array($pppActive)) {
                    $pppActive = [];
                }
            }
        } catch (\Exception $e) {
            // silent
        }

        try {
            $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawGet('/ip/hotspot/active'));
            if ($result->isSuccess()) {
                $hotspotActive = $result->toArray();
                if (! is_array($hotspotActive)) {
                    $hotspotActive = [];
                }
            }
        } catch (\Exception $e) {
            // silent
        }

        return $this->managerView('noc.internet.active', $router, ['success' => true, 'items' => []], [
            'pppActive' => $pppActive,
            'hotspotActive' => $hotspotActive,
        ]);
    }

    public function disconnectSession(Request $request, string $type, string $sessionId)
    {
        $router = $this->resolveRouter($request);
        $service = new RouterConnectionService($router);

        $path = $type === 'ppp' ? '/ppp/active' : '/ip/hotspot/active';
        $result = $service->run(fn (RouterCommandService $cmd) => $cmd->rawDelete($path.'/'.$sessionId));

        $status = $result->isSuccess() ? 'success' : 'danger';
        $message = $result->isSuccess() ? 'Session disconnected' : 'Failed: '.$result->getMessage();

        return back()->with($status, $message);
    }

    // ── Hotspot Hosts ──

    public function hotspotHosts(Request $request)
    {
        $router = $this->resolveRouter($request);
        $result = InternetServiceManager::getHotspotHosts($router);

        return $this->managerView('noc.internet.host', $router, $result);
    }

    // ── Hotspot Cookies ──

    public function hotspotCookies(Request $request)
    {
        $router = $this->resolveRouter($request);
        $result = InternetServiceManager::getHotspotCookies($router);

        return $this->managerView('noc.internet.cookie', $router, $result);
    }

    // ── Hotspot Login History ──

    public function hotspotLoginHistory(Request $request)
    {
        $router = $this->resolveRouter($request);
        $result = InternetServiceManager::getHotspotLoginHistory($router);

        return $this->managerView('noc.internet.login-history', $router, $result);
    }

    // ── Monitoring Center ──

    public function monitoring(Request $request)
    {
        $router = $this->resolveRouter($request);
        $monitoring = InternetServiceManager::getMonitoringData($router);
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.internet.monitoring', array_merge($monitoring, ['routers' => $routers, 'routerId' => $router->id]));
    }

    /**
     * Live TX/RX rate per interface (polled every 3s by the monitoring view).
     */
    public function interfaceRates(Request $request): JsonResponse
    {
        $router = $this->resolveRouter($request);
        if (! $router) {
            return response()->json(['error' => 'No active router'], 400);
        }

        $rates = [];
        try {
            $service = new RouterConnectionService($router);
            $interfaces = $service->run(fn (RouterCommandService $cmd) => $cmd->getInterfaces());
            if (! $interfaces->isSuccess()) {
                return response()->json(['rates' => []]);
            }

            $now = microtime(true);
            foreach ($interfaces->toArray() ?? [] as $iface) {
                $name = $iface['name'] ?? null;
                if (! $name) {
                    continue;
                }
                $rx = (int) ($iface['rx-byte'] ?? 0);
                $tx = (int) ($iface['tx-byte'] ?? 0);

                $cacheKey = "iface_rate:{$router->id}:{$name}";
                $prev = cache($cacheKey);
                $rateRx = 0;
                $rateTx = 0;
                if ($prev && ($elapsed = $now - $prev['time']) > 0) {
                    $rateRx = max(0, ($rx - $prev['rx']) * 8 / $elapsed);
                    $rateTx = max(0, ($tx - $prev['tx']) * 8 / $elapsed);
                }
                cache([$cacheKey => ['rx' => $rx, 'tx' => $tx, 'time' => $now]], now()->addSeconds(10));

                $rates[$name] = [
                    'rx_rate' => $rateRx,
                    'tx_rate' => $rateTx,
                ];
            }
        } catch (\Exception $e) {
        }

        return response()->json(['rates' => $rates]);
    }

    /**
     * Live router resource status (CPU, uptime, version, memory) polled by the monitoring view.
     */
    public function routerStatus(Request $request): JsonResponse
    {
        $router = $this->resolveRouter($request);
        if (! $router) {
            return response()->json(['error' => 'No active router'], 400);
        }

        try {
            $monitoring = InternetServiceManager::getMonitoringData($router);

            return response()->json([
                'cpu_load' => $monitoring['router_cpu'] ?? null,
                'uptime' => $monitoring['router_uptime'] ?? null,
                'version' => $monitoring['router_version'] ?? null,
                'status' => $router->status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'cpu_load' => null, 'uptime' => null, 'version' => null,
                'status' => $router->status,
                'debug_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Live active PPP & Hotspot sessions polled by the monitoring view.
     */
    public function activeSessionsLive(Request $request): JsonResponse
    {
        $router = $this->resolveRouter($request);
        if (! $router) {
            return response()->json(['error' => 'No active router'], 400);
        }

        try {
            $monitoring = InternetServiceManager::getMonitoringData($router);
            $ppp = array_map(function ($s) {
                return [
                    'user' => $s['name'] ?? $s['user'] ?? '—',
                    'service' => $s['service'] ?? 'pppoe',
                    'address' => $s['address'] ?? '—',
                    'caller' => $s['caller-id'] ?? '—',
                    'uptime' => $s['uptime'] ?? '—',
                ];
            }, $monitoring['ppp_active'] ?? []);
            $hotspot = array_map(function ($s) {
                return [
                    'user' => $s['user'] ?? $s['name'] ?? '—',
                    'server' => $s['server'] ?? '—',
                    'address' => $s['address'] ?? '—',
                    'mac' => $s['mac-address'] ?? '—',
                    'uptime' => $s['uptime'] ?? '—',
                ];
            }, $monitoring['hotspot_active'] ?? []);

            return response()->json([
                'ppp' => $ppp,
                'hotspot' => $hotspot,
                'ppp_count' => count($ppp),
                'hotspot_count' => count($hotspot),
            ]);
        } catch (\Exception $e) {
            return response()->json(['ppp' => [], 'hotspot' => [], 'ppp_count' => 0, 'hotspot_count' => 0, 'debug_error' => $e->getMessage()]);
        }
    }

    // ── IP Conflicts ──

    public function ipConflicts(Request $request)
    {
        $router = $this->resolveRouter($request);
        $result = InternetServiceManager::detectIpConflicts($router);
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();

        return view('noc.internet.conflicts', [
            'router' => $router,
            'routers' => $routers,
            'routerId' => $router->id,
            'conflicts' => $result['conflicts'] ?? [],
            'total' => $result['total'] ?? 0,
        ]);
    }

    // ── Audit Logs ──

    public function auditLogs(Request $request)
    {
        $logs = InternetServiceManager::getAuditLogs(
            resourceType: $request->input('resource_type'),
            routerId: $request->input('router_id'),
            action: $request->input('action'),
            limit: 30,
        );
        $routers = MikrotikRouter::where('is_active', true)->orderBy('name')->get();
        $resourceDefs = InternetServiceManager::getResourceDefs();

        return view('noc.internet.audit', compact('logs', 'routers', 'resourceDefs'));
    }

    // ── Radius Preparation ──

    public function radius()
    {
        return view('noc.internet.radius');
    }

    // ── Bulk Comment ──

    public function bulkComment(Request $request, string $resource = '')
    {
        $resource = $resource ?: $this->routeToResource($request->route()->getName());
        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
            'item_ids' => 'required|array|min:1',
            'comment' => 'required|string|max:255',
        ]);

        $router = MikrotikRouter::find($request->input('router_id'));
        $userId = $request->user()->id;

        $result = InternetServiceManager::bulkComment($router, $resource, $request->input('item_ids'), $request->input('comment'), $userId);

        return redirect()->route("noc.internet.{$this->resourceToRoute($resource)}", ['router_id' => $router->id])
            ->with($result['failed'] > 0 ? 'warning' : 'success', "{$result['success']} commented, {$result['failed']} failed");
    }

    // ── Bulk Refresh ──

    public function bulkRefresh(Request $request, string $resource = '')
    {
        $resource = $resource ?: $this->routeToResource($request->route()->getName());
        $router = $this->resolveRouter($request);

        return redirect()->route("noc.internet.{$this->resourceToRoute($resource)}", ['router_id' => $router->id])
            ->with('success', 'Data refreshed from router');
    }

    private function routeToResource(?string $routeName): string
    {
        $map = [
            'ippool' => 'ip_pool',
            'dhcp' => 'dhcp_server',
            'dhcplease' => 'dhcp_lease',
            'pppprofile' => 'ppp_profile',
            'pppsecret' => 'ppp_secret',
            'hotspot' => 'hotspot_server',
            'hotspotuser' => 'hotspot_user',
            'hotspotprofile' => 'hotspot_profile',
        ];

        foreach ($map as $key => $resource) {
            if (str_contains($routeName ?? '', $key)) {
                return $resource;
            }
        }

        return 'ip_pool';
    }

    // ── Shared Helpers ──

    private function resolveRouter(Request $request): MikrotikRouter
    {
        $routerId = $request->input('router_id');
        $router = $routerId ? MikrotikRouter::find($routerId) : MikrotikRouter::where('is_active', true)->first();
        abort_unless($router, 404);

        return $router;
    }

    private function managerView(string $view, MikrotikRouter $router, array $result, array $extra = []): View
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

        $result = InternetServiceManager::create($router, $resource, $data, $userId);

        return redirect()->route("noc.internet.{$this->resourceToRoute($resource)}", ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Created successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function updateAction(Request $request, string $resource, string $itemId, string $view): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $data = $request->except(['router_id', '_token', '_method']);
        $userId = $request->user()->id;

        $result = InternetServiceManager::update($router, $resource, $itemId, $data, $userId);

        return redirect()->route("noc.internet.{$this->resourceToRoute($resource)}", ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Updated successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function destroyAction(Request $request, string $resource, string $itemId, string $view): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $userId = $request->user()->id;

        $result = InternetServiceManager::delete($router, $resource, $itemId, $userId);

        return redirect()->route("noc.internet.{$this->resourceToRoute($resource)}", ['router_id' => $router->id])
            ->with($result['success'] ? 'success' : 'danger', $result['success'] ? 'Deleted successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function toggleAction(Request $request, string $resource, string $itemId): RedirectResponse
    {
        $router = $this->resolveRouter($request);
        $disable = $request->boolean('disable');
        $userId = $request->user()->id;

        $result = InternetServiceManager::toggle($router, $resource, $itemId, $disable, $userId);

        return back()->with($result['success'] ? 'success' : 'danger', $result['success'] ? ($disable ? 'Disabled' : 'Enabled').' successfully' : 'Failed: '.($result['error'] ?? 'Unknown'));
    }

    private function bulkAction(Request $request, string $resource, string $view): RedirectResponse
    {
        $request->validate([
            'router_id' => 'required|exists:mikrotik_routers,id',
            'action' => 'required|in:enable,disable,delete',
            'item_ids' => 'required|array|min:1',
        ]);

        $router = MikrotikRouter::find($request->input('router_id'));
        $userId = $request->user()->id;

        $result = InternetServiceManager::bulkOperation($router, $resource, $request->input('action'), $request->input('item_ids'), $userId);

        return redirect()->route("noc.internet.{$this->resourceToRoute($resource)}", ['router_id' => $router->id])
            ->with($result['failed'] > 0 ? 'warning' : 'success', "{$result['success']} succeeded, {$result['failed']} failed");
    }

    private function resourceToRoute(string $resource): string
    {
        return match ($resource) {
            'ip_pool' => 'ippool',
            'dhcp_server' => 'dhcp',
            'dhcp_lease' => 'dhcplease',
            'ppp_profile' => 'pppprofile',
            'ppp_secret' => 'pppsecret',
            'hotspot_server' => 'hotspot',
            'hotspot_user' => 'hotspotuser',
            'hotspot_profile' => 'hotspotprofile',
            default => $resource,
        };
    }
}
