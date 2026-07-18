<?php

namespace Tests\Unit\Services\Payment;

use App\Models\Invoice;
use App\Services\Payment\MidtransGateway;
use App\Services\Payment\PaymentService;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    protected PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentService;
    }

    public function test_register_and_get_gateway(): void
    {
        $gateway = new MidtransGateway;
        $this->service->registerGateway('midtrans', $gateway);

        $this->assertSame($gateway, $this->service->getGateway('midtrans'));
    }

    public function test_get_unknown_gateway_returns_null(): void
    {
        $this->assertNull($this->service->getGateway('unknown'));
    }

    public function test_get_available_gateways(): void
    {
        $this->service->registerGateway('midtrans', new MidtransGateway);

        $available = $this->service->getAvailableGateways();
        $this->assertEmpty($available);
    }

    public function test_create_transaction_unknown_gateway(): void
    {
        $result = $this->service->createTransaction('unknown', new Invoice);
        $this->assertFalse($result['success']);
    }

    public function test_process_webhook_unknown_gateway(): void
    {
        $result = $this->service->processWebhook('unknown', []);
        $this->assertFalse($result['success']);
    }

    public function test_process_webhook_invalid_signature(): void
    {
        $this->service->registerGateway('midtrans', new MidtransGateway);

        $result = $this->service->processWebhook('midtrans', [
            'order_id' => 'INV-202607-000001',
            'signature_key' => 'invalid',
        ]);

        $this->assertFalse($result['success']);
    }
}
