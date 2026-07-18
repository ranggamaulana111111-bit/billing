<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\InvoiceGenerator;
use Tests\TestCase;

class InvoiceGeneratorTest extends TestCase
{
    protected InvoiceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new InvoiceGenerator;
    }

    public function test_generates_inv_prefix(): void
    {
        $number = $this->generator->generate('17215072026001');
        $this->assertStringStartsWith('INV-', $number);
    }

    public function test_generates_correct_format(): void
    {
        $number = $this->generator->generate('17215072026001', '2026-07');
        $this->assertEquals('INV-202607-17215072026001', $number);
    }

    public function test_includes_customer_code(): void
    {
        $number = $this->generator->generate('17222062026003', '2026-07');
        $this->assertEquals('INV-202607-17222062026003', $number);
    }

    public function test_uses_current_date_when_no_period_given(): void
    {
        $number = $this->generator->generate('17215072026001');
        $expectedYm = now()->format('Ym');
        $this->assertStringStartsWith("INV-{$expectedYm}-17215072026001", $number);
    }

    public function test_validate_format(): void
    {
        $this->assertTrue($this->generator->validate('INV-202607-17215072026001'));
        $this->assertFalse($this->generator->validate('INV-202607-000001'));
        $this->assertFalse($this->generator->validate('INV20260717215072026001'));
    }

    public function test_extract_period(): void
    {
        $this->assertEquals('2026-07', $this->generator->extractPeriod('INV-202607-17215072026001'));
        $this->assertEquals('2025-12', $this->generator->extractPeriod('INV-202512-17215072026001'));
        $this->assertNull($this->generator->extractPeriod('invalid'));
    }

    public function test_extract_customer_code(): void
    {
        $this->assertEquals('17215072026001', $this->generator->extractCustomerCode('INV-202607-17215072026001'));
        $this->assertNull($this->generator->extractCustomerCode('INV-202607-000001'));
    }
}
