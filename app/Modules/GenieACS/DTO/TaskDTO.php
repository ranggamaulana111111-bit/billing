<?php

namespace App\Modules\GenieACS\DTO;

/**
 * Immutable representation of a GenieACS task.
 *
 * Tasks represent queued or executed operations on a device
 * (e.g., reboot, setParameterValues, getParameterValues).
 */
class TaskDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $deviceId,
        public readonly string $name,
        public readonly ?string $status = null,
        public readonly ?string $fault = null,
        public readonly ?int $retries = null,
        public readonly ?string $timestamp = null,
        public readonly ?string $lastUpdated = null,
        /** @var array<string, mixed> */
        public readonly array $raw = [],
    ) {}

    /**
     * Create an instance from a raw GenieACS task API response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromResponse(array $data): self
    {
        return new self(
            id: $data['_id'] ?? '',
            deviceId: $data['device'] ?? '',
            name: $data['name'] ?? '',
            status: $data['status'] ?? null,
            fault: $data['fault'] ?? null,
            retries: $data['retries'] ?? null,
            timestamp: $data['timestamp'] ?? null,
            lastUpdated: $data['lastUpdated'] ?? null,
            raw: $data,
        );
    }

    /**
     * Map an array of raw tasks into DTO instances.
     *
     * @param  array<int, array<string, mixed>>  $tasks
     * @return self[]
     */
    public static function fromCollection(array $tasks): array
    {
        return array_map(fn (array $task) => self::fromResponse($task), $tasks);
    }

    /**
     * Convert to a flat associative array for view/JSON rendering.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->deviceId,
            'name' => $this->name,
            'status' => $this->status,
            'fault' => $this->fault,
            'retries' => $this->retries,
            'timestamp' => $this->timestamp,
            'last_updated' => $this->lastUpdated,
        ];
    }

    /**
     * Check if the task completed successfully.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the task is in a fault state.
     */
    public function isFaulty(): bool
    {
        return $this->status === 'fault';
    }
}
