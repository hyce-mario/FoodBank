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
        // ── Settings ─────────────────────────────────────────────────────────

        $dashboardDefaultEvent = SettingService::get('general.dashboard_default_event', 'current');
        $showLowStockAlert     = (bool) SettingService::get('inventory.dashboard_low_stock_alert', true);
        $chartDefaultPeriod    = SettingService::get('system.chart_default_period', 'year');

        // Resolve chart year: for 'year' or 'ytd' use current year; others pass period to view
        $year = now()->year;

        // ── Stat Cards ────────────────────────────────────────────────────────

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

        $totalVolunteers  = Volunteer::count();
        $totalHouseholds  = Household::count();

        $stats = [
            'total_bundles'     => $totalBundles,
            'bundles_change'    => $bundlesChange,
            'bundles_up'        => $bundlesChange >= 0,
            'households_served' => $householdsServed,
            'total_households'  => $totalHouseholds,
            'people_served'     => $peopleServed,
            'volunteers'        => $totalVolunteers,
        ];

        // ── Monthly Distribution Chart ────────────────────────────────────────

        // For 'last_year', shift back one year; otherwise use current year
        $chartYear  = $chartDefaultPeriod === 'last_year' ? $year - 1 : $year;

        $monthlyRaw = Visit::where('visit_status', 'exited')
            ->whereYear('created_at', $chartYear)
            ->selectRaw('MONTH(created_at) as month, SUM(served_bags) as bundles, COUNT(*) as visits')
            ->groupBy('month')
            ->pluck('bundles', 'month')
            ->toArray();

        $monthLabels  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $monthlyData  = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[] = (int) ($monthlyRaw[$m] ?? 0);
        }

        // ── Household Size Distribution (donut chart) ─────────────────────────

        $sizeDist = Household::selectRaw("
            SUM(CASE WHEN household_size <= 2 THEN 1 ELSE 0 END)             AS small,
            SUM(CASE WHEN household_size >= 3 AND household_size <= 4 THEN 1 ELSE 0 END) AS medium,
            SUM(CASE WHEN household_size >= 5 THEN 1 ELSE 0 END)              AS large,
            COUNT(*) AS total
        ")->first();

        $sizeTotal  = max(1, (int) $sizeDist->total);
        $sizeData   = [
            'small'  => (int) $sizeDist->small,
            'medium' => (int) $sizeDist->medium,
            'large'  => (int) $sizeDist->large,
            'total'  => $sizeTotal,
            'pct'    => [
                'small'  => round($sizeDist->small  / $sizeTotal * 100),
                'medium' => round($sizeDist->medium / $sizeTotal * 100),
                'large'  => round($sizeDist->large  / $sizeTotal * 100),
            ],
        ];

        // ── Events ────────────────────────────────────────────────────────────

        // dashboard_default_event: 'current' | 'recent' | 'none'
        $currentEvent = null;
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

        $recentEvents = Event::past()
            ->orderByDesc('date')
            ->limit(5)
            ->withCount([
                'visits as exited_count' => fn ($q) => $q->where('visit_status', 'exited'),
            ])
            ->get(['id', 'name', 'date', 'location', 'status']);

        // ── Inventory Alerts ──────────────────────────────────────────────────

        $outOfStockItems = collect();
        $lowStockItems   = collect();

        if ($showLowStockAlert) {
            $outOfStockItems = InventoryItem::active()
                ->outOfStock()
                ->with('category')
                ->orderBy('name')
                ->get();

            $lowStockItems = InventoryItem::active()
                ->lowStock()
                ->with('category')
                ->orderBy('name')
                ->get();
        }

        return view('dashboard.index', compact(
            'stats',
            'monthLabels',
            'monthlyData',
            'sizeData',
            'currentEvent',
            'nextEvent',
            'recentEvents',
            'outOfStockItems',
            'lowStockItems',
            'showLowStockAlert',
            'chartDefaultPeriod',
            'chartYear',
        ));
    }
}
