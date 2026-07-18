<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransGateway implements PaymentGatewayInterface
{
    protected ?string $serverKey;

    protected bool $isProduction;

    public function __construct(?int $tenantId = null)
    {
        $this->serverKey = Setting::get('midtrans_server_key', null, $tenantId);
        $this->isProduction = (bool) Setting::get('midtrans_is_production', false, $tenantId);
    }

    public function getGatewayName(): string
    {
        return 'midtrans';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->serverKey);
    }

    public function getServerKey(): ?string
    {
        return $this->serverKey;
    }

    public function createTransaction(array $params): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Midtrans server key tidak dikonfigurasi.'];
        }

        $this->configure();

        try {
            $token = Snap::getSnapToken($params);

            return ['success' => true, 'token' => $token];
        } catch (\Exception $e) {
            report($e);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleNotification(array $payload): array
    {
        $this->configure();

        try {
            $notif = new Notification;

            return [
                'success' => in_array($notif->transaction_status, ['capture', 'settlement']),
                'order_id' => $notif->order_id,
                'transaction_id' => $notif->transaction_id,
                'transaction_status' => $notif->transaction_status,
                'payment_type' => $notif->payment_type,
                'gross_amount' => $notif->gross_amount,
                'status_code' => $notif->status_code,
                'status_message' => $notif->status_message,
            ];
        } catch (\Exception $e) {
            report($e);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifySignature(array $payload): bool
    {
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signatureKey = $payload['signature_key'] ?? '';

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        return hash_equals($expected, $signatureKey);
    }

    public function getStatus(string $orderId): array
    {
        $this->configure();

        try {
            $status = Transaction::status($orderId);

            return [
                'success' => true,
                'order_id' => $status->order_id,
                'transaction_status' => $status->transaction_status,
                'payment_type' => $status->payment_type,
                'gross_amount' => $status->gross_amount,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function configure(): void
    {
        Config::$serverKey = $this->serverKey;
        Config::$isProduction = $this->isProduction;
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }
}
