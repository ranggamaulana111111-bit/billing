<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Customer code: shorten from 7 to 6 digits (ALK0000001 → ALK000001)
        DB::table('customers')->where('customer_code', 'like', 'ALK%')->orderBy('id')->each(function ($customer) {
            $num = ltrim(preg_replace('/^ALK0+/', '', $customer->customer_code), '0') ?: $customer->id;
            DB::table('customers')->where('id', $customer->id)->update([
                'customer_code' => 'ALK'.str_pad($num, 6, '0', STR_PAD_LEFT),
            ]);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_code', 20)->change();
        });

        $hasIndex = DB::select("SHOW INDEX FROM customers WHERE Key_name = 'customers_customer_code_unique'");
        if (empty($hasIndex)) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unique('customer_code');
            });
        }

        // 2. Invoice: add invoice_number (INV-YYYYMM-NNNNNN) separate from invoice_code
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number', 30)->nullable()->unique()->after('id');
            $table->string('period', 7)->nullable()->after('invoice_number');
            $table->enum('status', ['unpaid', 'paid', 'overdue', 'cancelled'])->default('unpaid')->after('period');
        });

        // Backfill invoice_number from invoice_code for existing records
        $invoices = DB::table('invoices')->orderBy('id')->get();
        $counters = [];
        foreach ($invoices as $inv) {
            $period = $inv->billing_period ?? date('Y-m', strtotime($inv->created_at));
            $ym = str_replace('-', '', $period);
            if (! isset($counters[$ym])) {
                $counters[$ym] = 0;
            }
            $counters[$ym]++;
            $number = 'INV-'.$ym.'-'.str_pad($counters[$ym], 6, '0', STR_PAD_LEFT);
            DB::table('invoices')->where('id', $inv->id)->update([
                'invoice_number' => $number,
                'period' => $period,
                'status' => $inv->payment_status,
            ]);
        }

        // 3. Payments: add gateway fields for payment gateway abstraction
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway')->nullable()->after('invoice_id');
            $table->string('gateway_transaction_id')->nullable()->after('gateway');
            $table->string('gateway_order_id')->nullable()->after('gateway_transaction_id');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->after('gateway_order_id');
            $table->timestamp('paid_at')->nullable()->after('status');
        });

        // Backfill existing payments as 'paid'
        DB::table('payments')->update(['status' => 'paid', 'paid_at' => DB::raw('payment_date')]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway', 'gateway_transaction_id', 'gateway_order_id', 'status', 'paid_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'period', 'status']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_code', 20)->change();
        });
    }
};
