<?php

namespace App\Modules\GenieACS\DTO;

/**
 * Immutable representation of a GenieACS device (ONT/CPE).
 *
 * Maps from the raw GenieACS API response array into typed,
 * readable properties. Retains the full raw response for
 * advanced use cases.
 */
class DeviceDTO
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $serialNumber = null,
        public readonly ?string $manufacturer = null,
        public readonly ?string $modelName = null,
        public readonly ?string $softwareVersion = null,
        public readonly ?string $hardwareVersion = null,
        public readonly ?string $lastInform = null,
        public readonly ?string $ip = null,
        /** @var string[] */
        public readonly array $tags = [],
        /** @var array<string, mixed> */
        public readonly array $parameters = [],
        /** @var array<string, mixed> */
        public readonly array $raw = [],
    ) {}

    /**
     * Create an instance from a raw GenieACS device API response.
     *
     * @param  array<string, mixed>  $data  Raw JSON-decoded response from GenieACS
     */
    public static function fromResponse(array $data): self
    {
        return new self(
            id: $data['_id'] ?? '',
            serialNumber: $data['InternetGatewayDevice.DeviceInfo.SerialNumber'] ?? $data['serialNumber'] ?? null,
            manufacturer: $data['InternetGatewayDevice.DeviceInfo.Manufacturer'] ?? $data['manufacturer'] ?? null,
            modelName: $data['InternetGatewayDevice.DeviceInfo.ModelName'] ?? $data['modelName'] ?? null,
            softwareVersion: $data['InternetGatewayDevice.DeviceInfo.SoftwareVersion'] ?? $data['softwareVersion'] ?? null,
            hardwareVersion: $data['InternetGatewayDevice.DeviceInfo.HardwareVersion'] ?? $data['hardwareVersion'] ?? null,
            lastInform: $data['_lastInform'] ?? null,
            ip: $data['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.IPAddress'] ?? $data['ip'] ?? null,
            tags: $data['_tags'] ?? [],
            parameters: collect($data)->filter(fn ($v, $k) => str_starts_with((string) $k, 'InternetGatewayDevice.'))->toArray(),
            raw: $data,
        );
    }

    /**
     * Map an array of raw devices into DTO instances.
     *
     * @param  array<int, array<string, mixed>>  $devices
     * @return self[]
     */
    public static function fromCollection(array $devices): array
    {
        return array_map(fn (array $data) => self::fromResponse($data), $devices);
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
            'serial_number' => $this->serialNumber,
            'manufacturer' => $this->manufacturer,
            'model_name' => $this->modelName,
            'software_version' => $this->softwareVersion,
            'hardware_version' => $this->hardwareVersion,
            'last_inform' => $this->lastInform,
            'ip' => $this->ip,
            'tags' => $this->tags,
            'parameters' => $this->parameters,
        ];
    }

    /**
     * Get a human-readable display name (model or device ID).
     */
    public function getDisplayName(): string
    {
        return $this->modelName ?? $this->id;
    }
}
