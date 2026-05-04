<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class SyncEventStatuses extends Command
{
    protected $signature   = 'events:sync-statuses';
    protected $description = 'Transition event statuses based on date: upcoming→current when today arrives, current→past when date has passed.';

    public function handle(): int
    {
        $today = now()->toDateString();

        // upcoming events whose date is today → current
        $toCurrentCount = Event::upcoming()
            ->whereDate('date', $today)
            ->update(['status' => 'current']);

        // current or upcoming events whose date is before today → past
        // (catches any events the scheduler may have missed)
        $toPastCount = Event::whereIn('status', ['upcoming', 'current'])
            ->whereDate('date', '<', $today)
            ->update(['status' => 'past']);

        $this->info("Synced event statuses: {$toCurrentCount} → current, {$toPastCount} → past.");

        return self::SUCCESS;
    }
}
