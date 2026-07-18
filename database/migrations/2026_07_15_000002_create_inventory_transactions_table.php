<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('inventory_item_id')->constrained();
            $table->enum('type', ['in', 'out']);
            $table->integer('quantity');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'date']);
            $table->index(['tenant_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
