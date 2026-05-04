<?php

namespace App\Http\Controllers;

use App\Models\FinanceTransaction;
use App\Services\FinanceService;
use App\Services\SettingService;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __construct(protected FinanceService $service) {}

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): View
    {
        $kpis   = $this->service->dashboardKpis();
        $trend  = $this->service->monthlyTrend(12);
        $recent = FinanceTransaction::with(['category', 'event'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // Pass default date range so the view can pre-select the right filter
        $defaultDateRange = SettingService::get('finance.default_date_range', 'current_month');

        return view('finance.dashboard', compact('kpis', 'trend', 'recent', 'defaultDateRange'));
    }

    // Phase 7.1 — the old reports() method that rendered a single
    // four-chart Chart.js page has been replaced by FinanceReportController.
    // Its data sources (monthlyTrend / expenseByCategory / incomeBySource /
    // eventFinanceSummary) remain on FinanceService for the live dashboard;
    // the standalone "all-charts-on-one-page" view was retired in favor of
    // the per-report board-grade shell at /finance/reports.
}
