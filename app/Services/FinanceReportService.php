<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\FinanceTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Phase 7.1 — Finance Reports service.
 *
 * Sibling of (not replacement for) FinanceService. FinanceService backs
 * the live dashboard + small operational charts; this service backs the
 * board-grade reporting suite — Statement of Activities, Income/Expense
 * detail, Donor/Vendor analysis, Per-Event P&L, etc.
 *
 * Phase 7.1 ships only the period-resolution helper + Statement of
 * Activities; subsequent phases (7.2–7.4) extend this class with
 * additional report methods.
 *
 * Design notes:
 *   • Every report method returns a plain-array payload (no Eloquent
 *     collections) so the Blade views are dumb-renderers and the print/
 *     PDF/CSV exports can re-consume the same payload without re-
 *     querying. Re-querying would let screen + export drift.
 *   • Period resolution is centralised here (resolvePeriod) so the URL
 *     query-string contract stays consistent across all 11 reports.
 */
class FinanceReportService
{
    /**
     * Universal preset → Carbon range resolver. Returns:
     *   [
     *     'from'         => Carbon (start of range, 00:00:00)
     *     'to'           => Carbon (end of range, 23:59:59)
     *     'compare_from' => ?Carbon (start of prior period when compare flag set)
     *     'compare_to'   => ?Carbon (end of prior period)
     *     'preset'       => string  (echoed back for the period filter UI)
     *     'label'        => string  ("April 2026" / "Apr 1 – Apr 30, 2026")
     *   ]
     *
     * Presets:
     *   this_month      — 1st of current month → today (or end of month, whichever later)
     *   last_month      — full prior calendar month
     *   this_quarter    — calendar quarter containing today
     *   last_quarter    — prior calendar quarter
     *   ytd             — Jan 1 → today
     *   last_year       — full prior calendar year
     *   last_12_months  — today − 12 months → today
     *   custom          — explicit ?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     * Defaults to this_month when preset is missing, invalid, or custom
     * with malformed dates. Compare-prior is computed by subtracting the
     * range duration from the start; for calendar presets (last_month,
     * last_quarter, last_year), the prior period is the equivalent
     * calendar block before so labels read naturally.
     */
    public function resolvePeriod(Request $request): array
    {
        $preset  = $request->get('period', 'this_month');
        $compare = $request->boolean('compare') || $request->get('compare') === 'prior';

        [$from, $to, $resolvedPreset] = $this->rangeForPreset($preset, $request);

        [$compareFrom, $compareTo] = $compare
            ? $this->priorPeriodFor($resolvedPreset, $from, $to)
            : [null, null];

        return [
            'from'         => $from,
            'to'           => $to,
            'compare_from' => $compareFrom,
            'compare_to'   => $compareTo,
            'preset'       => $resolvedPreset,
            'label'        => $this->labelFor($from, $to),
            // Mirror of statementOfActivities()'s `compare` shape so the
            // common report shell + every report view can read
            // $period['compare']['label'] without poking at $data.
            'compare'      => $compareFrom
                ? ['label' => $this->labelFor($compareFrom, $compareTo), 'from' => $compareFrom, 'to' => $compareTo]
                : null,
        ];
    }

