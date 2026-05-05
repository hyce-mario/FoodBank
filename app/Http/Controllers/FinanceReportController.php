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
                'live'        => false,
            ],
            [
                'id'          => 'vendor_analysis',
                'title'       => 'Vendor / Payee Analysis',
                'description' => 'Top vendors by total spent. Useful for procurement leverage and audit prep.',
                'category'    => 'Analysis',
                'live'        => false,
            ],
            [
                'id'          => 'per_event_pl',
                'title'       => 'Per-Event P&L',
                'description' => 'Income vs expense for a single event, with cost-per-beneficiary computed.',
                'category'    => 'Analysis',
                'live'        => false,
            ],
            [
                'id'          => 'category_trend',
                'title'       => 'Category Trend Report',
                'description' => 'Monthly time-series for income and expense categories. Spot growth + variance.',
                'category'    => 'Analysis',
                'live'        => false,
            ],
            // ── Phase 7.4 (needs schema) ─────────────────────────────
            [
                'id'          => 'functional_expenses',
                'title'       => 'Statement of Functional Expenses',
                'description' => 'Expenses cross-tabulated by Program / Management / Fundraising. IRS Form 990 prep.',
                'category'    => 'Compliance',
                'live'        => false,
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
