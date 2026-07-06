<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_profiles', function (Blueprint $table) {
            $table->string('mikrotik_profile')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_profiles', function (Blueprint $table) {
            $table->dropColumn('mikrotik_profile');
        });
    }
};
