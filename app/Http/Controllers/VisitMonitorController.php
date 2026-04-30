<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Event;
use App\Models\Visit;
use App\Services\DistributionPostingService;
use App\Services\SettingService;
use App\Services\VisitReorderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VisitMonitorController extends Controller
{
    // ─── Page ─────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $events = Event::current()
            ->orderBy('name')
            ->get(['id', 'name', 'date', 'lanes']);

        $selected = $events->firstWhere('id', (int) $request->get('event'))
            ?? $events->first();

        return view('checkin.monitor', compact('events', 'selected'));
    }

    // ─── Live data (JSON) ─────────────────────────────────────────────────────

    public function data(Event $event): JsonResponse
    {
        $ruleset = $event->ruleset;

        // Active visits (all non-exited)
        $activeVisits = Visit::where('event_id', $event->id)
            ->where(fn ($q) => $q->whereNull('visit_status')->orWhere('visit_status', '!=', 'exited'))
            ->with('households')
            ->orderBy('queue_position')
            ->orderBy('start_time')
            ->get();

        // Last 5 exited per lane for the Served column
        $recentExited = Visit::where('event_id', $event->id)
            ->where('visit_status', 'exited')
            ->with('households')
            ->orderByDesc('end_time')
            ->limit($event->lanes * 5 + 10)
            ->get();

        // ── Row builder ───────────────────────────────────────────────────────
        $buildRow = function (Visit $visit) use ($ruleset, $event): ?array {
            $households  = $visit->households;
            $primary     = $households->firstWhere('representative_household_id', null) ?? $households->first();
            if (! $primary) return null;

            $represented = $households->filter(fn ($h) => $h->id !== $primary->id)->values();
            $bags        = $households->sum(fn ($h) => $ruleset ? $ruleset->getBagsFor($h->household_size) : 0);

            return [
                'id'                       => $visit->id,
                'lane'                     => max(1, min($visit->lane, $event->lanes)),
                'queue_position'           => $visit->queue_position,
                'visit_status'             => $visit->visit_status ?? 'checked_in',
                // Phase 1.1.c.3: optimistic-lock token consumed by reorder.
                'updated_at'               => $visit->updated_at?->toIso8601String(),
                'start_time'               => $visit->start_time->format('g:i A'),
                'waited_min'               => (int) now()->diffInMinutes($visit->start_time),
                'bags_needed'              => $bags,
                'total_people'             => $households->sum('household_size'),
                'is_representative_pickup' => $represented->isNotEmpty(),
                'household'                => [
                    'household_number' => $primary->household_number,
                    'full_name'        => $primary->full_name,
                    'vehicle_label'    => $primary->vehicle_label,
                    'household_size'   => $primary->household_size,
                ],
                'represented_households'   => $represented->map(fn ($r) => [
                    'full_name'      => $r->full_name,
                    'household_size' => $r->household_size,
                    'bags_needed'    => $ruleset ? $ruleset->getBagsFor($r->household_size) : 0,
                ])->values()->all(),
            ];
        };

        // ── Build per-lane structure ───────────────────────────────────────────
        $lanesData = [];
        for ($i = 1; $i <= $event->lanes; $i++) {
            $lanesData[$i] = ['checked_in' => [], 'queued' => [], 'loaded' => [], 'exited' => []];
        }

        foreach ($activeVisits as $visit) {
            $row = $buildRow($visit);
            if (! $row) continue;
            $status = match ($row['visit_status']) {
                'queued'  => 'queued',
                'loading',
                'loaded'  => 'loaded',
                default   => 'checked_in',
            };
            $lanesData[$row['lane']][$status][] = $row;
        }

        // Last 5 exited per lane
        $exitedPerLane = [];
        foreach ($recentExited as $visit) {
            $laneNum = max(1, min($visit->lane, $event->lanes));
            $exitedPerLane[$laneNum] ??= [];
            if (count($exitedPerLane[$laneNum]) < 5) {
                $row = $buildRow($visit);
                if ($row) {
                    $row['visit_status'] = 'exited';
                    $exitedPerLane[$laneNum][] = $row;
                }
            }
        }
        foreach ($exitedPerLane as $laneNum => $rows) {
            $lanesData[$laneNum]['exited'] = $rows;
        }

        // ── Stats ─────────────────────────────────────────────────────────────
        $servedCount    = Visit::where('event_id', $event->id)->where('visit_status', 'exited')->count();
        $familiesServed = DB::table('visit_households')
            ->join('visits', 'visit_households.visit_id', '=', 'visits.id')
            ->where('visits.event_id', $event->id)
            ->where('visits.visit_status', 'exited')
            ->count();
        $peopleServed   = (int) DB::table('visit_households')
            ->join('visits', 'visit_households.visit_id', '=', 'visits.id')
            ->join('households', 'visit_households.household_id', '=', 'households.id')
            ->where('visits.event_id', $event->id)
            ->where('visits.visit_status', 'exited')
            ->sum('households.household_size');

        return response()->json([
            'lanes'         => $lanesData,
            'poll_interval' => max(5, (int) SettingService::get('event_queue.queue_poll_interval', 10)),
            'ui'            => [
                'allow_queue_reorder' => (bool) SettingService::get('event_queue.allow_queue_reorder', true),
            ],
            'stats'         => [
                'checked_in'      => $activeVisits->whereIn('visit_status', ['checked_in', null])->count(),
                'queued'          => $activeVisits->where('visit_status', 'queued')->count(),
                'loading'         => $activeVisits->whereIn('visit_status', ['loading', 'loaded'])->count(),
                'served'          => $servedCount,
                'families_served' => $familiesServed,
                'people_served'   => $peopleServed,
            ],
        ]);
    }

    // ─── Status transition (supervisor override) ───────────────────────────────

    public function transition(Request $request, Event $event, Visit $visit): JsonResponse
    {
        abort_unless($visit->event_id === $event->id, 404);

        $data      = $request->validate(['status' => ['required', 'in:queued,loaded,exited']]);
        $newStatus = $data['status'];
        $now       = now();

        $validTransitions = [
            'checked_in' => 'queued',
            null         => 'queued',
            'queued'     => 'loaded',
            'loading'    => 'loaded',
            'loaded'     => 'exited',
        ];

        $currentStatus = $visit->visit_status;
        if (($validTransitions[$currentStatus] ?? null) !== $newStatus) {
            return response()->json([
                'error' => "Cannot advance from '{$currentStatus}' to '{$newStatus}'.",
            ], 422);
        }

        $update = ['visit_status' => $newStatus];

        if ($newStatus === 'queued') {
            $update['queued_at'] = $now;
        } elseif ($newStatus === 'loaded') {
            $update['loading_completed_at'] = $now;
        } elseif ($newStatus === 'exited') {
            $update['exited_at']      = $now;
            $update['end_time']       = $now;
            $bags = 0;
            if ($event->ruleset) {
                $visit->loadMissing('households');
                $bags = $visit->households->sum(fn ($h) => $event->ruleset->getBagsFor($h->household_size));
            }
            $update['served_bags']    = $bags;
            // Phase 1.1.c.1: position is meaningful only for active visits.
            $update['queue_position'] = null;
        }

        try {
            // Wrap status flip + distribution in one transaction so an
            // InsufficientStockException rolls back the status change too.
            DB::transaction(function () use ($visit, $update, $newStatus) {
                $visit->update($update);
                if ($newStatus === 'loaded') {
                    app(DistributionPostingService::class)->postForVisit($visit);
                }
            });
        } catch (InsufficientStockException $e) {
            return response()->json([
                'error'             => 'insufficient_stock',
                'message'           => 'Not enough stock to complete this distribution.',
                'inventory_item_id' => $e->inventoryItemId,
                'needed'            => $e->needed,
                'available'         => $e->available,
                'event_id'          => $e->eventId,
            ], 422);
        }

        return response()->json(['ok' => true]);
    }

    // ─── Reorder ──────────────────────────────────────────────────────────────

    public function reorder(Request $request, Event $event, VisitReorderService $reorderService): JsonResponse
    {
        if (! SettingService::get('event_queue.allow_queue_reorder', true)) {
            return response()->json(['error' => 'Queue reordering is disabled.'], 403);
        }

        $validated = $request->validate([
            'moves'                  => ['required', 'array'],
            // Scope (id-belongs-to-event) is enforced authoritatively by the
            // service inside its lockForUpdate transaction.
            'moves.*.id'             => ['required', 'integer'],
            'moves.*.lane'           => ['required', 'integer', 'min:1'],
            'moves.*.queue_position' => ['required', 'integer', 'min:1'],
            'moves.*.updated_at'     => ['required', 'date'],
        ]);

        try {
            $reorderService->reorder($event, $validated['moves']);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                VisitReorderService::ERR_VERSION_MISMATCH => response()->json([
                    'error' => 'A more recent change to one of these visits exists. Refresh and try again.',
                    'code'  => 'version_mismatch',
                ], 409),
                VisitReorderService::ERR_SCOPE_MISMATCH => response()->json([
                    'error' => 'One or more visits do not belong to this event.',
                    'code'  => 'scope_mismatch',
                ], 422),
                default => throw $e,
            };
        }

        // Echo back fresh updated_at per affected visit so the client can
        // refresh its optimistic-lock tokens without a full re-poll.
        $ids   = array_column($validated['moves'], 'id');
        $fresh = Visit::whereIn('id', $ids)
            ->get(['id', 'updated_at'])
            ->map(fn ($v) => [
                'id'         => $v->id,
                'updated_at' => $v->updated_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['ok' => true, 'visits' => $fresh]);
    }
}
