<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5.6.f — replace cascadeOnDelete with restrictOnDelete on the
 * volunteer_check_ins foreign keys.
 *
 * Pre-fix, deleting a volunteer or an event silently wiped that
 * volunteer's / event's entire service-history. Worst case: hours_served
 * becomes a payroll / grant-reporting input and a routine "clean up old
 * test event" admin click destroys compliance records.
 *
 * After this migration, the DB refuses the delete and the calling
 * controller catches the integrity violation (or pre-checks count) and
 * returns a friendly error: archive, soft-delete in the future, or
 * remove the check-in rows explicitly first.
 *
 * Safe to apply: no data is mutated, only FK behavior changes. SQLite
 * recreates the table to apply the constraint change; MySQL does an
 * in-place ALTER. Reversible via working down().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_check_ins', function (Blueprint $table) {
            // Drop both FKs by their default Laravel names.
            $table->dropForeign(['event_id']);
            $table->dropForeign(['volunteer_id']);

            // Re-add as restrict — column already exists, just re-attach
            // the FK with new behavior. The column-and-index pair from
            // 5.6.b's relax migration stays.
            $table->foreign('event_id')->references('id')->on('events')
                  ->restrictOnDelete();
            $table->foreign('volunteer_id')->references('id')->on('volunteers')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_check_ins', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropForeign(['volunteer_id']);

            $table->foreign('event_id')->references('id')->on('events')
                  ->cascadeOnDelete();
            $table->foreign('volunteer_id')->references('id')->on('volunteers')
                  ->cascadeOnDelete();
        });
    }
};
