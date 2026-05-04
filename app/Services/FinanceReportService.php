<?php

namespace App\Services;

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
     * Phase 7.1.b — Fixed 8-color palette for charts. Same colour for
     * the same category across reports (Donations always navy, Food
     * Supplies always red, etc.) so board members recognise categories
     * after the second report. Colors chosen to be distinguishable in
     * print, color-blind safer than full-saturation rainbow, and stable
     * across both income and expense families.
     */
    public const PALETTE = [
        '#1b2b4b', // navy — primary brand
        '#f97316', // orange — brand accent
        '#047857', // emerald
        '#0284c7', // sky
        '#7c3aed', // violet
        '#d97706', // amber
        '#0d9488', // teal
        '#e11d48', // rose
    ];

    /**
     * Stable palette assignment by category name — hash the name and
     * pick a palette slot. Same input always returns the same colour.
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

        $categories = [];
        foreach ($byCat as $name => $amount) {
            $categories[] = [
                'name'   => $name,
                'amount' => (float) $amount,
                'color'  => self::colorFor($name),
                'share'  => $total > 0 ? ($amount / $total) : 0.0,
            ];
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
}
