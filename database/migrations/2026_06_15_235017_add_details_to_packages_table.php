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
        Schema::table('packages', function (Blueprint $table) {
            if (! Schema::hasColumn('packages', 'description')) {
                $table->text('description')->nullable()->after('speed');
            }
            if (! Schema::hasColumn('packages', 'billing_cycle')) {
                $table->string('billing_cycle')->default('monthly')->after('price');
            }
            if (! Schema::hasColumn('packages', 'mikrotik_profile')) {
                $table->string('mikrotik_profile')->nullable()->after('billing_cycle');
            }
            if (! Schema::hasColumn('packages', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('mikrotik_profile');
            }
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['description', 'billing_cycle', 'mikrotik_profile', 'is_active']);
        });
    }
};
