<?php

namespace Tests\Unit\Services\Payment;

use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\XenditGateway;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class XenditGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function ($table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('key');
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_implements_interface(): void
    {
        $gateway = new XenditGateway;
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    public function test_gateway_name(): void
    {
        $gateway = new XenditGateway;
        $this->assertEquals('xendit', $gateway->getGatewayName());
    }

    public function test_not_configured_when_no_key(): void
    {
        $gateway = new XenditGateway;
        $this->assertFalse($gateway->isConfigured());
    }

    public function test_verify_signature_with_invalid_token(): void
    {
        config(['services.xendit.webhook_token' => 'correct-token']);

        $gateway = new XenditGateway;

        $result = $gateway->verifySignature([
            'callback_token' => 'invalid-token',
        ]);

        $this->assertFalse($result);
    }

    public function test_verify_signature_correct(): void
    {
        config(['services.xendit.webhook_token' => 'correct-token']);

        $gateway = new XenditGateway;

        $result = $gateway->verifySignature([
            'callback_token' => 'correct-token',
        ]);

        $this->assertTrue($result);
    }

    public function test_verify_signature_ignored_when_token_not_configured(): void
    {
        config(['services.xendit.webhook_token' => null]);

        $gateway = new XenditGateway;

        $this->assertTrue($gateway->verifySignature([]));
    }

    public function test_create_transaction_when_not_configured(): void
    {
        $gateway = new XenditGateway;
        $result = $gateway->createTransaction([]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_handle_notification_paid(): void
    {
        $gateway = new XenditGateway;

        $result = $gateway->handleNotification([
            'invoice' => [
                'id' => 'inv_12345',
                'external_id' => 'INV-202608-000001',
                'status' => 'PAID',
                'payment_channel' => 'BANK_TRANSFER',
                'paid_amount' => 150000,
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('INV-202608-000001', $result['order_id']);
        $this->assertEquals('inv_12345', $result['transaction_id']);
        $this->assertEquals('PAID', $result['transaction_status']);
    }

    public function test_handle_notification_pending(): void
    {
        $gateway = new XenditGateway;

        $result = $gateway->handleNotification([
            'invoice' => [
                'external_id' => 'INV-202608-000001',
                'status' => 'PENDING',
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('PENDING', $result['transaction_status']);
    }

    public function test_handle_notification_raw_array_payload(): void
    {
        $gateway = new XenditGateway;

        $result = $gateway->handleNotification([
            [
                'id' => 'inv_999',
                'external_id' => 'INV-202608-000002',
                'status' => 'PAID',
                'amount' => 100000,
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('inv_999', $result['transaction_id']);
        $this->assertEquals(100000, $result['gross_amount']);
    }
}
