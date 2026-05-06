<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\FinanceCategory;
use App\Services\FinanceReportService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

/**
 * Phase 7.1 — Finance Reports controller.
 *
 * Single controller hosting the hub + every individual report. Each
 * report ships with three sibling actions: <name>(), <name>Print(),
 * <name>Pdf(), <name>Csv() so the export trio is co-located with the
 * screen render.
 *
 * Phase 7.1 ships:
 *   • hub() — card-grid landing page listing all 11 reports
 *   • statementOfActivities() + 3 exports — the headline P&L
 *
 * Subsequent phases (7.2–7.4) extend this controller with the other 10
 * reports following the same shape.
 */
class FinanceReportController extends Controller
{
    public function __construct(protected FinanceReportService $service) {}

    /**
     * Reports hub — card grid of all 11 reports. The metadata constant
     * drives both the cards AND any future "search reports" feature, so
     * it lives in one place.
     *
     * Each card shows:
     *   • title + one-line description
     *   • category tag (Statements / Detail / Analysis / Compliance)
     *   • icon
     *   • status badge (Live / Coming Soon — disables the click for unbuilt ones)
     *   • export-format pills (Print · PDF · CSV)
     */
    public function hub(): View
    {
        $reports = $this->reportsCatalog();
        return view('finance.reports.index', compact('reports'));
    }

