<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onus', function (Blueprint $table) {

            // GenieACS
            $table->string('acs_device_id')->nullable()->after('serial_number');

            $table->string('acs_status')->nullable();

            $table->timestamp('acs_last_inform')->nullable();

            $table->string('acs_ip')->nullable();

            $table->string('acs_manufacturer')->nullable();

            $table->string('acs_product_class')->nullable();

            $table->string('acs_hardware_version')->nullable();

            $table->string('acs_software_version')->nullable();

            $table->string('acs_connection_request_url')->nullable();

            $table->string('acs_username')->nullable();

            $table->string('acs_password')->nullable();

            // WiFi
            $table->string('wifi_ssid')->nullable();

            $table->string('wifi_band')->nullable();

            $table->string('wifi_channel')->nullable();

            // WAN
            $table->string('wan_ip')->nullable();

            $table->string('wan_mac')->nullable();

            // Resource
            $table->float('cpu_usage')->nullable();

            $table->float('memory_usage')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('onus', function (Blueprint $table) {

            $table->dropColumn([
                'acs_device_id',
                'acs_status',
                'acs_last_inform',
                'acs_ip',
                'acs_manufacturer',
                'acs_product_class',
                'acs_hardware_version',
                'acs_software_version',
                'acs_connection_request_url',
                'acs_username',
                'acs_password',
                'wifi_ssid',
                'wifi_band',
                'wifi_channel',
                'wan_ip',
                'wan_mac',
                'cpu_usage',
                'memory_usage',
            ]);

        });
    }
};
