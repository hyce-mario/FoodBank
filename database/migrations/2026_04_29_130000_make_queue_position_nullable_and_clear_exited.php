<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.1.c.1 — make queue_position meaningful only for active visits.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.1.c.
 *
 * Phase 1.1.a renumbered every visit's queue_position to 1..N per
 * (event_id, lane), regardless of status. That created a real conflict:
 * the scanner/loader UIs renumber active visits to 1..N when the user
 * drags-and-drops, but exited visits still occupy small positions like
 * 1, 2, 3. The unique index from 1.1.a then rejects the reorder.
 *
 * The cleanest fix is to scope queue_position to active visits only:
 * once a visit exits, its position is no longer meaningful and is set to
 * NULL. MySQL and SQLite both treat NULLs as distinct in unique indexes
 * (multiple NULLs are allowed), so the unique constraint then
 * automatically becomes "active visits cannot share a position" while
 * letting an unbounded number of exited visits coexist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->unsignedSmallInteger('queue_position')->nullable()->change();
        });

        // Backfill: any visit that has already exited loses its (now meaningless)
        // position so a future reorder of active visits won't collide with it.
        DB::table('visits')
            ->where('visit_status', 'exited')
            ->update(['queue_position' => null]);
    }

    public function down(): void
    {
        // Restore non-null default by re-numbering exited visits with archive-range
        // values. We can't safely set them all back to 0 — that would create
        // duplicates that would fail the unique index from Phase 1.1.a.
        //
        // Limitation: queue_position is unsignedSmallInteger (max 65535). If a
        // single (event_id, lane) somehow has >35535 exited visits, this rollback
        // would overflow. That's an extreme corner case (a single lane serving
        // tens of thousands of households at one event) but would need a manual
        // intervention if it ever occurred.
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("
                UPDATE visits
                SET queue_position = (
                    SELECT 30000 + rn FROM (
                        SELECT id, ROW_NUMBER() OVER (
                            PARTITION BY event_id, lane
                            ORDER BY exited_at, id
                        ) AS rn
                        FROM visits
                        WHERE visit_status = 'exited'
                    ) sub WHERE sub.id = visits.id
                )
                WHERE visit_status = 'exited'
            ");
        } else {
            DB::statement("
                UPDATE visits v
                INNER JOIN (
                    SELECT id, ROW_NUMBER() OVER (
                        PARTITION BY event_id, lane
                        ORDER BY exited_at, id
                    ) AS rn
                    FROM visits
                    WHERE visit_status = 'exited'
                ) ranked ON v.id = ranked.id
                SET v.queue_position = 30000 + ranked.rn
                WHERE v.visit_status = 'exited'
            ");
        }

        Schema::table('visits', function (Blueprint $table) {
            $table->unsignedSmallInteger('queue_position')->default(0)->nullable(false)->change();
        });
    }
};