    /**
     * Decode a preset string into a [from, to, resolved-preset] tuple.
     * Falls back to this_month if anything goes wrong (invalid preset,
     * malformed custom dates).
     */
    private function rangeForPreset(string $preset, Request $request): array
    {
        $today = Carbon::today();

        return match ($preset) {
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth(),
                'last_month',
            ],
            'this_quarter' => [
                $today->copy()->startOfQuarter(),
                $today->copy()->endOfQuarter(),
                'this_quarter',
            ],
            'last_quarter' => [
                $today->copy()->subQuarterNoOverflow()->startOfQuarter(),
                $today->copy()->subQuarterNoOverflow()->endOfQuarter(),
                'last_quarter',
            ],
            'ytd' => [
                $today->copy()->startOfYear(),
                $today->copy()->endOfDay(),
                'ytd',
            ],
            'last_year' => [
                $today->copy()->subYearNoOverflow()->startOfYear(),
                $today->copy()->subYearNoOverflow()->endOfYear(),
                'last_year',
            ],
            'last_12_months' => [
                $today->copy()->subMonthsNoOverflow(12)->startOfDay(),
                $today->copy()->endOfDay(),
                'last_12_months',
            ],
            'custom' => $this->resolveCustomRange($request),
            default => [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth(),
                'this_month',
            ],
        };
    }

    private function resolveCustomRange(Request $request): array
    {
        try {
            $from = Carbon::parse($request->get('from'))->startOfDay();
            $to   = Carbon::parse($request->get('to'))->endOfDay();
            // Reverse if user passed them backwards
            if ($from->greaterThan($to)) {
                [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
            }
            return [$from, $to, 'custom'];
        } catch (\Throwable) {
            $today = Carbon::today();
            return [
                $today->copy()->startOfMonth(),
                $today->copy()->endOfMonth(),
                'this_month',
            ];
        }
    }

    /**
     * Compute the "prior period" for compare=prior. For calendar presets
     * we step back one calendar block; for custom or rolling presets we
     * subtract the range duration from the start so the comparison is
     * an equally-sized window immediately before.
     */
    private function priorPeriodFor(string $preset, Carbon $from, Carbon $to): array
    {
        return match ($preset) {
            'this_month'   => [$from->copy()->subMonthNoOverflow()->startOfMonth(),
                              $from->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_month'   => [$from->copy()->subMonthNoOverflow()->startOfMonth(),
                              $from->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$from->copy()->subQuarterNoOverflow()->startOfQuarter(),
                              $from->copy()->subQuarterNoOverflow()->endOfQuarter()],
            'last_quarter' => [$from->copy()->subQuarterNoOverflow()->startOfQuarter(),
                              $from->copy()->subQuarterNoOverflow()->endOfQuarter()],
            'ytd'          => [$from->copy()->subYearNoOverflow()->startOfYear(),
                              $from->copy()->subYearNoOverflow()->endOfDay()],
            'last_year'    => [$from->copy()->subYearNoOverflow()->startOfYear(),
                              $from->copy()->subYearNoOverflow()->endOfYear()],
            // For rolling / custom: step back by the range duration. Use
            // date-only Carbon copies for diffInDays so the time component
            // (start-of-day vs end-of-day) doesn't bias the day count
            // across Carbon major-version differences.
            default => (function () use ($from, $to) {
                $rangeDays   = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
                $compareTo   = $from->copy()->subDay()->endOfDay();
                $compareFrom = $compareTo->copy()->subDays($rangeDays - 1)->startOfDay();
                return [$compareFrom, $compareTo];
            })(),
        };
    }

    /**
     * Human-readable label for the resolved range. "April 2026" when the
     * range is exactly one calendar month; "Apr 1 – Apr 30, 2026"
     * otherwise; "Apr 1, 2026 – Mar 31, 2027" when the range spans years.
     */
    private function labelFor(Carbon $from, Carbon $to): string
    {
        // Whole calendar month
        if ($from->isSameMonth($to) && $from->day === 1 && $to->day === $to->copy()->endOfMonth()->day) {
            return $from->format('F Y');
        }
        // Whole calendar year
        if ($from->month === 1 && $from->day === 1 && $to->month === 12 && $to->day === 31 && $from->year === $to->year) {
            return $from->format('Y');
        }
        // Same year — collapse the year suffix
        if ($from->year === $to->year) {
            return $from->format('M j') . ' – ' . $to->format('M j, Y');
        }
        return $from->format('M j, Y') . ' – ' . $to->format('M j, Y');
    }

    /**
     * USD currency formatter — single source of truth so reports format
     * identically across screen, print, PDF, and CSV. Negative amounts
     * render as -$1,234.56 (not parentheses); preserves a leading minus
     * for unambiguous sign.
     */
    public static function usd(float|int|string $amount): string
    {
        $value = (float) $amount;
        $sign  = $value < 0 ? '-' : '';
        return $sign . '$' . number_format(abs($value), 2);
    }

    /**
     * Phase 7.1+ — Brand-only PALETTE that alternates navy and orange
     * shades. Both income and expense donuts draw from this same
     * palette, sequentially per donut, so:
     *
     *   • Two slices in the same donut never share a colour (up to
     *     palette size).
     *   • Each donut visually mixes navy + orange — the brand
     *     identity reads strongly even on small charts.
     *   • Largest-first sequential assignment gives the biggest
     *     slice the boldest brand colour (navy 700) and steps down
     *     in alternating navy/orange shades from there.
     *
     * Order matters: navy → orange → navy → orange → ... so adjacent
     * slices on the donut get visually distinct hues, not just
     * shades of the same colour.
     */
    public const PALETTE = [
        '#1b2b4b', // navy 700 — primary brand
        '#f97316', // brand orange 500 — primary accent
        '#1e3a8a', // navy 800 — deeper navy
        '#c2410c', // orange 700 — deeper orange
        '#3b5998', // navy 500 — mid navy
        '#fb923c', // orange 400 — mid orange
        '#5b7bb6', // navy 400 — lighter navy
        '#fdba74', // orange 300 — lighter orange
    ];

    /**
     * Sequential colour picker for donut slices. Returns the i-th
     * colour from PALETTE so two categories in the same donut never
     * share a colour (up to palette size = 8). Both income and
     * expense donuts use this same picker; the palette alternates
     * navy and orange so each donut mixes both brand colours.
     */
    public static function colorForSlice(int $index): string
    {
        return self::PALETTE[$index % count(self::PALETTE)];
    }

    /**
     * Hash-stable colour picker — kept for reports that want the same
     * category name to always render the same colour across pages
     * (e.g. Donor Analysis where a single donor might appear in
     * multiple reports). NOT used by Statement of Activities donuts;
     * those use sequential type-aware assignment via colorForSlice().
     */
    public static function colorFor(string $key): string
    {
        $hash = 0;
        foreach (str_split(strtolower($key)) as $ch) {
            $hash = ($hash * 33 + ord($ch)) & 0x7fffffff;
        }
        return self::PALETTE[$hash % count(self::PALETTE)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.1.e — Statement of Activities
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Statement of Activities (the nonprofit's Income Statement / P&L).
     *
     * Returns the full payload powering the screen, print, PDF, and CSV
     * outputs:
     *   [
     *     'period'        => ['label' => 'April 2026', 'from' => Carbon, 'to' => Carbon],
     *     'compare'       => ['label' => 'March 2026', 'from' => Carbon, 'to' => Carbon] | null,
     *     'income'        => ['categories' => [{name, amount, color, share, prior_amount?, delta?}], 'total' => float, 'prior_total' => ?float],
     *     'expense'       => same shape,
     *     'net_change'    => float,
     *     'prior_net'     => ?float,
     *     'insights'      => ['• Income up 18% versus the prior period', ...],
     *   ]
     *
     * Only `status = completed` transactions are included so pending /
     * cancelled rows don't skew the headline. (Statement of Activities
     * is GAAP territory; pending entries belong on Outstanding reports.)
     */
    public function statementOfActivities(Carbon $from, Carbon $to, ?Carbon $compareFrom = null, ?Carbon $compareTo = null): array
    {
        $income  = $this->breakdownByCategory('income',  $from, $to);
        $expense = $this->breakdownByCategory('expense', $from, $to);

        $compareIncome = $compareFrom ? $this->breakdownByCategory('income',  $compareFrom, $compareTo) : null;
        $compareExp    = $compareFrom ? $this->breakdownByCategory('expense', $compareFrom, $compareTo) : null;

        // Merge prior-period amounts into the current categories so the
        // detail table can render a side-by-side column.
        if ($compareIncome) {
            $income = $this->attachPriorAmounts($income, $compareIncome);
        }
        if ($compareExp) {
            $expense = $this->attachPriorAmounts($expense, $compareExp);
        }

        $netChange = $income['total'] - $expense['total'];
        $priorNet  = $compareIncome ? ($compareIncome['total'] - $compareExp['total']) : null;

        return [
            'period'      => ['label' => $this->labelFor($from, $to),       'from' => $from,        'to' => $to],
            'compare'     => $compareFrom
                ? ['label' => $this->labelFor($compareFrom, $compareTo), 'from' => $compareFrom, 'to' => $compareTo]
                : null,
            'income'      => $income,
            'expense'     => $expense,
            'net_change'  => $netChange,
            'prior_net'   => $priorNet,
            'insights'    => $this->buildInsights($income, $expense, $netChange, $priorNet, (bool) $compareFrom),
        ];
    }

    /**
     * Income or expense breakdown for a date range, grouped by category
     * name. Uncategorised transactions (category_id NULL) collapse into
     * "(Uncategorised)". Only completed transactions count toward
     * Statement-of-Activities totals.
     */
    private function breakdownByCategory(string $type, Carbon $from, Carbon $to): array
    {
        $rows = FinanceTransaction::query()
            ->where('transaction_type', $type)
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->with('category:id,name')
            ->get(['id', 'amount', 'category_id']);

        $byCat = [];
        foreach ($rows as $tx) {
            $name = $tx->category?->name ?? '(Uncategorised)';
            $byCat[$name] = ($byCat[$name] ?? 0) + (float) $tx->amount;
        }

        $total = array_sum($byCat);
        arsort($byCat); // largest first

        // Sequential colour assignment from the shared PALETTE so no
        // two slices in the same donut share a colour. The palette
        // alternates navy and orange shades, so each donut visually
        // mixes both brand colours — the largest slice (sorted first)
        // gets navy 700, the next gets brand orange, then navy 800,
        // then orange 700, etc.
        $categories = [];
        $i = 0;
        foreach ($byCat as $name => $amount) {
            $categories[] = [
                'name'   => $name,
                'amount' => (float) $amount,
                'color'  => self::colorForSlice($i),
                'share'  => $total > 0 ? ($amount / $total) : 0.0,
            ];
            $i++;
        }

        return ['categories' => $categories, 'total' => (float) $total];
    }

    /**
     * Inline a `prior_amount` and `delta` percentage onto each category
     * row for the side-by-side comparison column. Categories absent in
     * the prior period get prior_amount=0 + delta=null (infinite growth
     * is not a useful number; the UI renders "new").
     */
    private function attachPriorAmounts(array $current, array $prior): array
    {
        $priorIndex = [];
        foreach ($prior['categories'] as $row) {
            $priorIndex[$row['name']] = (float) $row['amount'];
        }

        foreach ($current['categories'] as &$row) {
            $row['prior_amount'] = $priorIndex[$row['name']] ?? 0.0;
            if ($row['prior_amount'] > 0) {
                $row['delta'] = ($row['amount'] - $row['prior_amount']) / $row['prior_amount'];
            } else {
                $row['delta'] = null; // no prior, can't compute %
            }
        }
        unset($row);

        $current['prior_total'] = (float) $prior['total'];
        return $current;
    }

    // ─────────────────────────────────────────────────────────────────────
     // Phase 7.2.a — Income Detail Report
     // ─────────────────────────────────────────────────────────────────────

    /**
     * Income Detail Report — every income transaction in the period,
     * grouped by category, with subtotals + grand total. Mirrors the
     * Statement of Activities filter contract but exposes per-row
     * detail (vs. category-level rollup) and adds a category /
     * source / event filter trio.
     *
     * Returns:
     *   [
     *     'period'         => array,
     *     'compare'        => ?array,
     *     'rows'           => array of {date, title, source, category, amount, status, event},
     *     'by_category'    => array of {name, amount, color, share, count, prior_amount?, delta?},
     *     'total'          => float,
     *     'prior_total'    => ?float,
     *     'count'          => int,
     *     'top_source'     => ?array {name, amount},
     *     'largest_single' => ?array {title, amount, source, date},
     *     'insights'       => array of strings,
     *     'filters'        => echo-back of applied filters for the export header,
     *   ]
     */
    public function incomeDetail(Carbon $from, Carbon $to, ?Carbon $compareFrom = null, ?Carbon $compareTo = null, array $filters = []): array
    {
        return $this->detailReport('income', $from, $to, $compareFrom, $compareTo, $filters);
    }

    /**
     * Expense Detail Report — same shape as Income Detail but for
     * expense transactions. The filter contract is identical (category
     * / payee search / event); the labelling in the view differs
     * (Source → Payee, etc.).
     */
    public function expenseDetail(Carbon $from, Carbon $to, ?Carbon $compareFrom = null, ?Carbon $compareTo = null, array $filters = []): array
    {
        return $this->detailReport('expense', $from, $to, $compareFrom, $compareTo, $filters);
    }

    /**
     * Shared engine for Income Detail + Expense Detail. The two reports
     * have identical data shape and filter axes — only the
     * `transaction_type` differs. Pulling the implementation here keeps
     * one source of truth for the row-level + category-rollup logic.
     */
    private function detailReport(string $type, Carbon $from, Carbon $to, ?Carbon $compareFrom, ?Carbon $compareTo, array $filters): array
    {
        $query = $this->buildDetailQuery($type, $from, $to, $filters);
        $rows  = $query->with(['category:id,name', 'event:id,name'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get(['id', 'transaction_date', 'title', 'source_or_payee', 'category_id', 'amount', 'status', 'event_id']);

        // Per-row payload — flat array, no Eloquent collection so the
        // exports can re-consume without re-querying.
        $rowsPayload = $rows->map(fn ($tx) => [
            'id'       => $tx->id,
            'date'     => $tx->transaction_date?->format('Y-m-d'),
            'title'    => $tx->title,
            'source'   => $tx->source_or_payee ?? '',
            'category' => $tx->category?->name ?? '(Uncategorised)',
            'amount'   => (float) $tx->amount,
            'status'   => $tx->status,
            'event'    => $tx->event?->name ?? '',
        ])->all();

        // Category rollup — sum amounts per category, sorted largest
        // first. Sequential palette colours same as SoA donuts.
        $byCatRaw = [];
        $countByCat = [];
        foreach ($rowsPayload as $r) {
            $name = $r['category'];
            $byCatRaw[$name]   = ($byCatRaw[$name] ?? 0) + $r['amount'];
            $countByCat[$name] = ($countByCat[$name] ?? 0) + 1;
        }
        arsort($byCatRaw);

        $total = (float) array_sum($byCatRaw);
        $byCategory = [];
        $i = 0;
        foreach ($byCatRaw as $name => $amount) {
            $byCategory[] = [
                'name'   => $name,
                'amount' => (float) $amount,
                'color'  => self::colorForSlice($i),
                'share'  => $total > 0 ? ($amount / $total) : 0.0,
                'count'  => $countByCat[$name],
            ];
            $i++;
        }

        // Prior-period comparison
        $priorTotal     = null;
        $priorByCatRaw  = [];
        if ($compareFrom) {
            $priorRows = $this->buildDetailQuery($type, $compareFrom, $compareTo, $filters)
                ->with('category:id,name')
                ->get(['amount', 'category_id']);
            foreach ($priorRows as $tx) {
                $name = $tx->category?->name ?? '(Uncategorised)';
                $priorByCatRaw[$name] = ($priorByCatRaw[$name] ?? 0) + (float) $tx->amount;
            }
            $priorTotal = (float) array_sum($priorByCatRaw);

            // Inline the prior amounts onto the current category rows
            foreach ($byCategory as &$row) {
                $row['prior_amount'] = (float) ($priorByCatRaw[$row['name']] ?? 0);
                if ($row['prior_amount'] > 0) {
                    $row['delta'] = ($row['amount'] - $row['prior_amount']) / $row['prior_amount'];
                } else {
                    $row['delta'] = null;
                }
            }
            unset($row);
        }

        // Top source (donor for income, vendor for expense). Excludes
        // empty source strings so a row with no donor doesn't dominate.
        $bySource = [];
        foreach ($rowsPayload as $r) {
            if ($r['source'] === '') continue;
            $bySource[$r['source']] = ($bySource[$r['source']] ?? 0) + $r['amount'];
        }
        arsort($bySource);
        $topSource = ! empty($bySource)
            ? ['name' => array_key_first($bySource), 'amount' => (float) array_values($bySource)[0]]
            : null;

        // Largest single transaction
        $largest = ! empty($rowsPayload)
            ? collect($rowsPayload)->sortByDesc('amount')->first()
            : null;
        $largestSingle = $largest
            ? ['title' => $largest['title'], 'amount' => $largest['amount'], 'source' => $largest['source'], 'date' => $largest['date']]
            : null;

        return [
            'period'         => ['label' => $this->labelFor($from, $to), 'from' => $from, 'to' => $to],
            'compare'        => $compareFrom
                ? ['label' => $this->labelFor($compareFrom, $compareTo), 'from' => $compareFrom, 'to' => $compareTo]
                : null,
            'rows'           => $rowsPayload,
            'by_category'    => $byCategory,
            'total'          => $total,
            'prior_total'    => $priorTotal,
            'count'          => count($rowsPayload),
            'top_source'     => $topSource,
            'largest_single' => $largestSingle,
            'insights'       => $this->buildDetailInsights($type, $rowsPayload, $total, $priorTotal, $topSource, $largestSingle, $byCategory),
            'filters'        => $filters,
        ];
    }

    /**
     * Compose the filtered query for income/expense detail. Filters
     * accepted: category_id (single), source (LIKE), event_id, status.
     * Status defaults to `completed` when not specified — same default
     * as Statement of Activities so headline numbers reconcile.
     */
    private function buildDetailQuery(string $type, Carbon $from, Carbon $to, array $filters)
    {
        $query = FinanceTransaction::query()
            ->where('transaction_type', $type)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()]);

        // Status default: completed only — same as SoA. Caller can
        // override via filters['status'] (e.g. 'all' to include
        // pending/cancelled, or a specific value).
        if (! empty($filters['status'])) {
            if ($filters['status'] !== 'all') {
                $query->where('status', $filters['status']);
            }
        } else {
            $query->where('status', 'completed');
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }
        if (! empty($filters['source'])) {
            $query->where('source_or_payee', 'like', '%' . $filters['source'] . '%');
        }
        return $query;
    }

    private function buildDetailInsights(string $type, array $rows, float $total, ?float $priorTotal, ?array $topSource, ?array $largest, array $byCategory): array
    {
        $bullets = [];
        $label = $type === 'income' ? 'income' : 'expense';
        $sourceLabel = $type === 'income' ? 'donor' : 'vendor';

        if (empty($rows)) {
            return ["No completed {$label} transactions were recorded for this period."];
        }

        // Totals
        $bullets[] = sprintf(
            '%d %s transactions totaled %s across %d %s.',
            count($rows),
            $label,
            self::usd($total),
            count($byCategory),
            count($byCategory) === 1 ? 'category' : 'categories',
        );

        // Trend
        if ($priorTotal !== null && $priorTotal > 0) {
            $delta = ($total - $priorTotal) / $priorTotal;
            $direction = $delta >= 0 ? 'up' : 'down';
            $bullets[] = sprintf(
                'Total is %s %s versus the prior period (%s).',
                $direction,
                number_format(abs($delta) * 100, 0) . '%',
                self::usd($priorTotal),
            );
        }

        // Top category
        if (! empty($byCategory)) {
            $top = $byCategory[0];
            $bullets[] = sprintf(
                '%s was the largest %s category at %s%% (%s).',
                $top['name'],
                $label,
                number_format($top['share'] * 100, 0),
                self::usd($top['amount']),
            );
        }

        // Top source
        if ($topSource) {
            $bullets[] = sprintf(
                'Top %s: %s at %s.',
                $sourceLabel,
                $topSource['name'],
                self::usd($topSource['amount']),
            );
        }

        // Largest single transaction
        if ($largest) {
            $bullets[] = sprintf(
                'Largest single transaction: %s at %s%s.',
                $largest['title'],
                self::usd($largest['amount']),
                $largest['source'] ? " ({$largest['source']})" : '',
            );
        }

        return $bullets;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.2.c — General Ledger
    // ─────────────────────────────────────────────────────────────────────

    /**
     * General Ledger — chronological list of every transaction in the
     * period (both income AND expense). The auditor's landing page.
     *
     * Shape:
     *   • Rows ordered by date ASC then id ASC so the earliest entry
     *     comes first — auditors read top-to-bottom chronologically.
     *   • Running balance column accumulates +income / −expense per
     *     row. Starting balance is zero (this is a period ledger, not
     *     a balance-sheet position; for a true period-opening balance
     *     we'd need a Statement of Financial Position which Phase 7
     *     intentionally doesn't model — see ADR-001 in HANDOFF).
     *   • By default includes every status; auditors want to see
     *     pending + cancelled rows too. Filter `?status=completed`
     *     narrows to GAAP-defensible numbers.
     *
     * Filters: type (income/expense/all), category_id, status,
     * source_or_payee (LIKE), event_id.
     */
    public function generalLedger(Carbon $from, Carbon $to, array $filters = []): array
    {
        $query = FinanceTransaction::query()
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()]);

        if (! empty($filters['type']) && in_array($filters['type'], ['income', 'expense'], true)) {
            $query->where('transaction_type', $filters['type']);
        }
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['source'])) {
            $query->where('source_or_payee', 'like', '%' . $filters['source'] . '%');
        }
        if (! empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        $rows = $query->with(['category:id,name', 'event:id,name'])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get([
                'id', 'transaction_date', 'title', 'source_or_payee',
                'category_id', 'amount', 'transaction_type', 'status',
                'event_id', 'reference_number',
            ]);

        // Walk the rows accumulating a running balance. Pending +
        // cancelled rows are still listed so the auditor sees them, but
        // they don't affect the running balance.
        $running = 0.0;
        $totalIn  = 0.0;
        $totalOut = 0.0;
        $payload = [];
        foreach ($rows as $tx) {
            $amt = (float) $tx->amount;
            $isCounted = $tx->status === 'completed';
            if ($isCounted) {
                if ($tx->transaction_type === 'income') {
                    $running  += $amt;
                    $totalIn  += $amt;
                } else {
                    $running  -= $amt;
                    $totalOut += $amt;
                }
            }

            $payload[] = [
                'id'        => $tx->id,
                'date'      => $tx->transaction_date?->format('Y-m-d'),
                'type'      => $tx->transaction_type,
                'title'     => $tx->title,
                'source'    => $tx->source_or_payee ?? '',
                'category'  => $tx->category?->name ?? '(Uncategorised)',
                'amount'    => $amt,
                'status'    => $tx->status,
                'reference' => $tx->reference_number ?? '',
                'event'     => $tx->event?->name ?? '',
                'running_balance' => $isCounted ? $running : null,
                'counted'   => $isCounted,
            ];
        }

        $netChange = $totalIn - $totalOut;
        $countedRows = count(array_filter($payload, fn ($r) => $r['counted']));

        return [
            'period'      => ['label' => $this->labelFor($from, $to), 'from' => $from, 'to' => $to],
            'rows'        => $payload,
            'count'       => count($payload),
            'counted'     => $countedRows,
            'total_in'    => $totalIn,
            'total_out'   => $totalOut,
            'net_change'  => $netChange,
            'closing_balance' => $running,
            'filters'     => $filters,
            'insights'    => $this->buildLedgerInsights($payload, $totalIn, $totalOut, $netChange),
        ];
    }

    private function buildLedgerInsights(array $rows, float $totalIn, float $totalOut, float $netChange): array
    {
        $bullets = [];
        if (empty($rows)) {
            return ['No transactions were recorded for this period.'];
        }

        $bullets[] = sprintf('%d transactions recorded (%d completed).',
            count($rows),
            count(array_filter($rows, fn ($r) => $r['counted'])),
        );
        $bullets[] = sprintf('Total inflow: %s · Total outflow: %s', self::usd($totalIn), self::usd($totalOut));

        if ($netChange >= 0) {
            $bullets[] = sprintf('Net change for the period: +%s.', self::usd($netChange));
        } else {
            $bullets[] = sprintf('Net change for the period: %s (outflow exceeded inflow).', self::usd($netChange));
        }

        // Pending watch
        $pending = array_filter($rows, fn ($r) => $r['status'] === 'pending');
        if (! empty($pending)) {
            $pendingTotal = array_sum(array_column($pending, 'amount'));
            $bullets[] = sprintf('%d pending transaction%s totalling %s — not included in the running balance.',
                count($pending),
                count($pending) === 1 ? '' : 's',
                self::usd($pendingTotal),
            );
        }

        return $bullets;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.3.a — Donor / Source Analysis
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Donor / Source Analysis — top contributors in the period with
     * gift counts, average gift, first/last activity, a 12-month
     * sparkline trail, and a prior-period comparison (delta + lapsed +
     * new + retention rate).
     *
     * The screen + print render the top 10; CSV exports every donor.
     * `donor_total_count` is the unique-donor count across the full
     * period (used for the "Show all (N)" toggle on the screen).
     *
     * Always uses status=completed (this is a fundraising report — pending
     * pledges belong on a Pledge Aging report, not here).
     *
     * Filters: `source` (LIKE), `category_id` (single income category).
     *
     * Returns:
     *   [
     *     'period'           => ['label', 'from', 'to'],
     *     'compare'          => ?['label', 'from', 'to'],
     *     'donors'           => array of top-10 donor records (see below),
     *     'all_donors'       => array of every donor record (CSV consumes this),
     *     'donor_total_count'=> int,    // unique donors in period
     *     'gift_count'       => int,    // total # gifts
     *     'total'            => float,
     *     'prior_total'      => ?float,
     *     'avg_gift'         => float,
     *     'top_donor'        => ?['name', 'total'],
     *     'lapsed'           => array of ['name', 'prior_total'] for prior donors absent in current,
     *     'new_donors'       => array of ['name', 'total'] for current donors absent in prior,
     *     'retention_rate'   => ?float, // fraction in [0..1]; null when no prior donors
     *     'insights'         => array of strings,
     *     'filters'          => echo-back of applied filters,
     *   ]
     *
     * Each donor record:
     *   ['name', 'total', 'count', 'avg_gift', 'first_gift', 'last_gift',
     *    'color', 'share', 'sparkline', 'prior_total', 'delta', 'is_new']
     */
    public function donorAnalysis(Carbon $from, Carbon $to, ?Carbon $compareFrom = null, ?Carbon $compareTo = null, array $filters = []): array
    {
        return $this->stakeholderAnalysis('income', $from, $to, $compareFrom, $compareTo, $filters);
    }

    /**
     * Vendor / Payee Analysis — same shape as Donor Analysis but for
     * expense transactions. Phase 7.3.b uses this; the engine is shared
     * because the ranking + retention + sparkline logic is identical
     * regardless of direction.
     */
    public function vendorAnalysis(Carbon $from, Carbon $to, ?Carbon $compareFrom = null, ?Carbon $compareTo = null, array $filters = []): array
    {
        return $this->stakeholderAnalysis('expense', $from, $to, $compareFrom, $compareTo, $filters);
    }

    /**
     * Shared engine for Donor Analysis (income) + Vendor Analysis
     * (expense). The two reports differ only in transaction_type and
     * the labelling in the view; the ranking, sparkline, retention,
     * and insight logic is identical.
     */
    private function stakeholderAnalysis(string $type, Carbon $from, Carbon $to, ?Carbon $compareFrom, ?Carbon $compareTo, array $filters): array
    {
        $current = $this->aggregateBySource($type, $from, $to, $filters);
        $prior   = $compareFrom ? $this->aggregateBySource($type, $compareFrom, $compareTo, $filters) : [];

        // Build the full donor list, sorted by total $ descending. Ties
        // broken alphabetically so output is deterministic across runs.
        $allDonors = [];
        foreach ($current as $name => $agg) {
            $priorAmt = $prior[$name]['total'] ?? 0.0;
            $allDonors[] = [
                'name'        => $name,
                'total'       => (float) $agg['total'],
                'count'       => (int)   $agg['count'],
                'avg_gift'    => $agg['count'] > 0 ? (float) $agg['total'] / $agg['count'] : 0.0,
                'first_gift'  => $agg['first'],
                'last_gift'   => $agg['last'],
                'prior_total' => (float) $priorAmt,
                'delta'       => $priorAmt > 0 ? (((float) $agg['total'] - $priorAmt) / $priorAmt) : null,
                'is_new'      => $compareFrom !== null && $priorAmt <= 0,
            ];
        }
        usort($allDonors, function ($a, $b) {
            if ($a['total'] === $b['total']) return strcmp($a['name'], $b['name']);
            return $b['total'] <=> $a['total'];
        });

        $total = array_sum(array_column($allDonors, 'total'));

        // Assign share + sequential brand palette colour to every donor
        // (top 10 use the colour on screen; CSV records carry it too so
        // anyone consuming the payload sees the same ordering signal).
        foreach ($allDonors as $i => &$row) {
            $row['share'] = $total > 0 ? $row['total'] / $total : 0.0;
            $row['color'] = self::colorForSlice($i);
        }
        unset($row);

        // Top 10 — main display set. Sparklines are computed only for
        // these to avoid a 12-month-per-donor query when the period has
        // hundreds of donors.
        $top = array_slice($allDonors, 0, 10);
        $sparklines = ! empty($top) ? $this->donorSparklines($type, array_column($top, 'name'), $to, $filters) : [];
        foreach ($top as &$row) {
            $row['sparkline'] = $sparklines[$row['name']] ?? array_fill(0, 12, 0.0);
        }
        unset($row);

        // Lapsed: gave in prior, didn't give in current.
        $lapsed = [];
        foreach ($prior as $name => $agg) {
            if (! isset($current[$name])) {
                $lapsed[] = ['name' => $name, 'prior_total' => (float) $agg['total']];
            }
        }
        usort($lapsed, fn ($a, $b) => $b['prior_total'] <=> $a['prior_total']);

        // New donors: appeared in current, weren't in prior. Only meaningful
        // when comparing — without a prior period, "new" is ambiguous.
        $newDonors = [];
        if ($compareFrom) {
            foreach ($allDonors as $row) {
                if ($row['is_new']) {
                    $newDonors[] = ['name' => $row['name'], 'total' => $row['total']];
                }
            }
        }

        // Retention: % of prior donors who also gave in current. Null when
        // prior period had no donors (division by zero is meaningless here,
        // and the UI prints a dash rather than 0%).
        $retentionRate = null;
        if (! empty($prior)) {
            $retained = count(array_intersect_key($prior, $current));
            $retentionRate = $retained / count($prior);
        }

        $giftCount = (int) array_sum(array_column($allDonors, 'count'));
        $priorTotal = $compareFrom ? (float) array_sum(array_column($prior, 'total')) : null;

        return [
            'period'            => ['label' => $this->labelFor($from, $to), 'from' => $from, 'to' => $to],
            'compare'           => $compareFrom
                ? ['label' => $this->labelFor($compareFrom, $compareTo), 'from' => $compareFrom, 'to' => $compareTo]
                : null,
            'donors'            => $top,
            'all_donors'        => $allDonors,
            'donor_total_count' => count($allDonors),
            'gift_count'        => $giftCount,
            'total'             => (float) $total,
            'prior_total'       => $priorTotal,
            'avg_gift'          => $giftCount > 0 ? (float) $total / $giftCount : 0.0,
            'top_donor'         => ! empty($allDonors) ? ['name' => $allDonors[0]['name'], 'total' => (float) $allDonors[0]['total']] : null,
            'lapsed'            => $lapsed,
            'new_donors'        => $newDonors,
            'retention_rate'    => $retentionRate,
            'insights'          => $this->buildStakeholderInsights($type, $allDonors, $total, $priorTotal, $newDonors, $lapsed, $retentionRate, (bool) $compareFrom),
            'filters'           => $filters,
        ];
    }

    /**
     * Aggregate transactions by source_or_payee for a date range. Returns
     * a map keyed by donor name → ['total' => float, 'count' => int,
     * 'first' => Y-m-d, 'last' => Y-m-d].
     *
     * Rows where source_or_payee is null / empty / whitespace-only are
     * collapsed under "(Anonymous)" — board still wants visibility into
     * unattributed giving rather than dropping it from the picture.
     */
    private function aggregateBySource(string $type, Carbon $from, Carbon $to, array $filters): array
    {
        $rows = FinanceTransaction::query()
            ->where('transaction_type', $type)
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(! empty($filters['source']), fn ($q) => $q->where('source_or_payee', 'like', '%' . $filters['source'] . '%'))
            ->get(['amount', 'source_or_payee', 'transaction_date']);

        $bySource = [];
        foreach ($rows as $tx) {
            $name = trim((string) ($tx->source_or_payee ?? ''));
            if ($name === '') $name = '(Anonymous)';
            $date = $tx->transaction_date?->toDateString();

            if (! isset($bySource[$name])) {
                $bySource[$name] = ['total' => 0.0, 'count' => 0, 'first' => $date, 'last' => $date];
            }
            $bySource[$name]['total'] += (float) $tx->amount;
            $bySource[$name]['count'] += 1;
            if ($date !== null && ($bySource[$name]['first'] === null || $date < $bySource[$name]['first'])) {
                $bySource[$name]['first'] = $date;
            }
            if ($date !== null && ($bySource[$name]['last'] === null || $date > $bySource[$name]['last'])) {
                $bySource[$name]['last'] = $date;
            }
        }
        return $bySource;
    }

    /**
     * Compute 12-month giving sparklines for a list of donors. Bucket
     * end-anchor is `$endingAt`'s month — so a report for "Last Year
     * 2025" anchors the sparkline at Dec 2025 (showing Jan-Dec 2025),
     * not at today. Each donor → array of 12 monthly totals,
     * oldest → newest.
     *
     * Single broad query + PHP grouping (per HANDOFF carry-forward
     * learning: sub-100-row aggregation is portable and trivial in
     * memory; SQL `MONTH()` / `YEARWEEK()` break sqlite tests).
     */
    private function donorSparklines(string $type, array $donorNames, Carbon $endingAt, array $filters): array
    {
        if (empty($donorNames)) return [];

        // Build an ordered list of 12 month-keys (YYYY-MM) ending at $endingAt's month.
        $endMonth = $endingAt->copy()->endOfMonth();
        $monthKeys = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthKeys[] = $endMonth->copy()->subMonthsNoOverflow($i)->format('Y-m');
        }

        // Window: first day of the oldest month → last day of the newest.
        $windowFrom = $endMonth->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $windowTo   = $endMonth;

        // Resolve the filter values — treat "(Anonymous)" specially: it's
        // a synthetic name we assign in PHP, not a value in the DB. For
        // anonymous, match rows where source_or_payee is null / empty.
        $hasAnonymous = in_array('(Anonymous)', $donorNames, true);
        $namedDonors  = array_values(array_filter($donorNames, fn ($n) => $n !== '(Anonymous)'));

        $query = FinanceTransaction::query()
            ->where('transaction_type', $type)
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$windowFrom->toDateString(), $windowTo->toDateString()])
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']));

        $query->where(function ($q) use ($namedDonors, $hasAnonymous) {
            if (! empty($namedDonors)) {
                $q->whereIn('source_or_payee', $namedDonors);
            }
            if ($hasAnonymous) {
                $q->orWhereNull('source_or_payee')->orWhere('source_or_payee', '');
            }
        });

        $rows = $query->get(['amount', 'source_or_payee', 'transaction_date']);

        // Bootstrap each donor with 12 zeroed buckets.
        $out = [];
        foreach ($donorNames as $name) {
            $out[$name] = array_fill(0, 12, 0.0);
        }

        foreach ($rows as $tx) {
            $rawName = trim((string) ($tx->source_or_payee ?? ''));
            $name = $rawName === '' ? '(Anonymous)' : $rawName;
            if (! isset($out[$name])) continue; // shouldn't happen, but guard

            $key = $tx->transaction_date?->format('Y-m');
            $idx = array_search($key, $monthKeys, true);
            if ($idx === false) continue; // outside the 12-month window
            $out[$name][$idx] += (float) $tx->amount;
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.3.d — Category Trend Report
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Category Trend Report — monthly buckets across the period showing
     * how each category's income/expense moves over time. Default
     * direction is "income"; pass `direction => 'expense' | 'both'` to
     * change. Top-6 categories by total are plotted; the rest collapse
     * into "Other" so the line chart stays readable.
     *
     * Bucket granularity is monthly (locked for v1). PHP-side bucketing
     * keeps the DB-portability promise — `MONTH()` etc. break sqlite
     * tests; per HANDOFF this scale (≤ ~24 months × ~30 categories) is
     * trivial in memory.
     *
     * Returns:
     *   [
     *     'period'      => ['label', 'from', 'to'],
     *     'direction'   => 'income' | 'expense' | 'both',
     *     'months'      => array of YYYY-MM keys ordered oldest → newest,
     *     'month_labels'=> array of human labels e.g. ['Jan 2026', 'Feb 2026', ...],
     *     'series'      => array of category records (see below),
     *     'totals'      => ['period' => float, 'months' => array of floats per month],
     *     'leaders'     => ['top_grower' => ?{name, delta}, 'top_shrinker' => ?{name, delta}],
     *     'insights'    => array of strings,
     *   ]
     *
     * Each category series:
     *   ['name', 'type', 'color', 'monthly' => [...], 'total' => float, 'first' => float, 'last' => float, 'delta' => ?float]
     */
    public function categoryTrend(Carbon $from, Carbon $to, string $direction = 'income'): array
    {
        $direction = in_array($direction, ['income', 'expense', 'both'], true) ? $direction : 'income';

        // Build month keys + labels
        $months      = [];
        $monthLabels = [];
        $cursor      = $from->copy()->startOfMonth();
        $end         = $to->copy()->endOfMonth();
        while ($cursor->lessThanOrEqualTo($end)) {
            $months[]      = $cursor->format('Y-m');
            $monthLabels[] = $cursor->format('M Y');
            $cursor->addMonthNoOverflow();
        }

        // Pull all completed transactions in the period in a single query
        $query = FinanceTransaction::query()
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->with('category:id,name,type');

        if ($direction !== 'both') {
            $query->where('transaction_type', $direction);
        }

        $rows = $query->get(['amount', 'category_id', 'transaction_type', 'transaction_date']);

        // Aggregate: name + type → month → total
        // Using "name|type" as composite key so an income "Donations" and
        // an expense "Donations" never collide. Output drops the suffix.
        $byKey = [];
        foreach ($rows as $tx) {
            $name = $tx->category?->name ?? '(Uncategorised)';
            $type = $tx->transaction_type;
            $key  = $name . '|' . $type;
            $monthKey = $tx->transaction_date?->format('Y-m');

            if (! isset($byKey[$key])) {
                $byKey[$key] = ['name' => $name, 'type' => $type, 'monthly' => array_fill_keys($months, 0.0), 'total' => 0.0];
            }
            if (isset($byKey[$key]['monthly'][$monthKey])) {
                $byKey[$key]['monthly'][$monthKey] += (float) $tx->amount;
            }
            $byKey[$key]['total'] += (float) $tx->amount;
        }

        // Sort by total desc, take top 6, fold remainder into "Other"
        uasort($byKey, fn ($a, $b) => $b['total'] <=> $a['total']);

        $series   = [];
        $i        = 0;
        $otherMonthly = array_fill_keys($months, 0.0);
        $otherTotal   = 0.0;
        $otherCount   = 0;

        foreach ($byKey as $row) {
            if ($i < 6) {
                $monthlyValues = array_values($row['monthly']);
                $first  = $monthlyValues[0] ?? 0.0;
                $last   = end($monthlyValues) ?: 0.0;
                $delta  = $first > 0 ? ($last - $first) / $first : null;

                $series[] = [
                    'name'    => $row['name'],
                    'type'    => $row['type'],
                    'color'   => self::colorForSlice($i),
                    'monthly' => $monthlyValues,
                    'total'   => $row['total'],
                    'first'   => $first,
                    'last'    => $last,
                    'delta'   => $delta,
                ];
            } else {
                foreach ($row['monthly'] as $m => $v) $otherMonthly[$m] += $v;
                $otherTotal += $row['total'];
                $otherCount += 1;
            }
            $i++;
        }

        if ($otherCount > 0) {
            $otherValues = array_values($otherMonthly);
            $first = $otherValues[0] ?? 0.0;
            $last  = end($otherValues) ?: 0.0;
            $series[] = [
                'name'    => 'Other (' . $otherCount . ' ' . ($otherCount === 1 ? 'category' : 'categories') . ')',
                'type'    => $direction === 'both' ? 'both' : $direction,
                'color'   => '#9ca3af', // neutral grey to visually demote
                'monthly' => $otherValues,
                'total'   => $otherTotal,
                'first'   => $first,
                'last'    => $last,
                'delta'   => $first > 0 ? ($last - $first) / $first : null,
            ];
        }

        // Period totals + per-month totals
        $monthlyTotals = array_fill_keys($months, 0.0);
        foreach ($byKey as $row) {
            foreach ($row['monthly'] as $m => $v) $monthlyTotals[$m] += $v;
        }
        $periodTotal = array_sum($monthlyTotals);

        // Leaders — biggest grower and biggest shrinker (by % delta)
        $top    = null;
        $bottom = null;
        foreach ($series as $s) {
            if ($s['delta'] === null) continue;
            if ($top === null || $s['delta'] > $top['delta']) {
                $top = ['name' => $s['name'], 'delta' => $s['delta']];
            }
            if ($bottom === null || $s['delta'] < $bottom['delta']) {
                $bottom = ['name' => $s['name'], 'delta' => $s['delta']];
            }
        }

        return [
            'period'       => ['label' => $this->labelFor($from, $to), 'from' => $from, 'to' => $to],
            'direction'    => $direction,
            'months'       => $months,
            'month_labels' => $monthLabels,
            'series'       => $series,
            'totals'       => ['period' => $periodTotal, 'months' => array_values($monthlyTotals)],
            'leaders'      => ['top_grower' => $top, 'top_shrinker' => $bottom],
            'insights'     => $this->buildCategoryTrendInsights($direction, $series, $periodTotal, $top, $bottom),
        ];
    }

    private function buildCategoryTrendInsights(string $direction, array $series, float $periodTotal, ?array $top, ?array $bottom): array
    {
        $directionLabel = match ($direction) {
            'income'  => 'income',
            'expense' => 'expense',
            'both'    => 'income + expense',
        };

        if (empty($series) || $periodTotal === 0.0) {
            return [sprintf('No completed %s transactions were recorded for this period.', $directionLabel)];
        }

        $bullets = [];
        $bullets[] = sprintf(
            '%d %s categories totaled %s across the period.',
            count($series),
            $directionLabel,
            self::usd($periodTotal),
        );

        if (! empty($series)) {
            $largest = $series[0];
            $bullets[] = sprintf(
                '%s was the largest %s category at %s (%d%% of total).',
                $largest['name'],
                $directionLabel,
                self::usd($largest['total']),
                $periodTotal > 0 ? (int) round(($largest['total'] / $periodTotal) * 100) : 0,
            );
        }

        if ($top && $top['delta'] !== null && $top['delta'] > 0) {
            $bullets[] = sprintf(
                'Biggest mover: %s grew %s%% from the first month to the last.',
                $top['name'],
                number_format($top['delta'] * 100, 0),
            );
        }
        if ($bottom && $bottom['delta'] !== null && $bottom['delta'] < 0) {
            $bullets[] = sprintf(
                'Biggest decline: %s dropped %s%% from the first month to the last.',
                $bottom['name'],
                number_format(abs($bottom['delta']) * 100, 0),
            );
        }

        return $bullets;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 7.3.c — Per-Event P&L
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Per-Event P&L — income vs expense for a single event, plus
     * cost-per-beneficiary computed against the visit_households
     * snapshot pivot (not live `households.household_size`, which
     * drifts when households are edited after the event).
     *
     * Period filter doesn't apply — this is a single-event report
     * picked from a dropdown. Compare-to-prior is dropped for v1
     * (the right comparison shape — same event a year ago, average
     * of last 3 events of the same type — is its own design call).
     *
     * Households-served + people-served use snapshot semantics from
     * Phase 1.2.c (`visit_households.household_size` set at attach
     * time). Only `visit_status = 'exited'` visits count — the same
     * gate the production /visit-log uses for "Households Served".
     *
     * Returns:
     *   [
     *     'event'              => ['id', 'name', 'date', 'status', 'location'],
     *     'income'             => ['categories' => [...], 'total' => float],
     *     'expense'            => same shape,
     *     'rows'               => array of transactions ordered by date,
     *     'net'                => float,
     *     'households_served'  => int,
     *     'people_served'      => int,
     *     'cost_per_household' => ?float,
     *     'cost_per_person'    => ?float,
     *     'income_per_household'=> ?float,
     *     'insights'           => array of plain-English bullets,
     *   ]
     */
    public function perEventPnl(int $eventId): array
    {
        $event = \App\Models\Event::findOrFail($eventId);

        $income  = $this->breakdownByCategoryForEvent('income',  $eventId);
        $expense = $this->breakdownByCategoryForEvent('expense', $eventId);

        // All transactions tied to this event, completed only, ordered by date
        $rows = FinanceTransaction::query()
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->with(['category:id,name'])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'transaction_date', 'title', 'source_or_payee', 'category_id', 'amount', 'transaction_type'])
            ->map(fn ($tx) => [
                'id'       => $tx->id,
                'date'     => $tx->transaction_date?->format('Y-m-d'),
                'type'     => $tx->transaction_type,
                'title'    => $tx->title,
                'source'   => $tx->source_or_payee ?? '',
                'category' => $tx->category?->name ?? '(Uncategorised)',
                'amount'   => (float) $tx->amount,
            ])->all();

        // Snapshot-driven beneficiary counts. The join goes through
        // `visits` (filtered to status=exited at this event) and uses
        // the snapshot fields on `visit_households`. We pull a single
        // aggregate result from the DB rather than loading rows into
        // memory — this scales to events with thousands of visits.
        $served = \DB::table('visit_households as vh')
            ->join('visits as v', 'v.id', '=', 'vh.visit_id')
            ->where('v.event_id', $eventId)
            ->where('v.visit_status', 'exited')
            ->selectRaw('COUNT(DISTINCT vh.household_id) as households, COALESCE(SUM(vh.household_size), 0) as people')
            ->first();

        $householdsServed = (int) ($served->households ?? 0);
        $peopleServed     = (int) ($served->people ?? 0);

        $net = $income['total'] - $expense['total'];

        $costPerHousehold   = $householdsServed > 0 ? $expense['total'] / $householdsServed : null;
        $costPerPerson      = $peopleServed > 0     ? $expense['total'] / $peopleServed     : null;
        $incomePerHousehold = $householdsServed > 0 ? $income['total']  / $householdsServed : null;

        return [
            'event' => [
                'id'       => $event->id,
                'name'     => $event->name,
                'date'     => $event->date?->format('Y-m-d'),
                'status'   => $event->status,
                'location' => $event->location ?? null,
            ],
            'income'              => $income,
            'expense'             => $expense,
            'rows'                => $rows,
            'net'                 => $net,
            'households_served'   => $householdsServed,
            'people_served'       => $peopleServed,
            'cost_per_household'  => $costPerHousehold,
            'cost_per_person'     => $costPerPerson,
            'income_per_household'=> $incomePerHousehold,
            'insights'            => $this->buildPerEventInsights($event, $income, $expense, $net, $householdsServed, $peopleServed, $costPerHousehold, $costPerPerson),
        ];
    }

    /**
     * Income or expense breakdown for a single event, grouped by category.
     * Mirrors `breakdownByCategory()` but scoped by event_id rather than
     * a date range.
     */
    private function breakdownByCategoryForEvent(string $type, int $eventId): array
    {
        $rows = FinanceTransaction::query()
            ->where('transaction_type', $type)
            ->where('status', 'completed')
            ->where('event_id', $eventId)
            ->with('category:id,name')
            ->get(['id', 'amount', 'category_id']);

        $byCat = [];
        foreach ($rows as $tx) {
            $name = $tx->category?->name ?? '(Uncategorised)';
            $byCat[$name] = ($byCat[$name] ?? 0) + (float) $tx->amount;
        }
        $total = array_sum($byCat);
        arsort($byCat);

        $categories = [];
        $i = 0;
        foreach ($byCat as $name => $amount) {
            $categories[] = [
                'name'   => $name,
                'amount' => (float) $amount,
                'color'  => self::colorForSlice($i),
                'share'  => $total > 0 ? ($amount / $total) : 0.0,
            ];
            $i++;
        }
        return ['categories' => $categories, 'total' => (float) $total];
    }

    private function buildPerEventInsights(\App\Models\Event $event, array $income, array $expense, float $net, int $households, int $people, ?float $costPerHousehold, ?float $costPerPerson): array
    {
        $bullets = [];
        $bullets[] = sprintf('Event %s — %s.', $event->name, $event->date?->format('M j, Y') ?? '(date unknown)');

        if ($income['total'] === 0.0 && $expense['total'] === 0.0) {
            $bullets[] = 'No completed finance transactions are linked to this event.';
        } else {
            $bullets[] = sprintf(
                'Net result: %s (income %s, expense %s).',
                $net >= 0 ? '+' . self::usd($net) : self::usd($net),
                self::usd($income['total']),
                self::usd($expense['total']),
            );
        }

        if ($households > 0) {
            $bullets[] = sprintf(
                '%d %s and %d %s served (snapshot at visit time).',
                $households, $households === 1 ? 'household' : 'households',
                $people, $people === 1 ? 'person' : 'people',
            );
        } else {
            $bullets[] = 'No exited visits recorded for this event yet — beneficiary metrics unavailable.';
        }

        if ($costPerHousehold !== null && $expense['total'] > 0) {
            $bullets[] = sprintf(
                'Cost per household served: %s · cost per person: %s.',
                self::usd($costPerHousehold),
                $costPerPerson !== null ? self::usd($costPerPerson) : '—',
            );
        }

        if (! empty($expense['categories'])) {
            $top = $expense['categories'][0];
            $bullets[] = sprintf(
                'Largest expense category: %s at %s (%d%% of expenses).',
                $top['name'],
                self::usd($top['amount']),
                (int) round($top['share'] * 100),
            );
        }

        return $bullets;
    }

    /**
     * Plain-English insight bullets for Donor / Vendor analysis.
     * Reusable across both report types; the labelling adjusts via $type.
     */
    private function buildStakeholderInsights(string $type, array $allDonors, float $total, ?float $priorTotal, array $newDonors, array $lapsed, ?float $retentionRate, bool $hasCompare): array
    {
        $entity        = $type === 'income' ? 'donor'  : 'vendor';
        $entityPlural  = $type === 'income' ? 'donors' : 'vendors';
        $action        = $type === 'income' ? 'gave'   : 'were paid';
        $totalLabel    = $type === 'income' ? 'raised' : 'spent';

        if (empty($allDonors)) {
            return [sprintf('No %s activity recorded for this period.', $entity)];
        }

        $bullets = [];

        $bullets[] = sprintf(
            '%d %s %s a total of %s.',
            count($allDonors),
            count($allDonors) === 1 ? $entity : $entityPlural,
            $action,
            self::usd($total),
        );

        // Period-over-period
        if ($hasCompare && $priorTotal !== null && $priorTotal > 0) {
            $delta = ($total - $priorTotal) / $priorTotal;
            $direction = $delta >= 0 ? 'up' : 'down';
            $bullets[] = sprintf(
                'Total %s is %s %s versus the prior period (%s).',
                $totalLabel,
                $direction,
                number_format(abs($delta) * 100, 0) . '%',
                self::usd($priorTotal),
            );
        }

        // Top contributor
        $top = $allDonors[0];
        $bullets[] = sprintf(
            'Top %s: %s at %s (%d%% of total).',
            $entity,
            $top['name'],
            self::usd($top['total']),
            (int) round($top['share'] * 100),
        );

        // New donors / vendors (only meaningful on compare)
        if ($hasCompare && ! empty($newDonors)) {
            $newTotal = (float) array_sum(array_column($newDonors, 'total'));
            $bullets[] = sprintf(
                '%d new %s contributed %s this period.',
                count($newDonors),
                count($newDonors) === 1 ? $entity : $entityPlural,
                self::usd($newTotal),
            );
        }

        // Lapsed
        if ($hasCompare && ! empty($lapsed)) {
            $lapsedTotal = (float) array_sum(array_column($lapsed, 'prior_total'));
            $bullets[] = sprintf(
                '%d %s from the prior period haven\'t %s this period (totalling %s previously).',
                count($lapsed),
                count($lapsed) === 1 ? $entity : $entityPlural,
                $type === 'income' ? 'given' : 'been paid',
                self::usd($lapsedTotal),
            );
        }

        // Retention
        if ($hasCompare && $retentionRate !== null) {
            $bullets[] = sprintf(
                'Retention rate: %d%% of last period\'s %s %s again.',
                (int) round($retentionRate * 100),
                $entityPlural,
                $type === 'income' ? 'gave' : 'were paid',
            );
        }

        return $bullets;
    }

    /**
     * Auto-generate 3-5 plain-English insight bullets at the bottom of
     * the report. The "board-pitch" differentiator — every report ends
     * with the equivalent of a CFO's summary email.
     */
    private function buildInsights(array $income, array $expense, float $netChange, ?float $priorNet, bool $hasCompare): array
    {
        $bullets = [];

        // Income trend (only if comparing)
        if ($hasCompare && isset($income['prior_total'])) {
            $prior = $income['prior_total'];
            if ($prior > 0) {
                $delta = ($income['total'] - $prior) / $prior;
                $direction = $delta >= 0 ? 'up' : 'down';
                $bullets[] = sprintf(
                    'Income is %s %s versus the prior period.',
                    $direction,
                    number_format(abs($delta) * 100, 0) . '%',
                );
            } elseif ($income['total'] > 0) {
                $bullets[] = 'Income recorded for the first time this period.';
            }
        }

        // Largest income source
        if (! empty($income['categories'])) {
            $top = $income['categories'][0];
            $bullets[] = sprintf(
                '%s was the largest income source at %s%% of total income.',
                $top['name'],
                number_format($top['share'] * 100, 0),
            );
        }

        // Largest expense category
        if (! empty($expense['categories'])) {
            $top = $expense['categories'][0];
            $bullets[] = sprintf(
                '%s was the largest expense at %s%% of total expenses.',
                $top['name'],
                number_format($top['share'] * 100, 0),
            );
        }

        // Net change
        if ($netChange >= 0) {
            $bullets[] = sprintf('The organization saw a positive change of %s in net assets.', self::usd($netChange));
        } else {
            $bullets[] = sprintf('Expenses exceeded income by %s for the period.', self::usd(abs($netChange)));
        }

        // Empty-state fallback
        if (empty($income['categories']) && empty($expense['categories'])) {
            $bullets = ['No completed transactions were recorded for this period.'];
        }

        return $bullets;
    }

    // ─── Phase 7.4.a — Statement of Functional Expenses ──────────────────────

    /**
     * Statement of Functional Expenses — IRS Form 990 expense rollup.
     *
     * Cross-tabulates completed expense transactions by NFP-functional
     * classification (Program / Management & General / Fundraising) using
     * the function_classification column added to finance_categories in
     * Phase 7.4.a. Income is excluded by definition.
     *
     * The headline metric is the **program ratio** — % of expenses that
     * went to Program. Watchdog charity-raters benchmark at 75%+; under
     * 65% is a yellow flag in donor screening.
     *
     * @return array{
     *   period: array{label:string, from:Carbon, to:Carbon},
     *   compare: ?array{label:string, from:Carbon, to:Carbon},
     *   by_function: array<string, array{
     *     key:string, label:string, color:string, total:float, count:int,
     *     share:float, categories: array<int, array{name:string, amount:float}>,
     *     prior_total?:float, prior_share?:float, delta?:?float
     *   }>,
     *   total: float,
     *   prior_total: ?float,
     *   program_ratio: float,
     *   prior_program_ratio: ?float,
     *   insights: array<int, string>,
     * }
     */
    public function functionalExpenses(Carbon $from, Carbon $to, ?Carbon $compareFrom = null, ?Carbon $compareTo = null): array
    {
        // Each function gets a distinct brand colour so the donut + table
        // headers read consistently across the report and its exports.
        $functions = [
            'program'            => ['label' => 'Program',              'color' => '#1b2b4b'], // navy 700
            'management_general' => ['label' => 'Management & General', 'color' => '#f97316'], // brand orange
            'fundraising'        => ['label' => 'Fundraising',          'color' => '#3a4a6b'], // navy 500
        ];

        // Initialize buckets so every function appears in the output even
        // when there are zero rows under it (clearer than missing keys).
        $byFunction = [];
        foreach ($functions as $key => $meta) {
            $byFunction[$key] = [
                'key'        => $key,
                'label'      => $meta['label'],
                'color'      => $meta['color'],
                'total'      => 0.0,
                'count'      => 0,
                'share'      => 0.0,
                'categories' => [],
            ];
        }

        $rows = FinanceTransaction::query()
            ->where('transaction_type', 'expense')
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$from, $to])
            ->with('category:id,name,function_classification')
            ->get(['id', 'transaction_date', 'amount', 'category_id']);

        // Buffer category sums per function so we can sort them at the end.
        $catSums = [
            'program'            => [],
            'management_general' => [],
            'fundraising'        => [],
        ];

        foreach ($rows as $tx) {
            $func    = $tx->category?->function_classification ?? 'program';
            $catName = $tx->category?->name ?? '(Uncategorised)';
            $amount  = (float) $tx->amount;

            // Defense — if category somehow has an unknown function value
            // (e.g. legacy data), fold it into program rather than 500.
            if (! isset($byFunction[$func])) {
                $func = 'program';
            }

            $byFunction[$func]['total'] += $amount;
            $byFunction[$func]['count']++;
            $catSums[$func][$catName] = ($catSums[$func][$catName] ?? 0) + $amount;
        }

        $total = (float) array_sum(array_column($byFunction, 'total'));

        // Inline category breakdown into each function (sorted desc by amount).
        foreach ($byFunction as $key => &$f) {
            arsort($catSums[$key]);
            $f['categories'] = [];
            foreach ($catSums[$key] as $name => $amount) {
                $f['categories'][] = [
                    'name'   => $name,
                    'amount' => (float) $amount,
                    'share'  => $f['total'] > 0 ? ($amount / $f['total']) : 0.0,
                ];
            }
            $f['share'] = $total > 0 ? ($f['total'] / $total) : 0.0;
        }
        unset($f);

        // Prior-period comparison
        $priorTotal       = null;
        $priorByFunction  = ['program' => 0.0, 'management_general' => 0.0, 'fundraising' => 0.0];
        $priorProgramRatio = null;
        if ($compareFrom) {
            $priorRows = FinanceTransaction::query()
                ->where('transaction_type', 'expense')
                ->where('status', 'completed')
                ->whereBetween('transaction_date', [$compareFrom, $compareTo])
                ->with('category:id,function_classification')
                ->get(['amount', 'category_id']);

            foreach ($priorRows as $tx) {
                $func = $tx->category?->function_classification ?? 'program';
                if (! isset($priorByFunction[$func])) {
                    $func = 'program';
                }
                $priorByFunction[$func] += (float) $tx->amount;
            }

            $priorTotal = (float) array_sum($priorByFunction);

            foreach ($byFunction as $key => &$f) {
                $f['prior_total'] = $priorByFunction[$key];
                $f['prior_share'] = $priorTotal > 0 ? ($priorByFunction[$key] / $priorTotal) : 0.0;
                $f['delta']       = $priorByFunction[$key] > 0
                    ? ($f['total'] - $priorByFunction[$key]) / $priorByFunction[$key]
                    : null;
            }
            unset($f);

            $priorProgramRatio = $priorTotal > 0
                ? ($priorByFunction['program'] / $priorTotal)
                : null;
        }

        $programRatio = $total > 0 ? ($byFunction['program']['total'] / $total) : 0.0;

        return [
            'period'              => ['label' => $this->labelFor($from, $to), 'from' => $from, 'to' => $to],
            'compare'             => $compareFrom
                ? ['label' => $this->labelFor($compareFrom, $compareTo), 'from' => $compareFrom, 'to' => $compareTo]
                : null,
            'by_function'         => $byFunction,
            'total'               => $total,
            'prior_total'         => $priorTotal,
            'program_ratio'       => $programRatio,
            'prior_program_ratio' => $priorProgramRatio,
            'insights'            => $this->buildFunctionalExpensesInsights($byFunction, $total, $programRatio, $priorTotal, $priorProgramRatio),
        ];
    }

    /** Insight bullets for Statement of Functional Expenses. */
    private function buildFunctionalExpensesInsights(array $byFunction, float $total, float $programRatio, ?float $priorTotal, ?float $priorProgramRatio): array
    {
        $bullets = [];

        if ($total <= 0) {
            return ['No completed expenses recorded for this period.'];
        }

        $bullets[] = sprintf('Total expenses: %s across %d transaction%s.',
            self::usd($total),
            array_sum(array_column($byFunction, 'count')),
            array_sum(array_column($byFunction, 'count')) === 1 ? '' : 's'
        );

        // Headline metric — program ratio. Industry guidance: 75%+ green,
        // 65–75% yellow, <65% concerning to donor watchdogs.
        $ratioPct = number_format($programRatio * 100, 1);
        if ($programRatio >= 0.75) {
            $bullets[] = sprintf('Program ratio: %s%% — meets the 75%%+ benchmark used by major charity raters.', $ratioPct);
        } elseif ($programRatio >= 0.65) {
            $bullets[] = sprintf('Program ratio: %s%% — within the 65–75%% yellow zone for charity-rating benchmarks.', $ratioPct);
        } else {
            $bullets[] = sprintf('Program ratio: %s%% — below the 65%% benchmark used by donor watchdogs; review overhead allocation.', $ratioPct);
        }

        // Each function's share + delta vs prior
        foreach ($byFunction as $f) {
            if ($f['total'] <= 0) {
                continue;
            }
            $line = sprintf('%s: %s (%s%% of expenses)',
                $f['label'],
                self::usd($f['total']),
                number_format($f['share'] * 100, 1)
            );
            if (isset($f['delta']) && $f['delta'] !== null) {
                $arrow = $f['delta'] >= 0 ? '▲' : '▼';
                $line .= sprintf(' %s %s%% vs prior period', $arrow, number_format(abs($f['delta']) * 100, 1));
            }
            $bullets[] = $line . '.';
        }

        // Prior program ratio comparison (if compare mode is on)
        if ($priorProgramRatio !== null) {
            $direction = $programRatio >= $priorProgramRatio ? 'up from' : 'down from';
            $bullets[] = sprintf('Prior period program ratio was %s%% (%s %s%% this period).',
                number_format($priorProgramRatio * 100, 1),
                $direction,
                $ratioPct
            );
        }

        return $bullets;
    }

    // ─── Phase 7.4.b — Budget vs. Actual / Variance ──────────────────────────

    /**
     * Budget vs. Actual report. Compares budgeted amounts (from the Phase
     * 7.4.b `budgets` table) against actual completed transaction amounts
     * for each category in the period.
     *
     * Direction defaults to 'expense' (the typical "budget" use case) but
     * 'income' is supported (grant pipeline budgeting) and 'both' shows
     * both sides separately.
     *
     * Variance = actual − budget. For expense categories, **negative
     * variance is good** (under budget); for income, **positive variance
     * is good** (over plan). The blade flips colour semantics by direction.
     *
     * Event filter: when $eventId is non-null, only budgets with
     * event_id = $eventId AND transactions with event_id = $eventId are
     * considered. When null (default), both org-wide budgets (event_id
     * IS NULL) AND any per-event budgets in the period are summed together.
     *
     * @return array{
     *   period: array{label:string, from:Carbon, to:Carbon},
     *   direction: string,
     *   event_id: ?int,
     *   rows: array<int, array{
     *     category_id:int, category_name:string, type:string,
     *     budget:float, actual:float, variance:float, variance_pct:?float,
     *     status:string,
     *   }>,
     *   totals: array{budget:float, actual:float, variance:float, variance_pct:?float},
     *   over_budget: array<int, array{name:string, variance:float}>,
     *   insights: array<int, string>,
     * }
     */
    public function budgetVsActual(Carbon $from, Carbon $to, string $direction = 'expense', ?int $eventId = null): array
    {
        $direction = in_array($direction, ['income', 'expense', 'both'], true) ? $direction : 'expense';

        // Pull budgets in the period scope. event_id filter:
        //   - null (default) → all budgets (org-wide + per-event)
        //   - int            → only budgets for that event
        $budgetQuery = Budget::query()
            ->where('period_start', '>=', $from->copy()->startOfDay())
            ->where('period_start', '<=', $to->copy()->endOfDay());
        if ($eventId !== null) {
            $budgetQuery->where('event_id', $eventId);
        }
        $budgets = $budgetQuery->with('category:id,name,type')->get(['category_id', 'event_id', 'amount']);

        // Sum budgets per category
        $budgetByCat = []; // [category_id => float]
        $catNames    = []; // [category_id => string]
        $catTypes    = []; // [category_id => 'income'|'expense']
        foreach ($budgets as $b) {
            if (! $b->category) {
                continue;
            }
            // Direction filter at the budget side
            if ($direction !== 'both' && $b->category->type !== $direction) {
                continue;
            }
            $budgetByCat[$b->category_id] = ($budgetByCat[$b->category_id] ?? 0) + (float) $b->amount;
            $catNames[$b->category_id]    = $b->category->name;
            $catTypes[$b->category_id]    = $b->category->type;
        }

        // Pull actuals in the period
        $actualQuery = FinanceTransaction::query()
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [$from, $to]);
        if ($direction !== 'both') {
            $actualQuery->where('transaction_type', $direction);
        }
        if ($eventId !== null) {
            $actualQuery->where('event_id', $eventId);
        }
        $actualRows = $actualQuery->with('category:id,name,type')->get(['category_id', 'amount', 'transaction_type']);

        $actualByCat = [];
        foreach ($actualRows as $tx) {
            $catId = $tx->category_id;
            if (! $catId) {
                continue; // skip uncategorised — no budget to compare against
            }
            // If the category wasn't in any budget but matches the direction,
            // still surface it (over-spending an unbudgeted category is the
            // headline anomaly the report exists to catch).
            if (! isset($catNames[$catId])) {
                $cat = $tx->category;
                if (! $cat) continue;
                if ($direction !== 'both' && $cat->type !== $direction) continue;
                $catNames[$catId] = $cat->name;
                $catTypes[$catId] = $cat->type;
                $budgetByCat[$catId] = 0.0; // explicit zero budget
            }
            $actualByCat[$catId] = ($actualByCat[$catId] ?? 0) + (float) $tx->amount;
        }

        // Build per-category rows
        $rows = [];
        $totalBudget = 0.0;
        $totalActual = 0.0;
        foreach ($catNames as $catId => $name) {
            $budget   = (float) ($budgetByCat[$catId] ?? 0);
            $actual   = (float) ($actualByCat[$catId] ?? 0);
            $variance = $actual - $budget;
            $variancePct = $budget > 0 ? ($variance / $budget) : null;

            // Status semantics depend on direction:
            //   expense: actual > budget  → 'over'  (bad);  actual <= budget → 'under' (good)
            //   income:  actual < budget  → 'under' (bad);  actual >= budget → 'over'  (good)
            $type = $catTypes[$catId];
            if ($type === 'expense') {
                $status = abs($variance) < 0.005 ? 'on_target' : ($variance > 0 ? 'over' : 'under');
            } else {
                $status = abs($variance) < 0.005 ? 'on_target' : ($actual < $budget ? 'under' : 'over');
            }

            $rows[] = [
                'category_id'   => $catId,
                'category_name' => $name,
                'type'          => $type,
                'budget'        => $budget,
                'actual'        => $actual,
                'variance'      => $variance,
                'variance_pct'  => $variancePct,
                'status'        => $status,
            ];

            $totalBudget += $budget;
            $totalActual += $actual;
        }

        // Sort: largest absolute variance first (most actionable rows up top)
        usort($rows, fn ($a, $b) => abs($b['variance']) <=> abs($a['variance']));

        $totalVariance    = $totalActual - $totalBudget;
        $totalVariancePct = $totalBudget > 0 ? ($totalVariance / $totalBudget) : null;

        // Over-budget callout: top 3 expense categories that overspent
        $overBudget = [];
        foreach ($rows as $r) {
            if ($r['type'] === 'expense' && $r['status'] === 'over') {
                $overBudget[] = ['name' => $r['category_name'], 'variance' => $r['variance']];
            }
        }
        $overBudget = array_slice($overBudget, 0, 3);

        return [
            'period'      => ['label' => $this->labelFor($from, $to), 'from' => $from, 'to' => $to],
            'direction'   => $direction,
            'event_id'    => $eventId,
            'rows'        => $rows,
            'totals'      => [
                'budget'       => $totalBudget,
                'actual'       => $totalActual,
                'variance'     => $totalVariance,
                'variance_pct' => $totalVariancePct,
            ],
            'over_budget' => $overBudget,
            'insights'    => $this->buildBudgetInsights($rows, $totalBudget, $totalActual, $totalVariance, $direction),
        ];
    }

    /** Insight bullets for Budget vs. Actual. */
    private function buildBudgetInsights(array $rows, float $totalBudget, float $totalActual, float $totalVariance, string $direction): array
    {
        $bullets = [];

        if ($totalBudget <= 0 && $totalActual <= 0) {
            return ['No budgets seeded and no actuals recorded — add budgets via /finance/budgets to start tracking variance.'];
        }

        if ($totalBudget <= 0) {
            $bullets[] = sprintf('No budgets seeded for this period yet — actuals total %s with no plan to compare against.', self::usd($totalActual));
            return $bullets;
        }

        $bullets[] = sprintf('Total budget: %s vs. actual: %s (variance: %s%s).',
            self::usd($totalBudget),
            self::usd($totalActual),
            $totalVariance >= 0 ? '+' : '',
            self::usd($totalVariance)
        );

        // Direction-aware overall verdict
        if ($direction === 'expense') {
            if ($totalVariance > 0) {
                $bullets[] = sprintf('Overall expenses ran %s over budget (%s%% above plan).',
                    self::usd($totalVariance),
                    $totalBudget > 0 ? number_format(($totalVariance / $totalBudget) * 100, 1) : '—'
                );
            } else {
                $bullets[] = sprintf('Overall expenses came in %s under budget — discipline maintained.',
                    self::usd(abs($totalVariance))
                );
            }
        } elseif ($direction === 'income') {
            if ($totalVariance >= 0) {
                $bullets[] = sprintf('Income exceeded plan by %s — pipeline performing above target.',
                    self::usd($totalVariance)
                );
            } else {
                $bullets[] = sprintf('Income fell short of plan by %s — review fundraising pipeline.',
                    self::usd(abs($totalVariance))
                );
            }
        }

        // Top variance row
        if (! empty($rows)) {
            $top = $rows[0]; // already sorted by abs(variance) desc
            if (abs($top['variance']) >= 0.01) {
                $arrow = $top['variance'] >= 0 ? 'over' : 'under';
                $bullets[] = sprintf('Largest variance: %s — %s by %s.',
                    $top['category_name'],
                    $arrow,
                    self::usd(abs($top['variance']))
                );
            }
        }

        // Count of rows over budget (expense direction only)
        $overCount = count(array_filter($rows, fn ($r) => $r['type'] === 'expense' && $r['status'] === 'over'));
        if ($overCount > 0 && $direction !== 'income') {
            $bullets[] = sprintf('%d expense categor%s over budget.',
                $overCount,
                $overCount === 1 ? 'y is' : 'ies are'
            );
        }

        return $bullets;
    }
}
