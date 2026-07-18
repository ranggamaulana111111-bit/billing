<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_interface_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_router_id')->constrained('mikrotik_routers')->cascadeOnDelete();
            $table->string('interface_name');
            $table->string('alias')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_monitored')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'mikrotik_router_id', 'interface_name'], 'ifmeta_tenant_router_if_unique');
            $table->index(['mikrotik_router_id', 'interface_name'], 'ifmeta_router_if_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_interface_metadata');
    }
};
