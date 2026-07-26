<?php

use App\Modules\GenieACS\Support\GenieacsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\BillingServiceProvider;

return [
    AppServiceProvider::class,
    BillingServiceProvider::class,
    GenieacsServiceProvider::class,
];
