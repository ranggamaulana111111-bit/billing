<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odc_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odc_id')->constrained('odcs')->cascadeOnDelete();
            $table->integer('port_number');
            $table->string('port_type'); // inlet / outlet
            $table->string('status')->default('available'); // available / used / broken
            $table->unsignedBigInteger('connected_to_odp_id')->nullable();
            $table->timestamps();

            $table->unique(['odc_id', 'port_number']);
            $table->foreign('connected_to_odp_id')->references('id')->on('odps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odc_ports');
    }
};
