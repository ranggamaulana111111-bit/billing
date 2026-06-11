<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

DB::statement('SET FOREIGN_KEY_CHECKS=0');
Invoice::truncate();
Customer::truncate();
ActivityLog::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1');

echo 'Data dibersihkan. Jalankan: php artisan db:seed --class=BillingSeeder'.PHP_EOL;
