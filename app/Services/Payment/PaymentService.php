<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /** @var array<string, PaymentGatewayInterface> */
    protected array $gateways = [];

    public function registerGateway(string $name, PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$name] = $gateway;
    }

    public function getGateway(string $name): ?PaymentGatewayInterface
    {
        return $this->gateways[$name] ?? null;
    }

    public function getAvailableGateways(): array
    {
        return array_filter($this->gateways, fn (PaymentGatewayInterface $g) => $g->isConfigured());
    }

    public function createTransaction(string $gatewayName, Invoice $invoice, array $extraParams = []): array
    {
        $gateway = $this->getGateway($gatewayName);

        if (! $gateway) {
            return ['success' => false, 'message' => 'Gateway "'.$gatewayName.'" tidak tersedia.'];
        }

        if (! $gateway->isConfigured()) {
            return ['success' => false, 'message' => 'Gateway "'.$gatewayName.'" belum dikonfigurasi.'];
        }

        $customer = $invoice->customer;
        $orderId = $invoice->invoice_number;

        $params = array_merge([
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $invoice->amount,
            ],
            'customer_details' => [
                'first_name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'billing_id' => $customer->customer_code,
            ],
            'item_details' => [
                [
                    'id' => $invoice->invoice_number,
                    'price' => (int) $invoice->amount,
                    'quantity' => 1,
                    'name' => 'Invoice '.$invoice->invoice_number.' - '.($customer->package->name ?? 'Internet'),
                ],
            ],
        ], $extraParams);

        $result = $gateway->createTransaction($params);

        if ($result['success']) {
            $invoice->update(['midtrans_order_id' => $orderId]);
        }

        return $result;
    }

    public function processWebhook(string $gatewayName, array $payload): array
    {
        $gateway = $this->getGateway($gatewayName);

        if (! $gateway) {
            Log::warning('Unknown gateway webhook', ['gateway' => $gatewayName]);

            return ['success' => false, 'message' => 'Unknown gateway'];
        }

        if (! $gateway->verifySignature($payload)) {
            Log::warning('Invalid gateway signature', ['gateway' => $gatewayName]);

            return ['success' => false, 'message' => 'Invalid signature'];
        }

        $notification = $gateway->handleNotification($payload);

        if (! $notification['success']) {
            return $notification;
        }

        $orderId = $notification['order_id'];
        $invoice = Invoice::allTenants()
            ->where('invoice_number', $orderId)
            ->orWhere('midtrans_order_id', $orderId)
            ->first();

        if (! $invoice) {
            Log::warning('Invoice not found for webhook', ['order_id' => $orderId]);

            return ['success' => false, 'message' => 'Invoice not found'];
        }

        if ($invoice->payment_status === 'paid') {
            Log::info('Invoice already paid, ignoring webhook', ['invoice_number' => $invoice->invoice_number]);

            return ['success' => true, 'message' => 'Already processed'];
        }

        return [
            'success' => true,
            'invoice' => $invoice,
            'notification' => $notification,
        ];
    }

    public function buildTransactionParams(Invoice $invoice): array
    {
        $customer = $invoice->customer;

        return [
            'transaction_details' => [
                'order_id' => $invoice->invoice_number,
                'gross_amount' => (int) $invoice->amount,
            ],
            'customer_details' => [
                'first_name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'billing_id' => $customer->customer_code,
            ],
            'item_details' => [
                [
                    'id' => $invoice->invoice_number,
                    'price' => (int) $invoice->amount,
                    'quantity' => 1,
                    'name' => 'Invoice '.$invoice->invoice_number,
                ],
            ],
        ];
    }
}
