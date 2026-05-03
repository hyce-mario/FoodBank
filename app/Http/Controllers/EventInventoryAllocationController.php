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
     * Atomic bulk allocate. Accepts an array of {inventory_item_id,
     * allocated_quantity} rows plus a batch-level `mode` of add | subtract
     * | replace. Each row passes through InventoryService so movements are
     * recorded; rows that can't be processed (insufficient stock, no
     * existing allocation to subtract from, replace-down below
     * already-distributed) are SKIPPED and reported back via session
     * flash. The whole batch is wrapped in DB::transaction so a thrown
     * exception still rolls everything back together.
     *
     * Mode semantics:
     *   add      — submitted is the increment (delta = +submitted)
     *   subtract — submitted is the decrement; clamped at existing alloc
     *              and at maxReturnable (delta = -min(submitted, max))
     *   replace  — submitted is the new total; delta = submitted - existing
     *              (positive → allocate more; negative → return surplus,
     *              capped at maxReturnable)
     *
     * Audit-trail invariant: subtract / replace-down increment the
     * allocation row's `returned_quantity` rather than decrementing
     * `allocated_quantity`. This preserves the "we originally pulled X"
     * record even after later corrections.
     */
    public function bulkStore(BulkAllocateInventoryRequest $request, Event $event): RedirectResponse
    {
        $validated = $request->validated();
        $rows      = $validated['items'];
        $mode      = $validated['mode'];
        $notes     = $validated['notes'] ?? null;

        $allocatedRows = 0;   // count of rows that resulted in any movement
        $totalUnits    = 0;   // sum of |delta| across processed rows
        $skipped       = [];  // [['name' => ..., 'reason' => ...], ...]

        DB::transaction(function () use (
            $event, $rows, $mode, $notes,
            &$allocatedRows, &$totalUnits, &$skipped
        ) {
            foreach ($rows as $row) {
                $submitted = (int) $row['allocated_quantity'];

                // Operator left the row blank — silent skip, no warning.
                if ($submitted === 0) {
                    continue;
                }

                // Lock the item row so concurrent allocations on the same
                // SKU serialize cleanly; matches the lockForUpdate pattern
                // used by adjustStock().
                $item = InventoryItem::lockForUpdate()->find($row['inventory_item_id']);
                if (! $item) {
                    // exists rule passed validation but the row may have
                    // been deleted between request and transaction. Skip
                    // rather than 500.
                    $skipped[] = ['name' => "#{$row['inventory_item_id']}", 'reason' => 'item no longer exists'];
                    continue;
                }

                $existing    = EventInventoryAllocation::where('event_id', $event->id)
                    ->where('inventory_item_id', $item->id)
                    ->lockForUpdate()
                    ->first();
                $existingQty = $existing?->allocated_quantity ?? 0;

                // Compute the delta to apply against the inventory shelf.
                // Positive = pull from shelf (allocateToEvent).
                // Negative = return to shelf (returnFromEvent).
                $delta = match ($mode) {
                    'add'      => $submitted,
                    'subtract' => -min($submitted, $existingQty),
                    'replace'  => $submitted - $existingQty,
                };

                // Subtract with no existing allocation → nothing to do.
                if ($mode === 'subtract' && $existingQty === 0) {
                    $skipped[] = ['name' => $item->name, 'reason' => 'nothing to subtract from'];
                    continue;
                }

                if ($delta === 0) {
                    // Replace-with-same value — operator chose the existing
                    // amount. No movement, no skip warning.
                    continue;
                }

                if ($delta > 0) {
                    // Pull more stock from the shelf.
                    if ($delta > $item->quantity_on_hand) {
                        $skipped[] = [
                            'name'   => $item->name,
                            'reason' => "insufficient stock (need {$delta} {$item->unit_type}, only {$item->quantity_on_hand} on hand)",
                        ];
                        continue;
                    }

                    $this->inventory->allocateToEvent(
                        item:     $item,
                        event:    $event,
                        quantity: $delta,
                        notes:    $notes,
                        userId:   auth()->id(),
                    );

                    if ($existing) {
                        $existing->increment('allocated_quantity', $delta);
                    } else {
                        EventInventoryAllocation::create([
                            'event_id'           => $event->id,
                            'inventory_item_id'  => $item->id,
                            'allocated_quantity' => $delta,
                            'notes'              => $notes,
                        ]);
                    }
                } else {
                    // delta < 0 — return surplus to the shelf. Capped at
                    // maxReturnable so we don't undershoot what's already
                    // been handed out.
                    $absDelta      = -$delta;
                    $maxReturnable = $existing
                        ? $existing->allocated_quantity - $existing->distributed_quantity - $existing->returned_quantity
                        : 0;

                    if ($absDelta > $maxReturnable) {
                        $alreadyDistributed = $existing?->distributed_quantity ?? 0;
                        $skipped[] = [
                            'name'   => $item->name,
                            'reason' => "can't reduce below already-distributed amount ({$alreadyDistributed} {$item->unit_type} already handed out)",
                        ];
                        continue;
                    }

                    $this->inventory->returnFromEvent(
                        item:     $item,
                        event:    $event,
                        quantity: $absDelta,
                        notes:    $notes,
                        userId:   auth()->id(),
                    );

                    // History-preserving rollback — increment returned
                    // rather than decrement allocated.
                    $existing->increment('returned_quantity', $absDelta);
                }

                $allocatedRows++;
                $totalUnits += abs($delta);
            }
        });

        // Build human-readable flash. Even when nothing landed (every row
        // skipped), surface the warning so the operator knows.
        $successMsg = $allocatedRows > 0
            ? "{$allocatedRows} item" . ($allocatedRows === 1 ? '' : 's')
                . " updated ({$totalUnits} units total)."
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
