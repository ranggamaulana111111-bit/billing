<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->char('billing_period', 7)->nullable()->after('payment_status');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("UPDATE invoices SET billing_period = strftime('%Y-%m', created_at)");
        } else {
            DB::statement("UPDATE invoices SET billing_period = DATE_FORMAT(created_at, '%Y-%m')");
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('billing_period');
        });
    }
};
