<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Yesterday's demand-signal digest, raised in the Workbench bell. Runs after
// the day has closed in SAST, and stays quiet on a day with no activity - see
// the command for why. Cron-driven artisan is not subject to the php-fpm
// ProtectSystem restriction, so writing the notification spool is fine here.
Schedule::command('openric:daily-digest')
    ->dailyAt('06:30')
    ->timezone('Africa/Johannesburg')
    ->withoutOverlapping();
