<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Immutable record — no updated_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'before_json',
        'after_json',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json'  => 'array',
        'created_at'  => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Short class name for display (e.g. "Household" not full namespace). */
    public function targetLabel(): string
    {
        return class_basename($this->target_type);
    }

    /**
     * Write a single audit row. Called by the Auditable trait.
     * Returns silently if writing fails rather than disrupting the main operation.
     */
    public static function writeEntry(
        string $action,
        Model $model,
        ?array $before,
        ?array $after,
    ): void {
        try {
            static::create([
                'user_id'     => auth()->id(),
                'action'      => $action,
                'target_type' => get_class($model),
                'target_id'   => $model->getKey(),
                'before_json' => $before ?: null,
                'after_json'  => $after  ?: null,
                'ip_address'  => request()->ip(),
                'user_agent'  => mb_substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable) {
            // Audit failures must never interrupt the main transaction.
        }
    }
}
