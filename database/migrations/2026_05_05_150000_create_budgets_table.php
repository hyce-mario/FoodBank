<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7.4.b — budgets table for the Budget vs. Actual / Variance report.
 *
 * Shape:
 *   - One budget row per (category, period_start, event_id) tuple. Same
 *     category in the same month for the same scope = one row, enforced
 *     by unique index.
 *   - period_type: monthly only for v1. Quarterly / fiscal-year roll up
 *     in PHP via SUM, no need for separate granularity (can revisit later
 *     without schema change — just add to the enum).
 *   - event_id nullable: NULL = org-wide budget; non-NULL = per-event budget.
 *     Both supported, the report's filter axis chooses which to display.
 *   - amount DECIMAL(12,2) matches finance_transactions.amount precision.
 *   - created_by FK to users → ON DELETE SET NULL preserves audit trail
 *     when the user who created the budget leaves.
 *   - category_id ON DELETE RESTRICT — deleting a category that has
 *     budgets on it would silently orphan them; force the admin to clear
 *     budgets first.
 *
 * Portability: enum compiles to TEXT+CHECK on SQLite. NULLs in unique
 * indexes: MySQL 8 treats NULL as DISTINCT (multiple NULL event_id rows
 * for same category+period_start coexist), SQLite does too.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('finance_categories')->restrictOnDelete();
            $table->enum('period_type', ['monthly'])->default('monthly');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 12, 2);
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Defensive uniqueness — one budget row per (category, period_start, event scope).
            // Use a short index name to stay under MySQL's 64-char limit.
            $table->unique(
                ['category_id', 'period_start', 'event_id'],
                'budgets_cat_period_event_unique'
            );
            $table->index(['period_start', 'period_end'], 'budgets_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
