<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('idempotency:prune')->hourly();
Schedule::command('wallet:reconcile')->everyFifteenMinutes();
Schedule::command('pulse:check')->everyMinute();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
