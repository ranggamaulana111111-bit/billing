<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            DELETE s1 FROM settings s1
            INNER JOIN settings s2
            WHERE s1.key = s2.key
              AND s1.tenant_id = s2.tenant_id
              AND s1.id < s2.id
        ');

        Schema::table('settings', function (Blueprint $table) {
            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'key']);
        });
    }
};
