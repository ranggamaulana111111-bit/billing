<?php

namespace Tests\Unit\Services\Billing;

use App\Services\Billing\CustomerCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerCodeGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new CustomerCodeGenerator;
    }

    public function test_generates_172_prefix(): void
    {
        $code = $this->generator->generate();
        $this->assertStringStartsWith('172', $code);
    }

    public function test_generates_14_digit_number(): void
    {
        $code = $this->generator->generate();
        $this->assertMatchesRegularExpression('/^172\d{11}$/', $code);
    }

    public function test_format_includes_date(): void
    {
        $code = $this->generator->generate();
        $today = now()->format('dmY');
        $this->assertStringContainsString($today, $code);
    }

    public function test_sequential_same_day(): void
    {
        $code1 = $this->generator->generate();
        DB::table('customers')->insert([
            'tenant_id' => 1,
            'customer_code' => $code1,
            'name' => 'Test',
        ]);

        $code2 = $this->generator->generate();
        $this->assertNotEquals($code1, $code2);
        $this->assertEquals(substr($code1, 0, -3), substr($code2, 0, -3));
    }

    public function test_validate_format(): void
    {
        $this->assertTrue($this->generator->validate('17222062026001'));
        $this->assertFalse($this->generator->validate('ALK000001'));
        $this->assertFalse($this->generator->validate('17212345'));
        $this->assertFalse($this->generator->validate('12322062026001'));
    }
}
