<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('odc_id')->nullable()->constrained('odcs')->nullOnDelete();
            $table->string('nama_odp');
            $table->string('koordinat')->nullable();
            $table->integer('kapasitas_port');
            $table->string('kabel_tube_color');
            $table->integer('kabel_core_number');
            $table->string('kondisi_jalur')->default('UP');
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odps');
    }
};
