<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 7.4.c — Pledge row for the Pledge / AR Aging report.
 *
 * Single-amount per pledge for v1; partial payments are tracked via the
 * 'partial' status (a future pledge_payments sibling table can be added
 * without schema breakage).
 */
class Pledge extends Model
{
    public const STATUS_OPEN        = 'open';
    public const STATUS_PARTIAL     = 'partial';
    public const STATUS_FULFILLED   = 'fulfilled';
    public const STATUS_WRITTEN_OFF = 'written_off';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PARTIAL,
        self::STATUS_FULFILLED,
        self::STATUS_WRITTEN_OFF,
    ];

    public const STATUS_LABELS = [
        self::STATUS_OPEN        => 'Open',
        self::STATUS_PARTIAL     => 'Partial',
        self::STATUS_FULFILLED   => 'Fulfilled',
        self::STATUS_WRITTEN_OFF => 'Written off',
    ];

    protected $fillable = [
        'household_id', 'source_or_payee', 'amount',
        'pledged_at', 'expected_at', 'received_at',
        'status', 'category_id', 'event_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'pledged_at'  => 'date',
        'expected_at' => 'date',
        'received_at' => 'date',
        'amount'      => 'decimal:2',
    ];

    public function household(): BelongsTo  { return $this->belongsTo(Household::class); }
    public function category(): BelongsTo   { return $this->belongsTo(FinanceCategory::class); }
    public function event(): BelongsTo      { return $this->belongsTo(Event::class); }
    public function creator(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeOutstanding(Builder $query): Builder
    {
        // Only open + partial count toward the aging report.
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_PARTIAL]);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
