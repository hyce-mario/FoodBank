<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Transition event statuses each morning at midnight.
// withoutOverlapping prevents two simultaneous executions from racing on the
// same UPDATE statements if the scheduler is triggered twice. See README
// "Scheduled Tasks" for cron / Windows Task Scheduler setup.
Schedule::command('events:sync-statuses')
    ->dailyAt('00:01')
    ->withoutOverlapping();
