<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->integer('download_mbps')->nullable()->after('speed');
            $table->integer('upload_mbps')->nullable()->after('download_mbps');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['download_mbps', 'upload_mbps']);
        });
    }
};
