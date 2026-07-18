<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routeros_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_router_id')->constrained('mikrotik_routers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sync_type', 20); // manual | scheduled
            $table->json('modules_synced');
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('new_items')->default(0);
            $table->unsignedInteger('updated_items')->default(0);
            $table->unsignedInteger('deleted_items')->default(0);
            $table->unsignedInteger('conflict_items')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 20); // success | partial | failed
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'mikrotik_router_id'], 'synclog_tenant_router_idx');
            $table->index('sync_type', 'synclog_type_idx');
            $table->index('status', 'synclog_status_idx');
            $table->index('started_at', 'synclog_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routeros_sync_logs');
    }
};
