<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interface_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mikrotik_router_id')->constrained('mikrotik_routers')->cascadeOnDelete();
            $table->string('interface_name');
            $table->string('change_type'); // enable, disable, rename, mtu, alias, tag, comment, auto_negotiation, speed
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('status'); // success, failed
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'mikrotik_router_id'], 'ifchange_tenant_router_idx');
            $table->index(['mikrotik_router_id', 'interface_name'], 'ifchange_router_if_idx');
            $table->index('change_type', 'ifchange_type_idx');
            $table->index('created_at', 'ifchange_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_change_logs');
    }
};
