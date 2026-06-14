<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:process')->dailyAt('08:00');
Schedule::command('voucher:sync-mikrotik')->everyFiveMinutes();
Schedule::command('olt:poll')->everyFifteenMinutes()->withoutOverlapping();
