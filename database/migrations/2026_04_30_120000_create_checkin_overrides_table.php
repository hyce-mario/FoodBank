<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.3.c — append-only audit trail for re-check-in supervisor overrides.
 *
 * Replaces the Log::warning('checkin.override', …) call introduced in 1.3.a
 * with a structured DB table so Phase 4's admin audit-log viewer inherits
 * real history. Per the audit, audit_logs in Phase 4 will be a broader
 * cross-cutting table; this is a focused, narrower table for one specific
 * audit event class. When Phase 4 lands the broader table, this can either
 * be absorbed (migration to copy rows) or kept as a specialized view.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.3 (last bullet — formalization deferred
 * to Phase 4; user opted in 2026-04-30 to start capturing structured data
 * earlier so Phase 4 has history to display).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkin_overrides', function (Blueprint $table) {
            $table->id();

            // The supervisor who clicked the override. Nullable + nullOnDelete
            // so deleting a user later does not destroy the audit trail —
            // matches inventory_movements pattern.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // The event the conflict was detected on. Nullable + nullOnDelete
            // for the same audit-survival reason. The override row keeps its
            // household_ids / prior_visit_ids context even if the event is
            // hard-deleted later.
            $table->foreignId('event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();

            // The household that was being checked in (the primary / driver
            // when it's a represented pickup). Nullable + nullOnDelete so the
            // audit row survives household deletions.
            $table->foreignId('representative_household_id')
                ->nullable()
                ->constrained('households')
                ->nullOnDelete();

            // The OFFENDING subset — household IDs that already had an exited
            // visit at this event. Always populated (the override is meaningful
            // only when at least one collision exists). JSON because a
            // representative pickup can collide on multiple represented IDs
            // at once.
            $table->json('household_ids');

            // The visit IDs that triggered the policy throw. Useful for
            // forensic queries ("which prior visit caused this override?").
            $table->json('prior_visit_ids');

            // The supervisor's free-text reason. Required by the validator
            // (CheckInRequest::withValidator after-hook) AND by the service
            // layer (EventCheckInService throws InvalidArgumentException on
            // empty reason before reaching this insert). This NOT NULL is
            // the DB's last line of defense if both upstream guards are
            // ever weakened.
            //
            // Retention TODO (Phase 4 audit_logs): supervisor free-text may
            // contain PII (e.g. "Sarah forgot her bag"). When Phase 4 lands
            // the broader audit viewer, define a retention policy + purge
            // job — the audit data is meant for board-meeting / compliance
            // review, not indefinite raw storage.
            $table->text('reason');

            $table->timestamp('created_at')->useCurrent();
            // No updated_at — overrides are immutable like inventory_movements.

            $table->index('event_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_overrides');
    }
};
