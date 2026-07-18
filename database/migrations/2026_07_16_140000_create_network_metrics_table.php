<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_router_id')->constrained()->cascadeOnDelete();
            $table->timestamp('collected_at');

            $table->unsignedInteger('bandwidth_download')->default(0);
            $table->unsignedInteger('bandwidth_upload')->default(0);
            $table->unsignedInteger('latency_idle')->default(0);
            $table->unsignedInteger('latency_load')->default(0);
            $table->decimal('packet_loss', 5, 2)->default(0);
            $table->unsignedInteger('total_connections')->default(0);

            $table->string('router_status', 20)->default('unknown');
            $table->unsignedInteger('cpu_load')->default(0);
            $table->unsignedInteger('memory_used')->default(0);
            $table->unsignedInteger('memory_total')->default(0);
            $table->unsignedInteger('uptime_seconds')->default(0);

            $table->json('interfaces_data')->nullable();
            $table->json('wan_data')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'mikrotik_router_id', 'collected_at']);
            $table->index('collected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_metrics');
    }
};
