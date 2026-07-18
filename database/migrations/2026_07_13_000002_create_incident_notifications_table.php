<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->string('recipient_phone');
            $table->enum('recipient_type', ['technician', 'customer']);
            $table->string('recipient_name');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->text('message');
            $table->enum('notification_type', ['created', 'status_change', 'resolved', 'sla_warning'])->default('created');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['incident_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_notifications');
    }
};
