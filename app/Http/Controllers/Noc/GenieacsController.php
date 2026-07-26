<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Modules\GenieACS\Contracts\IGenieACSClient;
use App\Modules\GenieACS\Exceptions\GenieACSAuthenticationException;
use App\Modules\GenieACS\Exceptions\GenieACSConnectionException;
use App\Modules\GenieACS\Repositories\GenieACSRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class GenieacsController extends Controller
{
    public function __construct(
        private IGenieACSClient $client,
        private GenieACSRepository $repo,
    ) {}

    /**
     * GenieACS Dashboard — overview stats.
     */
    public function dashboard(): View
    {
        $stats = $this->getDashboardStats();

        return view('noc.genieacs.dashboard', compact('stats'));
    }

    /**
     * Device list with filters.
     */
    public function devices(Request $request): View
    {
        $filters = $request->only(['search', 'model', 'manufacturer', 'software_version', 'tags']);
        $limit = min((int) $request->input('limit', 50), 200);
        $skip = max((int) $request->input('skip', 0), 0);

        $result = $this->repo->getDevices($filters, $limit, $skip);
        $devices = is_array($result) ? $result : [];
        $total = $this->repo->countDevices($filters);

        return view('noc.genieacs.devices', compact('devices', 'total', 'filters', 'limit', 'skip'));
    }

    /**
     * Single device detail with CWMP parameter tree.
     */
    public function deviceDetail(string $deviceId): View
    {
        $device = $this->repo->getDevice($deviceId);

        if (! $device) {
            return view('noc.genieacs.device-detail', [
                'device' => null,
                'deviceId' => $deviceId,
                'error' => 'Device not found or GenieACS unreachable.',
            ]);
        }

        $tasks = $this->repo->getTasks($deviceId);
        $faults = $this->repo->getFaultsByDevice($deviceId);

        return view('noc.genieacs.device-detail', compact('device', 'deviceId', 'tasks', 'faults'));
    }

    /**
     * Preset list.
     */
    public function presets(): View
    {
        $presets = $this->repo->getPresets();

        return view('noc.genieacs.presets', compact('presets'));
    }

    /**
     * Fault list with filters.
     */
    public function faults(Request $request): View
    {
        $filters = $request->only(['device', 'code']);
        $limit = min((int) $request->input('limit', 50), 200);
        $skip = max((int) $request->input('skip', 0), 0);

        $faults = $this->repo->getFaults($filters, $limit, $skip);

        return view('noc.genieacs.faults', compact('faults', 'filters', 'limit', 'skip'));
    }

    /**
     * Settings page — connection config & test.
     */
    public function settings(): View
    {
        return view('noc.genieacs.settings', [
            'baseUrl' => config('genieacs.base_url', ''),
            'username' => config('genieacs.username', ''),
            'hasPassword' => config('genieacs.password', '') !== '',
            'timeout' => config('genieacs.timeout', 30),
        ]);
    }

    // ── AJAX Actions ───────────────────────────────────────

    /**
     * Test GenieACS connection (AJAX).
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->client->testConnection();

            return response()->json([
                'success' => true,
                'message' => 'Koneksi ke GenieACS berhasil.',
                'data' => $result,
            ]);
        } catch (GenieACSConnectionException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Koneksi gagal: '.$e->getMessage(),
            ], 503);
        } catch (GenieACSAuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi gagal: '.$e->getMessage(),
            ], 401);
        } catch (\Exception $e) {
            Log::error('GenieACS test connection failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reboot a device (AJAX POST).
     */
    public function reboot(string $deviceId): JsonResponse
    {
        try {
            $result = $this->repo->rebootDevice($deviceId);

            return response()->json([
                'success' => true,
                'message' => 'Reboot task dikirim ke '.$deviceId,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal reboot: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Factory reset a device (AJAX POST).
     */
    public function factoryReset(string $deviceId): JsonResponse
    {
        try {
            $result = $this->repo->factoryResetDevice($deviceId);

            return response()->json([
                'success' => true,
                'message' => 'Factory reset task dikirim ke '.$deviceId,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal factory reset: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh CWMP object tree from device (AJAX POST).
     */
    public function refreshObject(Request $request, string $deviceId): JsonResponse
    {
        $objectName = $request->input('object', 'InternetGatewayDevice');

        try {
            $result = $this->repo->refreshObject($deviceId, $objectName);

            return response()->json([
                'success' => true,
                'message' => 'Refresh object "'.$objectName.'" dikirim ke '.$deviceId,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal refresh: '.$e->getMessage(),
            ], 500);
        }
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Compute dashboard stats by querying GenieACS.
     */
    private function getDashboardStats(): array
    {
        $stats = [
            'total_devices' => 0,
            'online_devices' => 0,
            'offline_devices' => 0,
            'fault_count' => 0,
            'preset_count' => 0,
            'connected' => false,
            'error' => null,
        ];

        try {
            $allDevices = $this->client->devices([], ['InternetGatewayDevice.DeviceInfo.ModelName']);
            $stats['total_devices'] = count($allDevices);
            $stats['connected'] = true;

            $now = time();
            foreach ($allDevices as $device) {
                $lastInform = $device['_lastInform'] ?? null;
                if ($lastInform && ($now - strtotime($lastInform)) < 600) {
                    $stats['online_devices']++;
                } else {
                    $stats['offline_devices']++;
                }
            }
        } catch (\Exception $e) {
            Log::warning('GenieACS dashboard: failed to fetch devices', ['error' => $e->getMessage()]);
            $stats['error'] = $e->getMessage();
        }

        try {
            $faults = $this->client->faults([], 100);
            $stats['fault_count'] = count($faults);
        } catch (\Exception $e) {
            Log::warning('GenieACS dashboard: failed to fetch faults', ['error' => $e->getMessage()]);
        }

        try {
            $presets = $this->client->presets();
            $stats['preset_count'] = count($presets);
        } catch (\Exception $e) {
            Log::warning('GenieACS dashboard: failed to fetch presets', ['error' => $e->getMessage()]);
        }

        return $stats;
    }
}
