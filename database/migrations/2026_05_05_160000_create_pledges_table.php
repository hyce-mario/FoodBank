<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7.4.c — pledges table for the Pledge / AR Aging report.
 *
 * Shape:
 *   - One row per pledge. Single-amount per pledge for v1; partial-payment
 *     support is additive (a future pledge_payments sibling table can be
 *     added without schema breakage).
 *   - household_id nullable: existing donors get the FK; new prospects
 *     come in via source_or_payee string only.
 *   - source_or_payee is ALWAYS populated (even when household_id is set —
 *     copy "First Last" from the household). This lets
 *     FinanceReportService::donorAnalysis() roll fulfilled pledges in
 *     alongside direct income without joining households.
 *   - status enum: open / partial / fulfilled / written_off. The aging
 *     report buckets only 'open' + 'partial' rows by expected_at.
 *   - category_id + event_id both nullable to associate a pledge with the
 *     finance category it'll roll into when fulfilled, and the fundraiser
 *     event it came from.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pledges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_or_payee', 200); // always populated
            $table->decimal('amount', 12, 2);
            $table->date('pledged_at');
            $table->date('expected_at'); // drives aging bucket
            $table->date('received_at')->nullable();
            $table->enum('status', ['open', 'partial', 'fulfilled', 'written_off'])->default('open');
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expected_at'], 'pledges_status_expected_idx');
            $table->index('household_id',           'pledges_household_idx');
            $table->index('source_or_payee',        'pledges_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pledges');
    }
};
