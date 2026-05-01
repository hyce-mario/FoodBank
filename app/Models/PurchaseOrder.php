<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phase 6.6 — A purchase order: an inventory acquisition record that, when
 * marked received, generates paired InventoryMovement(stock_in) records and
 * a single FinanceTransaction(expense) atomically. This is the bridge
 * between the previously-siloed inventory and finance domains.
 */
class PurchaseOrder extends Model
{
    public const STATUSES = ['draft', 'received', 'cancelled'];

    protected $fillable = [
        'po_number',
        'supplier_name',
        'order_date',
        'received_date',
        'status',
        'total_amount',
        'notes',
        'finance_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'received_date' => 'date',
        'total_amount'  => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function financeTransaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'received'  => 'bg-green-100 text-green-700',
            'cancelled' => 'bg-red-100 text-red-700',
            default     => 'bg-amber-100 text-amber-700',
        };
    }

    public static function generatePoNumber(): string
    {
        $prefix = 'PO-' . now()->format('Y') . '-';
        do {
            $candidate = $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('po_number', $candidate)->exists());
        return $candidate;
    }
}
