<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_config_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_router_id')->constrained('mikrotik_routers')->cascadeOnDelete();
            $table->string('resource_type', 50);
            $table->string('item_id', 100);
            $table->string('item_name', 255);
            $table->string('action', 30);
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->text('summary')->nullable();
            $table->string('status', 20)->default('success');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('api_error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'mikrotik_router_id', 'resource_type'], 'naudit_tenant_router_res_idx');
            $table->index(['resource_type', 'item_id'], 'naudit_res_item_idx');
            $table->index('action', 'naudit_action_idx');
            $table->index('user_id', 'naudit_user_idx');
            $table->index('created_at', 'naudit_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_config_audit_logs');
    }
};
