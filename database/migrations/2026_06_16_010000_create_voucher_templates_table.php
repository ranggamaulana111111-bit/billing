<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('content')->nullable()->comment('HTML konten landing page');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('voucher_template_id')->nullable()->after('router_id')->constrained('voucher_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['voucher_template_id']);
            $table->dropColumn('voucher_template_id');
        });

        Schema::dropIfExists('voucher_templates');
    }
};
