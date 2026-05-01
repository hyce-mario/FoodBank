<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6.7 — cache lifetime event-attendance count on households so the
 * households index page no longer runs a correlated subquery per row.
 *
 * Column is maintained going forward by EventCheckInService (increment on
 * attach) and a Visit observer (decrement on visit delete).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->unsignedInteger('events_attended_count')
                ->default(0)
                ->after('household_size');
        });

        // Backfill from the same query the index page used to run as a
        // correlated subquery. SQLite-and-MySQL portable form.
        $rows = DB::table('visit_households')
            ->join('visits', 'visit_households.visit_id', '=', 'visits.id')
            ->selectRaw('visit_households.household_id, COUNT(DISTINCT visits.event_id) AS cnt')
            ->groupBy('visit_households.household_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('households')
                ->where('id', $row->household_id)
                ->update(['events_attended_count' => (int) $row->cnt]);
        }
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('events_attended_count');
        });
    }
};
