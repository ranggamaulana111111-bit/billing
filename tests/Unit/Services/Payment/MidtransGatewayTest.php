<?php

namespace Tests\Unit\Services\Payment;

use App\Services\Payment\MidtransGateway;
use App\Services\Payment\PaymentGatewayInterface;
use Tests\TestCase;

class MidtransGatewayTest extends TestCase
{
    public function test_implements_interface(): void
    {
        $gateway = new MidtransGateway;
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    public function test_gateway_name(): void
    {
        $gateway = new MidtransGateway;
        $this->assertEquals('midtrans', $gateway->getGatewayName());
    }

    public function test_not_configured_when_no_key(): void
    {
        $gateway = new MidtransGateway;
        $this->assertFalse($gateway->isConfigured());
    }

    public function test_verify_signature_with_invalid_key(): void
    {
        $gateway = new MidtransGateway;

        $result = $gateway->verifySignature([
            'order_id' => 'INV-202607-000001',
            'status_code' => '200',
            'gross_amount' => '150000',
            'signature_key' => 'invalid_signature',
        ]);

        $this->assertFalse($result);
    }

    public function test_verify_signature_correct(): void
    {
        $gateway = new MidtransGateway;

        $orderId = 'INV-202607-000001';
        $statusCode = '200';
        $grossAmount = '150000';
        $serverKey = 'test-server-key';

        config(['services.midtrans.server_key' => $serverKey]);

        $gatewayWithKey = new MidtransGateway;
        $expectedHash = hash('sha512', $orderId.$statusCode.$grossAmount);

        $result = $gatewayWithKey->verifySignature([
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $expectedHash,
        ]);

        $this->assertTrue($result);
    }

    public function test_create_transaction_when_not_configured(): void
    {
        $gateway = new MidtransGateway;
        $result = $gateway->createTransaction([]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }
}
