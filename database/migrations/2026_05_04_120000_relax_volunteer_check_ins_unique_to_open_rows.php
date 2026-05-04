<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the strict UNIQUE(event_id, volunteer_id) on volunteer_check_ins.
 *
 * The original constraint was overly tight: it allowed only ONE row per
 * (event, volunteer) ever, which combined with VolunteerCheckInService::
 * checkIn()'s updateOrCreate(...) caused a re-check-in after checkout to
 * silently OVERWRITE the prior session — wiping checked_out_at and the
 * already-computed hours_served.
 *
 * The correct invariant is "at most ONE open row per (event, volunteer)",
 * not "at most one row ever". Each check-in / check-out cycle is its own
 * historical row; total hours for the event is the sum across rows.
 *
 * MySQL 8.0 doesn't support partial unique indexes (the most natural way
 * to express "unique where checked_out_at IS NULL"). Rather than emulating
 * one with a generated column, we drop the constraint here and enforce
 * the invariant at the application layer (see VolunteerCheckInService::
 * checkIn(), which now wraps the open-row check + insert in a transaction
 * with lockForUpdate). Realistically two admins won't race the same
 * volunteer at the same millisecond, but the lock makes it watertight.
 *
 * The named foreignId indexes that Laravel auto-creates on event_id and
 * volunteer_id remain — they were created by foreignId()->constrained()
 * and are unaffected by dropping the composite unique key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_check_ins', function (Blueprint $table) {
            // Laravel names composite unique keys as
            //   {table}_{col1}_{col2}_unique
            // Pass the explicit name to dropUnique() — passing an array
            // would re-derive the name and break if column order ever drifts.
            $table->dropUnique('volunteer_check_ins_event_id_volunteer_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_check_ins', function (Blueprint $table) {
            $table->unique(['event_id', 'volunteer_id']);
        });
    }
};
