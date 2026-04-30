<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInventoryAllocation extends Model
{
    use Auditable;
    protected $fillable = [
        'event_id',
        'inventory_item_id',
        'allocated_quantity',
        'distributed_quantity',
        'returned_quantity',
        'notes',
    ];

    protected $casts = [
        'allocated_quantity'   => 'integer',
        'distributed_quantity' => 'integer',
        'returned_quantity'    => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    // ─── Computed helpers ─────────────────────────────────────────────────────

    /**
     * Units still with the event team — not yet distributed or returned.
     */
    public function remainingQuantity(): int
    {
        return max(0, $this->allocated_quantity - $this->distributed_quantity - $this->returned_quantity);
    }

    /**
     * Maximum returnable quantity (can't return more than remaining).
     */
    public function maxReturnable(): int
    {
        return $this->remainingQuantity();
    }

    /**
     * Whether any stock can still be returned.
     */
    public function canReturn(): bool
    {
        return $this->remainingQuantity() > 0;
    }
}
