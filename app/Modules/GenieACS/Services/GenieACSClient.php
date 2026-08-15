<?php

namespace App\Modules\GenieACS\Services;

use App\Models\Setting;
use App\Modules\GenieACS\Contracts\IGenieACSClient;
use App\Modules\GenieACS\Exceptions\GenieACSApiException;
use App\Modules\GenieACS\Exceptions\GenieACSAuthenticationException;
use App\Modules\GenieACS\Exceptions\GenieACSConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for GenieACS NBI (Northbound Interface) REST API.
 *
 * Implements the full IGenieACSClient contract covering read,
 * write, and task operations against the GenieACS server.
 *
 * All methods return decoded JSON arrays. Exceptions are thrown
 * on connection, authentication, or API-level failures.
 */
class GenieACSClient implements IGenieACSClient
{
    protected string $baseUrl;

    protected string $username;

    protected string $password;

    protected int $timeout;

    public function __construct()
    {
        $settingBase = Setting::get('genieacs_base_url');
        $this->baseUrl = rtrim((string) ($settingBase ?: (config('genieacs.base_url') ?: 'http://localhost:7557')), '/');
        $this->username = (string) (config('genieacs.username') ?? '');
        $this->password = (string) (config('genieacs.password') ?? '');
        $this->timeout = (int) config('genieacs.timeout', 30);
    }

    /**
     * Test connectivity to the GenieACS NBI server.
     *
     * Returns a status array with response time and device count.
     *
     * @return array{success: bool, message: string, response_time: float, device_count?: int}
     */
    public function testConnection(): array
    {
        $start = microtime(true);

        try {
            $response = $this->request()->get('/devices', ['limit' => 1]);

            $responseTime = round((microtime(true) - $start) * 1000, 2);

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'Connection failed: HTTP '.$response->status(),
                    'response_time' => $responseTime,
                ];
            }

            $devices = $response->json();

