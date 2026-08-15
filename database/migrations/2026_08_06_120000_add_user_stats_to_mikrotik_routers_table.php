<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotik_routers', function (Blueprint $table) {
            $table->json('user_stats')->nullable()->after('connection_mode');
            $table->timestamp('user_stats_updated_at')->nullable()->after('user_stats');
        });
    }

    public function down(): void
    {
        Schema::table('mikrotik_routers', function (Blueprint $table) {
            $table->dropColumn(['user_stats', 'user_stats_updated_at']);
        });
    }
};
