<?php

namespace App\Console\Commands;

use App\Mail\InventoryReconcileAlert;
use App\Models\Event;
use App\Models\InventoryMovement;
use App\Models\Visit;
use App\Services\DistributionPostingService;
use App\Services\SettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReconcileInventoryNightly extends Command
{
    protected $signature   = 'inventory:reconcile-nightly';
    protected $description = 'Check event_distributed movement deltas for all current and recently-past events. '
                           . 'Emails the support address if any non-zero delta is found.';

    /**
     * How many days back to include past events. Keeps the check from
     * re-scanning the entire history on every run.
     */
    private const LOOKBACK_DAYS = 7;

    public function handle(DistributionPostingService $service): int
    {
        $cutoff = now()->subDays(self::LOOKBACK_DAYS)->toDateString();

        $events = Event::where(function ($q) use ($cutoff) {
            $q->where('status', 'current')
              ->orWhere(fn ($q2) => $q2->where('status', 'past')->where('date', '>=', $cutoff));
        })->get();

        if ($events->isEmpty()) {
            $this->info('No current or recent past events to check.');
            return self::SUCCESS;
        }

        $alerts = [];

        foreach ($events as $event) {
            $deltas = $this->computeDeltas($event, $service);
            $gaps   = array_filter($deltas, fn ($d) => $d['delta'] !== 0);

            if (! empty($gaps)) {
                $alerts[$event->id] = [
                    'name' => $event->name,
                    'gaps' => $gaps,
                ];
            }
        }

        if (empty($alerts)) {
            $this->info('All events balanced — no deltas found.');
            return self::SUCCESS;
        }

        $this->warn(count($alerts) . ' event(s) have inventory gaps.');
        $this->sendAlert($alerts);

        return self::SUCCESS;
    }

    // ── Delta calculation (same logic as ReconcileEventInventory) ─────────────

    private function computeDeltas(Event $event, DistributionPostingService $service): array
    {
        $visits = Visit::where('event_id', $event->id)
            ->where('visit_status', 'exited')
            ->with('households')
            ->get();

        if ($visits->isEmpty()) {
            return [];
        }

        $expected = [];
        foreach ($visits as $visit) {
            foreach ($service->compositionForVisit($visit) as $component) {
                $id = $component['inventory_item_id'];
                $expected[$id] = ($expected[$id] ?? 0) + $component['quantity'];
            }
        }

        if (empty($expected)) {
            return [];
        }

        $actual = InventoryMovement::where('event_id', $event->id)
            ->where('movement_type', 'event_distributed')
            ->selectRaw('inventory_item_id, SUM(ABS(quantity)) as total')
            ->groupBy('inventory_item_id')
            ->pluck('total', 'inventory_item_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $deltas = [];
        foreach (array_unique(array_merge(array_keys($expected), array_keys($actual))) as $itemId) {
            $deltas[] = [
                'item_id'  => $itemId,
                'expected' => $expected[$itemId] ?? 0,
                'actual'   => $actual[$itemId]   ?? 0,
                'delta'    => ($expected[$itemId] ?? 0) - ($actual[$itemId] ?? 0),
            ];
        }

        return $deltas;
    }

    // ── Alert email ───────────────────────────────────────────────────────────

    private function sendAlert(array $alerts): void
    {
        $emailTo = SettingService::get('notifications.support_email')
            ?: SettingService::get('notifications.sender_email');

        if (! $emailTo) {
            Log::warning('inventory.reconcile_nightly: gaps found but no support_email configured.');
            $this->warn('No notification email configured in Settings › Notifications. Gaps not emailed.');
            return;
        }

        $lines = ["Nightly inventory reconciliation found gaps in " . count($alerts) . " event(s):\n"];

        foreach ($alerts as $eventId => $info) {
            $lines[] = "Event #{$eventId}: {$info['name']}";
            foreach ($info['gaps'] as $g) {
                $lines[] = "  Item #{$g['item_id']}: expected {$g['expected']}, posted {$g['actual']}, delta {$g['delta']}";
            }
            $lines[] = '';
        }

        $lines[] = 'Run `php artisan inventory:reconcile {event} --post` to backfill missing movements.';

        $body = implode("\n", $lines);

        Mail::to($emailTo)->send(new InventoryReconcileAlert($body));

        $this->info("Alert email sent to {$emailTo}.");
    }
}
