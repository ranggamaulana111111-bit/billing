<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:process')->dailyAt('08:00');
// voucher:sync-mikrotik dihapus — diganti event-driven API (POST /api/v1/mikrotik/hotspot-login)
Schedule::command('olt:poll')->hourly()->withoutOverlapping();
Schedule::command('customers:onu-sync')->hourly()->withoutOverlapping();
Schedule::command('customer:auto-isolir')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('customer:sync-isolir-ips')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('incident:check-sla')->hourly();
Schedule::command('routeros:sync-config')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('automation:scheduler')->everyMinute()->withoutOverlapping();
Schedule::command('automation:worker --once')->everyMinute()->withoutOverlapping();

Schedule::command('network:data-collect')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('network:data-collect --aggregate')->hourly()->withoutOverlapping();
Schedule::command('network:data-collect --prune')->everySixHours()->withoutOverlapping();

Schedule::command('qos:optimize')->everyFiveMinutes()->withoutOverlapping();
