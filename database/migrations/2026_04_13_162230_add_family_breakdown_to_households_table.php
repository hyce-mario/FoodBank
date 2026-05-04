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
            // Number of distinct families this registrant represents (min 1)
            $table->unsignedTinyInteger('number_of_families')->default(1)->after('household_size');

            // JSON array of per-family sizes
            // e.g. [{"label":"Family 1","size":4},{"label":"Family 2","size":2}]
            $table->json('family_breakdown')->nullable()->after('number_of_families');

            // Widen household_size so totals across many families never overflow tinyInt
            $table->unsignedSmallInteger('household_size')->default(1)->change();
        });

        // Back-fill existing rows: wrap current household_size as a single-family record
        DB::statement('
            UPDATE households
            SET family_breakdown = JSON_ARRAY(
                JSON_OBJECT("label", "Family 1", "size", household_size)
            )
            WHERE family_breakdown IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['number_of_families', 'family_breakdown']);
            $table->unsignedTinyInteger('household_size')->default(1)->change();
        });
    }
};
