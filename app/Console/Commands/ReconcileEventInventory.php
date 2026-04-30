<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventInventoryAllocation;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Visit;
use App\Services\DistributionPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileEventInventory extends Command
{
    protected $signature = 'inventory:reconcile
                            {event : Event ID to reconcile}
                            {--post : Write missing event_distributed movements instead of just reporting}';

    protected $description = 'Compare expected vs actual event_distributed movements for an event. '
                            . 'Default is dry-run; add --post to write missing movements.';

    public function handle(DistributionPostingService $service): int
    {
        $eventId = (int) $this->argument('event');
        $event   = Event::find($eventId);

        if (! $event) {
            $this->error("Event #{$eventId} not found.");
            return self::FAILURE;
        }

        $this->info("Reconciling event #{$event->id}: {$event->name}");

        // ── Load exited visits with pivot snapshot data ────────────────────────
        $visits = Visit::where('event_id', $event->id)
            ->where('visit_status', 'exited')
            ->with('households')
            ->get();

        if ($visits->isEmpty()) {
            $this->info('No exited visits found — nothing to reconcile.');
            return self::SUCCESS;
        }

        $this->line("Exited visits: {$visits->count()}");

        // ── Calculate expected deduction per inventory item ────────────────────
        // Uses the same resolver as postForVisit so the numbers match exactly.
        $expected = [];
        foreach ($visits as $visit) {
            foreach ($service->compositionForVisit($visit) as $component) {
                $id = $component['inventory_item_id'];
                $expected[$id] = ($expected[$id] ?? 0) + $component['quantity'];
            }
        }

        if (empty($expected)) {
            $this->info('Event has no ruleset or no bag components — composition is empty, nothing to reconcile.');
            return self::SUCCESS;
        }

        // ── Calculate actual posted movements ──────────────────────────────────
        // Movements are stored as negative quantities; ABS gives the posted total.
        $actual = InventoryMovement::where('event_id', $event->id)
            ->where('movement_type', 'event_distributed')
            ->selectRaw('inventory_item_id, SUM(ABS(quantity)) as total')
            ->groupBy('inventory_item_id')
            ->pluck('total', 'inventory_item_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        // ── Build the delta table ──────────────────────────────────────────────
        $allIds   = array_unique(array_merge(array_keys($expected), array_keys($actual)));
        $rows     = [];
        $hasGaps  = false;

        foreach ($allIds as $itemId) {
            $exp   = $expected[$itemId] ?? 0;
            $act   = $actual[$itemId]   ?? 0;
            $delta = $exp - $act;
            if ($delta !== 0) $hasGaps = true;
            $rows[] = [$itemId, $exp, $act, $delta];
        }

        $this->table(
            ['Item ID', 'Expected', 'Actual Posted', 'Delta'],
            $rows
        );

        if (! $hasGaps) {
            $this->info('✓ All deltas are zero — event inventory is balanced.');
            return self::SUCCESS;
        }

        if (! $this->option('post')) {
            $this->warn('Gaps found. Run with --post to write the missing movements.');
            return self::SUCCESS;
        }

        // ── --post: write missing movements ───────────────────────────────────
        foreach ($rows as [$itemId, , , $delta]) {
            if ($delta <= 0) continue;

            DB::transaction(function () use ($event, $itemId, $delta) {
                $item = InventoryItem::lockForUpdate()->findOrFail($itemId);

                InventoryMovement::create([
                    'inventory_item_id' => $itemId,
                    'movement_type'     => 'event_distributed',
                    'quantity'          => -$delta,
                    'event_id'          => $event->id,
                    'notes'             => 'Backfill via inventory:reconcile',
                ]);

                $item->decrement('quantity_on_hand', $delta);

                EventInventoryAllocation::where('event_id', $event->id)
                    ->where('inventory_item_id', $itemId)
                    ->increment('distributed_quantity', $delta);
            });

            $this->info("Backfilled item #{$itemId}: posted -{$delta} units.");
        }

        return self::SUCCESS;
    }
}
