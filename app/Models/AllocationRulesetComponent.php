<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationRulesetComponent extends Model
{
    protected $fillable = [
        'allocation_ruleset_id',
        'inventory_item_id',
        'qty_per_bag',
    ];

    protected $casts = [
        'qty_per_bag' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(AllocationRuleset::class, 'allocation_ruleset_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
