<?php

namespace App\Modules\GenieACS\DTO;

/**
 * Immutable representation of a GenieACS fault.
 *
 * Faults occur when a task fails to execute on a device.
 * Each fault has a numeric code indicating the error type.
 */
class FaultDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $deviceId,
        public readonly string $channel,
        public readonly ?int $code = null,
        public readonly ?string $message = null,
        public readonly ?string $timestamp = null,
        public readonly int $retries = 0,
        public readonly ?string $lastRetry = null,
        public readonly ?string $faultTimestamp = null,
        public readonly ?string $inform = null,
        /** @var array<string, mixed> */
        public readonly array $raw = [],
    ) {}

    /**
     * Create an instance from a raw GenieACS fault API response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromResponse(array $data): self
    {
        return new self(
            id: $data['_id'] ?? '',
            deviceId: $data['device'] ?? '',
            channel: $data['channel'] ?? '',
            code: isset($data['code']) ? (int) $data['code'] : null,
            message: $data['message'] ?? null,
            timestamp: $data['timestamp'] ?? null,
            retries: $data['retries'] ?? 0,
            lastRetry: $data['lastRetry'] ?? null,
            faultTimestamp: $data['faultTimestamp'] ?? null,
            inform: $data['inform'] ?? null,
            raw: $data,
        );
    }

    /**
     * Map an array of raw faults into DTO instances.
     *
     * @param  array<int, array<string, mixed>>  $faults
     * @return self[]
     */
    public static function fromCollection(array $faults): array
    {
        return array_map(fn (array $fault) => self::fromResponse($fault), $faults);
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
            'channel' => $this->channel,
            'code' => $this->code,
            'message' => $this->message,
            'timestamp' => $this->timestamp,
            'retries' => $this->retries,
            'last_retry' => $this->lastRetry,
            'fault_timestamp' => $this->faultTimestamp,
            'inform' => $this->inform,
        ];
    }

    /**
     * Get a human-readable label for the fault code.
     */
    public function getCodeLabel(): string
    {
        return match (true) {
            $this->code === 0 => 'No Fault',
            $this->code === 1 => 'Failure in contacting device',
            $this->code === 2 => 'Failure in connecting to device',
            $this->code === 3 => 'Failure in sending message to device',
            $this->code === 4 => 'Response received from device',
            $this->code === 5 => 'Timeout waiting for response from device',
            default => 'Fault-'.$this->code,
        };
    }
}
