<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    public function getGatewayName(): string;

    public function isConfigured(): bool;

    public function createTransaction(array $params): array;

    public function handleNotification(array $payload): array;

    public function verifySignature(array $payload): bool;

    public function getStatus(string $orderId): array;
}
