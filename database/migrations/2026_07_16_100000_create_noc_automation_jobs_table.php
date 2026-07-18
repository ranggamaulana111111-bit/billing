<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noc_automation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('type', 100);
            $table->json('parameters')->nullable();
            $table->string('schedule_type', 30)->default('manual');
            $table->string('schedule_config', 255)->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->unsignedSmallInteger('timeout_seconds')->default(300);
            $table->boolean('is_active')->default(true);
            $table->string('status', 20)->default('idle');
            $table->unsignedSmallInteger('current_attempt')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'ajob_tenant_status_idx');
            $table->index(['tenant_id', 'type'], 'ajob_tenant_type_idx');
            $table->index('next_run_at', 'ajob_next_run_idx');
            $table->index('status', 'ajob_status_idx');
            $table->index('priority', 'ajob_priority_idx');
            $table->index('is_active', 'ajob_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noc_automation_jobs');
    }
};
