<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->text('description')->nullable()->after('speed');
            $table->string('billing_cycle')->default('monthly')->after('price');
            $table->string('mikrotik_profile')->nullable()->after('billing_cycle');
            $table->boolean('is_active')->default(true)->after('mikrotik_profile');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['description', 'billing_cycle', 'mikrotik_profile', 'is_active']);
        });
    }
};
