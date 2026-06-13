<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('odcs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('odp_routes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('odp_points', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        try {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique('settings_key_unique');
            });
        } catch (Exception $e) {
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->unique(['user_id', 'key']);
        });

        $adminId = DB::table('users')->min('id') ?? 1;
        foreach (['customers', 'packages', 'invoices', 'payments', 'vouchers', 'odcs', 'odp_routes', 'odp_points', 'settings'] as $tableName) {
            DB::table($tableName)->whereNull('user_id')->update(['user_id' => $adminId]);
        }

        $rows = DB::table('settings')->where('user_id', $adminId)->get();
        $others = DB::table('users')->where('id', '!=', $adminId)->pluck('id');
        foreach ($others as $uid) {
            $existing = DB::table('settings')->where('user_id', $uid)->pluck('key')->toArray();
            foreach ($rows as $row) {
                if (! in_array($row->key, $existing, true)) {
                    DB::table('settings')->insert([
                        'user_id' => $uid,
                        'key' => $row->key,
                        'value' => $row->value,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['customers', 'packages', 'invoices', 'payments', 'vouchers', 'odcs', 'odp_routes', 'odp_points'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'key']);
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->string('key')->unique()->change();
        });
    }
};
