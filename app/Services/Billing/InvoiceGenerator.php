<?php

namespace App\Services\Billing;

class InvoiceGenerator
{
    private const PREFIX = 'INV';

    public function generate(string $customerCode, ?string $period = null): string
    {
        $ym = $period ? str_replace('-', '', $period) : now()->format('Ymd');

        return self::PREFIX.'-'.$ym.'-'.$customerCode;
    }

    public function generateForCustomer(string $customerCode, ?string $period = null): string
    {
        return $this->generate($customerCode, $period);
    }

    public function format(): string
    {
        return self::PREFIX.'-YYYYMM-{customer_code}';
    }

    public function validate(string $number): bool
    {
        return (bool) preg_match('/^'.self::PREFIX.'-\d{6}-\d{14}$/', $number);
    }

    public function extractPeriod(string $number): ?string
    {
        if (preg_match('/^'.self::PREFIX.'-(\d{4})(\d{2})-/', $number, $m)) {
            return $m[1].'-'.$m[2];
        }

        return null;
    }

    public function extractCustomerCode(string $number): ?string
    {
        if (preg_match('/^'.self::PREFIX.'-\d{6}-(\d+)$/', $number, $m)) {
            return $m[1];
        }

        return null;
    }
}
