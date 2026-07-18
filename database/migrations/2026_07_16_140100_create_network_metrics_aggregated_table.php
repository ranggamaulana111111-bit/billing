<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_metrics_aggregated', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_router_id')->constrained()->cascadeOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedSmallInteger('interval_minutes')->default(5);
            $table->unsignedSmallInteger('sample_count')->default(0);

            $table->unsignedInteger('avg_bandwidth_download')->default(0);
            $table->unsignedInteger('avg_bandwidth_upload')->default(0);
            $table->unsignedInteger('max_bandwidth_download')->default(0);
            $table->unsignedInteger('max_bandwidth_upload')->default(0);
            $table->unsignedInteger('min_bandwidth_download')->default(0);
            $table->unsignedInteger('min_bandwidth_upload')->default(0);

            $table->unsignedInteger('avg_latency_idle')->default(0);
            $table->unsignedInteger('max_latency_idle')->default(0);
            $table->unsignedInteger('avg_latency_load')->default(0);
            $table->unsignedInteger('max_latency_load')->default(0);

            $table->decimal('avg_packet_loss', 5, 2)->default(0);
            $table->decimal('max_packet_loss', 5, 2)->default(0);
            $table->unsignedInteger('avg_connections')->default(0);

            $table->unsignedInteger('avg_cpu_load')->default(0);
            $table->unsignedInteger('max_cpu_load')->default(0);
            $table->decimal('avg_memory_usage_pct', 5, 2)->default(0);

            $table->json('interfaces_summary')->nullable();
            $table->json('wan_summary')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'mikrotik_router_id', 'period_start', 'interval_minutes'], 'nma_tenant_router_period_unique');
            $table->index(['tenant_id', 'mikrotik_router_id', 'period_start'], 'nma_tenant_router_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_metrics_aggregated');
    }
};
