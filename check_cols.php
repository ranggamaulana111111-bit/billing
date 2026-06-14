<?php
$tables = ['customers','packages','invoices','payments','vouchers','odcs','odp_routes','odp_points','settings','activity_logs'];
foreach ($tables as $t) {
    $cols = DB::connection('sqlite')->getSchemaBuilder()->getColumnListing($t);
    echo $t.': '.(in_array('user_id', $cols) ? 'OK' : 'MISSING').PHP_EOL;
}
