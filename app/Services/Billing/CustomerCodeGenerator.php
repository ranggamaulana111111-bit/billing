<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;

class CustomerCodeGenerator
{
    private const AREA_CODE = '172';

    public function generate(): string
    {
        $datePart = now()->format('dmY');
        $prefix = self::AREA_CODE.$datePart;

        $last = DB::table('customers')
            ->where('customer_code', 'like', $prefix.'%')
            ->orderByDesc('customer_code')
            ->value('customer_code');

        if ($last && preg_match('/^'.preg_quote($prefix, '/').'(\d{3})$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return $prefix.str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    public function format(): string
    {
        return '172DDMMYYYYNNN';
    }

    public function validate(string $code): bool
    {
        return (bool) preg_match('/^172\d{11}$/', $code);
    }

    public function exists(string $code): bool
    {
        return DB::table('customers')->where('customer_code', $code)->exists();
    }

    public function isAvailable(string $code): bool
    {
        return $this->validate($code) && ! $this->exists($code);
    }
}
