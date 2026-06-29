<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'customers', 'packages', 'invoices', 'payments', 'vouchers',
        'odcs', 'odp_routes', 'odp_points', 'settings', 'olts',
        'mikrotik_routers', 'voucher_profiles', 'voucher_templates',
        'olt_ports', 'onus',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'user_id')) {
                continue;
            }

            // Add tenant_id column (keep user_id for now)
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->index('tenant_id');
            });

            // Copy existing user_id values to tenant_id
            DB::table($tableName)->whereNull('tenant_id')->update([
                'tenant_id' => DB::raw('user_id'),
            ]);

            // Fallback for any nulls
            $defaultTenantId = DB::table('users')->min('tenant_id') ?? 1;
            DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);

            // Drop old user_id foreign key and column
            // Drop foreign key
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (Exception $e) {
                // ignore if no FK or named differently
            }

            // Drop regular index (needed by SQLite before dropping column)
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropIndex(['user_id']);
                });
            } catch (Exception $e) {
                // ignore if no index
            }

            // Drop unique constraint on (user_id, key) — settings table only
            try {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropUnique(['user_id', 'key']);
                });
            } catch (Exception $e) {
                // ignore
            }

            // Drop column
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }

        // ActivityLog: special case — add tenant_id but KEEP user_id for audit
        if (Schema::hasColumn('activity_logs', 'user_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->index('tenant_id');
            });

            DB::table('activity_logs')->whereNull('tenant_id')->update([
                'tenant_id' => DB::raw('user_id'),
            ]);

            $defaultTenantId = DB::table('users')->min('tenant_id') ?? 1;
            DB::table('activity_logs')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);

            // Keep user_id column for audit trail — only drop FK if exists
            try {
                Schema::table('activity_logs', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (Exception $e) {
                // ignore
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });

            DB::table($tableName)->whereNull('user_id')->update([
                'user_id' => DB::raw('tenant_id'),
            ]);

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        if (Schema::hasColumn('activity_logs', 'tenant_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};
