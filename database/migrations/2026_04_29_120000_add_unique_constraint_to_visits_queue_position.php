<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1.1.a — pin queue ordering at the database level.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.1, Part 3 #1.
 *
 * Before this migration, two concurrent check-ins on the same lane could
 * both read MAX(queue_position) and both insert position N+1, producing
 * duplicate positions and unstable queue order. The follow-up service-layer
 * fix (Phase 1.1.b) wraps the read+insert in a transaction with a row lock;
 * this migration adds the unique index that makes the race fail loudly
 * instead of silently corrupting the queue if the lock is ever bypassed.
 *
 * The renumber pass below is defensive: any duplicates caused by the pre-fix
 * race are resolved before the unique index is applied. ROW_NUMBER works on
 * MySQL 8+ and SQLite 3.25+ (the project's supported drivers).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('visits')->exists()) {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                DB::statement("
                    UPDATE visits
                    SET queue_position = (
                        SELECT rn FROM (
                            SELECT id, ROW_NUMBER() OVER (
                                PARTITION BY event_id, lane
                                ORDER BY start_time, id
                            ) AS rn
                            FROM visits
                        ) sub WHERE sub.id = visits.id
                    )
                ");
            } else {
                DB::statement("
                    UPDATE visits v
                    INNER JOIN (
                        SELECT id, ROW_NUMBER() OVER (
                            PARTITION BY event_id, lane
                            ORDER BY start_time, id
                        ) AS rn
                        FROM visits
                    ) ranked ON v.id = ranked.id
                    SET v.queue_position = ranked.rn
                ");
            }
        }

        Schema::table('visits', function (Blueprint $table) {
            $table->unique(
                ['event_id', 'lane', 'queue_position'],
                'visits_event_lane_position_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropUnique('visits_event_lane_position_unique');
        });
    }
};
