<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('voucher_profiles')) {
            Schema::drop('voucher_profiles');
        }

        Schema::create('voucher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('speed')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('time_limit')->nullable()->comment('in hours');
            $table->bigInteger('quota_limit')->nullable()->comment('in MB');
            $table->integer('validity_days')->nullable()->comment('masa aktif in days');
            $table->integer('shared_users')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (!Schema::hasColumn('vouchers', 'voucher_profile_id')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->foreignId('voucher_profile_id')->nullable()->after('id')->constrained('voucher_profiles')->nullOnDelete();
                $table->decimal('price', 12, 2)->nullable()->after('duration_hours');
                $table->string('prefix')->nullable()->after('price');
                $table->string('speed')->nullable()->after('prefix');
                $table->bigInteger('quota_limit')->nullable()->after('speed')->comment('in MB');
                $table->integer('validity_days')->nullable()->after('quota_limit');
                $table->integer('shared_users')->default(1)->after('validity_days');
                $table->integer('printed_count')->default(0)->after('shared_users');
                $table->bigInteger('downloaded')->default(0)->after('printed_count');
                $table->bigInteger('uploaded')->default(0)->after('downloaded');
                $table->bigInteger('total_traffic')->default(0)->after('uploaded');
                $table->string('ip_address')->nullable()->after('total_traffic');
                $table->string('mac_address')->nullable()->after('ip_address');
                $table->timestamp('last_login_at')->nullable()->after('mac_address');
                $table->foreignId('router_id')->nullable()->after('last_login_at')->constrained('mikrotik_routers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['voucher_profile_id']);
            $table->dropForeign(['router_id']);
            $table->dropColumn([
                'voucher_profile_id', 'price', 'prefix', 'speed', 'quota_limit',
                'validity_days', 'shared_users', 'printed_count', 'downloaded',
                'uploaded', 'total_traffic', 'ip_address', 'mac_address',
                'last_login_at', 'router_id',
            ]);
        });

        Schema::dropIfExists('voucher_profiles');
    }
};
