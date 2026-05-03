<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAllocateInventoryRequest;
use App\Http\Requests\ReturnInventoryAllocationRequest;
use App\Http\Requests\StoreEventInventoryAllocationRequest;
use App\Http\Requests\UpdateAllocationDistributedRequest;
use App\Models\Event;
use App\Models\EventInventoryAllocation;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EventInventoryAllocationController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    // ─── Allocate item to event ───────────────────────────────────────────────

    public function store(StoreEventInventoryAllocationRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();
        $item = InventoryItem::findOrFail($data['inventory_item_id']);
        $qty  = (int) $data['allocated_quantity'];

        try {
            $this->inventory->allocateToEvent(
                item:     $item,
                event:    $event,
                quantity: $qty,
                notes:    $data['notes'] ?? null,
                userId:   auth()->id(),
            );
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->with('alloc_error', $e->getMessage());
        }

        EventInventoryAllocation::create([
            'event_id'           => $event->id,
            'inventory_item_id'  => $item->id,
            'allocated_quantity' => $qty,
            'notes'              => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('events.show', $event)
            ->with('success', "{$qty} {$item->unit_type} of \"{$item->name}\" allocated to this event.")
            ->with('open_tab', 'inventory');
    }

    // ─── Update distributed quantity ──────────────────────────────────────────

    public function updateDistributed(
        UpdateAllocationDistributedRequest $request,
        Event                              $event,
        EventInventoryAllocation           $allocation,
    ): RedirectResponse {
        $newDistributed = (int) $request->validated('distributed_quantity');

        // Cannot exceed what's been allocated minus what's already been returned
        $maxDistributable = $allocation->allocated_quantity - $allocation->returned_quantity;

        if ($newDistributed > $maxDistributable) {
            return back()->with(
                'alloc_error',
                "Cannot distribute more than {$maxDistributable} {$allocation->item->unit_type} (allocated minus returned)."
            );
        }

        $allocation->update(['distributed_quantity' => $newDistributed]);

        return redirect()
            ->route('events.show', $event)
            ->with('success', "Distributed quantity updated to {$newDistributed}.")
            ->with('open_tab', 'inventory');
    }

    // ─── Return unused stock from event ──────────────────────────────────────

    public function returnStock(
        ReturnInventoryAllocationRequest $request,
        Event                            $event,
        EventInventoryAllocation         $allocation,
    ): RedirectResponse {
        $returnQty = (int) $request->validated('return_quantity');

        if ($returnQty > $allocation->maxReturnable()) {
            return back()->with(
                'alloc_error',
                "Cannot return more than {$allocation->maxReturnable()} {$allocation->item->unit_type} (the remaining balance)."
            );
        }

        try {
            $this->inventory->returnFromEvent(
                item:     $allocation->item,
                event:    $event,
                quantity: $returnQty,
                notes:    $request->validated('notes'),
                userId:   auth()->id(),
            );
        } catch (RuntimeException $e) {
            return back()->with('alloc_error', $e->getMessage());
        }

        $allocation->increment('returned_quantity', $returnQty);

        return redirect()
            ->route('events.show', $event)
            ->with('success', "{$returnQty} {$allocation->item->unit_type} of \"{$allocation->item->name}\" returned to inventory.")
            ->with('open_tab', 'inventory');
    }

    // ─── Remove allocation ────────────────────────────────────────────────────

    public function destroy(Event $event, EventInventoryAllocation $allocation): RedirectResponse
    {
        // Only allow deletion if nothing has been distributed or returned yet
        if ($allocation->distributed_quantity > 0 || $allocation->returned_quantity > 0) {
            return back()->with(
                'alloc_error',
                'Cannot delete an allocation that has recorded distributions or returns. Set distributed to 0 first or use Return Stock.'
            );
        }

        // Reverse the stock movement — items go back to shelf
        try {
            $this->inventory->returnFromEvent(
                item:     $allocation->item,
                event:    $event,
                quantity: $allocation->allocated_quantity,
                notes:    'Allocation removed — stock reversed.',
                userId:   auth()->id(),
            );
        } catch (RuntimeException $e) {
            return back()->with('alloc_error', $e->getMessage());
        }

        $allocation->delete();

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Allocation removed and stock restored.')
            ->with('open_tab', 'inventory');
    }

    // ─── Bulk allocate (Phase D) ──────────────────────────────────────────────

    /**
     * Atomic bulk allocate. Add-only by intent: each non-zero row pulls
     * stock from the shelf and adds to the event's allocation total. If
     * the item already has an allocation row, the new quantity is added
     * to the existing one (so a follow-up bulk submit topping up an event
     * "just works"). Returning surplus to the shelf is a separate flow
     * via the per-row Return action — bulk submit never reduces.
     *
     * Each row passes through InventoryService so movements are recorded.
     * Rows whose requested quantity exceeds the on-hand stock are SKIPPED
     * and reported back via the alloc_warning flash; other rows in the
     * same batch still process. The whole batch is wrapped in
     * DB::transaction so a thrown exception still rolls everything back
     * together.
     */
    public function bulkStore(BulkAllocateInventoryRequest $request, Event $event): RedirectResponse
    {
        $validated = $request->validated();
        $rows      = $validated['items'];
        $notes     = $validated['notes'] ?? null;

        $allocatedRows = 0;   // count of rows that resulted in a movement
        $totalUnits    = 0;   // sum of allocated quantities across processed rows
        $skipped       = [];  // [['name' => ..., 'reason' => ...], ...]

        DB::transaction(function () use (
            $event, $rows, $notes,
            &$allocatedRows, &$totalUnits, &$skipped
        ) {
            foreach ($rows as $row) {
                $qty = (int) $row['allocated_quantity'];

                // Operator left the row blank — silent skip, no warning.
                if ($qty === 0) {
                    continue;
                }

                // Lock the item row so concurrent allocations on the same
                // SKU serialize cleanly.
                $item = InventoryItem::lockForUpdate()->find($row['inventory_item_id']);
                if (! $item) {
                    // exists rule passed validation but the row may have
                    // been deleted between request and transaction. Skip
                    // rather than 500.
                    $skipped[] = ['name' => "#{$row['inventory_item_id']}", 'reason' => 'item no longer exists'];
                    continue;
                }

                if ($qty > $item->quantity_on_hand) {
                    $skipped[] = [
                        'name'   => $item->name,
                        'reason' => "insufficient stock (need {$qty} {$item->unit_type}, only {$item->quantity_on_hand} on hand)",
                    ];
                    continue;
                }

                $this->inventory->allocateToEvent(
                    item:     $item,
                    event:    $event,
                    quantity: $qty,
                    notes:    $notes,
                    userId:   auth()->id(),
                );

                $existing = EventInventoryAllocation::where('event_id', $event->id)
                    ->where('inventory_item_id', $item->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->increment('allocated_quantity', $qty);
                } else {
                    EventInventoryAllocation::create([
                        'event_id'           => $event->id,
                        'inventory_item_id'  => $item->id,
                        'allocated_quantity' => $qty,
                        'notes'              => $notes,
                    ]);
                }

                $allocatedRows++;
                $totalUnits += $qty;
            }
        });

        $successMsg = $allocatedRows > 0
            ? "{$allocatedRows} item" . ($allocatedRows === 1 ? '' : 's')
                . " allocated ({$totalUnits} units total)."
            : null;

        $warningMsg = null;
        if (! empty($skipped)) {
            $lines = array_map(
                fn ($s) => "{$s['name']} — {$s['reason']}",
                $skipped,
            );
            $warningMsg = "Skipped " . count($skipped) . ' item'
                . (count($skipped) === 1 ? '' : 's') . ":\n" . implode("\n", $lines);
        }

        return redirect()
            ->route('events.show', $event)
            ->with('success',     $successMsg)
            ->with('alloc_warning', $warningMsg)
            ->with('open_tab',    'inventory');
    }
}
