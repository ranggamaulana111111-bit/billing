<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected ?string $token;

    protected ?int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->tenantId = $tenantId;
        $this->token = Setting::get('fonnte_token', null, $tenantId) ?: config('services.fonnte.token');
    }

    public static function cleanPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } elseif (str_starts_with($digits, '62') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    public function send(string $phone, string $message, string $countryCode = '62'): array
    {
        if (! $this->token) {
            return ['success' => false, 'error' => 'Token Fonnte tidak dikonfigurasi'];
        }

        $cleanPhone = static::cleanPhone($phone);

        if (static::isCooldown($cleanPhone)) {
            Log::info("Fonnte blocked: cooldown active for {$cleanPhone}");

            return ['success' => false, 'error' => 'Cooldown aktif, pesan ditunda'];
        }

        $dailyKey = 'fonnte_daily_'.date('Y-m-d');
        $dailyCount = Cache::get($dailyKey, 0);
        if ($dailyCount >= 200) {
            Log::warning('Fonnte blocked: daily limit reached (200 messages)');

            return ['success' => false, 'error' => 'Batas harian tercapai (200 pesan)'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->timeout(15)->post('https://api.fonnte.com/send', [
                'target' => $cleanPhone,
                'message' => $message,
                'countryCode' => $countryCode,
            ]);

            $body = $response->json();

            if ($response->successful() && ($body['status'] ?? false)) {
                Cache::put($dailyKey, $dailyCount + 1, now()->endOfDay());
                static::setCooldown($cleanPhone, 60);

                return ['success' => true, 'response' => $body];
            }

            $errorMsg = $body['reason'] ?? $body['message'] ?? 'Unknown error';
            Log::warning("Fonnte API error: {$errorMsg}", [
                'phone_raw' => $phone,
                'phone_clean' => $cleanPhone,
                'response' => $body,
            ]);

            return ['success' => false, 'error' => $errorMsg, 'response' => $body];
        } catch (\Exception $e) {
            Log::error("Fonnte HTTP exception: {$e->getMessage()}", [
                'phone_raw' => $phone,
                'phone_clean' => $cleanPhone,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function isCooldown(string $cleanPhone): bool
    {
        return Cache::has('fonnte_cooldown_'.$cleanPhone);
    }

    public static function setCooldown(string $cleanPhone, int $seconds = 60): void
    {
        Cache::put('fonnte_cooldown_'.$cleanPhone, true, now()->addSeconds($seconds));
    }
}