            return [
                'success' => true,
                'message' => 'Connected to GenieACS',
                'response_time' => $responseTime,
                'device_count' => is_array($devices) ? count($devices) : 0,
            ];
        } catch (\Exception $e) {
            Log::error('GenieACS connection failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
                'response_time' => round((microtime(true) - $start) * 1000, 2),
            ];
        }
    }

    /**
     * Search for devices in GenieACS.
     *
     * @param  array<string, mixed>  $query  MongoDB-style search query
     * @param  string[]  $projection  CWMP parameter paths to include
     */
    public function devices(array $query = [], array $projection = [], int $limit = 0, int $skip = 0): array
    {
        $params = $this->buildListParams($query, $projection, $limit, $skip);

        return $this->sendRequest('GET', '/devices', $params);
    }

    /**
     * Get a single device by its ID.
     *
     * @param  string[]  $projection  CWMP parameter paths to include
     */
    public function device(string $deviceId, array $projection = []): ?array
    {
        $params = ['query' => json_encode(['_id' => $deviceId])];

        if ($projection !== []) {
            $params['projection'] = implode(',', $projection);
        }

        $devices = $this->sendRequest('GET', '/devices', $params);

        return is_array($devices) && count($devices) > 0 ? $devices[0] : null;
    }

    /**
     * Get pending tasks for a specific device.
     */
    public function tasks(string $deviceId): array
    {
        $params = ['query' => json_encode(['device' => $deviceId])];

        return $this->sendRequest('GET', '/tasks', $params);
    }

    /**
     * Search for faults in GenieACS.
     *
     * @param  array<string, mixed>  $query  MongoDB-style search query
     */
    public function faults(array $query = [], int $limit = 0, int $skip = 0): array
    {
        $params = [];

        if ($query !== []) {
            $params['query'] = json_encode($query);
        }

        if ($limit > 0) {
            $params['limit'] = $limit;
        }

        if ($skip > 0) {
            $params['skip'] = $skip;
        }

        return $this->sendRequest('GET', '/faults', $params);
    }

    /**
     * Get all presets from GenieACS.
     */
    public function presets(): array
    {
        return $this->sendRequest('GET', '/presets');
    }

    /**
     * Get all provisions from GenieACS.
     */
    public function provisions(): array
    {
        return $this->sendRequest('GET', '/provisions');
    }

    /**
     * Trigger a connection request to a device.
     *
     * Sends a CWMP ConnectionRequest to the device, prompting it
     * to initiate an inform session with the ACS.
     */
    public function connectionRequest(string $deviceId): array
    {
        return $this->sendRequest('POST', "/devices/{$deviceId}/tasks?connection_request", [
            'name' => 'connectionRequest',
        ]);
    }

    /**
     * Send a reboot task to a device.
     */
    public function reboot(string $deviceId): array
    {
        return $this->sendRequest('POST', "/devices/{$deviceId}/tasks", [
            'name' => 'reboot',
        ]);
    }

    /**
     * Send a factory reset task to a device.
     */
    public function factoryReset(string $deviceId): array
    {
        return $this->sendRequest('POST', "/devices/{$deviceId}/tasks", [
            'name' => 'factoryReset',
        ]);
    }

    /**
     * Send a firmware download task to a device.
     */
    public function downloadFirmware(string $deviceId, string $fileName): array
    {
        return $this->sendRequest('POST', "/devices/{$deviceId}/tasks", [
            'name' => 'download',
            'file' => $fileName,
        ]);
    }

    /**
     * Refresh (re-read) a CWMP object from the device.
     */
    public function refreshObject(string $deviceId, string $objectName): array
    {
        return $this->sendRequest('POST', "/devices/{$deviceId}/tasks", [
            'name' => 'refreshObject',
            'objectName' => $objectName,
        ]);
    }

    /**
     * Set parameter values on a device.
     *
     * @param  array<array{0: string, 1: mixed, 2?: string}>  $parameterValues
     *                                                                          Each entry: [cwmpPath, value, type?]
     */
    public function setParameterValues(string $deviceId, array $parameterValues): array
    {
        return $this->sendRequest('POST', "/devices/{$deviceId}/tasks", [
            'name' => 'setParameterValues',
            'parameterValues' => $parameterValues,
        ]);
    }

    /**
     * Get parameter values from a device.
     *
     * @param  string[]  $parameterNames  CWMP parameter paths to read
     */
    public function getParameterValues(string $deviceId, array $parameterNames): array
    {
        return $this->sendRequest('POST', "/devices/{$deviceId}/tasks", [
            'name' => 'getParameterValues',
            'parameterNames' => $parameterNames,
        ]);
    }

    /**
     * Build the base HTTP request with auth and headers.
     */
    protected function request(): PendingRequest
    {
        $http = Http::timeout($this->timeout)->baseUrl($this->baseUrl);

        if ($this->username !== '' && $this->password !== '') {
            $http = $http->withBasicAuth($this->username, $this->password);
        }

        return $http->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Send an HTTP request and return the decoded JSON response.
     *
     * @param  'GET'|'POST'|'PUT'|'DELETE'  $method
     */
    protected function sendRequest(string $method, string $endpoint, array $data = []): mixed
    {
        try {
            $response = match ($method) {
                'GET' => $this->request()->get($endpoint, $data),
                'POST' => $this->request()->post($endpoint, $data),
                'PUT' => $this->request()->put($endpoint, $data),
                'DELETE' => $this->request()->delete($endpoint),
            };

            if ($response->failed()) {
                $this->handleHttpError($response, $endpoint);
            }

            return $response->json();
        } catch (\Exception $e) {
            if ($e instanceof GenieACSConnectionException || $e instanceof GenieACSAuthenticationException || $e instanceof GenieACSApiException) {
                throw $e;
            }

            Log::error('GenieACS request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw GenieACSConnectionException::failed($e->getMessage());
        }
    }

    /**
     * Build query parameters for collection listing endpoints.
     *
     * @param  array<string, mixed>  $query
     * @param  string[]  $projection
     */
    protected function buildListParams(array $query, array $projection, int $limit, int $skip): array
    {
        $params = [];

        if ($query !== []) {
            $params['query'] = json_encode($query);
        }

        if ($projection !== []) {
            $params['projection'] = implode(',', $projection);
        }

        if ($limit > 0) {
            $params['limit'] = $limit;
        }

        if ($skip > 0) {
            $params['skip'] = $skip;
        }

        return $params;
    }

    /**
     * Map HTTP error responses to typed exceptions.
     *
     * @throws GenieACSAuthenticationException
     * @throws GenieACSApiException
     */
    protected function handleHttpError(Response $response, string $endpoint): never
    {
        $status = $response->status();

        if ($status === 401 || $status === 403) {
            Log::warning('GenieACS authentication failed', [
                'status' => $status,
                'endpoint' => $endpoint,
            ]);

            throw GenieACSAuthenticationException::invalidCredentials();
        }

        Log::warning('GenieACS API error', [
            'status' => $status,
            'endpoint' => $endpoint,
        ]);

        throw GenieACSApiException::failed($status, $endpoint);
    }
}
