<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.2.b — tighten the demographic snapshot columns on `visit_households`
 * to NOT NULL.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.2.
 *
 * Background: 1.2.a added the snapshot columns as nullable so the schema
 * change could land before the service-layer write-on-attach existed.
 * 1.2.b updates `EventCheckInService::checkIn` (and the demo seeder) to
 * always populate the snapshot. With every existing row backfilled in
 * 1.2.a (108 rows on dev DB, 0 NULLs) and every new row guaranteed by
 * the service, we can flip the demographic columns to NOT NULL.
 *
 * The constraint then becomes the test: any future code path that
 * attaches a household to a visit without a pivot payload will fail
 * loud at insert time, instead of silently corrupting reports with
 * NULL pivot values that get COALESCEd to 0.
 *
 * Vehicle columns (`vehicle_make`, `vehicle_color`) stay nullable —
 * their source on `households` is nullable, and not every household
 * has vehicle info captured.
 *
 * Defensive guard: if any pre-existing row still has NULL in a
 * demographic column (shouldn't happen after 1.2.a's backfill, but
 * possible if a row was inserted between migrations on a different
 * environment), patch it from `households` first so the ALTER doesn't
 * blow up. Only runs when the table is non-empty (skip on fresh
 * install / sqlite tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Defensive re-backfill: catches any row that was inserted into
        // visit_households between when 1.2.a's backfill ran and now
        // without a snapshot payload (e.g. on an out-of-band environment).
        if (DB::table('visit_households')->exists()) {
            $hasNulls = DB::table('visit_households')
                ->whereNull('household_size')
                ->orWhereNull('children_count')
                ->orWhereNull('adults_count')
                ->orWhereNull('seniors_count')
                ->exists();

            if ($hasNulls) {
                // Outer COALESCE(..., 0) is a final safety net: the source
                // `households.*` demographics are NOT NULL with defaults
                // (tinyInteger default 1, smallInt default 0) so the
                // subquery practically can never return NULL — but if a
                // legacy environment somehow has a NULL demographic on a
                // household row, we'd rather pin it to 0 than fail the
                // ALTER below and leave the migration half-applied.
                DB::statement(<<<'SQL'
                    UPDATE visit_households
                    SET
                        household_size = COALESCE(household_size, (SELECT household_size FROM households WHERE households.id = visit_households.household_id), 1),
                        children_count = COALESCE(children_count, (SELECT children_count FROM households WHERE households.id = visit_households.household_id), 0),
                        adults_count   = COALESCE(adults_count,   (SELECT adults_count   FROM households WHERE households.id = visit_households.household_id), 0),
                        seniors_count  = COALESCE(seniors_count,  (SELECT seniors_count  FROM households WHERE households.id = visit_households.household_id), 0)
                SQL);
            }
        }

        Schema::table('visit_households', function (Blueprint $table) {
            $table->tinyInteger('household_size')->unsigned()->nullable(false)->change();
            $table->unsignedSmallInteger('children_count')->nullable(false)->change();
            $table->unsignedSmallInteger('adults_count')->nullable(false)->change();
            $table->unsignedSmallInteger('seniors_count')->nullable(false)->change();
            // Vehicle columns intentionally stay nullable — source columns
            // on `households` are nullable.
        });
    }

    public function down(): void
    {
        Schema::table('visit_households', function (Blueprint $table) {
            $table->tinyInteger('household_size')->unsigned()->nullable()->change();
            $table->unsignedSmallInteger('children_count')->nullable()->change();
            $table->unsignedSmallInteger('adults_count')->nullable()->change();
            $table->unsignedSmallInteger('seniors_count')->nullable()->change();
        });
    }
};
