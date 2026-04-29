<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('status', ['upcoming', 'current', 'past'])
                  ->default('upcoming')
                  ->after('date');

            $table->index('status');
        });

        // Backfill is only meaningful when rows already exist (production migration).
        // CURDATE() is MySQL-only; on fresh installs / sqlite test DBs we can skip.
        if (DB::table('events')->doesntExist()) {
            return;
        }

        DB::statement("
            UPDATE events SET status = CASE
                WHEN date < CURDATE() THEN 'past'
                WHEN date = CURDATE() THEN 'current'
                ELSE 'upcoming'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
