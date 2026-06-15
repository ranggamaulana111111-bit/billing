<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_templates', function (Blueprint $table) {
            $table->text('status_page')->nullable()->after('content')->comment('status.html');
            $table->text('redirect_page')->nullable()->after('status_page')->comment('redirect.html');
            $table->text('error_page')->nullable()->after('redirect_page')->comment('error.html');
            $table->text('alive_page')->nullable()->after('error_page')->comment('alive.html');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_templates', function (Blueprint $table) {
            $table->dropColumn(['status_page', 'redirect_page', 'error_page', 'alive_page']);
        });
    }
};
