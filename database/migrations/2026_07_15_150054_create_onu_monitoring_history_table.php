<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onu_monitoring_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('onu_id')->constrained('onus')->cascadeOnDelete();
            $table->float('rx_power')->nullable();
            $table->float('tx_power')->nullable();
            $table->float('temperature')->nullable();
            $table->float('voltage')->nullable();
            $table->float('bias_current')->nullable();
            $table->string('status')->default('unknown');
            $table->boolean('los_detected')->default(false);
            $table->boolean('dying_gasp_detected')->default(false);
            $table->boolean('auth_failed')->default(false);
            $table->boolean('rogue_detected')->default(false);
            $table->integer('uptime')->nullable();
            $table->bigInteger('download_bytes')->nullable();
            $table->bigInteger('upload_bytes')->nullable();
            $table->integer('restart_count')->nullable();
            $table->timestamps();

            $table->index(['onu_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onu_monitoring_history');
    }
};
