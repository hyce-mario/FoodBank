<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 1.3.c — append-only audit row for a re-check-in supervisor override.
 *
 * Written by EventCheckInService::checkIn() when the configured re-check-in
 * policy is 'override' AND the caller passed force=true with a non-empty
 * reason. The active-duplicate path (RuntimeException) does NOT produce one
 * of these — the policy setting only governs the after-exit case.
 *
 * Records are immutable: like InventoryMovement, this model has no
 * updated_at and no update path through the service. If a row's data is
 * wrong, the right answer is to write a new compensating row, not edit the
 * old one.
 *
 * Column semantics:
 *   - representative_household_id: the household passed as the primary to
 *     checkIn() — the one whose visit row gets created. In a represented
 *     pickup this is the driver / rep, who may NOT themselves be in the
 *     offending subset.
 *   - household_ids: the OFFENDING subset — the household IDs that already
 *     had an exited visit at this event and triggered the policy. These
 *     can be disjoint from representative_household_id (e.g. the rep is
 *     fine but a represented family member was served earlier).
 *   - prior_visit_ids: the visit row IDs whose existence triggered the throw.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.3.
 */
class CheckInOverride extends Model
{
    /**
     * Class follows the `CheckIn` convention shared with CheckInController,
     * EventCheckInService, and CheckInRequest. The project uses lowercase
     * `checkin` for routes, views, and tables, so override the auto-derived
     * `check_in_overrides` to match the actual table name.
     */
    protected $table = 'checkin_overrides';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event_id',
        'representative_household_id',
        'household_ids',
        'prior_visit_ids',
        'reason',
    ];

    protected $casts = [
        'household_ids'   => 'array',
        'prior_visit_ids' => 'array',
        'created_at'      => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'representative_household_id');
    }
}
