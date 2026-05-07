<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Household;
use App\Models\InventoryItem;
use App\Models\Visit;
use App\Models\Volunteer;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Per-widget permission gating. Each section below is computed only if
        // the user can actually see it; the view skips rendering missing data.
        // Without these gates, the dashboard leaks aggregate org metrics
        // (bundles served, households served, low-stock counts, etc.) to any
        // authenticated user regardless of their role's permissions.
        $user = auth()->user();
        $canVisits     = $user?->hasPermission('checkin.view')    ?? false;
        $canHouseholds = $user?->hasPermission('households.view') ?? false;
        $canVolunteers = $user?->hasPermission('volunteers.view') ?? false;
        $canEvents     = $user?->hasPermission('events.view')     ?? false;
        $canInventory  = $user?->hasPermission('inventory.view')  ?? false;

        // ── Settings ─────────────────────────────────────────────────────────

        $dashboardDefaultEvent = SettingService::get('general.dashboard_default_event', 'current');
        $showLowStockAlert     = (bool) SettingService::get('inventory.dashboard_low_stock_alert', true);
        $chartDefaultPeriod    = SettingService::get('system.chart_default_period', 'year');

        // Resolve chart year: for 'year' or 'ytd' use current year; others pass period to view
        $year = now()->year;

        // ── Stat Cards ────────────────────────────────────────────────────────
        // Each stat is gated independently. Defaults to 0 so the view can still
        // render without conditionals when a stat is hidden — we use @can in
        // the blade to actually drop the card. Keeps the controller boring.

        $stats = [
            'total_bundles'     => 0,
            'bundles_change'    => 0,
            'bundles_up'        => true,
            'households_served' => 0,
            'total_households'  => 0,
            'people_served'     => 0,
            'volunteers'        => 0,
        ];

        if ($canVisits) {
            $totalBundles = Visit::where('visit_status', 'exited')->sum('served_bags');

            // Bundles this month vs last month for % change
            $bundlesThisMonth = Visit::where('visit_status', 'exited')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('served_bags');

            $lastMonthDate    = now()->subMonth();
            $bundlesLastMonth = Visit::where('visit_status', 'exited')
                ->whereYear('created_at', $lastMonthDate->year)
                ->whereMonth('created_at', $lastMonthDate->month)
                ->sum('served_bags');

            $bundlesChange = $bundlesLastMonth > 0
                ? round((($bundlesThisMonth - $bundlesLastMonth) / $bundlesLastMonth) * 100)
                : ($bundlesThisMonth > 0 ? 100 : 0);

            // Distinct households that have had at least one exited visit
            $householdsServed = DB::table('visit_households')
                ->join('visits', 'visit_households.visit_id', '=', 'visits.id')
                ->where('visits.visit_status', 'exited')
                ->distinct('visit_households.household_id')
                ->count('visit_households.household_id');

            // People served — sum of household_size across all exited-visit households
            $peopleServed = DB::table('visit_households')
                ->join('visits', 'visit_households.visit_id', '=', 'visits.id')
                ->join('households', 'visit_households.household_id', '=', 'households.id')
                ->where('visits.visit_status', 'exited')
                ->sum('households.household_size');

            $stats['total_bundles']     = $totalBundles;
            $stats['bundles_change']    = $bundlesChange;
            $stats['bundles_up']        = $bundlesChange >= 0;
            $stats['households_served'] = $householdsServed;
            $stats['people_served']     = $peopleServed;
        }

        if ($canHouseholds) {
            $stats['total_households'] = Household::count();
        }

        if ($canVolunteers) {
            $stats['volunteers'] = Volunteer::count();
        }

        // ── Monthly Distribution Chart ────────────────────────────────────────

        // For 'last_year', shift back one year; otherwise use current year
        $chartYear  = $chartDefaultPeriod === 'last_year' ? $year - 1 : $year;

        $monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $monthlyData = array_fill(0, 12, 0);

        if ($canVisits) {
            // Group in PHP rather than SQL so the query is portable across MySQL
            // and SQLite (the test runner uses sqlite, which has no MONTH() fn).
            // Volume is bounded by a year of completed visits — trivial in memory.
            $monthlyRaw = Visit::where('visit_status', 'exited')
                ->whereYear('created_at', $chartYear)
                ->get(['created_at', 'served_bags'])
                ->groupBy(fn ($v) => $v->created_at->month)
                ->map(fn ($group) => (int) $group->sum('served_bags'))
                ->toArray();

            for ($m = 1; $m <= 12; $m++) {
                $monthlyData[$m - 1] = (int) ($monthlyRaw[$m] ?? 0);
            }
        }

        // ── Family Composition (donut chart) ─────────────────────────────────
        //
        // Aggregate of children/adults/seniors across all registered households —
        // the actionable lens for grant reporting and ration planning. Gated on
        // households.view since it surfaces demographic counts.

        $compositionData = [
            'children'   => 0,
            'adults'     => 0,
            'seniors'    => 0,
            'total'      => 0,
            'households' => 0,
            'pct'        => ['children' => 0, 'adults' => 0, 'seniors' => 0],
            'hex'        => ['children' => '#1b2b4b', 'adults' => '#f59e0b', 'seniors' => '#d1d5db'],
            'class'      => ['children' => 'bg-navy-700', 'adults' => 'bg-amber-500', 'seniors' => 'bg-gray-300'],
        ];

        if ($canHouseholds) {
            // COALESCE hardens against legacy rows where counts might be null
            // even though current schema defaults them to 0.
            $composition = Household::selectRaw("
                SUM(COALESCE(children_count, 0)) AS children,
                SUM(COALESCE(adults_count,   0)) AS adults,
                SUM(COALESCE(seniors_count,  0)) AS seniors,
                SUM(COALESCE(household_size, 0)) AS people,
                COUNT(*) AS households
            ")->first();

            $peopleTotal = max(1, (int) $composition->people);
            $compositionData['children']   = (int) $composition->children;
            $compositionData['adults']     = (int) $composition->adults;
            $compositionData['seniors']    = (int) $composition->seniors;
            $compositionData['total']      = (int) $composition->people;
            $compositionData['households'] = (int) $composition->households;
            $compositionData['pct'] = [
                'children' => round($composition->children / $peopleTotal * 100),
                'adults'   => round($composition->adults   / $peopleTotal * 100),
                'seniors'  => round($composition->seniors  / $peopleTotal * 100),
            ];

            // Rank-based palette so the largest demographic is always navy, the
            // middle one amber, and the smallest gray — visually stable colour
            // weights regardless of which category currently dominates the data.
            $buckets = [
                'children' => $compositionData['children'],
                'adults'   => $compositionData['adults'],
                'seniors'  => $compositionData['seniors'],
            ];
            arsort($buckets); // largest → smallest, keys preserved
            $rankPalette = ['#1b2b4b', '#f59e0b', '#d1d5db']; // navy-700, amber-500, gray-300
            $rankClasses = ['bg-navy-700', 'bg-amber-500', 'bg-gray-300'];
            $rank = 0;
            foreach (array_keys($buckets) as $key) {
                $compositionData['hex'][$key]   = $rankPalette[$rank];
                $compositionData['class'][$key] = $rankClasses[$rank];
                $rank++;
            }
        }

        // ── Events ────────────────────────────────────────────────────────────

        $currentEvent = null;
        $nextEvent    = null;
        $recentEvents = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 7, 1, ['pageName' => 'events_page']);

        if ($canEvents) {
            // dashboard_default_event: 'current' | 'recent' | 'none'
            if ($dashboardDefaultEvent !== 'none') {
                $eventQuery = $dashboardDefaultEvent === 'recent'
                    ? Event::past()->orderByDesc('date')
                    : Event::current();

                $currentEvent = $eventQuery
                    ->withCount([
                        'visits as total_visits',
                        'visits as exited_count' => fn ($q) => $q->where('visit_status', 'exited'),
                        'visits as active_count' => fn ($q) => $q->where('visit_status', '!=', 'exited'),
                    ])
                    ->first();
            }

            $nextEvent = Event::upcoming()
                ->orderBy('date')
                ->withCount('assignedVolunteers')
                ->first();

            // Dashboard tables are capped at 7 per page so the dashboard never
            // becomes a long scroll. Each table uses its own page query string
            // (events_page, stock_page) so the two paginate independently.
            $recentEvents = Event::past()
                ->orderByDesc('date')
                ->withCount([
                    'visits as exited_count' => fn ($q) => $q->where('visit_status', 'exited'),
                ])
                ->paginate(7, ['id', 'name', 'date', 'location', 'status'], 'events_page')
                ->withQueryString();
        }

        // ── Inventory Alerts ──────────────────────────────────────────────────
        // Combine out-of-stock + low-stock into a single paginated list, sorted
        // by severity (out-of-stock first), then alphabetically. Header summary
        // counts are computed separately so they reflect the totals across all
        // pages, not just the current one.

        $stockAlerts     = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 7, 1, ['pageName' => 'stock_page']);
        $outOfStockCount = 0;
        $lowStockCount   = 0;

        if ($canInventory && $showLowStockAlert) {
            $outOfStockCount = InventoryItem::active()->where('quantity_on_hand', 0)->count();
            // Exclude the truly-out items from the low-stock count so the two
            // numbers never double-count the same item.
            $lowStockCount = InventoryItem::active()
                ->where('reorder_level', '>', 0)
                ->where('quantity_on_hand', '>', 0)
                ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                ->count();

            $stockAlerts = InventoryItem::active()
                ->where(function ($q) {
                    $q->where('quantity_on_hand', 0)
                      ->orWhere(function ($q2) {
                          $q2->where('reorder_level', '>', 0)
                             ->whereColumn('quantity_on_hand', '<=', 'reorder_level');
                      });
                })
                ->with('category')
                // Out-of-stock first (CASE evaluates to 0); rest by name.
                ->orderByRaw('CASE WHEN quantity_on_hand = 0 THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->paginate(7, ['*'], 'stock_page')
                ->withQueryString();
        }

        return view('dashboard.index', compact(
            'stats',
            'monthLabels',
            'monthlyData',
            'compositionData',
            'currentEvent',
            'nextEvent',
            'recentEvents',
            'stockAlerts',
            'outOfStockCount',
            'lowStockCount',
            'showLowStockAlert',
            'chartDefaultPeriod',
            'chartYear',
            'canVisits',
            'canHouseholds',
            'canVolunteers',
            'canEvents',
            'canInventory',
        ));
    }
}
