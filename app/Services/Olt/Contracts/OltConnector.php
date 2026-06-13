<?php

namespace App\Services\Olt\Contracts;

interface OltConnector
{
    public function connect(string $host, int $port, string $username, string $password): bool;

    public function disconnect(): void;

    public function testConnection(): array;

    public function getSystemInfo(): array;

    public function getOnuList(int $slot, int $port): array;

    public function getOnuDetail(string $onuId): array;

    public function provisionOnu(array $data): array;

    public function removeOnu(string $onuId): array;

    public function rebootOnu(string $onuId): array;

    public function getPortStatus(int $slot, int $port): array;

    public function getOpticalPower(string $onuId): array;
}
