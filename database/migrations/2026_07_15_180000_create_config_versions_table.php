<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_router_id')->constrained('mikrotik_routers')->cascadeOnDelete();
            $table->string('module', 50);
            $table->string('item_id', 100);
            $table->string('item_name', 255);
            $table->unsignedInteger('version')->default(1);
            $table->json('config_data');
            $table->string('checksum', 64);
            $table->string('change_source', 20)->default('sync');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('change_summary')->nullable();
            $table->json('diff_from_previous')->nullable();
            $table->foreignId('sync_log_id')->nullable()->constrained('routeros_sync_logs')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'mikrotik_router_id', 'module', 'item_id', 'version'], 'cfgver_unique_idx');
            $table->index(['tenant_id', 'mikrotik_router_id', 'module', 'item_id'], 'cfgver_item_idx');
            $table->index(['tenant_id', 'mikrotik_router_id', 'module'], 'cfgver_router_mod_idx');
            $table->index('module', 'cfgver_module_idx');
            $table->index('change_source', 'cfgver_source_idx');
            $table->index('user_id', 'cfgver_user_idx');
            $table->index('sync_log_id', 'cfgver_sync_log_idx');
            $table->index('created_at', 'cfgver_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_versions');
    }
};
