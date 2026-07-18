<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noc_automation_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('event_type', 50);
            $table->json('event_config')->nullable();
            $table->foreignId('automation_job_id')->constrained('noc_automation_jobs')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('fire_count')->default(0);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'event_type'], 'atrig_tenant_event_idx');
            $table->index(['tenant_id', 'is_active'], 'atrig_tenant_active_idx');
            $table->index('event_type', 'atrig_event_idx');
            $table->index('is_active', 'atrig_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noc_automation_triggers');
    }
};
