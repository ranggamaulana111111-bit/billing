<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odp_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odp_id')->constrained('odps')->cascadeOnDelete();
            $table->integer('port_number');
            $table->string('status')->default('available'); // available / used / broken
            $table->timestamps();

            $table->unique(['odp_id', 'port_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odp_ports');
    }
};
