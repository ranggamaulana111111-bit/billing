<?php

namespace App\Services;

use App\Models\Setting;
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

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->token,
            ])->post('https://api.fonnte.com/send', [
                'target' => $cleanPhone,
                'message' => $message,
                'countryCode' => $countryCode,
            ]);

            $body = $response->json();

            if ($response->successful() && ($body['status'] ?? false)) {
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
}
