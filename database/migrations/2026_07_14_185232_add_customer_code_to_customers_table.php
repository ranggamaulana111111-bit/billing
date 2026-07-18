<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_code', 20)->nullable()->after('id');
        });

        // Generate codes for existing customers: ALK + padded ID (7 digits)
        $customers = DB::table('customers')->orderBy('id')->get();
        foreach ($customers as $c) {
            DB::table('customers')
                ->where('id', $c->id)
                ->update(['customer_code' => 'ALK'.str_pad($c->id, 7, '0', STR_PAD_LEFT)]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('customer_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('customer_code');
        });
    }
};
