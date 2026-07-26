<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'customers',
        'packages',
        'invoices',
        'payments',
        'vouchers',
        'odcs',
        'odp_routes',
        'odp_points',
        'settings',
        'olts',
        'mikrotik_routers',
        'voucher_profiles',
        'voucher_templates',
        'olt_ports',
        'onus',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {

            if (!Schema::hasColumn($tableName, 'user_id')) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('tenant_id')
                        ->nullable()
                        ->after('id')
                        ->constrained()
                        ->nullOnDelete();

                    $table->index('tenant_id');
                });
            }

            DB::table($tableName)
                ->whereNull('tenant_id')
                ->update([
                    'tenant_id' => DB::raw('user_id')
                ]);

            $defaultTenant = DB::table('users')->min('tenant_id') ?? 1;

            DB::table($tableName)
                ->whereNull('tenant_id')
                ->update([
                    'tenant_id' => $defaultTenant
                ]);

            /*
             |--------------------------------------------------------------------------
             | PostgreSQL Safe Constraint Removal
             |--------------------------------------------------------------------------
             */

            DB::statement("
                ALTER TABLE {$tableName}
                DROP CONSTRAINT IF EXISTS {$tableName}_user_id_foreign
            ");

            DB::statement("
                DROP INDEX IF EXISTS {$tableName}_user_id_index
            ");

            DB::statement("
                ALTER TABLE {$tableName}
                DROP CONSTRAINT IF EXISTS {$tableName}_user_id_key
            ");

            DB::statement("
                ALTER TABLE {$tableName}
                DROP CONSTRAINT IF EXISTS {$tableName}_user_id_key1
            ");

            if (Schema::hasColumn($tableName, 'user_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('user_id');
                });
            }
        }

        /*
         |--------------------------------------------------------------------------
         | Activity Logs
         |--------------------------------------------------------------------------
         | user_id dipertahankan untuk audit.
         */

        if (Schema::hasColumn('activity_logs', 'user_id')) {

            if (!Schema::hasColumn('activity_logs', 'tenant_id')) {

                Schema::table('activity_logs', function (Blueprint $table) {

                    $table->foreignId('tenant_id')
                        ->nullable()
                        ->after('id')
                        ->constrained()
                        ->nullOnDelete();

                    $table->index('tenant_id');

                });

            }

            DB::table('activity_logs')
                ->whereNull('tenant_id')
                ->update([
                    'tenant_id' => DB::raw('user_id')
                ]);

            $defaultTenant = DB::table('users')->min('tenant_id') ?? 1;

            DB::table('activity_logs')
                ->whereNull('tenant_id')
                ->update([
                    'tenant_id' => $defaultTenant
                ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {

            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'user_id')) {

                Schema::table($tableName, function (Blueprint $table) {

                    $table->foreignId('user_id')
                        ->nullable()
                        ->after('id')
                        ->constrained()
                        ->nullOnDelete();

                });

            }

            DB::table($tableName)
                ->whereNull('user_id')
                ->update([
                    'user_id' => DB::raw('tenant_id')
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
