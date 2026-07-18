<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('pppoe_password')->nullable()->after('pppoe_username');
            $table->string('modem_sn')->nullable()->after('serial_number');
            $table->string('modem_photo')->nullable()->after('modem_sn');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['pppoe_password', 'modem_sn', 'modem_photo']);
        });
    }
};
