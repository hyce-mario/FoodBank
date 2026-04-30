<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\EventInventoryAllocation;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class DistributionPostingService
{
    /**
     * Post an event_distributed inventory movement for each item in the bag
     * composition, debiting quantity_on_hand and incrementing the allocation's
     * distributed_quantity tracker.
     *
     * All writes run inside a single DB::transaction so the visit's inventory
     * impact is all-or-nothing. If any item has insufficient stock, an
     * InsufficientStockException is thrown and the whole transaction rolls back
     * before any movements are persisted.
     *
     * @throws InsufficientStockException when any component item has insufficient stock.
     *
     * Refs: AUDIT_REPORT.md Part 13 §2.1.
     */
    public function postForVisit(Visit $visit): void
    {
        $composition = $this->resolveBagComposition($visit);

        if (empty($composition)) {
            return;
        }

        DB::transaction(function () use ($visit, $composition) {
            foreach ($composition as $component) {
                $itemId = (int) $component['inventory_item_id'];
                $needed = (int) $component['quantity'];

                $item = InventoryItem::lockForUpdate()->findOrFail($itemId);

                if ($item->quantity_on_hand < $needed) {
                    throw new InsufficientStockException(
                        eventId: $visit->event_id,
                        inventoryItemId: $itemId,
                        needed: $needed,
                        available: $item->quantity_on_hand,
                    );
                }

                InventoryMovement::create([
                    'inventory_item_id' => $itemId,
                    'movement_type'     => 'event_distributed',
                    'quantity'          => -$needed,
                    'event_id'          => $visit->event_id,
                ]);

                $item->decrement('quantity_on_hand', $needed);

                // Update the allocation tracker when a row exists for this item + event.
                // A missing allocation row is not an error — the movement record is the
                // authoritative ledger; the allocation column is operational bookkeeping.
                EventInventoryAllocation::where('event_id', $visit->event_id)
                    ->where('inventory_item_id', $itemId)
                    ->increment('distributed_quantity', $needed);
            }
        });
    }

    /**
     * Resolve the bag composition for this visit: which inventory items to
     * distribute and in what total quantity.
     *
     * Returns an array of components, each with:
     *   - inventory_item_id (int)
     *   - quantity          (int) — total units to post for this visit
     *
     * Calculation:
     *   1. Load the event's AllocationRuleset and its components (Option A schema:
     *      allocation_ruleset_components table, one row per item per ruleset).
     *   2. For each household in the visit, call getBagsFor(snapshot_size) using
     *      the Phase 1.2 pivot snapshot so edits made after the visit do not
     *      retroactively change the distribution quantity.
     *   3. Total bags × each component's qty_per_bag = quantity to post.
     *
     * Returns [] when the event has no ruleset, the ruleset has no components,
     * or the total bags calculation yields zero.
     *
     * Refs: AUDIT_REPORT.md Part 13 §2.1.b.
     *
     * @return array<int, array{inventory_item_id: int, quantity: int}>
     */
    protected function resolveBagComposition(Visit $visit): array
    {
        $visit->loadMissing(['event.ruleset.components', 'households']);

        $ruleset = optional($visit->event)->ruleset;

        if (! $ruleset) {
            return [];
        }

        $components = $ruleset->components;

        if ($components->isEmpty()) {
            return [];
        }

        // Use the Phase 1.2 snapshot household_size so that editing a household
        // record after the visit does not change how much inventory gets posted.
        $totalBags = $visit->households->sum(
            fn ($h) => $ruleset->getBagsFor((int) $h->pivot->household_size)
        );

        if ($totalBags === 0) {
            return [];
        }

        return $components->map(fn ($c) => [
            'inventory_item_id' => $c->inventory_item_id,
            'quantity'          => $totalBags * $c->qty_per_bag,
        ])->values()->all();
    }
}
