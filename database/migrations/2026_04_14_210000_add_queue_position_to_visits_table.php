<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->unsignedSmallInteger('queue_position')
                  ->default(0)
                  ->after('lane');
        });

        // Backfill is only meaningful when rows already exist (production migration).
        // On fresh installs or test databases the visits table is empty and we can
        // skip the driver-specific UPDATE entirely.
        if (DB::table('visits')->doesntExist()) {
            return;
        }

        // The user-variable syntax below is MySQL-only. Other drivers should use
        // a ROW_NUMBER() OVER (PARTITION BY event_id, lane ORDER BY ...) variant.
        DB::statement('
            UPDATE visits v
            JOIN (
                SELECT id,
                       (@rn := IF(@key = CONCAT(event_id, "_", lane), @rn + 1, 1)) AS pos,
                       (@key := CONCAT(event_id, "_", lane))
                FROM visits, (SELECT @rn := 0, @key := "") AS vars
                ORDER BY event_id, lane, start_time ASC, id ASC
            ) ranked ON v.id = ranked.id
            SET v.queue_position = ranked.pos
        ');
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn('queue_position');
        });
    }
};
