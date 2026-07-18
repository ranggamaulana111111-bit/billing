<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->foreignId('odp_port_id')->nullable()->after('olt_port_id')->constrained('odp_ports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->dropForeign(['odp_port_id']);
            $table->dropColumn('odp_port_id');
        });
    }
};
