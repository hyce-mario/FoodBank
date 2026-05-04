<?php

namespace App\Http\Controllers;

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
                'live'        => false,
            ],
            [
                'id'          => 'expense_detail',
                'title'       => 'Expense Detail Report',
                'description' => 'Every expense in the period, grouped by category. Filterable by payee + status.',
                'category'    => 'Detail',
                'live'        => false,
            ],
            [
                'id'          => 'general_ledger',
                'title'       => 'General Ledger',
                'description' => 'Chronological list of every transaction. The auditor\'s landing page.',
                'category'    => 'Detail',
                'live'        => false,
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
