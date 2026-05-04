<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    // Movements are immutable — no updated_at
    public const UPDATED_AT = null;

    /**
     * All valid movement types.
     * Positive quantity = stock increases.
     * Negative quantity = stock decreases.
     */
    public const TYPES = [
        'stock_in'          => 'Stock In',
        'stock_out'         => 'Stock Out',
        'adjustment'        => 'Adjustment',
        'damaged'           => 'Damaged',
        'expired'           => 'Expired',
        'event_allocated'   => 'Event Allocated',
        'event_returned'    => 'Event Returned',
        'event_distributed' => 'Event Distributed',
    ];

    /** Types that increase stock (positive quantity). */
    public const INBOUND = ['stock_in', 'event_returned'];

    /** Types that decrease stock (negative quantity). */
    public const OUTBOUND = ['stock_out', 'damaged', 'expired', 'event_allocated', 'event_distributed'];

    protected $fillable = [
        'inventory_item_id',
        'movement_type',
        'quantity',
        'event_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'created_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Display helpers ──────────────────────────────────────────────────────

    public function typeLabel(): string
    {
        return self::TYPES[$this->movement_type] ?? ucfirst($this->movement_type);
    }

    public function typeBadgeClasses(): string
    {
        return match ($this->movement_type) {
            'stock_in'          => 'bg-green-100 text-green-700',
            'stock_out'         => 'bg-red-100 text-red-700',
            'adjustment'        => 'bg-blue-100 text-blue-700',
            'damaged'           => 'bg-orange-100 text-orange-700',
            'expired'           => 'bg-gray-100 text-gray-600',
            'event_allocated'   => 'bg-purple-100 text-purple-700',
            'event_returned'    => 'bg-teal-100 text-teal-700',
            'event_distributed' => 'bg-pink-100 text-pink-700',
            default             => 'bg-gray-100 text-gray-500',
        };
    }

    /**
     * Human-readable signed quantity string, e.g. "+50" or "−10".
     */
    public function quantityDisplay(): string
    {
        return $this->quantity >= 0
            ? '+' . number_format($this->quantity)
            : '−' . number_format(abs($this->quantity));
    }

    public function quantityClasses(): string
    {
        return $this->quantity >= 0 ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold';
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('movement_type', $type);
    }

    public function scopeForItem(Builder $query, int $itemId): Builder
    {
        return $query->where('inventory_item_id', $itemId);
    }
}
