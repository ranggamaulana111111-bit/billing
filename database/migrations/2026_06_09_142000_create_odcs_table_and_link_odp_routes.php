<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odcs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('active');
            $table->integer('capacity')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('odp_routes', function (Blueprint $table) {
            $table->foreignId('odc_id')->nullable()->after('id')->constrained('odcs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('odp_routes', function (Blueprint $table) {
            $table->dropForeign(['odc_id']);
            $table->dropColumn('odc_id');
        });

        Schema::dropIfExists('odcs');
    }
};
