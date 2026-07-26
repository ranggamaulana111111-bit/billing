<?php

namespace App\Modules\GenieACS\Repositories;

use App\Modules\GenieACS\Contracts\IGenieACSClient;

/**
 * Data-access layer for GenieACS resources.
 *
 * Wraps the HTTP client and provides filter-aware query building
 * for devices, faults, presets, provisions, and tasks. All methods
 * delegate to IGenieACSClient — no direct HTTP calls.
 */
class GenieACSRepository
{
    public function __construct(
        protected IGenieACSClient $client,
    ) {}

    // ── Devices ────────────────────────────────────────────

    /**
     * Get a filtered, paginated list of devices.
     *
     * Supported filters: search, model, manufacturer, software_version, tags, last_inform_before.
     *
     * @param  array<string, string>  $filters
     */
    public function getDevices(array $filters = [], int $limit = 50, int $skip = 0): array
    {
        $query = $this->buildDeviceQuery($filters);

        return $this->client->devices($query, [], $limit, $skip);
    }

    /**
     * Get a single device by ID with full CWMP parameter tree.
     */
    public function getDevice(string $deviceId): ?array
    {
        return $this->client->device($deviceId);
    }

    /**
     * Get a single device with specific CWMP parameters only.
     *
     * @param  string[]  $projection  CWMP parameter paths to include
     */
    public function getDeviceByProjection(string $deviceId, array $projection): ?array
    {
        return $this->client->device($deviceId, $projection);
    }

    /**
     * Count devices matching the given filters.
     *
     * @param  array<string, string>  $filters
     */
    public function countDevices(array $filters = []): int
    {
        $query = $this->buildDeviceQuery($filters);
        $devices = $this->client->devices($query, [], 0, 0);

        return is_array($devices) ? count($devices) : 0;
    }

    // ── Faults ─────────────────────────────────────────────

    /**
     * Get a filtered, paginated list of faults.
     *
     * @param  array<string, string|int>  $filters  Supported: device, code
     */
    public function getFaults(array $filters = [], int $limit = 50, int $skip = 0): array
    {
        $query = $this->buildFaultQuery($filters);

        return $this->client->faults($query, $limit, $skip);
    }

    /**
     * Get all faults for a specific device.
     */
    public function getFaultsByDevice(string $deviceId): array
    {
        return $this->client->faults(['device' => $deviceId]);
    }

    // ── Presets ────────────────────────────────────────────

    /**
     * Get all presets configured in GenieACS.
     */
    public function getPresets(): array
    {
        return $this->client->presets();
    }

    // ── Provisions ─────────────────────────────────────────

    /**
     * Get all provision scripts configured in GenieACS.
     */
    public function getProvisions(): array
    {
        return $this->client->provisions();
    }

    // ── Tasks ──────────────────────────────────────────────

    /**
     * Get pending tasks for a specific device.
     */
    public function getTasks(string $deviceId): array
    {
        return $this->client->tasks($deviceId);
    }

    // ── Device Actions ─────────────────────────────────────

    /**
     * Send a reboot task to a device.
     */
    public function rebootDevice(string $deviceId): array
    {
        return $this->client->reboot($deviceId);
    }

    /**
     * Send a factory reset task to a device.
     */
    public function factoryResetDevice(string $deviceId): array
    {
        return $this->client->factoryReset($deviceId);
    }

    /**
     * Refresh (re-read) a CWMP object tree from the device.
     */
    public function refreshObject(string $deviceId, string $objectName): array
    {
        return $this->client->refreshObject($deviceId, $objectName);
    }

    /**
     * Set parameter values on a device via CWMP.
     *
     * @param  array<array{0: string, 1: mixed, 2?: string}>  $parameterValues
     */
    public function setParameterValues(string $deviceId, array $parameterValues): array
    {
        return $this->client->setParameterValues($deviceId, $parameterValues);
    }

    /**
     * Read parameter values from a device via CWMP.
     *
     * @param  string[]  $parameterNames
     */
    public function getParameterValues(string $deviceId, array $parameterNames): array
    {
        return $this->client->getParameterValues($deviceId, $parameterNames);
    }

    /**
     * Send a firmware download task to a device.
     */
    public function downloadFirmware(string $deviceId, string $fileName): array
    {
        return $this->client->downloadFirmware($deviceId, $fileName);
    }

    // ── Query Builders ─────────────────────────────────────

    /**
     * Build a MongoDB-style query from human-readable filters.
     *
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    protected function buildDeviceQuery(array $filters): array
    {
        $query = [];

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query['_id'] = ['$regex' => $search, '$options' => 'i'];
        }

        if (isset($filters['model']) && $filters['model'] !== '') {
            $query['InternetGatewayDevice.DeviceInfo.ModelName'] = $filters['model'];
        }

        if (isset($filters['manufacturer']) && $filters['manufacturer'] !== '') {
            $query['InternetGatewayDevice.DeviceInfo.Manufacturer'] = $filters['manufacturer'];
        }

        if (isset($filters['software_version']) && $filters['software_version'] !== '') {
            $query['InternetGatewayDevice.DeviceInfo.SoftwareVersion'] = $filters['software_version'];
        }

        if (isset($filters['tags']) && $filters['tags'] !== '') {
            $query['_tags'] = $filters['tags'];
        }

        if (isset($filters['last_inform_before']) && $filters['last_inform_before'] !== '') {
            $query['_lastInform'] = ['$lt' => $filters['last_inform_before']];
        }

        return $query;
    }

    /**
     * Build a MongoDB-style query for fault filtering.
     *
     * @param  array<string, string|int>  $filters
     * @return array<string, mixed>
     */
    protected function buildFaultQuery(array $filters): array
    {
        $query = [];

        if (isset($filters['device']) && $filters['device'] !== '') {
            $query['device'] = $filters['device'];
        }

        if (isset($filters['code']) && $filters['code'] !== '') {
            $query['code'] = (int) $filters['code'];
        }

        return $query;
    }
}
