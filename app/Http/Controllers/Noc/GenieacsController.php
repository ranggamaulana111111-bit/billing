<?php

namespace App\Http\Controllers\Noc;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Modules\GenieACS\Contracts\IGenieACSClient;
use App\Modules\GenieACS\Exceptions\GenieACSAuthenticationException;
use App\Modules\GenieACS\Exceptions\GenieACSConnectionException;
use App\Modules\GenieACS\Repositories\GenieACSRepository;
use App\Modules\GenieACS\Services\AcsCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class GenieacsController extends Controller
{
    public function __construct(
        private IGenieACSClient $client,
        private GenieACSRepository $repo,
        private AcsCatalogService $catalog,
    ) {}

    /**
     * GenieACS Overview — ACS Config & Monitoring.
     */
    public function dashboard(): View
    {
        try {
            $devices = $this->catalog->fetchDevices([], 0, 0);
            $rows = $this->catalog->enrichMany($devices, $this->catalog->context());
            $overview = $this->catalog->overviewStats($rows);
            $connected = true;
            $error = null;
        } catch (\Throwable $e) {
            Log::warning('GenieACS overview failed', ['error' => $e->getMessage()]);
            $overview = $this->catalog->emptyOverview();
            $connected = false;
            $error = $e->getMessage();
        }

        return view('noc.genieacs.dashboard', compact('overview', 'connected', 'error'));
    }

    /**
     * Device list with filters.
     */
    public function devices(Request $request): View
    {
        $filters = $request->only(['search', 'model', 'manufacturer', 'software_version', 'tags']);
        $limit = min((int) $request->input('limit', 50), 200);
        $skip = max((int) $request->input('skip', 0), 0);

        try {
            $devices = $this->catalog->fetchDevices($filters, $limit, $skip);
            $rows = $this->catalog->enrichMany($devices, $this->catalog->context());
            $total = $this->catalog->countDevices($filters);
            $connected = true;
            $error = null;
        } catch (\Throwable $e) {
            Log::warning('GenieACS device list failed', ['error' => $e->getMessage()]);
            $rows = [];
            $total = 0;
            $connected = false;
            $error = $e->getMessage();
        }

        return view('noc.genieacs.devices', compact('rows', 'total', 'filters', 'limit', 'skip', 'connected', 'error'));
    }

    /**
     * Single device detail with CWMP parameter tree.
     */
    public function deviceDetail(string $deviceId): View
    {
        $device = $this->repo->getDevice($deviceId);

        // GenieACS _id dapat mengandung "%2D" untuk hyphen di ProductClass (mis. H1s%2D3).
        // URL yang datang sudah di-decode Laravel menjadi "-" sehingga query exact _id gagal.
        // Fallback: cari via serial (segmen terakhir _id) menggunakan findBySerial.
        if (! $device) {
            $decoded = urldecode($deviceId);
            if ($decoded !== $deviceId) {
                $device = $this->repo->getDevice($decoded);
            }
        }
        if (! $device) {
            $serial = null;
            // _id format OUI-ProductClass-Serial, serial adalah setelah hyphen terakhir
            if (str_contains($deviceId, '-')) {
                $parts = explode('-', $deviceId);
                $serial = end($parts);
            }
            if ($serial) {
                try {
                    $found = $this->client->findBySerial($serial);
                    if ($found && isset($found['_id'])) {
                        $device = $this->repo->getDevice($found['_id']);
                        if ($device) {
                            $deviceId = $found['_id'];
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('GenieACS device fallback findBySerial failed', ['deviceId' => $deviceId, 'serial' => $serial, 'error' => $e->getMessage()]);
                }
            }
        }

        if (! $device) {
            return view('noc.genieacs.device-detail', [
                'device' => null,
                'deviceId' => $deviceId,
                'error' => 'Device not found or GenieACS unreachable.',
            ]);
        }

        $summary = null;
        $ping = null;
        $writable = [];

        try {
            $summary = $this->catalog->summarize($device);

            try {
                $summary = array_merge($summary, $this->catalog->enrich($device, $this->catalog->context()));
            } catch (\Throwable $e) {
                Log::warning('GenieACS device enrich failed', ['device' => $deviceId, 'error' => $e->getMessage()]);
            }

            $ping = $this->pingDevice($summary['wan_ip']);
            $writable = $this->catalog->writableLeaves($device);
        } catch (\Throwable $e) {
            Log::warning('GenieACS device summary failed', ['device' => $deviceId, 'error' => $e->getMessage()]);
        }

        $tasks = $this->repo->getTasks($deviceId);
        $faults = $this->repo->getFaultsByDevice($deviceId);

        return view('noc.genieacs.device-detail', compact('device', 'deviceId', 'tasks', 'faults', 'summary', 'ping', 'writable'));
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
            'baseUrl' => Setting::get('genieacs_base_url') ?: config('genieacs.base_url', ''),
            'username' => Setting::get('genieacs_username') ?: config('genieacs.username', ''),
            'hasPassword' => filled(Setting::get('genieacs_password')) || filled(config('genieacs.password')),
            'timeout' => config('genieacs.timeout', 30),
        ]);
    }

    /**
     * Save GenieACS connection settings (AJAX POST).
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_url' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        $url = trim((string) ($data['base_url'] ?? ''));
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            return response()->json(['ok' => false, 'message' => 'URL harus diawali http:// atau https://'], 422);
        }

        Setting::set('genieacs_base_url', $url !== '' ? $url : null);

        $username = trim((string) ($data['username'] ?? ''));
        Setting::set('genieacs_username', $username !== '' ? $username : null);

        if (array_key_exists('password', $data)) {
            $password = trim((string) ($data['password'] ?? ''));
            Setting::set('genieacs_password', $password !== '' ? $password : null);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Konfigurasi GenieACS tersimpan',
        ]);
    }

    // ── AJAX Actions ───────────────────────────────────────

    /**
     * Simpan konfigurasi WAN/LAN/WLAN ke ONT via setParameterValues.
     * Hanya menerima path yang benar-benar writable pada device tsb.
     */
    public function setParams(Request $request, string $deviceId): JsonResponse
    {
        $request->validate([
            'parameters' => ['required', 'array', 'min:1'],
            'parameters.*.path' => ['required', 'string'],
            'parameters.*.value' => ['nullable', 'string'],
        ]);

        $device = $this->repo->getDevice($deviceId);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Device tidak ditemukan atau GenieACS tidak terjangkau.',
            ], 422);
        }

        $allowed = $this->catalog->writableLeaves($device);

        $parameterValues = [];

        foreach ($request->input('parameters') as $item) {
            $path = (string) $item['path'];

            if (! isset($allowed[$path])) {
                continue;
            }

            $value = trim((string) ($item['value'] ?? ''));
            $type = $allowed[$path];
            $parameterValues[] = [$path, $this->castValue($value, $type), $type];
        }

        if ($parameterValues === []) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada parameter yang boleh diubah.',
            ], 422);
        }

        try {
            $result = $this->repo->setParameterValues($deviceId, $parameterValues);

            // Paksa sesi inform segera sehingga task setParameterValues langsung
            // dieksekusi CPE. Dijalankan afterResponse agar notifikasi sukses
            // langsung kembali ke browser tanpa menunggu device merespon
            // Connection Request (yang bisa 5-30 detik / timeout jika offline).
            $repo = $this->repo;
            app()->terminating(function () use ($repo, $deviceId) {
                try {
                    $repo->connectionRequest($deviceId, 5);
                } catch (\Throwable $e) {
                    Log::warning('GenieACS connection request after setParameterValues', ['device' => $deviceId, 'error' => $e->getMessage()]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Task setParameterValues dikirim ('.count($parameterValues).' parameter), perangkat di-refresh via Connection Request.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenieACS setParameterValues failed', ['device' => $deviceId, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal kirim konfigurasi: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  'xsd:string'|'xsd:boolean'|'xsd:unsignedInt'|'xsd:int'|string  $type
     */
    private function castValue(string $value, string $type): string
    {
        $lower = mb_strtolower($type);

        return match (true) {
            str_contains($lower, 'boolean') => in_array(mb_strtolower($value), ['1', 'true', 'on', 'yes'], true)
                ? 'true'
                : 'false',
            str_contains($lower, 'int') => (string) (int) $value,
            default => $value,
        };
    }

    /**
     * Tambah tag ke device (disimpan ke `_tags` device GenieACS).
     */
    public function addTag(Request $request, string $deviceId): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:40'],
        ]);

        $device = $this->repo->getDevice($deviceId);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Device tidak ditemukan atau GenieACS tidak terjangkau.',
            ], 422);
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nama tag tidak boleh kosong.',
            ], 422);
        }

        $tags = is_array($device['_tags'] ?? null) ? $device['_tags'] : [];
        $tags[$name] = '1';

        try {
            $this->repo->updateTags($deviceId, $tags);

            return response()->json([
                'success' => true,
                'message' => 'Tag "'.$name.'" ditambahkan ke device.',
                'data' => $tags,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenieACS update tags failed', ['device' => $deviceId, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal simpan tag: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ukur latency device via TCP ke port TR069 (7547) lalu fallback 80.
     */
    private function pingDevice(?string $ip): ?int
    {
        if (! is_string($ip) || $ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        foreach ([7547, 80] as $port) {
            $start = microtime(true);
            $conn = @fsockopen($ip, $port, $errno, $errstr, 1.5);

            if (is_resource($conn)) {
                $elapsed = (microtime(true) - $start) * 1000;
                fclose($conn);

                return (int) round($elapsed);
            }
        }

        return null;
    }

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
     * Trigger a connection request (summon) to a device (AJAX POST).
     */
    public function summon(string $deviceId): JsonResponse
    {
        try {
            $result = $this->repo->connectionRequest($deviceId);

            return response()->json([
                'success' => true,
                'message' => 'Summon (connection request) dikirim ke '.$deviceId,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal summon: '.$e->getMessage(),
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

    /**
     * Delete CWMP object instance (AJAX POST) — hapus WANConnection dll.
     */
    public function deleteObject(Request $request, string $deviceId): JsonResponse
    {
        $request->validate([
            'object' => ['required', 'string', 'max:255'],
        ]);

        $objectName = trim((string) $request->input('object'));

        try {
            $result = $this->repo->deleteObject($deviceId, $objectName);

            return response()->json([
                'success' => true,
                'message' => 'Delete object "'.$objectName.'" dikirim ke '.$deviceId,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal hapus object: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download / upgrade firmware (AJAX POST).
     */
    public function download(Request $request, string $deviceId): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'string', 'max:255'],
        ]);

        $file = trim((string) $request->input('file'));

        try {
            $result = $this->repo->downloadFirmware($deviceId, $file);

            return response()->json([
                'success' => true,
                'message' => 'Upgrade firmware "'.$file.'" dikirim ke '.$deviceId,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal upgrade firmware: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete device from GenieACS (AJAX DELETE).
     */
    public function destroy(string $deviceId): JsonResponse
    {
        try {
            $result = $this->repo->deleteDevice($deviceId);

            return response()->json([
                'success' => true,
                'message' => 'Device '.$deviceId.' dihapus dari GenieACS.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal hapus device: '.$e->getMessage(),
            ], 500);
        }
    }
}
