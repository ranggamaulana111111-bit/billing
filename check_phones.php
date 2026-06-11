<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Contracts\Console\Kernel;

$customers = Customer::select('id', 'name', 'phone')->get();
foreach ($customers as $c) {
    echo $c->id.': '.$c->name.' - phone: '.($c->phone ?? 'KOSONG').PHP_EOL;
}

echo PHP_EOL.'--- Unpaid Invoices ---'.PHP_EOL;
$invoices = Invoice::where('payment_status', 'unpaid')->with('customer')->get();
foreach ($invoices as $inv) {
    echo 'Invoice #'.$inv->id.' ('.$inv->invoice_code.')'.PHP_EOL;
    echo '  Customer: '.($inv->customer->name ?? '?').PHP_EOL;
    echo '  Phone: '.($inv->customer->phone ?? 'KOSONG').PHP_EOL;
    echo '  Amount: Rp '.$inv->amount.PHP_EOL;
}
