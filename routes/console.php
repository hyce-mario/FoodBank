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

// Phase 2.2: nightly inventory reconciliation. Checks all current/recent past
// events for missed event_distributed movements and emails the support address
// if any delta is found. Runs just after midnight (after sync-statuses).
Schedule::command('inventory:reconcile-nightly')
    ->dailyAt('00:05')
    ->withoutOverlapping();

// Phase 5.3.a: auto-checkout open volunteer check-ins for past events.
// Runs hourly at :10 past — safely after sync-statuses has marked events past.
Schedule::command('volunteers:auto-checkout')
    ->hourlyAt(10)
    ->withoutOverlapping();
