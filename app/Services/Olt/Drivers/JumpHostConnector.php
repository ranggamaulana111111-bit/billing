<?php

namespace App\Services\Olt\Drivers;

use App\Services\Olt\Contracts\OltConnector;
use App\Services\Olt\SshTunnel;

class JumpHostConnector implements OltConnector
{
    private ?SshTunnel $tunnel = null;

    public function __construct(
        private OltConnector $inner,
        private string $jumpHost,
        private int $jumpPort,
        private string $jumpUser,
        private string $jumpPass,
    ) {}

    public function connect(string $host, int $port, string $username, string $password): bool
    {
        $this->tunnel = new SshTunnel(
            $this->jumpHost,
            $this->jumpPort,
            $this->jumpUser,
            $this->jumpPass,
            $host,
            $port,
        );

        return $this->inner->connect('127.0.0.1', $this->tunnel->getLocalPort(), $username, $password);
    }

    public function disconnect(): void
    {
        $this->inner->disconnect();
        if ($this->tunnel) {
            $this->tunnel->close();
            $this->tunnel = null;
        }
    }

    public function testConnection(): array
    {
        return $this->inner->testConnection();
    }

    public function getSystemInfo(): array
    {
        return $this->inner->getSystemInfo();
    }

    public function getOnuList(int $slot, int $port): array
    {
        return $this->inner->getOnuList($slot, $port);
    }

    public function getOnuDetail(string $onuId): array
    {
        return $this->inner->getOnuDetail($onuId);
    }

    public function provisionOnu(array $data): array
    {
        return $this->inner->provisionOnu($data);
    }

    public function removeOnu(string $onuId): array
    {
        return $this->inner->removeOnu($onuId);
    }

    public function rebootOnu(string $onuId): array
    {
        return $this->inner->rebootOnu($onuId);
    }

    public function getPortStatus(int $slot, int $port): array
    {
        return $this->inner->getPortStatus($slot, $port);
    }

    public function getOpticalPower(string $onuId): array
    {
        return $this->inner->getOpticalPower($onuId);
    }
}
