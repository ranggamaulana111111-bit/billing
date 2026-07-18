<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\InterfaceChangeLog;
use App\Models\MikrotikRouter;
use App\Services\InterfaceCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InterfaceCenterController extends Controller
{
    public function __construct(
        private InterfaceCenterService $service = new InterfaceCenterService,
    ) {}

    /**
     * Dashboard summary — stats across all routers.
     */
    public function dashboard(): View
    {
        $allData = $this->service->fetchAllInterfaces();
        $stats = $this->service->computeStats($allData);
        $interfaceTypes = $this->service->getInterfaceTypes($allData);
        $filterOptions = $this->service->getFilterOptions();

        return view('noc.interface-center.dashboard', compact('stats', 'interfaceTypes', 'filterOptions'));
    }

    /**
     * Interface list with filters.
     */
    public function index(Request $request): View
    {
        $allData = $this->service->fetchAllInterfaces();
        $stats = $this->service->computeStats($allData);
        $filterOptions = $this->service->getFilterOptions();
        $interfaceTypes = $this->service->getInterfaceTypes($allData);

        // Flatten all interfaces for the list view
        $interfaces = [];
        foreach ($allData as $rd) {
            foreach ($rd['interfaces'] as $iface) {
                $iface['router_name'] = $rd['router_name'];
                $iface['router_id'] = $rd['router_id'];
                $interfaces[] = $iface;
            }
        }

        return view('noc.interface-center.index', compact('interfaces', 'stats', 'filterOptions', 'interfaceTypes'));
    }

    /**
     * Interface detail page.
     */
    public function detail(int $routerId, string $interfaceName): View
    {
        $router = MikrotikRouter::findOrFail($routerId);
        $data = $this->service->fetchInterfaceDetail($router, $interfaceName);

        return view('noc.interface-center.detail', ['router' => $router, 'data' => $data, 'interfaceName' => $interfaceName]);
    }

    /**
     * Live API endpoint for auto-refresh (dashboard).
     */
    public function liveApi(Request $request): JsonResponse
    {
        $allData = $this->service->fetchAllInterfaces();
        $stats = $this->service->computeStats($allData);

        return response()->json(['interfaces' => $allData, 'stats' => $stats]);
    }

    /**
     * Live API endpoint for detail page auto-refresh.
     */
    public function liveDetailApi(int $routerId, string $interfaceName): JsonResponse
    {
        $router = MikrotikRouter::find($routerId);
        if (! $router) {
            return response()->json(['error' => 'Router tidak ditemukan'], 404);
        }

        $data = $this->service->fetchInterfaceDetail($router, $interfaceName);

        return response()->json($data);
    }

    /**
     * Update interface configuration.
     */
    public function update(Request $request, int $routerId, string $interfaceName)
    {
        $request->validate([
            'disabled' => 'nullable|boolean',
            'name' => 'nullable|string|max:100',
            'mtu' => 'nullable|integer|min:64|max:65535',
            'comment' => 'nullable|string|max:500',
            'auto_negotiation' => 'nullable|boolean',
            'speed' => 'nullable|string|max:20',
        ]);

        $router = MikrotikRouter::findOrFail($routerId);

        $params = $request->only(['disabled', 'name', 'mtu', 'comment', 'auto_negotiation', 'speed']);

        $result = $this->service->updateInterface($router, $interfaceName, $params);

        if ($result->isSuccess()) {
            return back()->with('success', 'Interface berhasil diperbarui: '.$result->getMessage());
        }

        return back()->with('error', 'Gagal memperbarui interface: '.$result->getMessage());
    }

    /**
     * Update interface metadata (alias, tags, notes).
     */
    public function updateMetadata(Request $request, int $routerId, string $interfaceName)
    {
        $request->validate([
            'alias' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'notes' => 'nullable|string|max:1000',
            'is_monitored' => 'nullable|boolean',
        ]);

        $this->service->updateMetadata($routerId, $interfaceName, $request->only(['alias', 'tags', 'notes', 'is_monitored']));

        return back()->with('success', 'Metadata interface berhasil diperbarui');
    }

    /**
     * Execute bulk operation.
     */
    public function bulk(Request $request)
    {
        $request->validate([
            'router_id' => 'required|integer|exists:mikrotik_routers,id',
            'interfaces' => 'required|array|min:1',
            'interfaces.*' => 'string|max:100',
            'action' => 'required|in:enable,disable,set_tag,remove_tag,refresh',
            'params' => 'nullable|array',
        ]);

        $result = $this->service->bulkOperation([
            'router_id' => $request->input('router_id'),
            'interfaces' => $request->input('interfaces'),
            'action' => $request->input('action'),
            'params' => $request->input('params'),
        ]);

        return response()->json($result);
    }

    /**
     * Get change history for an interface.
     */
    public function history(int $routerId, string $interfaceName): JsonResponse
    {
        $logs = InterfaceChangeLog::where('mikrotik_router_id', $routerId)
            ->where('interface_name', $interfaceName)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }
}
