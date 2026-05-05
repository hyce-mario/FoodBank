<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Household;
use App\Services\InventoryReportService;
use App\Services\ReportAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function __construct(
        protected ReportAnalyticsService  $analytics,
        protected InventoryReportService  $inventoryReport,
    ) {}

    // ─── Shared: resolve date range + pass to view ───────────────────────────

    private function filterParams(Request $request): array
    {
        [$from, $to] = $this->analytics->resolveDateRange($request);

        return [
            'from'   => $from,
            'to'     => $to,
            'preset' => $request->get('preset', 'last_30'),
            'date_from' => $request->get('date_from', ''),
            'date_to'   => $request->get('date_to', ''),
        ];
    }

    // ─── Overview ─────────────────────────────────────────────────────────────

    public function overview(Request $request): View
    {
        $filter  = $this->filterParams($request);
        $from    = $filter['from'];
        $to      = $filter['to'];

        $overview  = $this->analytics->overview($from, $to);
        $trend     = $this->analytics->overviewTrend($from, $to);
        $insights  = $this->analytics->insights($from, $to, $overview);

        return view('reports.overview', compact('filter', 'overview', 'trend', 'insights'));
    }

    // ─── Event Performance ────────────────────────────────────────────────────

    public function events(Request $request): View
    {
        $filter = $this->filterParams($request);
        $events = $this->analytics->eventPerformance($filter['from'], $filter['to']);

        return view('reports.events', compact('filter', 'events'));
    }

    // ─── Trends ───────────────────────────────────────────────────────────────

    public function trends(Request $request): View
    {
        $filter = $this->filterParams($request);
        $trends = $this->analytics->trends($filter['from'], $filter['to']);

        return view('reports.trends', compact('filter', 'trends'));
    }

    // ─── Demographics ─────────────────────────────────────────────────────────

    public function demographics(Request $request): View
    {
        $filter = $this->filterParams($request);
        $demo   = $this->analytics->demographics($filter['from'], $filter['to']);

        return view('reports.demographics', compact('filter', 'demo'));
    }

    // ─── Lane Performance ─────────────────────────────────────────────────────

    public function lanes(Request $request): View
    {
        $filter  = $this->filterParams($request);
        $eventId = $request->filled('event_id') ? (int) $request->event_id : null;
        $data    = $this->analytics->lanePerformance($filter['from'], $filter['to'], $eventId);

        return view('reports.lanes', array_merge(compact('filter'), $data, compact('eventId')));
    }

    // ─── Queue Flow ───────────────────────────────────────────────────────────

    public function queueFlow(Request $request): View
    {
        $filter  = $this->filterParams($request);
        $eventId = $request->filled('event_id') ? (int) $request->event_id : null;
        $data    = $this->analytics->queueFlow($filter['from'], $filter['to'], $eventId);

        return view('reports.queue-flow', array_merge(compact('filter'), $data, compact('eventId')));
    }

    // ─── Volunteers ───────────────────────────────────────────────────────────

    public function volunteers(Request $request): View
    {
        $filter = $this->filterParams($request);
        $data   = $this->analytics->volunteers($filter['from'], $filter['to']);

        return view('reports.volunteers', array_merge(compact('filter'), $data));
    }

    // ─── Reviews ─────────────────────────────────────────────────────────────

    public function reviews(Request $request): View
    {
        $filter = $this->filterParams($request);
        $data   = $this->analytics->reviews($filter['from'], $filter['to']);

        return view('reports.reviews', array_merge(compact('filter'), $data));
    }

    // ─── Inventory ────────────────────────────────────────────────────────────

    public function inventory(Request $request): View
    {
        $filter = $this->filterParams($request);
        $from   = $filter['from'];
        $to     = $filter['to'];

        $summary     = $this->inventoryReport->summary($from, $to);
        $topItems    = $this->inventoryReport->topDistributedItems($from, $to, 10);
        $chartData   = $this->inventoryReport->topItemsChartData($topItems);
        $timeChart   = $this->inventoryReport->distributionOverTime($from, $to);
        $eventUsage  = $this->inventoryReport->eventInventoryUsage($from, $to);
        $wasteItems  = $this->inventoryReport->wasteBreakdown($from, $to);

        return view('reports.inventory', compact(
            'filter', 'summary', 'topItems', 'chartData', 'timeChart', 'eventUsage', 'wasteItems'
        ));
    }

    // ─── First-Timers Report ──────────────────────────────────────────────────

    public function firstTimers(Request $request): View
    {
        $filter = $this->filterParams($request);
        $from   = $filter['from'];
        $to     = $filter['to'];

        $fromDate = $from->format('Y-m-d');
        $toDate   = $to->format('Y-m-d');

        // ── KPI stats (date-range only, no extra filters) ─────────────────────
        $totalFirstTimers = DB::select("
            SELECT COUNT(*) AS total FROM (
                SELECT h.id
                FROM households h
                JOIN visit_households vh ON vh.household_id = h.id
                JOIN visits v ON vh.visit_id = v.id
                JOIN events e ON v.event_id = e.id
                GROUP BY h.id
                HAVING MIN(e.date) >= ? AND MIN(e.date) <= ?
            ) AS ft
        ", [$fromDate, $toDate])[0]->total ?? 0;

        $representedFirstTimers = DB::select("
            SELECT COUNT(*) AS total FROM (
                SELECT h.id
                FROM households h
                JOIN visit_households vh ON vh.household_id = h.id
                JOIN visits v ON vh.visit_id = v.id
                JOIN events e ON v.event_id = e.id
                WHERE h.representative_household_id IS NOT NULL
                GROUP BY h.id
                HAVING MIN(e.date) >= ? AND MIN(e.date) <= ?
            ) AS ft
        ", [$fromDate, $toDate])[0]->total ?? 0;

        // First-timers grouped by their first event (top 5 for KPI display)
        $eventBreakdown = DB::select("
            SELECT first_event_id, first_event_name, first_event_date, COUNT(*) AS count
            FROM (
                SELECT h.id,
                    (SELECT e2.id   FROM events e2
                     JOIN visits v2 ON v2.event_id = e2.id
                     JOIN visit_households vh2 ON vh2.visit_id = v2.id
                     WHERE vh2.household_id = h.id ORDER BY e2.date ASC LIMIT 1) AS first_event_id,
                    (SELECT e2.name FROM events e2
                     JOIN visits v2 ON v2.event_id = e2.id
                     JOIN visit_households vh2 ON vh2.visit_id = v2.id
                     WHERE vh2.household_id = h.id ORDER BY e2.date ASC LIMIT 1) AS first_event_name,
                    (SELECT e2.date FROM events e2
                     JOIN visits v2 ON v2.event_id = e2.id
                     JOIN visit_households vh2 ON vh2.visit_id = v2.id
                     WHERE vh2.household_id = h.id ORDER BY e2.date ASC LIMIT 1) AS first_event_date
                FROM households h
                JOIN visit_households vh ON vh.household_id = h.id
                JOIN visits v ON vh.visit_id = v.id
                JOIN events e ON v.event_id = e.id
                GROUP BY h.id
                HAVING MIN(e.date) >= ? AND MIN(e.date) <= ?
            ) AS ft
            GROUP BY first_event_id, first_event_name, first_event_date
            ORDER BY count DESC
            LIMIT 5
        ", [$fromDate, $toDate]);

        // ── Paginated list (all active filters) ───────────────────────────────
        // Use correlated subqueries instead of JOIN+GROUP BY to avoid ONLY_FULL_GROUP_BY.
        $firstDateSql = '(SELECT MIN(e2.date) FROM visit_households vh2
                           JOIN visits v2 ON vh2.visit_id = v2.id
                           JOIN events e2 ON v2.event_id = e2.id
                           WHERE vh2.household_id = households.id)';

        $query = Household::query()
            ->select('households.*')
            ->selectRaw("{$firstDateSql} AS first_event_date")
            ->selectRaw('(SELECT COUNT(DISTINCT v2.event_id) FROM visit_households vh2
                          JOIN visits v2 ON vh2.visit_id = v2.id
                          WHERE vh2.household_id = households.id) AS total_events_attended')
            ->selectRaw('(SELECT e2.id   FROM events e2
                          JOIN visits v2 ON v2.event_id = e2.id
                          JOIN visit_households vh2 ON vh2.visit_id = v2.id
                          WHERE vh2.household_id = households.id
                          ORDER BY e2.date ASC LIMIT 1) AS first_event_id')
            ->selectRaw('(SELECT e2.name FROM events e2
                          JOIN visits v2 ON v2.event_id = e2.id
                          JOIN visit_households vh2 ON vh2.visit_id = v2.id
                          WHERE vh2.household_id = households.id
                          ORDER BY e2.date ASC LIMIT 1) AS first_event_name')
            ->whereRaw("{$firstDateSql} BETWEEN ? AND ?", [$fromDate, $toDate])
            ->with('representative:id,first_name,last_name,household_number');

        // Optional filters
        if ($eventId = $request->get('event_id')) {
            $query->whereRaw(
                '(SELECT e2.id FROM events e2
                  JOIN visits v2 ON v2.event_id = e2.id
                  JOIN visit_households vh2 ON vh2.visit_id = v2.id
                  WHERE vh2.household_id = households.id
                  ORDER BY e2.date ASC LIMIT 1) = ?',
                [(int) $eventId]
            );
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('households.first_name', 'like', "%{$search}%")
                  ->orWhere('households.last_name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(households.first_name, ' ', households.last_name) LIKE ?", ["%{$search}%"])
                  ->orWhere('households.household_number', 'like', "%{$search}%")
                  ->orWhere('households.phone', 'like', "%{$search}%");
            });
        }

        if ($zip = $request->get('zip')) {
            $query->where('households.zip', $zip);
        }

        if ($city = $request->get('city')) {
            $query->where('households.city', 'like', "%{$city}%");
        }

        $represented = $request->get('represented');
        if ($represented === '1') {
            $query->whereNotNull('households.representative_household_id');
        } elseif ($represented === '0') {
            $query->whereNull('households.representative_household_id');
        }

        $query->orderByRaw("{$firstDateSql} ASC");

        $firstTimers = $query->paginate(25)->withQueryString();

        // Dropdowns for filters
        $events   = Event::orderBy('date', 'desc')->get(['id', 'name', 'date']);
        $zipCodes = Household::whereNotNull('zip')->distinct()->orderBy('zip')->pluck('zip');
        $cities   = Household::whereNotNull('city')->distinct()->orderBy('city')->pluck('city');

        $kpi = [
            'total'       => $totalFirstTimers,
            'represented' => $representedFirstTimers,
            'breakdown'   => $eventBreakdown,
        ];

        return view('reports.first-timers', compact(
            'filter', 'firstTimers', 'kpi', 'events', 'zipCodes', 'cities'
        ));
    }

    // ─── Export Hub ───────────────────────────────────────────────────────────

    public function export(Request $request): View
    {
        $filter = $this->filterParams($request);
        return view('reports.export', compact('filter'));
    }

    // ─── CSV Downloads ────────────────────────────────────────────────────────

    public function downloadExport(Request $request): StreamedResponse
    {
        $filter = $this->filterParams($request);
        $from   = $filter['from'];
        $to     = $filter['to'];
        $type   = $request->get('type', 'events');

        [$data, $filename] = match ($type) {
            'events'        => [$this->analytics->exportEvents($from, $to),       "events-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}.csv"],
            'visits'        => [$this->analytics->exportVisits($from, $to),       "visits-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}.csv"],
            'households'    => [$this->analytics->exportHouseholds($from, $to),   "households-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}.csv"],
            'reviews'       => [$this->analytics->exportReviews($from, $to),      "reviews-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}.csv"],
            'demographics'  => [$this->analytics->exportDemographics($from, $to), "demographics-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}.csv"],
            'volunteers'    => [$this->analytics->exportVolunteers($from, $to),   "volunteers-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}.csv"],
            'inventory'     => [$this->inventoryReport->exportInventoryUsage($from, $to), "inventory-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}.csv"],
            'first-timers'  => [$this->analytics->exportFirstTimers($from, $to),  "first-timers-{$from->format('Y-m-d')}-{$to->format('Y-m-d')}.csv"],
            default         => [['headers' => [], 'rows' => []], 'export.csv'],
        };

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $data['headers']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
