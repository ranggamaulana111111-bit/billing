<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noc_automation_job_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_job_id')->constrained('noc_automation_jobs')->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('result_data')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            $table->index(['tenant_id', 'automation_job_id'], 'alog_tenant_job_idx');
            $table->index(['automation_job_id', 'status'], 'alog_job_status_idx');
            $table->index('status', 'alog_status_idx');
            $table->index('started_at', 'alog_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noc_automation_job_logs');
    }
};
