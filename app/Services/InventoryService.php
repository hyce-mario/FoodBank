<?php

namespace App\Services;

use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    // ─── Add Stock ────────────────────────────────────────────────────────────

    /**
     * Record a stock_in movement and increase quantity_on_hand.
     *
     * @throws RuntimeException if quantity is not positive
     */
    public function addStock(
        InventoryItem $item,
        int           $quantity,
        ?string       $notes  = null,
        ?int          $userId = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('Add stock quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $quantity, $notes, $userId) {
            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type'     => 'stock_in',
                'quantity'          => $quantity,        // positive
                'user_id'           => $userId,
                'notes'             => $notes,
            ]);

            $item->increment('quantity_on_hand', $quantity);

            return $movement;
        });
    }

    // ─── Remove Stock ─────────────────────────────────────────────────────────

    /**
     * Record a removal movement (stock_out | damaged | expired) and decrease quantity_on_hand.
     *
     * @throws RuntimeException if insufficient stock or invalid type
     */
    public function removeStock(
        InventoryItem $item,
        int           $quantity,
        string        $type   = 'stock_out',
        ?string       $notes  = null,
        ?int          $userId = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('Remove stock quantity must be greater than zero.');
        }

        $allowed = ['stock_out', 'damaged', 'expired'];
        if (! in_array($type, $allowed)) {
            throw new RuntimeException("Invalid removal type \"{$type}\". Allowed: " . implode(', ', $allowed));
        }

        if ($item->quantity_on_hand < $quantity) {
            throw new RuntimeException(
                "Insufficient stock. Available: {$item->quantity_on_hand}, requested: {$quantity}."
            );
        }

        return DB::transaction(function () use ($item, $quantity, $type, $notes, $userId) {
            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type'     => $type,
                'quantity'          => -$quantity,       // negative = decrease
                'user_id'           => $userId,
                'notes'             => $notes,
            ]);

            $item->decrement('quantity_on_hand', $quantity);

            return $movement;
        });
    }

    // ─── Adjust Stock ─────────────────────────────────────────────────────────

    /**
     * Set stock to an absolute target value.
     * Records a signed adjustment movement reflecting the delta.
     *
     * @throws RuntimeException if target is negative
     */
    public function adjustStock(
        InventoryItem $item,
        int           $targetQty,
        ?string       $notes  = null,
        ?int          $userId = null,
    ): InventoryMovement {
        if ($targetQty < 0) {
            throw new RuntimeException('Target quantity cannot be negative.');
        }

        return DB::transaction(function () use ($item, $targetQty, $notes, $userId) {
            // Lock the row to prevent race conditions
            $item = InventoryItem::lockForUpdate()->findOrFail($item->id);

            $delta = $targetQty - $item->quantity_on_hand;   // signed delta

            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type'     => 'adjustment',
                'quantity'          => $delta,               // signed (± )
                'user_id'           => $userId,
                'notes'             => $notes,
            ]);

            $item->update(['quantity_on_hand' => $targetQty]);

            return $movement;
        });
    }

    // ─── Allocate to Event ────────────────────────────────────────────────────

    /**
     * Record stock pulled from the shelf for a specific event (event_allocated).
     *
     * @throws RuntimeException if insufficient stock
     */
    public function allocateToEvent(
        InventoryItem $item,
        Event         $event,
        int           $quantity,
        ?string       $notes  = null,
        ?int          $userId = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('Allocation quantity must be greater than zero.');
        }

        if ($item->quantity_on_hand < $quantity) {
            throw new RuntimeException(
                "Insufficient stock for allocation. Available: {$item->quantity_on_hand}, requested: {$quantity}."
            );
        }

        return DB::transaction(function () use ($item, $event, $quantity, $notes, $userId) {
            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type'     => 'event_allocated',
                'quantity'          => -$quantity,           // negative = decrease
                'event_id'          => $event->id,
                'user_id'           => $userId,
                'notes'             => $notes,
            ]);

            $item->decrement('quantity_on_hand', $quantity);

            return $movement;
        });
    }

    // ─── Return from Event ────────────────────────────────────────────────────

    /**
     * Record unused stock returned to the shelf after an event (event_returned).
     */
    public function returnFromEvent(
        InventoryItem $item,
        Event         $event,
        int           $quantity,
        ?string       $notes  = null,
        ?int          $userId = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('Return quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $event, $quantity, $notes, $userId) {
            $movement = InventoryMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type'     => 'event_returned',
                'quantity'          => $quantity,            // positive = increase
                'event_id'          => $event->id,
                'user_id'           => $userId,
                'notes'             => $notes,
            ]);

            $item->increment('quantity_on_hand', $quantity);

            return $movement;
        });
    }
}
