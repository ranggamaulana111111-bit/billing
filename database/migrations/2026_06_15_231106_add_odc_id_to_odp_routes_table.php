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
        Schema::table('odp_routes', function (Blueprint $table) {
            if (! Schema::hasColumn('odp_routes', 'odc_id')) {
                $table->foreignId('odc_id')->nullable()->after('id')->constrained('odcs')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('odp_routes', function (Blueprint $table) {
            $table->dropForeign(['odc_id']);
            $table->dropColumn('odc_id');
        });
    }
};
