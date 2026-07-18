<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->string('category'); // mikrotik, otb, odc, odp, olt, kabel_rj45, kabel, ont_modem, roset, esklem
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('brand')->nullable();
            $table->string('serial_number')->nullable();
            $table->unsignedInteger('port_count')->nullable();
            $table->unsignedInteger('pon_port_count')->nullable();
            $table->string('cable_type')->nullable();
            $table->string('unit')->default('pcs');
            $table->integer('stock')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
