<?php

namespace App\Modules\GenieACS\Contracts;

/**
 * Contract for GenieACS NBI (Northbound Interface) HTTP client.
 *
 * Defines all read, write, and task operations against the
 * GenieACS REST API. Implementations must handle authentication,
 * error mapping, and JSON decoding.
 */
interface IGenieACSClient
{
    // ── Read Operations ────────────────────────────────────

    /**
     * Search for devices matching a MongoDB-style query.
     *
     * @param  array<string, mixed>  $query  MongoDB query filter
     * @param  string[]  $projection  CWMP parameter paths to include
     */
    public function devices(array $query = [], array $projection = [], int $limit = 0, int $skip = 0): array;

    /**
     * Get a single device by its unique identifier.
     *
     * @param  string[]  $projection  CWMP parameter paths to include
     */
    public function device(string $deviceId, array $projection = []): ?array;

    /**
     * Find a single device by its serial number (case-insensitive).
     *
     * Used for on-demand ACS detection from the map card without waiting
     * for the scheduled sync. Returns the raw GenieACS device array or null.
     */
    public function findBySerial(string $serial, int $timeout = 8): ?array;

    /**
     * Get pending tasks for a specific device.
     */
    public function tasks(string $deviceId): array;

    /**
     * Search for faults matching a MongoDB-style query.
     *
     * @param  array<string, mixed>  $query  MongoDB query filter
     */
    public function faults(array $query = [], int $limit = 0, int $skip = 0): array;

    /**
     * Get all presets configured in GenieACS.
     */
    public function presets(): array;

    /**
     * Get all provision scripts configured in GenieACS.
     */
    public function provisions(): array;

    // ── Write / Task Operations ────────────────────────────

    /**
     * Trigger a CWMP connection request to a device.
     */
    public function connectionRequest(string $deviceId): array;

    /**
     * Send a reboot task to a device.
     */
    public function reboot(string $deviceId): array;

    /**
     * Send a factory reset task to a device.
     */
    public function factoryReset(string $deviceId): array;

    /**
     * Send a firmware download task to a device.
     */
    public function downloadFirmware(string $deviceId, string $fileName): array;

    /**
     * Refresh (re-read) a CWMP object tree from the device.
     */
    public function refreshObject(string $deviceId, string $objectName): array;

    /**
     * Set parameter values on a device via CWMP.
     *
     * @param  array<array{0: string, 1: mixed, 2?: string}>  $parameterValues
     *                                                                          Each entry: [cwmpPath, value, type?]
     */
    public function setParameterValues(string $deviceId, array $parameterValues): array;

    /**
     * Read parameter values from a device via CWMP.
     *
     * @param  string[]  $parameterNames  CWMP parameter paths to read
     */
    public function getParameterValues(string $deviceId, array $parameterNames): array;
}
