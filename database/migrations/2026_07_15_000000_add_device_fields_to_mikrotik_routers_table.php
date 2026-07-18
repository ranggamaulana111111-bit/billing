<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotik_routers', function (Blueprint $table) {
            $table->string('identity')->nullable()->after('name');
            $table->integer('api_ssl_port')->nullable()->after('ssh_port');
            $table->string('routeros_version')->nullable()->after('api_ssl_port');
            $table->string('model')->nullable()->after('routeros_version');
            $table->string('architecture')->nullable()->after('model');
            $table->string('serial_number')->nullable()->after('architecture');
            $table->string('site')->nullable()->after('type');
            $table->string('location')->nullable()->after('site');
            $table->string('timezone')->nullable()->after('location');
            $table->decimal('latitude', 10, 7)->nullable()->after('timezone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->integer('management_vlan')->nullable()->after('longitude');
            $table->string('management_interface')->nullable()->after('management_vlan');
            $table->string('connection_type', 20)->default('rest_api')->after('management_interface');
            $table->string('status', 20)->default('unknown')->after('connection_type');
            $table->timestamp('last_seen')->nullable()->after('status');
            $table->timestamp('last_connected')->nullable()->after('last_seen');
            $table->integer('timeout')->default(10)->after('last_connected');
            $table->text('notes')->nullable()->after('timeout');
            $table->json('tags')->nullable()->after('notes');
            $table->softDeletes()->after('is_active');
        });

        // Encrypt existing plaintext passwords
        $routers = DB::table('mikrotik_routers')->select('id', 'password')->get();
        foreach ($routers as $router) {
            if ($router->password && ! str_starts_with($router->password, 'eyJ')) {
                DB::table('mikrotik_routers')
                    ->where('id', $router->id)
                    ->update(['password' => Crypt::encryptString($router->password)]);
            }
        }
    }

    public function down(): void
    {
        // Decrypt passwords back to plaintext before dropping columns
        $routers = DB::table('mikrotik_routers')->select('id', 'password')->get();
        foreach ($routers as $router) {
            if ($router->password) {
                try {
                    DB::table('mikrotik_routers')
                        ->where('id', $router->id)
                        ->update(['password' => Crypt::decryptString($router->password)]);
                } catch (Exception $e) {
                    // Already plaintext or corrupt — skip
                }
            }
        }

        Schema::table('mikrotik_routers', function (Blueprint $table) {
            $table->dropColumn([
                'identity', 'api_ssl_port', 'routeros_version', 'model', 'architecture',
                'serial_number', 'site', 'location', 'timezone', 'latitude', 'longitude',
                'management_vlan', 'management_interface', 'connection_type', 'status',
                'last_seen', 'last_connected', 'timeout', 'notes', 'tags',
            ]);
            $table->dropSoftDeletes();
        });
    }
};
