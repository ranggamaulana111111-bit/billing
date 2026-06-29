<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('odp_id')->nullable()->after('package_id')->constrained('odps')->nullOnDelete();
            $table->foreignId('odp_port_id')->nullable()->after('odp_id')->unique()->constrained('odp_ports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['odp_id']);
            $table->dropColumn('odp_id');
            $table->dropForeign(['odp_port_id']);
            $table->dropColumn('odp_port_id');
        });
    }
};
