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

    // ─── Reports ──────────────────────────────────────────────────────────────

    public function reports(): View
    {
        $monthlyTrend      = $this->service->monthlyTrend(12);
        $expenseByCategory = $this->service->expenseByCategory();
        $incomeBySource    = $this->service->incomeBySource();
        $eventSummary      = $this->service->eventFinanceSummary();

        return view('finance.reports', compact(
            'monthlyTrend', 'expenseByCategory', 'incomeBySource', 'eventSummary'
        ));
    }
}
