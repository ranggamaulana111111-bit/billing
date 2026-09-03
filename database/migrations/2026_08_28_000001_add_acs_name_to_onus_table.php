<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->string('acs_name')->nullable()->after('acs_device_id');
        });
    }

    public function down(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->dropColumn('acs_name');
        });
    }
};
