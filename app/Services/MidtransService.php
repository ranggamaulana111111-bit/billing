<?php

namespace App\Services;

use App\Models\Setting;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;

class MidtransService
{
    protected ?string $serverKey;

    protected bool $isProduction;

    public function __construct(?int $userId = null)
    {
        $this->serverKey = Setting::get('midtrans_server_key', null, $userId);
        $this->isProduction = (bool) Setting::get('midtrans_is_production', false, $userId);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->serverKey);
    }

    public function getSnapToken(array $params): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Server key tidak dikonfigurasi.'];
        }

        Config::$serverKey = $this->serverKey;
        Config::$isProduction = $this->isProduction;
        Config::$isSanitized = true;
        Config::$is3ds = true;

        try {
            $token = Snap::getSnapToken($params);

            return ['success' => true, 'token' => $token];
        } catch (\Exception $e) {
            report($e);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleNotification(): array
    {
        Config::$serverKey = $this->serverKey;
        Config::$isProduction = $this->isProduction;

        try {
            $notif = new Notification;

            return [
                'order_id' => $notif->order_id,
                'status' => $notif->transaction_status,
                'gross_amount' => $notif->gross_amount,
                'payment_type' => $notif->payment_type,
                'success' => in_array($notif->transaction_status, ['capture', 'settlement']),
            ];
        } catch (\Exception $e) {
            report($e);

            return ['success' => false];
        }
    }
}
