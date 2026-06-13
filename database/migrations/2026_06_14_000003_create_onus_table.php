<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_port_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('onu_id'); // ID from OLT (frame/slot/port:onu)
            $table->string('serial_number')->nullable();
            $table->string('vendor')->nullable();
            $table->string('model')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('status')->default('offline'); // online, offline, active, inactive
            $table->float('rx_power')->nullable();
            $table->float('tx_power')->nullable();
            $table->integer('distance')->nullable();
            $table->integer('uptime')->nullable(); // seconds
            $table->integer('slot_number')->nullable();
            $table->integer('port_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['olt_port_id', 'onu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onus');
    }
};
