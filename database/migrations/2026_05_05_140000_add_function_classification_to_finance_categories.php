<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7.4.a — adds the NFP-functional classification axis to finance
 * categories, required by the Statement of Functional Expenses report
 * (the standard IRS-990 expense rollup: Program / Management & General
 * / Fundraising).
 *
 * Column name: function_classification (NOT 'function' — PHP reserved word
 * would clash with method names if a model accessor were ever added).
 *
 * Existing rows backfill to 'program' as the safest default — most foodbank
 * expense categories ARE program. The /finance/categories index renders a
 * yellow "Review functional classifications" banner so admins can reclassify
 * the small handful that aren't program (Marketing → fundraising,
 * Administrative → management_general, etc.).
 *
 * Portability:
 *   - MySQL: native ALTER TABLE ADD COLUMN ENUM(...).
 *   - SQLite: $table->enum() compiles to TEXT + CHECK constraint, fine for
 *     a fresh column add (the EventMedia document migration's add-copy-
 *     drop-rename dance was only needed when WIDENING an existing enum).
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE finance_categories
                ADD COLUMN function_classification
                ENUM('program','management_general','fundraising')
                NOT NULL DEFAULT 'program'
                AFTER type
            ");
            return;
        }

        Schema::table('finance_categories', function (Blueprint $table) {
            $table->enum('function_classification', ['program', 'management_general', 'fundraising'])
                  ->default('program')
                  ->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('finance_categories', function (Blueprint $table) {
            $table->dropColumn('function_classification');
        });
    }
};
