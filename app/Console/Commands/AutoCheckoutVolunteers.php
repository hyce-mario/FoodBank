<?php

namespace App\Console\Commands;

use App\Models\VolunteerCheckIn;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AutoCheckoutVolunteers extends Command
{
    protected $signature   = 'volunteers:auto-checkout {--dry-run : List affected records without writing}';
    protected $description = 'Close open volunteer check-ins for past events (runs 1h+ after event ends)';

    public function handle(): int
    {
        // Only auto-checkout check-ins where the event is already marked past.
        // The SyncEventStatuses command transitions events to 'past' after their date,
        // so any 'past' event has ended at minimum yesterday.
        $openCheckIns = VolunteerCheckIn::whereNull('checked_out_at')
            ->whereHas('event', fn ($q) => $q->where('status', 'past'))
            ->with('event', 'volunteer')
            ->get();

        if ($openCheckIns->isEmpty()) {
            $this->info('No open check-ins to close.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $count  = 0;

        foreach ($openCheckIns as $checkIn) {
            $checkoutTime = Carbon::parse($checkIn->event->date)->endOfDay();
            $hoursServed  = $checkIn->checked_in_at
                ? round($checkIn->checked_in_at->diffInMinutes($checkoutTime) / 60, 2)
                : 0;

            $name    = $checkIn->volunteer?->full_name ?? "ID {$checkIn->volunteer_id}";
            $event   = $checkIn->event->name;

            if ($dryRun) {
                $this->line("  [dry-run] Would close: {$name} @ {$event} ({$hoursServed}h)");
            } else {
                $checkIn->update([
                    'checked_out_at' => $checkoutTime,
                    'hours_served'   => $hoursServed,
                ]);
                $this->line("  Closed: {$name} @ {$event} ({$hoursServed}h)");
            }

            $count++;
        }

        $action = $dryRun ? 'Would close' : 'Closed';
        $this->info("{$action} {$count} check-in(s).");

        return self::SUCCESS;
    }
}
