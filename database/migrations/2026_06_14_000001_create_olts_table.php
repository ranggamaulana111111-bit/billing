<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('brand'); // huawei, zte, fiberhome
            $table->string('model')->nullable();
            $table->string('ip_address');
            $table->integer('ssh_port')->default(22);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('snmp_community')->nullable();
            $table->string('snmp_version')->nullable(); // v1, v2c, v3
            $table->integer('snmp_port')->default(161);
            $table->string('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('active'); // active, maintenance, inactive
            $table->text('notes')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olts');
    }
};