    /**
     * Catalogue of all 11 reports for the hub. `live` = clickable;
     * `route` is null for not-yet-implemented reports (rendered as
     * disabled cards). Updated as each report ships in 7.1–7.4.
     */
    private function reportsCatalog(): array
    {
        return [
            // ── Phase 7.1 — live ─────────────────────────────────────
            [
                'id'          => 'statement_of_activities',
                'title'       => 'Statement of Activities',
                'description' => 'Period revenue and expenses by category, with change in net assets — the nonprofit P&L.',
                'category'    => 'Statements',
                'live'        => true,
                'route'       => 'finance.reports.statement-of-activities',
                'icon'        => 'document',
            ],
            // ── Phase 7.2 ────────────────────────────────────────────
            [
                'id'          => 'income_detail',
                'title'       => 'Income Detail Report',
                'description' => 'Every income transaction in the period, grouped by category, with subtotals.',
                'category'    => 'Detail',
                'live'        => true,
                'route'       => 'finance.reports.income-detail',
                'icon'        => 'document',
            ],
            [
                'id'          => 'expense_detail',
                'title'       => 'Expense Detail Report',
                'description' => 'Every expense in the period, grouped by category. Filterable by payee + status.',
                'category'    => 'Detail',
                'live'        => true,
                'route'       => 'finance.reports.expense-detail',
                'icon'        => 'document',
            ],
            [
                'id'          => 'general_ledger',
                'title'       => 'General Ledger',
                'description' => 'Chronological list of every transaction. The auditor\'s landing page.',
                'category'    => 'Detail',
                'live'        => true,
                'route'       => 'finance.reports.general-ledger',
                'icon'        => 'document',
            ],
            // ── Phase 7.3 ────────────────────────────────────────────
            [
                'id'          => 'donor_analysis',
                'title'       => 'Donor / Source Analysis',
                'description' => 'Top sources by total contribution, frequency, and average gift size.',
                'category'    => 'Analysis',
                'live'        => true,
                'route'       => 'finance.reports.donor-analysis',
                'icon'        => 'document',
            ],
            [
                'id'          => 'vendor_analysis',
                'title'       => 'Vendor / Payee Analysis',
                'description' => 'Top vendors by total spent. Useful for procurement leverage and audit prep.',
                'category'    => 'Analysis',
                'live'        => true,
                'route'       => 'finance.reports.vendor-analysis',
                'icon'        => 'document',
            ],
            [
                'id'          => 'per_event_pl',
                'title'       => 'Per-Event P&L',
                'description' => 'Income vs expense for a single event, with cost-per-beneficiary computed.',
                'category'    => 'Analysis',
                'live'        => true,
                'route'       => 'finance.reports.per-event-pnl',
                'icon'        => 'document',
            ],
            [
                'id'          => 'category_trend',
                'title'       => 'Category Trend Report',
                'description' => 'Monthly time-series for income and expense categories. Spot growth + variance.',
                'category'    => 'Analysis',
                'live'        => true,
                'route'       => 'finance.reports.category-trend',
                'icon'        => 'document',
            ],
            // ── Phase 7.4 ────────────────────────────────────────────
            [
                'id'          => 'functional_expenses',
                'title'       => 'Statement of Functional Expenses',
                'description' => 'Expenses cross-tabulated by Program / Management / Fundraising. IRS Form 990 prep.',
                'category'    => 'Compliance',
                'live'        => true,
                'route'       => 'finance.reports.functional-expenses',
                'icon'        => 'document',
            ],
            [
                'id'          => 'budget_vs_actual',
                'title'       => 'Budget vs. Actual / Variance',
                'description' => 'Period budget vs. actual by category. Color-coded over/under with % variance.',
                'category'    => 'Compliance',
                'live'        => false,
            ],
            [
                'id'          => 'pledge_aging',
                'title'       => 'Pledge / AR Aging',
                'description' => 'Outstanding pledges aged into Current / 30 / 60 / 90+ day buckets.',
                'category'    => 'Compliance',
                'live'        => false,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Statement of Activities
    // ─────────────────────────────────────────────────────────────────────

    public function statementOfActivities(Request $request): View
    {
        $period = $this->service->resolvePeriod($request);
        $data = $this->service->statementOfActivities(
            $period['from'], $period['to'],
            $period['compare_from'], $period['compare_to'],
        );

        return view('finance.reports.statement-of-activities', [
            'period' => $period,
            'data'   => $data,
        ]);
    }

    public function statementOfActivitiesPrint(Request $request): View
    {
        $period = $this->service->resolvePeriod($request);
        $data = $this->service->statementOfActivities(
            $period['from'], $period['to'],
            $period['compare_from'], $period['compare_to'],
        );
        $branding  = $this->exportBranding();
        $autoPrint = true;

        return view('finance.reports.exports.statement-of-activities-print', compact(
            'period', 'data', 'branding', 'autoPrint',
        ));
    }

    public function statementOfActivitiesPdf(Request $request): Response
    {
        $period = $this->service->resolvePeriod($request);
        $data = $this->service->statementOfActivities(
            $period['from'], $period['to'],
            $period['compare_from'], $period['compare_to'],
        );
        $branding = $this->exportBranding();

        $filename = 'statement-of-activities-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.pdf';
        $payload  = compact('period', 'data', 'branding');

        try {
            return Pdf::loadView('finance.reports.exports.statement-of-activities-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        } catch (\Throwable $e) {
            Log::warning('finance-soa-pdf: dompdf failed with logo embedded; retrying without logo.', [
                'message' => $e->getMessage(),
            ]);
            $payload['branding']['logo_src'] = null;
            return Pdf::loadView('finance.reports.exports.statement-of-activities-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }
    }

    public function statementOfActivitiesCsv(Request $request): StreamedResponse
    {
        $period = $this->service->resolvePeriod($request);
        $data = $this->service->statementOfActivities(
            $period['from'], $period['to'],
            $period['compare_from'], $period['compare_to'],
        );

        $filename = 'statement-of-activities-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($data, $period) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

            // Metadata band so the CSV is self-documenting once the
            // accountant downloads it.
            fputcsv($out, ['Statement of Activities']);
            fputcsv($out, ['Period', $period['label']]);
            if (! empty($period['compare'])) {
                fputcsv($out, ['Compare to', $period['compare']['label']]);
            }
            fputcsv($out, []);

            // Revenue section
            fputcsv($out, ['REVENUE']);
            $hasCompare = ! empty($data['income']['prior_total']);
            $headers = $hasCompare
                ? ['Category', 'Amount', 'Prior Period', 'Δ %']
                : ['Category', 'Amount'];
            fputcsv($out, $headers);
            foreach ($data['income']['categories'] as $row) {
                $line = [$row['name'], number_format($row['amount'], 2, '.', '')];
                if ($hasCompare) {
                    $line[] = number_format($row['prior_amount'] ?? 0, 2, '.', '');
                    $line[] = $row['delta'] !== null ? number_format($row['delta'] * 100, 1) . '%' : '';
                }
                fputcsv($out, $line);
            }
            fputcsv($out, $hasCompare
                ? ['Total Revenue', number_format($data['income']['total'], 2, '.', ''),
                   number_format($data['income']['prior_total'] ?? 0, 2, '.', ''), '']
                : ['Total Revenue', number_format($data['income']['total'], 2, '.', '')]);
            fputcsv($out, []);

            // Expense section
            fputcsv($out, ['EXPENSES']);
            fputcsv($out, $headers);
            foreach ($data['expense']['categories'] as $row) {
                $line = [$row['name'], number_format($row['amount'], 2, '.', '')];
                if ($hasCompare) {
                    $line[] = number_format($row['prior_amount'] ?? 0, 2, '.', '');
                    $line[] = $row['delta'] !== null ? number_format($row['delta'] * 100, 1) . '%' : '';
                }
                fputcsv($out, $line);
            }
            fputcsv($out, $hasCompare
                ? ['Total Expenses', number_format($data['expense']['total'], 2, '.', ''),
                   number_format($data['expense']['prior_total'] ?? 0, 2, '.', ''), '']
                : ['Total Expenses', number_format($data['expense']['total'], 2, '.', '')]);
            fputcsv($out, []);

            // Net change
            fputcsv($out, ['CHANGE IN NET ASSETS', number_format($data['net_change'], 2, '.', '')]);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.2.a — Income Detail Report
    // ─────────────────────────────────────────────────────────────────────

    public function incomeDetail(Request $request): View
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->detailFilters($request);

        $data = $this->service->incomeDetail(
            $period['from'], $period['to'],
            $period['compare_from'], $period['compare_to'],
            $filters,
        );

        // Filter dropdown payloads — Income side shows only income
        // categories so the picker doesn't list expense categories.
        $categories = FinanceCategory::active()->where('type', 'income')->orderBy('name')->get(['id', 'name']);
        $events     = Event::orderByDesc('date')->limit(50)->get(['id', 'name', 'date']);

        return view('finance.reports.income-detail', compact(
            'period', 'data', 'filters', 'categories', 'events',
        ));
    }

    public function incomeDetailPrint(Request $request): View
    {
        $period   = $this->service->resolvePeriod($request);
        $filters  = $this->detailFilters($request);
        $data     = $this->service->incomeDetail($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);
        $branding  = $this->exportBranding();
        $autoPrint = true;

        return view('finance.reports.exports.detail-print', [
            'period'    => $period,
            'data'      => $data,
            'branding'  => $branding,
            'autoPrint' => $autoPrint,
            'reportTitle' => 'Income Detail Report',
            'rowLabel'    => 'Income',
            'sourceLabel' => 'Source',
            'totalLabel'  => 'Total Income',
            'colorClass'  => 'income',
        ]);
    }

    public function incomeDetailPdf(Request $request): Response
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->detailFilters($request);
        $data    = $this->service->incomeDetail($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);
        $branding = $this->exportBranding();

        $filename = 'income-detail-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.pdf';
        $payload  = [
            'period'      => $period,
            'data'        => $data,
            'branding'    => $branding,
            'reportTitle' => 'Income Detail Report',
            'rowLabel'    => 'Income',
            'sourceLabel' => 'Source',
            'totalLabel'  => 'Total Income',
            'colorClass'  => 'income',
        ];

        try {
            return Pdf::loadView('finance.reports.exports.detail-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        } catch (\Throwable $e) {
            Log::warning('finance-income-detail-pdf: dompdf failed; retrying without logo.', ['message' => $e->getMessage()]);
            $payload['branding']['logo_src'] = null;
            return Pdf::loadView('finance.reports.exports.detail-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }
    }

    public function incomeDetailCsv(Request $request): StreamedResponse
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->detailFilters($request);
        $data    = $this->service->incomeDetail($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);

        $filename = 'income-detail-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.csv';
        return $this->detailCsv($filename, 'Income Detail Report', 'Source / Donor', $period, $data);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.2.b — Expense Detail Report
    // ─────────────────────────────────────────────────────────────────────

    public function expenseDetail(Request $request): View
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->detailFilters($request);
        $data    = $this->service->expenseDetail($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);

        $categories = FinanceCategory::active()->where('type', 'expense')->orderBy('name')->get(['id', 'name']);
        $events     = Event::orderByDesc('date')->limit(50)->get(['id', 'name', 'date']);

        return view('finance.reports.expense-detail', compact(
            'period', 'data', 'filters', 'categories', 'events',
        ));
    }

    public function expenseDetailPrint(Request $request): View
    {
        $period   = $this->service->resolvePeriod($request);
        $filters  = $this->detailFilters($request);
        $data     = $this->service->expenseDetail($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);
        $branding  = $this->exportBranding();
        $autoPrint = true;

        return view('finance.reports.exports.detail-print', [
            'period'      => $period,
            'data'        => $data,
            'branding'    => $branding,
            'autoPrint'   => $autoPrint,
            'reportTitle' => 'Expense Detail Report',
            'rowLabel'    => 'Expense',
            'sourceLabel' => 'Payee',
            'totalLabel'  => 'Total Expenses',
            'colorClass'  => 'expense',
        ]);
    }

    public function expenseDetailPdf(Request $request): Response
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->detailFilters($request);
        $data    = $this->service->expenseDetail($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);
        $branding = $this->exportBranding();

        $filename = 'expense-detail-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.pdf';
        $payload = [
            'period'      => $period,
            'data'        => $data,
            'branding'    => $branding,
            'reportTitle' => 'Expense Detail Report',
            'rowLabel'    => 'Expense',
            'sourceLabel' => 'Payee',
            'totalLabel'  => 'Total Expenses',
            'colorClass'  => 'expense',
        ];

        try {
            return Pdf::loadView('finance.reports.exports.detail-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        } catch (\Throwable $e) {
            Log::warning('finance-expense-detail-pdf: dompdf failed; retrying without logo.', ['message' => $e->getMessage()]);
            $payload['branding']['logo_src'] = null;
            return Pdf::loadView('finance.reports.exports.detail-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }
    }

    public function expenseDetailCsv(Request $request): StreamedResponse
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->detailFilters($request);
        $data    = $this->service->expenseDetail($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);

        $filename = 'expense-detail-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.csv';
        return $this->detailCsv($filename, 'Expense Detail Report', 'Payee', $period, $data);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.2.c — General Ledger
    // ─────────────────────────────────────────────────────────────────────

    public function generalLedger(Request $request): View
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->ledgerFilters($request);
        $data    = $this->service->generalLedger($period['from'], $period['to'], $filters);

        $categories = FinanceCategory::active()->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);
        $events     = Event::orderByDesc('date')->limit(50)->get(['id', 'name', 'date']);

        return view('finance.reports.general-ledger', compact(
            'period', 'data', 'filters', 'categories', 'events',
        ));
    }

    public function generalLedgerPrint(Request $request): View
    {
        $period   = $this->service->resolvePeriod($request);
        $filters  = $this->ledgerFilters($request);
        $data     = $this->service->generalLedger($period['from'], $period['to'], $filters);
        $branding  = $this->exportBranding();
        $autoPrint = true;

        return view('finance.reports.exports.general-ledger-print', compact(
            'period', 'data', 'branding', 'autoPrint',
        ));
    }

    public function generalLedgerPdf(Request $request): Response
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->ledgerFilters($request);
        $data    = $this->service->generalLedger($period['from'], $period['to'], $filters);
        $branding = $this->exportBranding();

        $filename = 'general-ledger-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.pdf';
        $payload  = compact('period', 'data', 'branding');

        try {
            return Pdf::loadView('finance.reports.exports.general-ledger-pdf', $payload)
                ->setPaper('a4', 'landscape')
                ->download($filename);
        } catch (\Throwable $e) {
            Log::warning('finance-general-ledger-pdf: dompdf failed; retrying without logo.', ['message' => $e->getMessage()]);
            $payload['branding']['logo_src'] = null;
            return Pdf::loadView('finance.reports.exports.general-ledger-pdf', $payload)
                ->setPaper('a4', 'landscape')
                ->download($filename);
        }
    }

    public function generalLedgerCsv(Request $request): StreamedResponse
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->ledgerFilters($request);
        $data    = $this->service->generalLedger($period['from'], $period['to'], $filters);

        $filename = 'general-ledger-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($period, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($out, ['General Ledger']);
            fputcsv($out, ['Period', $period['label']]);
            fputcsv($out, ['Total Inflow',  number_format($data['total_in'],  2, '.', '')]);
            fputcsv($out, ['Total Outflow', number_format($data['total_out'], 2, '.', '')]);
            fputcsv($out, ['Net Change',    number_format($data['net_change'], 2, '.', '')]);
            fputcsv($out, []);

            fputcsv($out, [
                'Date', 'Type', 'Title', 'Source / Payee', 'Category',
                'Reference', 'Event', 'Status', 'Amount', 'Running Balance',
            ]);
            foreach ($data['rows'] as $r) {
                fputcsv($out, [
                    $r['date'],
                    ucfirst($r['type']),
                    $r['title'],
                    $r['source'],
                    $r['category'],
                    $r['reference'],
                    $r['event'],
                    ucfirst((string) $r['status']),
                    // Income positive, expense negative — auditor-friendly
                    ($r['type'] === 'expense' ? '-' : '') . number_format($r['amount'], 2, '.', ''),
                    $r['running_balance'] !== null ? number_format($r['running_balance'], 2, '.', '') : '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.3.a — Donor / Source Analysis
    // ─────────────────────────────────────────────────────────────────────

    public function donorAnalysis(Request $request): View
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->stakeholderFilters($request);
        $data    = $this->service->donorAnalysis(
            $period['from'], $period['to'],
            $period['compare_from'], $period['compare_to'],
            $filters,
        );

        $categories = FinanceCategory::active()->where('type', 'income')->orderBy('name')->get(['id', 'name']);

        return view('finance.reports.analysis', [
            'period'      => $period,
            'data'        => $data,
            'filters'     => $filters,
            'categories'  => $categories,
            'reportTitle' => 'Donor / Source Analysis',
            'entityLabel' => 'donor',
            'entityLabelPlural' => 'donors',
            'totalLabel'  => 'Total Raised',
            'sourceLabel' => 'Source / Donor',
            'colorClass'  => 'income',
            'exportRoutes' => [
                'print' => 'finance.reports.donor-analysis.print',
                'pdf'   => 'finance.reports.donor-analysis.pdf',
                'csv'   => 'finance.reports.donor-analysis.csv',
            ],
        ]);
    }

    public function donorAnalysisPrint(Request $request): View
    {
        return $this->renderAnalysisPrint($request, 'income', 'Donor / Source Analysis', 'donor', 'donors', 'Total Raised', 'Source / Donor', 'income');
    }

    public function donorAnalysisPdf(Request $request): Response
    {
        return $this->renderAnalysisPdf($request, 'income', 'Donor / Source Analysis', 'donor', 'donors', 'Total Raised', 'Source / Donor', 'income', 'donor-analysis');
    }

    public function donorAnalysisCsv(Request $request): StreamedResponse
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->stakeholderFilters($request);
        $data    = $this->service->donorAnalysis($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);

        $filename = 'donor-analysis-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.csv';
        return $this->stakeholderCsv($filename, 'Donor / Source Analysis', 'Donor', 'income', $period, $data);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.3.b — Vendor / Payee Analysis
    // ─────────────────────────────────────────────────────────────────────

    public function vendorAnalysis(Request $request): View
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->stakeholderFilters($request);
        $data    = $this->service->vendorAnalysis(
            $period['from'], $period['to'],
            $period['compare_from'], $period['compare_to'],
            $filters,
        );

        $categories = FinanceCategory::active()->where('type', 'expense')->orderBy('name')->get(['id', 'name']);

        return view('finance.reports.analysis', [
            'period'      => $period,
            'data'        => $data,
            'filters'     => $filters,
            'categories'  => $categories,
            'reportTitle' => 'Vendor / Payee Analysis',
            'entityLabel' => 'vendor',
            'entityLabelPlural' => 'vendors',
            'totalLabel'  => 'Total Spent',
            'sourceLabel' => 'Vendor / Payee',
            'colorClass'  => 'expense',
            'exportRoutes' => [
                'print' => 'finance.reports.vendor-analysis.print',
                'pdf'   => 'finance.reports.vendor-analysis.pdf',
                'csv'   => 'finance.reports.vendor-analysis.csv',
            ],
        ]);
    }

    public function vendorAnalysisPrint(Request $request): View
    {
        return $this->renderAnalysisPrint($request, 'expense', 'Vendor / Payee Analysis', 'vendor', 'vendors', 'Total Spent', 'Vendor / Payee', 'expense');
    }

    public function vendorAnalysisPdf(Request $request): Response
    {
        return $this->renderAnalysisPdf($request, 'expense', 'Vendor / Payee Analysis', 'vendor', 'vendors', 'Total Spent', 'Vendor / Payee', 'expense', 'vendor-analysis');
    }

    public function vendorAnalysisCsv(Request $request): StreamedResponse
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->stakeholderFilters($request);
        $data    = $this->service->vendorAnalysis($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);

        $filename = 'vendor-analysis-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.csv';
        return $this->stakeholderCsv($filename, 'Vendor / Payee Analysis', 'Vendor', 'expense', $period, $data);
    }

    /**
     * Shared print-export pipeline for Donor + Vendor analysis. Both
     * reports render through the same Blade — only the labelling
     * differs. Centralising avoids drift between the two.
     */
    private function renderAnalysisPrint(Request $request, string $type, string $title, string $entity, string $entityPlural, string $totalLabel, string $sourceLabel, string $colorClass): View
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->stakeholderFilters($request);
        $data    = $type === 'income'
            ? $this->service->donorAnalysis($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters)
            : $this->service->vendorAnalysis($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);

        return view('finance.reports.exports.analysis-print', [
            'period'            => $period,
            'data'              => $data,
            'branding'          => $this->exportBranding(),
            'autoPrint'         => true,
            'reportTitle'       => $title,
            'entityLabel'       => $entity,
            'entityLabelPlural' => $entityPlural,
            'totalLabel'        => $totalLabel,
            'sourceLabel'       => $sourceLabel,
            'colorClass'        => $colorClass,
        ]);
    }

    private function renderAnalysisPdf(Request $request, string $type, string $title, string $entity, string $entityPlural, string $totalLabel, string $sourceLabel, string $colorClass, string $filenameStem): Response
    {
        $period  = $this->service->resolvePeriod($request);
        $filters = $this->stakeholderFilters($request);
        $data    = $type === 'income'
            ? $this->service->donorAnalysis($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters)
            : $this->service->vendorAnalysis($period['from'], $period['to'], $period['compare_from'], $period['compare_to'], $filters);

        $filename = $filenameStem . '-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.pdf';
        $payload  = [
            'period'            => $period,
            'data'              => $data,
            'branding'          => $this->exportBranding(),
            'reportTitle'       => $title,
            'entityLabel'       => $entity,
            'entityLabelPlural' => $entityPlural,
            'totalLabel'        => $totalLabel,
            'sourceLabel'       => $sourceLabel,
            'colorClass'        => $colorClass,
        ];

        try {
            return Pdf::loadView('finance.reports.exports.analysis-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        } catch (\Throwable $e) {
            Log::warning('finance-' . $filenameStem . '-pdf: dompdf failed; retrying without logo.', ['message' => $e->getMessage()]);
            $payload['branding']['logo_src'] = null;
            return Pdf::loadView('finance.reports.exports.analysis-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }
    }

    /**
     * Shared CSV writer for Donor + Vendor analysis. Two sections:
     * top-line summary (totals + retention), then every donor / vendor
     * with totals, count, average, first/last activity, prior-period
     * comparison. UTF-8 BOM so Excel opens it cleanly.
     */
    private function stakeholderCsv(string $filename, string $title, string $entityLabel, string $type, array $period, array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($title, $entityLabel, $type, $period, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($out, [$title]);
            fputcsv($out, ['Period', $period['label']]);
            if (! empty($period['compare'])) {
                fputcsv($out, ['Compare to', $period['compare']['label']]);
            }
            fputcsv($out, ['Total ' . ($type === 'income' ? 'Raised' : 'Spent'), number_format($data['total'], 2, '.', '')]);
            fputcsv($out, ['Unique ' . strtolower($entityLabel) . 's', $data['donor_total_count']]);
            fputcsv($out, ['Total ' . ($type === 'income' ? 'gifts' : 'payments'), $data['gift_count']]);
            fputcsv($out, ['Average ' . ($type === 'income' ? 'gift' : 'payment'), number_format($data['avg_gift'], 2, '.', '')]);
            if ($data['retention_rate'] !== null) {
                fputcsv($out, ['Retention rate', number_format($data['retention_rate'] * 100, 1) . '%']);
            }
            fputcsv($out, []);

            // Every donor / vendor — CSV doesn't get the top-10 cap.
            $hasCompare = ! empty($period['compare']);
            $hdr = $hasCompare
                ? [$entityLabel, 'Total', 'Count', 'Average', 'First', 'Last', 'Prior Period', 'Δ %']
                : [$entityLabel, 'Total', 'Count', 'Average', 'First', 'Last'];
            fputcsv($out, [$type === 'income' ? 'CONTRIBUTORS' : 'PAYEES']);
            fputcsv($out, $hdr);
            foreach ($data['all_donors'] as $row) {
                $line = [
                    $row['name'],
                    number_format($row['total'], 2, '.', ''),
                    $row['count'],
                    number_format($row['avg_gift'], 2, '.', ''),
                    $row['first_gift'] ?? '',
                    $row['last_gift'] ?? '',
                ];
                if ($hasCompare) {
                    $line[] = number_format($row['prior_total'] ?? 0, 2, '.', '');
                    $line[] = $row['delta'] !== null ? number_format($row['delta'] * 100, 1) . '%' : ($row['is_new'] ? 'NEW' : '');
                }
                fputcsv($out, $line);
            }

            // Lapsed (compare only) — separate section so it's clear these
            // are people who gave previously but not in the current period.
            if ($hasCompare && ! empty($data['lapsed'])) {
                fputcsv($out, []);
                fputcsv($out, ['LAPSED ' . strtoupper($entityLabel) . 'S (gave in prior period, not current)']);
                fputcsv($out, [$entityLabel, 'Prior Period Total']);
                foreach ($data['lapsed'] as $l) {
                    fputcsv($out, [$l['name'], number_format($l['prior_total'], 2, '.', '')]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.3.d — Category Trend Report
    // ─────────────────────────────────────────────────────────────────────

    public function categoryTrend(Request $request): View
    {
        // Default period: last 12 months (trend reports need range)
        if (! $request->has('period') && ! $request->has('from')) {
            $request->merge(['period' => 'last_12_months']);
        }
        $period    = $this->service->resolvePeriod($request);
        $direction = $request->get('direction', 'income');
        $data      = $this->service->categoryTrend($period['from'], $period['to'], $direction);

        return view('finance.reports.category-trend', [
            'period' => $period,
            'data'   => $data,
        ]);
    }

    public function categoryTrendPrint(Request $request): View
    {
        if (! $request->has('period') && ! $request->has('from')) {
            $request->merge(['period' => 'last_12_months']);
        }
        $period    = $this->service->resolvePeriod($request);
        $direction = $request->get('direction', 'income');
        $data      = $this->service->categoryTrend($period['from'], $period['to'], $direction);

        return view('finance.reports.exports.category-trend-print', [
            'period'    => $period,
            'data'      => $data,
            'branding'  => $this->exportBranding(),
            'autoPrint' => true,
        ]);
    }

    public function categoryTrendPdf(Request $request): Response
    {
        if (! $request->has('period') && ! $request->has('from')) {
            $request->merge(['period' => 'last_12_months']);
        }
        $period    = $this->service->resolvePeriod($request);
        $direction = $request->get('direction', 'income');
        $data      = $this->service->categoryTrend($period['from'], $period['to'], $direction);

        $filename = 'category-trend-' . $direction . '-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.pdf';
        $payload  = [
            'period'   => $period,
            'data'     => $data,
            'branding' => $this->exportBranding(),
        ];

        try {
            return Pdf::loadView('finance.reports.exports.category-trend-pdf', $payload)
                ->setPaper('a4', 'landscape')
                ->download($filename);
        } catch (\Throwable $e) {
            Log::warning('finance-category-trend-pdf: dompdf failed; retrying without logo.', ['message' => $e->getMessage()]);
            $payload['branding']['logo_src'] = null;
            return Pdf::loadView('finance.reports.exports.category-trend-pdf', $payload)
                ->setPaper('a4', 'landscape')
                ->download($filename);
        }
    }

    public function categoryTrendCsv(Request $request): StreamedResponse
    {
        if (! $request->has('period') && ! $request->has('from')) {
            $request->merge(['period' => 'last_12_months']);
        }
        $period    = $this->service->resolvePeriod($request);
        $direction = $request->get('direction', 'income');
        $data      = $this->service->categoryTrend($period['from'], $period['to'], $direction);

        $filename = 'category-trend-' . $direction . '-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($data, $period, $direction) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Category Trend Report']);
            fputcsv($out, ['Period',    $period['label']]);
            fputcsv($out, ['Direction', ucfirst($direction)]);
            fputcsv($out, ['Total',     number_format($data['totals']['period'], 2, '.', '')]);
            fputcsv($out, []);

            // Wide format: months as columns, categories as rows
            $header = ['Category', 'Type'];
            foreach ($data['month_labels'] as $label) $header[] = $label;
            $header[] = 'Total';
            $header[] = 'Δ first→last';
            fputcsv($out, $header);

            foreach ($data['series'] as $s) {
                $row = [$s['name'], ucfirst((string) $s['type'])];
                foreach ($s['monthly'] as $v) {
                    $row[] = number_format($v, 2, '.', '');
                }
                $row[] = number_format($s['total'], 2, '.', '');
                $row[] = $s['delta'] !== null ? number_format($s['delta'] * 100, 1) . '%' : '';
                fputcsv($out, $row);
            }

            // Footer total row
            $totalRow = ['TOTAL', ''];
            foreach ($data['totals']['months'] as $v) {
                $totalRow[] = number_format($v, 2, '.', '');
            }
            $totalRow[] = number_format($data['totals']['period'], 2, '.', '');
            $totalRow[] = '';
            fputcsv($out, $totalRow);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.4.a — Statement of Functional Expenses
    // ─────────────────────────────────────────────────────────────────────

    public function functionalExpenses(Request $request): View
    {
        $period = $this->service->resolvePeriod($request);
        $data   = $this->service->functionalExpenses($period['from'], $period['to'], $period['compare_from'], $period['compare_to']);

        return view('finance.reports.functional-expenses', compact('period', 'data'));
    }

    public function functionalExpensesPrint(Request $request): View
    {
        $period   = $this->service->resolvePeriod($request);
        $data     = $this->service->functionalExpenses($period['from'], $period['to'], $period['compare_from'], $period['compare_to']);
        $branding = $this->exportBranding();
        $autoPrint = true;

        return view('finance.reports.exports.functional-expenses-print', compact('period', 'data', 'branding', 'autoPrint'));
    }

    public function functionalExpensesPdf(Request $request): Response
    {
        $period   = $this->service->resolvePeriod($request);
        $data     = $this->service->functionalExpenses($period['from'], $period['to'], $period['compare_from'], $period['compare_to']);
        $branding = $this->exportBranding();

        $filename = 'functional-expenses-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.pdf';
        $payload  = compact('period', 'data', 'branding');

        try {
            return Pdf::loadView('finance.reports.exports.functional-expenses-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        } catch (\Throwable $e) {
            Log::warning('finance-functional-expenses-pdf: dompdf failed; retrying without logo.', ['message' => $e->getMessage()]);
            $payload['branding']['logo_src'] = null;
            return Pdf::loadView('finance.reports.exports.functional-expenses-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }
    }

    public function functionalExpensesCsv(Request $request): StreamedResponse
    {
        $period = $this->service->resolvePeriod($request);
        $data   = $this->service->functionalExpenses($period['from'], $period['to'], $period['compare_from'], $period['compare_to']);

        $filename = 'functional-expenses-' . $period['from']->format('Ymd') . '-' . $period['to']->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($data, $period) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel friendliness
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Statement of Functional Expenses']);
            fputcsv($out, ['Period',         $period['label']]);
            fputcsv($out, ['Total Expenses', number_format($data['total'], 2, '.', '')]);
            fputcsv($out, ['Program Ratio',  number_format($data['program_ratio'] * 100, 1) . '%']);
            if ($data['compare']) {
                fputcsv($out, ['Compare Period', $data['compare']['label']]);
                fputcsv($out, ['Prior Total',    number_format($data['prior_total'] ?? 0, 2, '.', '')]);
                fputcsv($out, ['Prior Program Ratio',
                    $data['prior_program_ratio'] !== null
                        ? number_format($data['prior_program_ratio'] * 100, 1) . '%'
                        : '—']);
            }
            fputcsv($out, []);

            $header = ['Function', 'Category', 'Amount', 'Share of Function', 'Share of Total'];
            if ($data['compare']) {
                $header[] = 'Prior Function Total';
                $header[] = 'Δ vs Prior';
            }
            fputcsv($out, $header);

            foreach ($data['by_function'] as $f) {
                if (empty($f['categories'])) {
                    $row = [$f['label'], '(no categories)', '0.00', '0.0%', '0.0%'];
                    if ($data['compare']) {
                        $row[] = number_format($f['prior_total'] ?? 0, 2, '.', '');
                        $row[] = '';
                    }
                    fputcsv($out, $row);
                    continue;
                }

                foreach ($f['categories'] as $c) {
                    $row = [
                        $f['label'],
                        $c['name'],
                        number_format($c['amount'], 2, '.', ''),
                        number_format($c['share'] * 100, 1) . '%',
                        $data['total'] > 0
                            ? number_format(($c['amount'] / $data['total']) * 100, 1) . '%'
                            : '0.0%',
                    ];
                    if ($data['compare']) {
                        $row[] = number_format($f['prior_total'] ?? 0, 2, '.', '');
                        $row[] = isset($f['delta']) && $f['delta'] !== null
                            ? number_format($f['delta'] * 100, 1) . '%'
                            : '';
                    }
                    fputcsv($out, $row);
                }

                $subtotalRow = [
                    $f['label'] . ' SUBTOTAL', '',
                    number_format($f['total'], 2, '.', ''),
                    '100.0%',
                    number_format($f['share'] * 100, 1) . '%',
                ];
                if ($data['compare']) {
                    $subtotalRow[] = number_format($f['prior_total'] ?? 0, 2, '.', '');
                    $subtotalRow[] = isset($f['delta']) && $f['delta'] !== null
                        ? number_format($f['delta'] * 100, 1) . '%'
                        : '';
                }
                fputcsv($out, $subtotalRow);
            }

            $grandRow = ['GRAND TOTAL', '', number_format($data['total'], 2, '.', ''), '', '100.0%'];
            if ($data['compare']) {
                $grandRow[] = number_format($data['prior_total'] ?? 0, 2, '.', '');
                $grandRow[] = '';
            }
            fputcsv($out, $grandRow);

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.3.c — Per-Event P&L
    // ─────────────────────────────────────────────────────────────────────

    public function perEventPnl(Request $request): View
    {
        $eventId = $request->integer('event_id') ?: null;

        // Picker: every event ordered newest-first. We could limit to those
        // with finance activity, but auditors sometimes want to see "this
        // event has zero entries" as a finding.
        $events = Event::orderByDesc('date')->get(['id', 'name', 'date', 'status']);

        $data = $eventId ? $this->service->perEventPnl($eventId) : null;

        return view('finance.reports.per-event-pnl', [
            'events'  => $events,
            'eventId' => $eventId,
            'data'    => $data,
        ]);
    }

    public function perEventPnlPrint(Request $request): View
    {
        $eventId = $request->integer('event_id') ?: abort(400, 'event_id is required');
        $data    = $this->service->perEventPnl($eventId);

        return view('finance.reports.exports.per-event-pnl-print', [
            'data'      => $data,
            'branding'  => $this->exportBranding(),
            'autoPrint' => true,
        ]);
    }

    public function perEventPnlPdf(Request $request): Response
    {
        $eventId = $request->integer('event_id') ?: abort(400, 'event_id is required');
        $data    = $this->service->perEventPnl($eventId);

        $filename = 'event-pnl-' . $eventId . '-' . ($data['event']['date'] ?? 'undated') . '.pdf';
        $payload  = [
            'data'     => $data,
            'branding' => $this->exportBranding(),
        ];

        try {
            return Pdf::loadView('finance.reports.exports.per-event-pnl-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        } catch (\Throwable $e) {
            Log::warning('finance-per-event-pnl-pdf: dompdf failed; retrying without logo.', ['message' => $e->getMessage()]);
            $payload['branding']['logo_src'] = null;
            return Pdf::loadView('finance.reports.exports.per-event-pnl-pdf', $payload)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }
    }

    public function perEventPnlCsv(Request $request): StreamedResponse
    {
        $eventId = $request->integer('event_id') ?: abort(400, 'event_id is required');
        $data    = $this->service->perEventPnl($eventId);

        $filename = 'event-pnl-' . $eventId . '-' . ($data['event']['date'] ?? 'undated') . '.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Per-Event P&L']);
            fputcsv($out, ['Event', $data['event']['name']]);
            fputcsv($out, ['Date',  $data['event']['date'] ?? '']);
            fputcsv($out, ['Status', ucfirst((string) $data['event']['status'])]);
            fputcsv($out, []);

            fputcsv($out, ['SUMMARY']);
            fputcsv($out, ['Total Income',  number_format($data['income']['total'],  2, '.', '')]);
            fputcsv($out, ['Total Expense', number_format($data['expense']['total'], 2, '.', '')]);
            fputcsv($out, ['Net',           number_format($data['net'],              2, '.', '')]);
            fputcsv($out, ['Households served', $data['households_served']]);
            fputcsv($out, ['People served',     $data['people_served']]);
            if ($data['cost_per_household'] !== null) {
                fputcsv($out, ['Cost per household', number_format($data['cost_per_household'], 2, '.', '')]);
            }
            if ($data['cost_per_person'] !== null) {
                fputcsv($out, ['Cost per person', number_format($data['cost_per_person'], 2, '.', '')]);
            }
            fputcsv($out, []);

            fputcsv($out, ['INCOME BY CATEGORY']);
            fputcsv($out, ['Category', 'Amount', 'Share']);
            foreach ($data['income']['categories'] as $cat) {
                fputcsv($out, [$cat['name'], number_format($cat['amount'], 2, '.', ''), number_format($cat['share'] * 100, 1) . '%']);
            }
            fputcsv($out, []);

            fputcsv($out, ['EXPENSE BY CATEGORY']);
            fputcsv($out, ['Category', 'Amount', 'Share']);
            foreach ($data['expense']['categories'] as $cat) {
                fputcsv($out, [$cat['name'], number_format($cat['amount'], 2, '.', ''), number_format($cat['share'] * 100, 1) . '%']);
            }
            fputcsv($out, []);

            fputcsv($out, ['ALL TRANSACTIONS']);
            fputcsv($out, ['Date', 'Type', 'Title', 'Source / Payee', 'Category', 'Amount']);
            foreach ($data['rows'] as $r) {
                fputcsv($out, [
                    $r['date'],
                    ucfirst($r['type']),
                    $r['title'],
                    $r['source'],
                    $r['category'],
                    ($r['type'] === 'expense' ? '-' : '') . number_format($r['amount'], 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Filter set for Donor + Vendor analysis. Narrower than the detail
     * report filters — there's no event filter (donor analysis is
     * donor-centric, not event-centric) and status is always completed.
     */
    private function stakeholderFilters(Request $request): array
    {
        $f = [];
        if ($v = $request->get('category_id')) $f['category_id'] = (int) $v;
        if ($v = $request->get('source'))      $f['source']      = trim((string) $v);
        return $f;
    }

    private function ledgerFilters(Request $request): array
    {
        $f = [];
        if ($v = $request->get('type'))        $f['type']        = $v;
        if ($v = $request->get('category_id')) $f['category_id'] = (int) $v;
        if ($v = $request->get('event_id'))    $f['event_id']    = (int) $v;
        if ($v = $request->get('source'))      $f['source']      = trim((string) $v);
        if ($v = $request->get('status'))      $f['status']      = $v;
        return $f;
    }

    /**
     * Read the detail-report filter set from the request. Validates +
     * narrows down to the keys FinanceReportService::detailReport
     * accepts — anything else is dropped.
     */
    private function detailFilters(Request $request): array
    {
        $f = [];
        if ($v = $request->get('category_id')) $f['category_id'] = (int) $v;
        if ($v = $request->get('event_id'))    $f['event_id']    = (int) $v;
        if ($v = $request->get('source'))      $f['source']      = trim((string) $v);
        if ($v = $request->get('status'))      $f['status']      = $v;
        return $f;
    }

    /**
     * Shared CSV writer for Income Detail + Expense Detail (and any
     * future detail report). Two sections: row-level transactions, then
     * the category rollup. UTF-8 BOM, accountant-friendly formatting.
     */
    private function detailCsv(string $filename, string $title, string $sourceLabel, array $period, array $data): StreamedResponse
    {
        return response()->streamDownload(function () use ($title, $sourceLabel, $period, $data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

            fputcsv($out, [$title]);
            fputcsv($out, ['Period', $period['label']]);
            if (! empty($period['compare'])) {
                fputcsv($out, ['Compare to', $period['compare']['label']]);
            }
            fputcsv($out, ['Total', number_format($data['total'], 2, '.', '')]);
            fputcsv($out, ['Transaction count', $data['count']]);
            fputcsv($out, []);

            // Row-level transactions
            fputcsv($out, ['TRANSACTIONS']);
            fputcsv($out, ['Date', 'Title', $sourceLabel, 'Category', 'Amount', 'Status', 'Event']);
            foreach ($data['rows'] as $r) {
                fputcsv($out, [
                    $r['date'], $r['title'], $r['source'], $r['category'],
                    number_format($r['amount'], 2, '.', ''),
                    ucfirst((string) $r['status']),
                    $r['event'],
                ]);
            }
            fputcsv($out, []);

            // Category rollup
            fputcsv($out, ['BY CATEGORY']);
            $hasCompare = ! empty($period['compare']);
            $hdr = $hasCompare
                ? ['Category', 'Amount', 'Count', 'Prior Period', 'Δ %']
                : ['Category', 'Amount', 'Count'];
            fputcsv($out, $hdr);
            foreach ($data['by_category'] as $row) {
                $line = [
                    $row['name'],
                    number_format($row['amount'], 2, '.', ''),
                    $row['count'],
                ];
                if ($hasCompare) {
                    $line[] = number_format($row['prior_amount'] ?? 0, 2, '.', '');
                    $line[] = $row['delta'] !== null ? number_format($row['delta'] * 100, 1) . '%' : '';
                }
                fputcsv($out, $line);
            }
            fputcsv($out, $hasCompare
                ? ['TOTAL', number_format($data['total'], 2, '.', ''), $data['count'],
                   number_format($data['prior_total'] ?? 0, 2, '.', ''), '']
                : ['TOTAL', number_format($data['total'], 2, '.', ''), $data['count']]);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Branding payload reused by every export — same shape used by
     * VolunteerController + HouseholdController + FinanceTransactionController.
     */
    private function exportBranding(): array
    {
        return [
            'logo_src' => SettingService::brandingLogoDataUri(),
            'app_name' => (string) SettingService::get('general.app_name', config('app.name')),
        ];
    }
}
