<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XenditGateway implements PaymentGatewayInterface
{
    protected ?string $secretKey;

    protected ?string $webhookToken;

    protected bool $isProduction;

    public function __construct(?int $tenantId = null)
    {
        $this->secretKey = Setting::get('xendit_secret_key', config('services.xendit.secret_key'), $tenantId);
        $this->webhookToken = Setting::get('xendit_webhook_token', config('services.xendit.webhook_token'), $tenantId);
        $this->isProduction = (bool) Setting::get('xendit_is_production', false, $tenantId);
    }

    public function getGatewayName(): string
    {
        return 'xendit';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->secretKey);
    }

    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    public function getWebhookToken(): ?string
    {
        return $this->webhookToken;
    }

    public function createTransaction(array $params): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Xendit secret key tidak dikonfigurasi.'];
        }

        $details = $params['transaction_details'] ?? [];
        $customer = $params['customer_details'] ?? [];

        $orderId = $params['external_id'] ?? ($details['order_id'] ?? null);
        $amount = $params['amount'] ?? ($details['gross_amount'] ?? null);

        if (! $orderId || ! $amount) {
            return ['success' => false, 'message' => 'Parameter transaksi tidak lengkap (order_id & amount).'];
        }

        $description = $params['description'] ?? ($params['item_details'][0]['name'] ?? 'Pembayaran '.$orderId);
        if (strlen((string) $description) > 255) {
            $description = substr((string) $description, 0, 252).'...';
        }

        $payload = [
            'external_id' => $orderId,
            'amount' => (int) $amount,
            'description' => $description,
            'currency' => 'IDR',
            'invoice_duration' => 86400,
            'success_redirect_url' => $params['success_redirect_url'] ?? route('xendit.finish', ['order_id' => $orderId]),
            'failure_redirect_url' => $params['failure_redirect_url'] ?? route('xendit.finish', ['order_id' => $orderId]),
            'webhook_url' => $params['webhook_url'] ?? route('xendit.notification'),
        ];

        if (! empty($customer['email'])) {
            $payload['payer_email'] = $customer['email'];
        }

        if (! empty($customer['phone'])) {
            $payload['payer_phone'] = $customer['phone'];
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://api.xendit.co/v2/invoices', $payload);

            $body = $response->json();

            if ($response->failed()) {
                Log::error('Xendit create transaction failed', ['response' => $body]);

                return [
                    'success' => false,
                    'message' => $body['message'] ?? ($body['error'] ?? 'Xendit API error ('.$response->status().')'),
                ];
            }

            return [
                'success' => true,
                'order_id' => $body['external_id'] ?? $orderId,
                'invoice_id' => $body['id'] ?? null,
                'invoice_url' => $body['invoice_url'] ?? null,
                'status' => $body['status'] ?? 'PENDING',
            ];
        } catch (\Exception $e) {
            report($e);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleNotification(array $payload): array
    {
        $invoice = $payload['invoice'] ?? $payload;

        if (is_array($invoice) && array_is_list($invoice)) {
            $invoice = $invoice[0] ?? [];
        }

        $status = strtoupper((string) ($invoice['status'] ?? ''));
        $paid = $status === 'PAID';

        return [
            'success' => $paid,
            'order_id' => $invoice['external_id'] ?? null,
            'transaction_id' => $invoice['id'] ?? null,
            'transaction_status' => $status,
            'payment_type' => $invoice['payment_channel'] ?? ($invoice['payment_method'] ?? 'xendit'),
            'gross_amount' => $invoice['paid_amount'] ?? ($invoice['amount'] ?? null),
            'status_code' => $paid ? '200' : $status,
        ];
    }

    public function verifySignature(array $payload): bool
    {
        $token = $payload['callback_token'] ?? null;

        if (empty($this->webhookToken)) {
            Log::warning('Xendit webhook token belum dikonfigurasi — signature diabaikan.');

            return true;
        }

        return ! empty($token) && hash_equals($this->webhookToken, (string) $token);
    }

    public function getStatus(string $orderId): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Xendit secret key tidak dikonfigurasi.'];
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->withHeaders(['Accept' => 'application/json'])
                ->get('https://api.xendit.co/v2/invoices/'.$orderId);

            if ($response->failed()) {
                $response = Http::withBasicAuth($this->secretKey, '')
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get('https://api.xendit.co/v2/invoices', [
                        'external_id' => $orderId,
                        'limit' => 1,
                    ]);
            }

            if ($response->failed()) {
                return ['success' => false, 'message' => 'Xendit: '.$response->status()];
            }

            $body = $response->json();
            $invoice = is_array($body) && array_is_list($body) ? ($body[0] ?? null) : $body;

            if (! $invoice) {
                return ['success' => false, 'message' => 'Invoice tidak ditemukan.'];
            }

            return [
                'success' => true,
                'order_id' => $invoice['external_id'] ?? $orderId,
                'transaction_status' => $invoice['status'] ?? null,
                'payment_type' => $invoice['payment_channel'] ?? ($invoice['payment_method'] ?? 'xendit'),
                'gross_amount' => $invoice['paid_amount'] ?? ($invoice['amount'] ?? null),
            ];
        } catch (\Exception $e) {
            report($e);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
