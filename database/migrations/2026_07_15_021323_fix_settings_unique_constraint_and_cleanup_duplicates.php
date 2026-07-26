<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {

            DB::statement("
                DELETE FROM settings a
                USING settings b
                WHERE a.key = b.key
                  AND a.tenant_id = b.tenant_id
                  AND a.id < b.id
            ");

        } else {

            DB::statement("
                DELETE s1
                FROM settings s1
                INNER JOIN settings s2
                    ON s1.key = s2.key
                   AND s1.tenant_id = s2.tenant_id
                WHERE s1.id < s2.id
            ");

        }

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
