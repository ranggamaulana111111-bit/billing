<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $table->integer('slot_number');
            $table->integer('port_number');
            $table->string('port_type')->default('gpon'); // gpon, xgspon, epon
            $table->string('status')->default('active'); // active, inactive, blocked
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['olt_id', 'slot_number', 'port_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_ports');
    }
};
