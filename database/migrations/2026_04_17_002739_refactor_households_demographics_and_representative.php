<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            // ── Demographic breakdown ─────────────────────────────────────────
            $table->unsignedSmallInteger('children_count')->default(0)->after('household_size');
            $table->unsignedSmallInteger('adults_count')->default(0)->after('children_count');
            $table->unsignedSmallInteger('seniors_count')->default(0)->after('adults_count');

            // ── Representative relationship (self-referential FK) ──────────────
            // A represented household stores the ID of its representative.
            // nullOnDelete: detach automatically when the representative is deleted.
            $table->foreignId('representative_household_id')
                  ->nullable()
                  ->after('seniors_count')
                  ->constrained('households')
                  ->nullOnDelete();
        });

        // Back-fill: treat all existing members as adults until admin updates demographics.
        DB::statement('UPDATE households SET adults_count = household_size');

        Schema::table('households', function (Blueprint $table) {
            // Drop the old JSON-based multi-family columns — replaced by linked records.
            $table->dropColumn(['number_of_families', 'family_breakdown']);
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropForeign(['representative_household_id']);
            $table->dropColumn([
                'children_count',
                'adults_count',
                'seniors_count',
                'representative_household_id',
            ]);

            $table->unsignedSmallInteger('number_of_families')->default(1)->after('household_size');
            $table->json('family_breakdown')->nullable()->after('number_of_families');
        });
    }
};
