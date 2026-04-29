<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.2.a — snapshot demographics + vehicle on visit_households.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.2.
 *
 * Reports currently SUM live `households.*` joined through `visit_households`,
 * which means editing a household's size *after* a visit silently rewrites
 * historical reports. After this migration, demographics and vehicle make
 * /color are captured at attach-time on the pivot itself; reports read
 * from the snapshot and become temporally stable.
 *
 * This migration ONLY adds the columns and backfills from the current
 * household state. The service layer (1.2.b) populates the snapshot at
 * attach time going forward; the report layer (1.2.c) switches to read
 * from the snapshot. Both are separate commits.
 *
 * Columns are nullable so a future incident where a row is created
 * without snapshot data fails loud at read time (NULL surfaces in
 * reports) rather than silently defaulting to 0/empty. Backfill
 * guarantees every existing row has non-null values; the service
 * change in 1.2.b guarantees every new row will too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_households', function (Blueprint $table) {
            // Demographics — match the source column types on `households`.
            $table->tinyInteger('household_size')->unsigned()->nullable()->after('household_id');
            $table->unsignedSmallInteger('children_count')->nullable()->after('household_size');
            $table->unsignedSmallInteger('adults_count')->nullable()->after('children_count');
            $table->unsignedSmallInteger('seniors_count')->nullable()->after('adults_count');

            // Vehicle — make/color match the household column widths.
            $table->string('vehicle_make', 100)->nullable()->after('seniors_count');
            $table->string('vehicle_color', 50)->nullable()->after('vehicle_make');
        });

        // Backfill from the current household state so every existing row
        // has non-null snapshot values. Skip on empty (fresh installs and
        // sqlite test runs both start with zero pivot rows).
        if (! DB::table('visit_households')->exists()) {
            return;
        }

        // Correlated subquery form — portable across MySQL 8 and SQLite 3.33+.
        // One pass per column, but only runs once per environment.
        DB::statement(<<<'SQL'
            UPDATE visit_households
            SET
                household_size = (SELECT household_size FROM households WHERE households.id = visit_households.household_id),
                children_count = (SELECT children_count FROM households WHERE households.id = visit_households.household_id),
                adults_count   = (SELECT adults_count   FROM households WHERE households.id = visit_households.household_id),
                seniors_count  = (SELECT seniors_count  FROM households WHERE households.id = visit_households.household_id),
                vehicle_make   = (SELECT vehicle_make   FROM households WHERE households.id = visit_households.household_id),
                vehicle_color  = (SELECT vehicle_color  FROM households WHERE households.id = visit_households.household_id)
        SQL);
    }

    public function down(): void
    {
        Schema::table('visit_households', function (Blueprint $table) {
            $table->dropColumn([
                'household_size',
                'children_count',
                'adults_count',
                'seniors_count',
                'vehicle_make',
                'vehicle_color',
            ]);
        });
    }
};
