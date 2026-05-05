<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    // ─── Summary KPIs ─────────────────────────────────────────────────────────

    /**
     * Top-line totals for the given date range, derived from inventory_movements.
     * Uses movements (the authoritative audit trail) rather than denormalized totals.
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('inventory_movements')
            ->whereBetween('created_at', [$from, $to])
            ->select('movement_type', DB::raw('SUM(ABS(quantity)) as total'))
            ->groupBy('movement_type')
            ->pluck('total', 'movement_type');

        return [
            'total_allocated'   => (int) ($rows['event_allocated']   ?? 0),
            'total_distributed' => (int) ($rows['event_distributed'] ?? 0),
            'total_returned'    => (int) ($rows['event_returned']    ?? 0),
            'total_damaged'     => (int) ($rows['damaged']           ?? 0),
            'total_expired'     => (int) ($rows['expired']           ?? 0),
            'total_stock_in'    => (int) ($rows['stock_in']          ?? 0),
            'total_stock_out'   => (int) ($rows['stock_out']         ?? 0),
            // Combined waste = damaged + expired + manual stock-out
            'total_waste'       => (int) (($rows['damaged'] ?? 0) + ($rows['expired'] ?? 0) + ($rows['stock_out'] ?? 0)),
            // Items that had any movement in range
            'items_active'      => (int) DB::table('inventory_movements')
                ->whereBetween('created_at', [$from, $to])
                ->distinct('inventory_item_id')
                ->count('inventory_item_id'),
        ];
    }

    // ─── Top Distributed Items ─────────────────────────────────────────────────

    /**
     * Items with the most distributed_quantity on allocations whose events
     * fall within the date range.
     */
    public function topDistributedItems(Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        return DB::table('event_inventory_allocations as eia')
            ->join('events as e',            'e.id',  '=', 'eia.event_id')
            ->join('inventory_items as ii',  'ii.id', '=', 'eia.inventory_item_id')
            ->leftJoin('inventory_categories as ic', 'ic.id', '=', 'ii.category_id')
            ->whereBetween('e.date', [$from->toDateString(), $to->toDateString()])
            ->select(
                'ii.id',
                'ii.name',
                'ii.unit_type',
                'ic.name as category_name',
                DB::raw('SUM(eia.allocated_quantity)   as total_allocated'),
                DB::raw('SUM(eia.distributed_quantity) as total_distributed'),
                DB::raw('SUM(eia.returned_quantity)    as total_returned'),
            )
            ->groupBy('ii.id', 'ii.name', 'ii.unit_type', 'ic.name')
            ->orderByDesc('total_distributed')
            ->limit($limit)
            ->get();
    }

    // ─── Distribution Over Time ───────────────────────────────────────────────

    /**
     * Daily movement totals (allocated vs returned) for the line chart.
     * Uses movements table for precision; groups by day.
     */
    public function distributionOverTime(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('inventory_movements')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('movement_type', ['event_allocated', 'event_returned', 'stock_in'])
            ->select(
                DB::raw("DATE(created_at) as day"),
                'movement_type',
                DB::raw('SUM(ABS(quantity)) as total'),
            )
            ->groupBy('day', 'movement_type')
            ->orderBy('day')
            ->get();

        // Build date scaffold as a plain array to avoid Collection indirect-modification warning
        $days = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $days[$cursor->toDateString()] = ['allocated' => 0, 'returned' => 0, 'stock_in' => 0];
            $cursor->addDay();
        }

        foreach ($rows as $row) {
            if (! isset($days[$row->day])) {
                continue;
            }
            match ($row->movement_type) {
                'event_allocated' => $days[$row->day]['allocated'] += $row->total,
                'event_returned'  => $days[$row->day]['returned']  += $row->total,
                'stock_in'        => $days[$row->day]['stock_in']  += $row->total,
                default           => null,
            };
        }

        $labels    = [];
        $allocated = [];
        $returned  = [];
        $stockIn   = [];

        if (count($days) > 45) {
            // Collapse into ISO weeks
            $buckets = [];
            foreach ($days as $date => $vals) {
                $week = Carbon::parse($date)->startOfWeek()->toDateString();
                $buckets[$week] ??= ['allocated' => 0, 'returned' => 0, 'stock_in' => 0];
                $buckets[$week]['allocated'] += $vals['allocated'];
                $buckets[$week]['returned']  += $vals['returned'];
                $buckets[$week]['stock_in']  += $vals['stock_in'];
            }
            foreach ($buckets as $week => $vals) {
                $labels[]    = Carbon::parse($week)->format('M j');
                $allocated[] = $vals['allocated'];
                $returned[]  = $vals['returned'];
                $stockIn[]   = $vals['stock_in'];
            }
        } else {
            foreach ($days as $date => $vals) {
                $labels[]    = Carbon::parse($date)->format('M j');
                $allocated[] = $vals['allocated'];
                $returned[]  = $vals['returned'];
                $stockIn[]   = $vals['stock_in'];
            }
        }

        return compact('labels', 'allocated', 'returned', 'stockIn');
    }

    // ─── Event Inventory Usage Table ──────────────────────────────────────────

    /**
     * Per-event breakdown: items allocated, distributed, returned, remaining.
     */
    public function eventInventoryUsage(Carbon $from, Carbon $to): Collection
    {
        return DB::table('events as e')
            ->join('event_inventory_allocations as eia', 'eia.event_id', '=', 'e.id')
            ->whereBetween('e.date', [$from->toDateString(), $to->toDateString()])
            ->select(
                'e.id as event_id',
                'e.name as event_name',
                'e.date as event_date',
                'e.status as event_status',
                DB::raw('COUNT(DISTINCT eia.inventory_item_id) as item_count'),
                DB::raw('SUM(eia.allocated_quantity)   as total_allocated'),
                DB::raw('SUM(eia.distributed_quantity) as total_distributed'),
                DB::raw('SUM(eia.returned_quantity)    as total_returned'),
                DB::raw('SUM(eia.allocated_quantity - eia.distributed_quantity - eia.returned_quantity) as total_remaining'),
            )
            ->groupBy('e.id', 'e.name', 'e.date', 'e.status')
            ->orderByDesc('e.date')
            ->get();
    }

    // ─── Waste / Loss Breakdown ───────────────────────────────────────────────

    /**
     * Items with damaged, expired, or manual stock-out movements in range.
     */
    public function wasteBreakdown(Carbon $from, Carbon $to): Collection
    {
        return DB::table('inventory_movements as im')
            ->join('inventory_items as ii',             'ii.id', '=', 'im.inventory_item_id')
            ->leftJoin('inventory_categories as ic',    'ic.id', '=', 'ii.category_id')
            ->whereBetween('im.created_at', [$from, $to])
            ->whereIn('im.movement_type', ['damaged', 'expired', 'stock_out'])
            ->select(
                'ii.id',
                'ii.name',
                'ii.unit_type',
                'ic.name as category_name',
                DB::raw("SUM(CASE WHEN im.movement_type = 'damaged'   THEN ABS(im.quantity) ELSE 0 END) as damaged_qty"),
                DB::raw("SUM(CASE WHEN im.movement_type = 'expired'   THEN ABS(im.quantity) ELSE 0 END) as expired_qty"),
                DB::raw("SUM(CASE WHEN im.movement_type = 'stock_out' THEN ABS(im.quantity) ELSE 0 END) as stock_out_qty"),
                DB::raw('SUM(ABS(im.quantity)) as total_waste'),
            )
            ->groupBy('ii.id', 'ii.name', 'ii.unit_type', 'ic.name')
            ->orderByDesc('total_waste')
            ->get();
    }

    // ─── Top items chart data (for horizontal bar chart) ──────────────────────

    /**
     * Returns labels + allocated/distributed arrays for Chart.js.
     */
    public function topItemsChartData(Collection $topItems): array
    {
        return [
            'labels'      => $topItems->pluck('name')->all(),
            'allocated'   => $topItems->pluck('total_allocated')->map(fn($v) => (int) $v)->all(),
            'distributed' => $topItems->pluck('total_distributed')->map(fn($v) => (int) $v)->all(),
            'returned'    => $topItems->pluck('total_returned')->map(fn($v) => (int) $v)->all(),
        ];
    }

    // ─── CSV Export ───────────────────────────────────────────────────────────

    /**
     * Per-event item-level inventory usage for the date range, in the shape
     * the controller's `streamDownload` expects: ['headers' => [], 'rows' => []].
     * One row per (event × item) so the export reconciles to both the per-event
     * and per-item breakdowns the on-screen report shows.
     */
    public function exportInventoryUsage(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('event_inventory_allocations as eia')
            ->join('events as e',                    'e.id',  '=', 'eia.event_id')
            ->join('inventory_items as ii',          'ii.id', '=', 'eia.inventory_item_id')
            ->leftJoin('inventory_categories as ic', 'ic.id', '=', 'ii.category_id')
            ->whereBetween('e.date', [$from->toDateString(), $to->toDateString()])
            ->select(
                'e.date         as event_date',
                'e.name         as event_name',
                'e.status       as event_status',
                'ic.name        as category_name',
                'ii.name        as item_name',
                'ii.unit_type',
                'eia.allocated_quantity',
                'eia.distributed_quantity',
                'eia.returned_quantity',
            )
            ->orderBy('e.date', 'desc')
            ->orderBy('ii.name')
            ->get();

        return [
            'headers' => [
                'Event Date', 'Event', 'Event Status', 'Category', 'Item', 'Unit',
                'Allocated', 'Distributed', 'Returned', 'Remaining',
            ],
            'rows' => $rows->map(fn ($r) => [
                $r->event_date,
                $r->event_name,
                $r->event_status,
                $r->category_name ?? '',
                $r->item_name,
                $r->unit_type,
                (int) $r->allocated_quantity,
                (int) $r->distributed_quantity,
                (int) $r->returned_quantity,
                (int) ($r->allocated_quantity - $r->distributed_quantity - $r->returned_quantity),
            ])->all(),
        ];
    }
}
