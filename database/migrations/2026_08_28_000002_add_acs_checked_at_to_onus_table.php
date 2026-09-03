<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->timestamp('acs_checked_at')->nullable()->after('acs_connection_request_url');
        });
    }

    public function down(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->dropColumn('acs_checked_at');
        });
    }
};
