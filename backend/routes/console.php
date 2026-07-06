<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Live monitoring schedules.
Schedule::command('monitor:uptime')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('webvitals:fetch')->dailyAt('03:00'); // CrUX updates daily
Schedule::command('gam:sync')->dailyAt('04:00')->withoutOverlapping(); // GAM report (skips if unconfigured)
