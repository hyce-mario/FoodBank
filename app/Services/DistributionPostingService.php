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
     * Phase 2.1.a stub: returns [] so no movements are posted until the
     * bag_composition schema is decided and 2.1.b fills in the real resolver.
     * See HANDOFF.md "Open questions" for the pending design decision
     * (new allocation_ruleset_components table vs. extending rules JSON).
     *
     * @return array<int, array{inventory_item_id: int, quantity: int}>
     */
    protected function resolveBagComposition(Visit $visit): array
    {
        return [];
    }
}
