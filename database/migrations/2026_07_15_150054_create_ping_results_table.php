<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ping_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('target_host');
            $table->string('target_label');
            $table->float('latency_ms')->nullable();
            $table->float('jitter_ms')->nullable();
            $table->float('packet_loss_percent')->default(0);
            $table->integer('response_time_ms')->nullable();
            $table->string('status')->default('unknown');
            $table->foreignId('onu_id')->nullable()->constrained('onus')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'target_host', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ping_results');
    }
};
