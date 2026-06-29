<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olt_ports', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('user_id');
        });

        Schema::table('onus', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('user_id');
        });

        // Set user_id from parent OLT for existing records
        $adminId = DB::table('users')->min('id') ?? 1;

        DB::statement('
            UPDATE olt_ports
            SET user_id = (SELECT user_id FROM olts WHERE olts.id = olt_ports.olt_id)
            WHERE user_id IS NULL
        ');
        DB::table('olt_ports')->whereNull('user_id')->update(['user_id' => $adminId]);

        DB::statement('
            UPDATE onus
            SET user_id = (SELECT user_id FROM olt_ports WHERE olt_ports.id = onus.olt_port_id)
            WHERE user_id IS NULL
        ');
        DB::table('onus')->whereNull('user_id')->update(['user_id' => $adminId]);
    }

    public function down(): void
    {
        Schema::table('olt_ports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('onus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
