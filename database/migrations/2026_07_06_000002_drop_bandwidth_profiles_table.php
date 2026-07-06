<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bandwidth_profiles');
    }

    public function down(): void
    {
        // Table recreated by 2026_07_06_000001_create_bandwidth_profiles_table.php
    }
};
