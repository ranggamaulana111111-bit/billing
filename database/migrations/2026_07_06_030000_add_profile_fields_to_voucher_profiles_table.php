<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_profiles', function (Blueprint $table) {
            $table->decimal('selling_price', 12, 2)->nullable()->after('price');
            $table->boolean('lock_user')->default(false)->after('selling_price');
            $table->string('expired_mode', 50)->nullable()->after('lock_user');
            $table->string('parent_queue', 255)->nullable()->after('expired_mode');
            $table->string('address_pool', 255)->nullable()->after('parent_queue');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_profiles', function (Blueprint $table) {
            $table->dropColumn(['selling_price', 'lock_user', 'expired_mode', 'parent_queue', 'address_pool']);
        });
    }
};
