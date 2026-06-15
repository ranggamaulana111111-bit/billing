<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('voucher_templates', 'logout_page')) {
            return;
        }

        Schema::table('voucher_templates', function (Blueprint $table) {
            $table->text('logout_page')->nullable()->comment('logout.html');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_templates', function (Blueprint $table) {
            $table->dropColumn('logout_page');
        });
    }
};
