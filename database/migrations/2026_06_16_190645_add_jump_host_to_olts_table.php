<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->string('jump_host')->nullable()->after('password');
            $table->integer('jump_port')->default(22)->after('jump_host');
            $table->string('jump_username')->nullable()->after('jump_port');
            $table->text('jump_password')->nullable()->after('jump_username');
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            $table->dropColumn(['jump_host', 'jump_port', 'jump_username', 'jump_password']);
        });
    }
};
