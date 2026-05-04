<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    const PAYMENT_METHODS = ['Cash', 'Bank Transfer', 'Check', 'Online', 'Other'];
    const STATUSES        = ['pending', 'completed', 'cancelled'];

    protected $fillable = [
        'transaction_type',
        'title',
        'category_id',
        'amount',
        'transaction_date',
        'source_or_payee',
        'payment_method',
        'reference_number',
        'event_id',
        'notes',
        'attachment_path',
        'status',
        'created_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('transaction_type', 'income');
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('transaction_type', 'expense');
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isIncome(): bool
    {
        return $this->transaction_type === 'income';
    }

    public function isExpense(): bool
    {
        return $this->transaction_type === 'expense';
    }

    public function typeBadgeClasses(): string
    {
        return $this->isIncome()
            ? 'bg-green-100 text-green-700'
            : 'bg-red-100 text-red-700';
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'completed' => 'bg-green-100 text-green-700',
            'pending'   => 'bg-amber-100 text-amber-700',
            'cancelled' => 'bg-gray-100 text-gray-500',
            default     => 'bg-gray-100 text-gray-500',
        };
    }

    public function formattedAmount(): string
    {
        return '$' . number_format($this->amount, 2);
    }
}
