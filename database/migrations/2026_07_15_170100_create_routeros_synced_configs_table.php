<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routeros_synced_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_router_id')->constrained('mikrotik_routers')->cascadeOnDelete();
            $table->string('module', 50); // interface, bridge, vlan, ip_address, etc.
            $table->string('item_id', 100); // MikroTik .id or unique key
            $table->string('item_name', 255);
            $table->json('config_data');
            $table->string('checksum', 64); // SHA-256 of config_data
            $table->foreignId('sync_log_id')->nullable()->constrained('routeros_sync_logs')->nullOnDelete();
            $table->string('status', 20)->default('active'); // active | deleted | conflict
            $table->timestamp('last_synced_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'mikrotik_router_id', 'module', 'item_id'], 'synccfg_unique_idx');
            $table->index(['tenant_id', 'mikrotik_router_id', 'module'], 'synccfg_tenant_router_mod_idx');
            $table->index('module', 'synccfg_module_idx');
            $table->index('status', 'synccfg_status_idx');
            $table->index('checksum', 'synccfg_checksum_idx');
            $table->index('last_synced_at', 'synccfg_synced_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routeros_synced_configs');
    }
};
