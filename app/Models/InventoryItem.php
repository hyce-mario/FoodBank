<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'unit_type',
        'quantity_on_hand',
        'reorder_level',
        'description',
        'manufacturing_date',
        'expiry_date',
        'is_active',
    ];

    protected $casts = [
        'quantity_on_hand'   => 'integer',
        'reorder_level'      => 'integer',
        'is_active'          => 'boolean',
        'manufacturing_date' => 'date',
        'expiry_date'        => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'inventory_item_id');
    }

    public function eventAllocations(): HasMany
    {
        return $this->hasMany(EventInventoryAllocation::class, 'inventory_item_id');
    }

    // ─── Stock status helpers ─────────────────────────────────────────────────

    /**
     * Returns 'out', 'low', or 'in'.
     */
    public function stockStatus(): string
    {
        if ($this->quantity_on_hand === 0) {
            return 'out';
        }

        if ($this->reorder_level > 0 && $this->quantity_on_hand <= $this->reorder_level) {
            return 'low';
        }

        return 'in';
    }

    public function stockLabel(): string
    {
        return match ($this->stockStatus()) {
            'out' => 'Out of Stock',
            'low' => 'Low Stock',
            default => 'In Stock',
        };
    }

    public function stockBadgeClasses(): string
    {
        return match ($this->stockStatus()) {
            'out' => 'bg-red-100 text-red-700',
            'low' => 'bg-amber-100 text-amber-700',
            default => 'bg-green-100 text-green-700',
        };
    }

    // ─── Expiry helpers ───────────────────────────────────────────────────────

    /**
     * Returns 'expired', 'expiring_soon', 'ok', or null (no expiry date set).
     */
    public function expiryStatus(): ?string
    {
        if (! $this->expiry_date) {
            return null;
        }

        $today = now()->startOfDay();

        if ($this->expiry_date->lt($today)) {
            return 'expired';
        }

        if ($this->expiry_date->lte($today->copy()->addDays(30))) {
            return 'expiring_soon';
        }

        return 'ok';
    }

    public function expiryLabel(): ?string
    {
        return match ($this->expiryStatus()) {
            'expired'       => 'Expired',
            'expiring_soon' => 'Expiring Soon',
            'ok'            => null,
            default         => null,
        };
    }

    public function expiryBadgeClasses(): string
    {
        return match ($this->expiryStatus()) {
            'expired'       => 'bg-red-100 text-red-700',
            'expiring_soon' => 'bg-amber-100 text-amber-700',
            default         => 'bg-gray-100 text-gray-500',
        };
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%' . $term . '%';
        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
              ->orWhere('sku', 'like', $like)
              ->orWhereHas('category', fn($c) => $c->where('name', 'like', $like));
        });
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('reorder_level', '>', 0)
                     ->whereColumn('quantity_on_hand', '<=', 'reorder_level');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('quantity_on_hand', 0);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
                     ->whereDate('expiry_date', '<', now()->toDateString());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expiry_date')
                     ->whereDate('expiry_date', '>=', now()->toDateString())
                     ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString());
    }
}
