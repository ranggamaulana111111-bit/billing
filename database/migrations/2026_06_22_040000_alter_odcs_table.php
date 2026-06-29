<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odcs', function (Blueprint $table) {
            if (Schema::hasColumn('odcs', 'name')) {
                $table->renameColumn('name', 'nama_odc');
            }
            if (Schema::hasColumn('odcs', 'address')) {
                $table->dropColumn('address');
            }
            if (Schema::hasColumn('odcs', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('odcs', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('odcs', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('odcs', 'notes')) {
                $table->dropColumn('notes');
            }
            if (Schema::hasColumn('odcs', 'capacity')) {
                $table->renameColumn('capacity', 'kapasitas_port');
            }
            if (! Schema::hasColumn('odcs', 'koordinat')) {
                $table->string('koordinat')->nullable()->after('nama_odc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('odcs', function (Blueprint $table) {
            if (Schema::hasColumn('odcs', 'nama_odc')) {
                $table->renameColumn('nama_odc', 'name');
            }
            if (Schema::hasColumn('odcs', 'kapasitas_port')) {
                $table->renameColumn('kapasitas_port', 'capacity');
            }
            if (Schema::hasColumn('odcs', 'koordinat')) {
                $table->dropColumn('koordinat');
            }
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
        });
    }
};
