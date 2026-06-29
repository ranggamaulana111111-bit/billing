<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('odcs', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('odp_routes', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('odp_points', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('olts', function (Blueprint $table) {
            $table->index('user_id');
        });

        if (! Schema::hasColumn('activity_logs', 'user_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('mikrotik_routers', function (Blueprint $table) {
            // user_id might not exist yet - add if not present
            if (! Schema::hasColumn('mikrotik_routers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
            $table->index('user_id');
        });

        Schema::table('voucher_profiles', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('voucher_templates', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        // Index drops - safely ignore errors
        $tables = [
            'customers', 'packages', 'invoices', 'payments', 'vouchers',
            'odcs', 'odp_routes', 'odp_points', 'settings', 'olts',
            'activity_logs', 'mikrotik_routers', 'voucher_profiles', 'voucher_templates',
        ];

        foreach ($tables as $table) {
            try {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex(['user_id']);
                });
            } catch (Exception $e) {
                // ignore
            }
        }

        try {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        } catch (Exception $e) {
            // ignore
        }
    }
};
