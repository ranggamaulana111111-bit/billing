<?php

namespace App\Modules\GenieACS\DTO;

/**
 * Immutable representation of a GenieACS preset.
 *
 * Presets apply configurations or provision scripts to devices
 * based on a precondition filter, schedule, and priority weight.
 */
class PresetDTO
{
    public function __construct(
        public readonly string $name,
        public readonly int $weight = 0,
        public readonly ?string $precondition = null,
        /** @var array<int, array<string, mixed>> */
        public readonly array $configurations = [],
        public readonly ?string $schedule = null,
        /** @var array<string, mixed> */
        public readonly array $raw = [],
    ) {}

    /**
     * Create an instance from a raw GenieACS preset API response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromResponse(string $name, array $data): self
    {
        return new self(
            name: $name,
            weight: $data['weight'] ?? 0,
            precondition: $data['precondition'] ?? null,
            configurations: $data['configurations'] ?? [],
            schedule: $data['schedule'] ?? null,
            raw: $data,
        );
    }

    /**
     * Map a collection of raw presets into DTO instances.
     *
     * GenieACS returns presets as an associative array keyed by name.
     *
     * @param  array<string, array<string, mixed>>  $presets
     * @return self[]
     */
    public static function fromCollection(array $presets): array
    {
        $result = [];

        foreach ($presets as $name => $data) {
            if (is_array($data)) {
                $result[] = self::fromResponse($name, $data);
            }
        }

        return $result;
    }

    /**
     * Convert to a flat associative array for view/JSON rendering.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'weight' => $this->weight,
            'precondition' => $this->precondition,
            'configurations' => $this->configurations,
            'schedule' => $this->schedule,
        ];
    }

    /**
     * Get the number of configuration entries in this preset.
     */
    public function getConfigurationCount(): int
    {
        return count($this->configurations);
    }

    /**
     * Check if this preset has a device filter precondition.
     */
    public function hasPrecondition(): bool
    {
        return $this->precondition !== null && $this->precondition !== '';
    }

    /**
     * Check if this preset has a cron schedule.
     */
    public function hasSchedule(): bool
    {
        return $this->schedule !== null && $this->schedule !== '';
    }
}
