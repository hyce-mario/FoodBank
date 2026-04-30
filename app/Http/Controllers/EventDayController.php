<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Event;
use App\Models\Visit;
use App\Services\DistributionPostingService;
use App\Services\VisitReorderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventDayController extends Controller
{
    private const ROLES = ['intake', 'scanner', 'loader', 'exit'];

    // ─── Session helpers ──────────────────────────────────────────────────────

    private function sessionKey(Event $event, string $role): string
    {
        return "ed_{$event->id}_{$role}";
    }

    private function isAuthorised(Request $request, Event $event, string $role): bool
    {
        return $request->session()->get($this->sessionKey($event, $role)) === true;
    }

    private function logoutUrl(Event $event, string $role): string
    {
        return route("event-day.{$role}.logout", $event);
    }

    // ─── Unified page handler ─────────────────────────────────────────────────
    // Route: GET /{role}/{event}

    public function page(Request $request, Event $event, string $role = 'intake'): View|RedirectResponse
    {
        // Detect role from the route path segment
        $role = $this->detectRole($request);

        abort_unless(in_array($role, self::ROLES, true), 404);
        abort_unless($event->authCodesActive(), 403, 'This event is not currently active.');

        // Show auth wall if not yet authorised
        if (! $this->isAuthorised($request, $event, $role)) {
            return view('event-day.auth', compact('event', 'role'));
        }

        return match ($role) {
            'intake'  => $this->intakeView($event),
            'scanner' => $this->scannerView($event),
            'loader'  => $this->loaderView($event),
            'exit'    => $this->exitView($event),
        };
    }

    // ─── Auth submit ──────────────────────────────────────────────────────────
    // Route: POST /{role}/{event}/auth

    public function submitAuth(Request $request, Event $event): RedirectResponse
    {
        $role = $this->detectRole($request);

        abort_unless(in_array($role, self::ROLES, true), 404);
        abort_unless($event->authCodesActive(), 403, 'This event is not currently active.');

        $request->validate(['code' => ['required', 'string', 'size:4']]);

        if ($request->input('code') === $event->authCodeFor($role)) {
            $request->session()->put($this->sessionKey($event, $role), true);
            return redirect()->route("event-day.{$role}", $event);
        }

        return back()->withErrors(['code' => 'Incorrect code. Please try again.']);
    }

    // ─── Logout ───────────────────────────────────────────────────────────────
    // Route: POST /{role}/{event}/out

    public function logout(Request $request, Event $event): RedirectResponse
    {
        $role = $this->detectRole($request);
        abort_unless(in_array($role, self::ROLES, true), 404);
        $request->session()->forget($this->sessionKey($event, $role));
        return redirect()->route("event-day.{$role}", $event);
    }

    // ─── Live data ────────────────────────────────────────────────────────────
    // Route: GET /{role}/{event}/data

    public function data(Request $request, Event $event): JsonResponse
    {
        $role = $this->detectRole($request);
        abort_unless(in_array($role, self::ROLES, true), 404);

        if (! $this->isAuthorised($request, $event, $role)) {
            return response()->json(['error' => 'Unauthorised'], 401);
        }

        $ruleset = $event->ruleset;

        $visits = Visit::where('event_id', $event->id)
            ->where(fn ($q) => $q->whereNull('visit_status')->orWhere('visit_status', '!=', 'exited'))
            ->with('households')
            ->orderBy('lane')
            ->orderBy('queue_position')
            ->orderBy('start_time')
            ->get();

        $lanesData = [];
        for ($i = 1; $i <= $event->lanes; $i++) {
            $lanesData[$i] = [];
        }

        foreach ($visits as $visit) {
            $households  = $visit->households;
            $primary     = $households->firstWhere('representative_household_id', null) ?? $households->first();
            $represented = $households->filter(fn ($h) => $h->id !== $primary?->id)->values();

            if (! $primary) continue;

            // Sum bags and people across ALL households in this visit
            $totalBags   = $ruleset
                ? $households->sum(fn ($h) => $ruleset->getBagsFor($h->household_size))
                : 0;
            $totalPeople = $households->sum('household_size');

            $laneNum = max(1, min($visit->lane, $event->lanes));

            $row = [
                'id'                       => $visit->id,
                'lane'                     => $visit->lane,
                'queue_position'           => $visit->queue_position,
                'visit_status'             => $visit->visit_status,
                // Phase 1.1.c.2: optimistic-lock token consumed by reorder.
                'updated_at'               => $visit->updated_at?->toIso8601String(),
                'start_time'               => $visit->start_time->format('g:i A'),
                'waited_min'               => (int) now()->diffInMinutes($visit->start_time),
                'bags_needed'              => $totalBags,
                'total_people'             => $totalPeople,
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
                    'bags_needed'    => $ruleset ? $ruleset->getBagsFor($r->household_size) : null,
                ])->all(),
            ];

            // Loader and Exit pages hide household names (privacy)
            if (in_array($role, ['loader', 'exit'], true)) {
                unset($row['household']['full_name']);
                $row['represented_households'] = array_map(
                    fn ($rh) => array_diff_key($rh, ['full_name' => '']),
                    $row['represented_households']
                );
            }

            $lanesData[$laneNum][] = $row;
        }

        $stats = [
            'checked_in' => $visits->whereIn('visit_status', ['checked_in', null])->count(),
            'queued'     => $visits->where('visit_status', 'queued')->count(),
            'loaded'     => $visits->where('visit_status', 'loaded')->count(),
            'served'     => Visit::where('event_id', $event->id)->where('visit_status', 'exited')->count(),
        ];

        return response()->json([
            'lanes' => $lanesData,
            'stats' => $stats,
        ]);
    }

    // ─── Reorder (drag-and-drop persist) ─────────────────────────────────────

    public function reorder(Request $request, Event $event, VisitReorderService $reorderService): JsonResponse
    {
        // Require ANY valid role session for this event
        $authed = false;
        foreach (self::ROLES as $r) {
            if ($this->isAuthorised($request, $event, $r)) { $authed = true; break; }
        }
        if (! $authed) return response()->json(['error' => 'Unauthorised'], 401);

        $validated = $request->validate([
            'moves'                  => ['required', 'array'],
            // Scope (id-belongs-to-event) is enforced authoritatively by the
            // service inside its lockForUpdate transaction; doing it here too
            // would just add N extra SELECTs per request.
            'moves.*.id'             => ['required', 'integer'],
            'moves.*.lane'           => ['required', 'integer', 'min:1'],
            'moves.*.queue_position' => ['required', 'integer', 'min:1'],
            // 'date' rejects garbage strings cleanly with 422 instead of
            // letting Carbon::parse explode inside the service as a 500.
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

        // Echo back the new updated_at per affected visit so the client can
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

    // ─── Status transitions ───────────────────────────────────────────────────

    public function markQueued(Request $request, Event $event, Visit $visit): JsonResponse
    {
        if (! $this->isAuthorised($request, $event, 'scanner')) {
            return response()->json(['error' => 'Unauthorised'], 401);
        }
        abort_unless($visit->event_id === $event->id, 404);

        if (! in_array($visit->visit_status, ['checked_in', null], true)) {
            return response()->json(['error' => 'Visit is not checked_in.'], 422);
        }

        $visit->update(['visit_status' => 'queued', 'queued_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markLoaded(Request $request, Event $event, Visit $visit): JsonResponse
    {
        if (! $this->isAuthorised($request, $event, 'loader')) {
            return response()->json(['error' => 'Unauthorised'], 401);
        }
        abort_unless($visit->event_id === $event->id, 404);

        if (! in_array($visit->visit_status, ['queued', 'loading'], true)) {
            return response()->json(['error' => 'Visit is not in a loadable status.'], 422);
        }

        try {
            // Status flip + inventory deduction in a single transaction so a
            // stock shortage rolls back the status change — the visit stays
            // queued and the loader can choose to skip or substitute (2.1.e).
            // skip_inventory=1 is set by the "Skip & Mark Loaded" modal button.
            DB::transaction(function () use ($visit, $request) {
                $visit->update(['visit_status' => 'loaded', 'loading_completed_at' => now()]);
                if (! $request->boolean('skip_inventory')) {
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

    public function markExited(Request $request, Event $event, Visit $visit): JsonResponse
    {
        if (! $this->isAuthorised($request, $event, 'exit')) {
            return response()->json(['error' => 'Unauthorised'], 401);
        }
        abort_unless($visit->event_id === $event->id, 404);

        if ($visit->visit_status !== 'loaded') {
            return response()->json(['error' => 'Visit is not loaded yet.'], 422);
        }

        // Sum bags for all households in the visit (handles representative pickups)
        $bags = 0;
        if ($event->ruleset) {
            $visit->loadMissing('households');
            $bags = $visit->households->sum(fn ($h) => $event->ruleset->getBagsFor($h->household_size));
        }

        $visit->update([
            'visit_status'   => 'exited',
            'exited_at'      => now(),
            'end_time'       => now(),
            'served_bags'    => $bags,
            // Phase 1.1.c.1: free the position so a later reorder doesn't collide.
            'queue_position' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    // ─── Private view factories ───────────────────────────────────────────────

    private function intakeView(Event $event): View
    {
        $event->load(['ruleset', 'preRegistrations.household', 'preRegistrations.potentialHousehold']);
        return view('event-day.intake', ['event' => $event, 'role' => 'intake']);
    }

    private function scannerView(Event $event): View
    {
        $event->load('ruleset');
        return view('event-day.scanner', ['event' => $event, 'role' => 'scanner']);
    }

    private function loaderView(Event $event): View
    {
        $event->load('ruleset');
        return view('event-day.loader', ['event' => $event, 'role' => 'loader']);
    }

    private function exitView(Event $event): View
    {
        $event->load('ruleset');
        return view('event-day.exit', ['event' => $event, 'role' => 'exit']);
    }

    // ─── Helper: detect role from request URI ─────────────────────────────────

    private function detectRole(Request $request): string
    {
        $segments = $request->segments();
        // URL structure: /{role}/{event}/... → first segment is role
        return $segments[0] ?? 'intake';
    }
}
