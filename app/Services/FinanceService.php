<?php

namespace App\Services;

use App\Models\FinanceTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    // ─── Dashboard KPIs ───────────────────────────────────────────────────────

    public function dashboardKpis(): array
    {
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd   = Carbon::now()->endOfMonth()->toDateString();

        $totals = FinanceTransaction::where('status', '!=', 'cancelled')
            ->select('transaction_type', DB::raw('SUM(amount) as total'))
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $monthTotals = FinanceTransaction::where('status', '!=', 'cancelled')
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->select('transaction_type', DB::raw('SUM(amount) as total'))
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $topExpenseCategory = FinanceTransaction::expense()
            ->where('status', '!=', 'cancelled')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->with('category:id,name')
            ->first();

        $eventLinkedSpend = (float) FinanceTransaction::expense()
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('event_id')
            ->sum('amount');

        $income   = (float) ($totals['income']  ?? 0);
        $expenses = (float) ($totals['expense'] ?? 0);

        return [
            'total_income'         => $income,
            'total_expenses'       => $expenses,
            'net_balance'          => $income - $expenses,
            'month_income'         => (float) ($monthTotals['income']  ?? 0),
            'month_expenses'       => (float) ($monthTotals['expense'] ?? 0),
            'top_expense_category' => $topExpenseCategory?->category?->name ?? '—',
            'event_linked_spend'   => $eventLinkedSpend,
        ];
    }

    // ─── Monthly Trend (last N months) ────────────────────────────────────────

    public function monthlyTrend(int $months = 12): array
    {
        $start = Carbon::now()->subMonths($months - 1)->startOfMonth();

        $rows = FinanceTransaction::where('status', '!=', 'cancelled')
            ->where('transaction_date', '>=', $start->toDateString())
            ->select(
                DB::raw("DATE_FORMAT(transaction_date, '%Y-%m') as month"),
                'transaction_type',
                DB::raw('SUM(amount) as total'),
            )
            ->groupBy('month', 'transaction_type')
            ->orderBy('month')
            ->get();

        // Build scaffold
        $scaffold = [];
        $cursor   = $start->copy();
        $now      = Carbon::now();
        while ($cursor->lte($now)) {
            $scaffold[$cursor->format('Y-m')] = ['income' => 0, 'expense' => 0];
            $cursor->addMonth();
        }

        foreach ($rows as $row) {
            if (isset($scaffold[$row->month])) {
                $scaffold[$row->month][$row->transaction_type] = (float) $row->total;
            }
        }

        $labels  = [];
        $income  = [];
        $expense = [];

        foreach ($scaffold as $key => $vals) {
            $labels[]  = Carbon::createFromFormat('Y-m', $key)->format('M Y');
            $income[]  = $vals['income'];
            $expense[] = $vals['expense'];
        }

        return compact('labels', 'income', 'expense');
    }

    // ─── Expense by Category ──────────────────────────────────────────────────

    public function expenseByCategory(): array
    {
        $rows = FinanceTransaction::expense()
            ->where('status', '!=', 'cancelled')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category:id,name')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('category.name')->map(fn($n) => $n ?? 'Uncategorized')->all(),
            'totals' => $rows->pluck('total')->map(fn($v) => round((float) $v, 2))->all(),
        ];
    }

    // ─── Income by Source ─────────────────────────────────────────────────────

    public function incomeBySource(): Collection
    {
        return FinanceTransaction::income()
            ->where('status', '!=', 'cancelled')
            ->select('source_or_payee', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('source_or_payee')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
    }

    // ─── Event Finance Summary ────────────────────────────────────────────────

    public function eventFinanceSummary(): Collection
    {
        return FinanceTransaction::whereNotNull('event_id')
            ->where('status', '!=', 'cancelled')
            ->select(
                'event_id',
                DB::raw("SUM(CASE WHEN transaction_type = 'income'  THEN amount ELSE 0 END) as total_income"),
                DB::raw("SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as total_expense"),
                DB::raw('COUNT(*) as transaction_count'),
            )
            ->groupBy('event_id')
            ->with('event:id,name,date')
            ->orderByDesc('total_expense')
            ->limit(10)
            ->get();
    }

    // ─── Single Event KPIs ────────────────────────────────────────────────────

    public function eventKpis(int $eventId): array
    {
        $rows = FinanceTransaction::forEvent($eventId)
            ->where('status', '!=', 'cancelled')
            ->select('transaction_type', DB::raw('SUM(amount) as total'))
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $income   = (float) ($rows['income']  ?? 0);
        $expenses = (float) ($rows['expense'] ?? 0);

        return [
            'income'   => $income,
            'expenses' => $expenses,
            'net'      => $income - $expenses,
        ];
    }
}
