<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 7.4.b — Budget row for the Budget vs. Actual / Variance report.
 *
 * One row per (category, period_start, event_id?) tuple. Uniqueness
 * enforced at the DB layer.
 */
class Budget extends Model
{
    protected $fillable = [
        'category_id', 'period_type', 'period_start', 'period_end',
        'amount', 'event_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'amount'       => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOrgWide(Builder $query): Builder
    {
        return $query->whereNull('event_id');
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeInPeriod(Builder $query, $from, $to): Builder
    {
        return $query->where('period_start', '>=', $from)
                     ->where('period_end',   '<=', $to);
    }
}
