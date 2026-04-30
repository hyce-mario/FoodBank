<?php

namespace App\Http\Controllers;

use App\Exceptions\HouseholdAlreadyServedException;
use App\Http\Requests\CheckInRequest;
use App\Models\Event;
use App\Models\Household;
use App\Models\Visit;
use App\Services\EventCheckInService;
use App\Services\HouseholdService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckInController extends Controller
{
    public function __construct(
        protected EventCheckInService $service,
        protected HouseholdService $householdService,
    ) {}

    // ─── index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $events        = Event::current()->orderBy('date')->get();
        $selectedEvent = $request->filled('event_id')
            ? Event::find($request->event_id)
            : $events->first();

        $lanes = $selectedEvent ? range(1, $selectedEvent->lanes) : [];

        // Queue UI behaviour driven by settings — consumed as JS config in the view
        $checkInSettings = [
            'show_vehicle_info'     => (bool) SettingService::get('event_queue.show_vehicle_info_queue',      true),
            'show_family_breakdown' => (bool) SettingService::get('event_queue.show_family_breakdown',        true),
            'show_names'            => (bool) SettingService::get('event_queue.show_household_names_scanner', true),
            'allow_lane_drag'       => (bool) SettingService::get('event_queue.allow_lane_drag',              true),
            'allow_queue_reorder'   => (bool) SettingService::get('event_queue.allow_queue_reorder',          true),
            'poll_interval'         => max(5, (int) SettingService::get('event_queue.queue_poll_interval',    10)),
        ];

        return view('checkin.index', compact('events', 'selectedEvent', 'lanes', 'checkInSettings'));
    }

    // ─── search ───────────────────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'event_id' => ['nullable', 'exists:events,id'],
        ]);

        $q       = (string) $request->input('q', '');
        $eventId = $request->filled('event_id') ? (int) $request->event_id : null;

        if (trim($q) === '') {
            return response()->json(['results' => []]);
        }

        $event      = $eventId ? Event::find($eventId) : null;
        $ruleset    = $event?->ruleset;
        $households = $this->service->search($q, $eventId);

        $mapped = $households->map(fn (Household $h) => $this->householdPayload($h, $ruleset));

        return response()->json(['results' => $mapped]);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function store(CheckInRequest $request): JsonResponse
    {
        $event     = Event::findOrFail($request->event_id);
        $household = Household::findOrFail($request->household_id);
        $lane      = (int) $request->lane;

        // Use explicit represented_ids from the UI (staff-curated during check-in)
        // when provided; fall back to the DB relationship otherwise.
        $representedIds = $request->filled('represented_ids')
            ? array_map('intval', (array) $request->represented_ids)
            : null;

        $force          = $request->boolean('force');
        $overrideReason = $request->filled('override_reason')
            ? (string) $request->input('override_reason')
            : null;

        try {
            $visit = $this->service->checkIn(
                $event,
                $household,
                $lane,
                representedIds: $representedIds,
                force: $force,
                overrideReason: $overrideReason,
            );
        } catch (HouseholdAlreadyServedException $e) {
            // Phase 1.3.b: surface the conflict as a structured 422 the
            // browser can render an override modal from. The exception
            // carries only IDs — resolve names + numbers here so the modal
            // doesn't need a follow-up request. Caught BEFORE the broader
            // RuntimeException catch since this class extends it.
            $households = Household::whereIn('id', $e->householdIds)
                ->orderBy('id')
                ->get(['id', 'household_number', 'first_name', 'last_name'])
                ->map(fn ($h) => [
                    'id'               => $h->id,
                    'household_number' => $h->household_number,
                    'full_name'        => $h->full_name,
                ])
                ->all();

            return response()->json([
                'error'          => 'household_already_served',
                'message'        => $e->allowOverride
                    ? 'This household has already been served at this event. Supervisor override required.'
                    : 'This household has already been served at this event. The current policy does not permit re-check-in.',
                'allow_override' => $e->allowOverride,
                'event_id'       => $e->eventId,
                'households'     => $households,
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $primary = $visit->households->first();

        return response()->json([
            'visit' => [
                'id'         => $visit->id,
                'lane'       => $visit->lane,
                'start_time' => $visit->start_time->toIso8601String(),
                'household'  => $primary ? [
                    'id'               => $primary->id,
                    'household_number' => $primary->household_number,
                    'full_name'        => $primary->full_name,
                ] : null,
            ],
        ], 201);
    }

    // ─── update vehicle ───────────────────────────────────────────────────────

    public function updateVehicle(Request $request, Household $household): JsonResponse
    {
        $data = $request->validate([
            'vehicle_make'  => ['nullable', 'string', 'max:100'],
            'vehicle_color' => ['nullable', 'string', 'max:50'],
        ]);

        $household->update($data);

        return response()->json([
            'ok'        => true,
            'household' => [
                'vehicle_make'  => $household->vehicle_make,
                'vehicle_color' => $household->vehicle_color,
            ],
        ]);
    }

    // ─── quick-add ────────────────────────────────────────────────────────────

    public function quickAdd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id'       => ['required', 'exists:events,id'],
            'lane'           => ['required', 'integer', 'min:1'],
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:50'],
            'children_count' => ['required', 'integer', 'min:0', 'max:50'],
            'adults_count'   => ['required', 'integer', 'min:0', 'max:50'],
            'seniors_count'  => ['required', 'integer', 'min:0', 'max:50'],
            'vehicle_make'   => ['nullable', 'string', 'max:100'],
            'vehicle_color'  => ['nullable', 'string', 'max:50'],
        ]);

        $event     = Event::findOrFail($data['event_id']);
        $household = $this->householdService->create($data);

        try {
            $visit = $this->service->checkIn($event, $household, (int) $data['lane']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $primary = $visit->households->first();

        return response()->json([
            'visit' => [
                'id'         => $visit->id,
                'lane'       => $visit->lane,
                'start_time' => $visit->start_time->toIso8601String(),
                'household'  => $primary ? [
                    'id'               => $primary->id,
                    'household_number' => $primary->household_number,
                    'full_name'        => $primary->full_name,
                ] : null,
            ],
        ], 201);
    }

    // ─── quickCreate ─────────────────────────────────────────────────────────

    /**
     * Create a new household record only — does NOT check in.
     * Returns the full household payload so the JS can set it as the
     * selectedHousehold and let staff add represented households before
     * clicking "Check In".
     */
    public function quickCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_id'       => ['nullable', 'exists:events,id'],
            'first_name'     => ['required', 'string', 'max:100'],
            'last_name'      => ['required', 'string', 'max:100'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:50'],
            'children_count' => ['required', 'integer', 'min:0', 'max:50'],
            'adults_count'   => ['required', 'integer', 'min:0', 'max:50'],
            'seniors_count'  => ['required', 'integer', 'min:0', 'max:50'],
            'vehicle_make'   => ['nullable', 'string', 'max:100'],
            'vehicle_color'  => ['nullable', 'string', 'max:50'],
        ]);

        $household = $this->householdService->create($data);
        $household->load('representedHouseholds');

        $event   = isset($data['event_id']) ? Event::find($data['event_id']) : null;
        $ruleset = $event?->ruleset;

        return response()->json([
            'household' => $this->householdPayload($household, $ruleset),
        ], 201);
    }

    // ─── log ──────────────────────────────────────────────────────────────────

    public function log(Request $request): JsonResponse
    {
        $request->validate(['event_id' => ['required', 'exists:events,id']]);

        $event  = Event::findOrFail($request->event_id);
        $visits = $this->service->recentLog($event);

        $mapped = $visits->map(function (Visit $visit) {
            $households = $visit->households;
            $primary    = $households->firstWhere('representative_household_id', null) ?? $households->first();
            $represented = $households->filter(fn ($h) => $h->id !== ($primary?->id));
            return [
                'id'                       => $visit->id,
                'lane'                     => $visit->lane,
                'start_time'               => $visit->start_time->toIso8601String(),
                'active'                   => $visit->isActive(),
                'is_representative_pickup' => $represented->isNotEmpty(),
                'household'                => $primary ? [
                    'id'               => $primary->id,
                    'household_number' => $primary->household_number,
                    'full_name'        => $primary->full_name,
                    'vehicle_color'    => $primary->vehicle_color,
                    'vehicle_make'     => $primary->vehicle_make,
                ] : null,
            ];
        });

        return response()->json(['log' => $mapped]);
    }

    // ─── done ─────────────────────────────────────────────────────────────────

    public function done(Request $request, Visit $visit): JsonResponse
    {
        $this->service->markDone($visit);

        return response()->json(['ok' => true]);
    }

    // ─── createRepresented ────────────────────────────────────────────────────

    /**
     * Create a new household record and immediately link it to a representative household.
     * Called via AJAX from the check-in offcanvas panel.
     */
    public function createRepresented(Request $request): JsonResponse
    {
        $data = $request->validate([
            'representative_id' => ['required', 'exists:households,id'],
            'event_id'          => ['nullable', 'exists:events,id'],
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['required', 'string', 'max:100'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'children_count'    => ['required', 'integer', 'min:0', 'max:50'],
            'adults_count'      => ['required', 'integer', 'min:0', 'max:50'],
            'seniors_count'     => ['required', 'integer', 'min:0', 'max:50'],
            'vehicle_make'      => ['nullable', 'string', 'max:100'],
            'vehicle_color'     => ['nullable', 'string', 'max:50'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ]);

        $representative = Household::findOrFail($data['representative_id']);

        $household = $this->householdService->createRepresented($representative, $data);

        $event   = isset($data['event_id']) ? Event::find($data['event_id']) : null;
        $ruleset = $event?->ruleset;

        return response()->json([
            'household' => $this->householdPayload($household, $ruleset),
        ], 201);
    }

    // ─── attachRepresented ────────────────────────────────────────────────────

    /**
     * Attach an existing household to a representative household.
     * Called via AJAX from the check-in attach-search panel.
     */
    public function attachRepresented(Request $request): JsonResponse
    {
        $data = $request->validate([
            'representative_id' => ['required', 'exists:households,id'],
            'household_id'      => ['required', 'exists:households,id'],
            'event_id'          => ['nullable', 'exists:events,id'],
        ]);

        $representative = Household::findOrFail($data['representative_id']);
        $household      = Household::findOrFail($data['household_id']);

        try {
            $this->householdService->attach($representative, $household);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Reload to get the updated representative_household_id
        $household->refresh();

        $event   = isset($data['event_id']) ? Event::find($data['event_id']) : null;
        $ruleset = $event?->ruleset;

        return response()->json([
            'household' => $this->householdPayload($household, $ruleset),
        ]);
    }

    // ─── searchRepresented ────────────────────────────────────────────────────

    /**
     * Search households that can be attached as represented pickups.
     * Excludes: the representative itself, households already linked to someone else,
     * and households already active in this event.
     */
    public function searchRepresented(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'                 => ['required', 'string', 'min:1', 'max:100'],
            'representative_id' => ['required', 'exists:households,id'],
            'event_id'          => ['nullable', 'exists:events,id'],
        ]);

        $term  = trim($data['q']);
        $repId = (int) $data['representative_id'];

        $households = Household::where(function ($q) use ($term) {
                $q->where('household_number', 'like', "%{$term}%")
                  ->orWhere('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
                  ->orWhere('phone', 'like', "%{$term}%");
            })
            // Exclude the representative itself
            ->where('id', '!=', $repId)
            // Only unlinked households (representative_household_id is null OR already linked to this rep)
            ->where(function ($q) use ($repId) {
                $q->whereNull('representative_household_id')
                  ->orWhere('representative_household_id', $repId);
            })
            ->limit(8)
            ->get();

        $event   = isset($data['event_id']) ? Event::find($data['event_id']) : null;
        $ruleset = $event?->ruleset;

        return response()->json([
            'results' => $households->map(fn ($h) => $this->householdPayload($h, $ruleset)),
        ]);
    }

    // ─── queue ────────────────────────────────────────────────────────────────

    public function queue(Request $request): JsonResponse
    {
        $request->validate([
            'event_id' => ['required', 'exists:events,id'],
        ]);

        $event = Event::findOrFail($request->event_id);
        $queue = $this->service->activeQueue($event);

        $mapped = $queue->map(function (Visit $visit) {
            $households  = $visit->households;
            $primary     = $households->firstWhere('representative_household_id', null) ?? $households->first();
            $represented = $households->filter(fn ($h) => $h->id !== ($primary?->id))->values();
            return [
                'id'                       => $visit->id,
                'lane'                     => $visit->lane,
                'start_time'               => $visit->start_time->toIso8601String(),
                'duration_minutes'         => $visit->durationMinutes(),
                'is_representative_pickup' => $represented->isNotEmpty(),
                'household'                => $primary ? [
                    'id'               => $primary->id,
                    'household_number' => $primary->household_number,
                    'full_name'        => $primary->full_name,
                    'phone'            => $primary->phone,
                    'household_size'   => $primary->household_size,
                    'children_count'   => $primary->children_count,
                    'adults_count'     => $primary->adults_count,
                    'seniors_count'    => $primary->seniors_count,
                    'vehicle_make'     => $primary->vehicle_make,
                    'vehicle_color'    => $primary->vehicle_color,
                ] : null,
                'represented_households'   => $represented->map(fn ($r) => [
                    'id'               => $r->id,
                    'full_name'        => $r->full_name,
                    'household_number' => $r->household_number,
                    'household_size'   => $r->household_size,
                    'children_count'   => $r->children_count,
                    'adults_count'     => $r->adults_count,
                    'seniors_count'    => $r->seniors_count,
                ])->all(),
            ];
        });

        return response()->json(['queue' => $mapped]);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    /**
     * Serialize a household to the shape used by check-in JS.
     * Includes bags_needed when a ruleset is available.
     */
    private function householdPayload(Household $h, mixed $ruleset = null): array
    {
        $bagsNeeded = $ruleset ? $ruleset->getBagsFor($h->household_size) : null;

        $payload = [
            'id'                     => $h->id,
            'household_number'       => $h->household_number,
            'full_name'              => $h->full_name,
            'phone'                  => $h->phone,
            'city'                   => $h->city,
            'state'                  => $h->state,
            'household_size'         => $h->household_size,
            'children_count'         => $h->children_count,
            'adults_count'           => $h->adults_count,
            'seniors_count'          => $h->seniors_count,
            'vehicle_make'           => $h->vehicle_make,
            'vehicle_color'          => $h->vehicle_color,
            'bags_needed'            => $bagsNeeded,
            'is_representative'      => false,
            'is_pre_registered'      => (bool) ($h->getAttribute('is_pre_registered') ?? false),
            'represented_households' => [],
        ];

        // Only resolve represented households if already loaded (avoids N+1 in lists)
        if ($h->relationLoaded('representedHouseholds')) {
            $payload['is_representative']      = $h->representedHouseholds->isNotEmpty();
            $payload['represented_households'] = $h->representedHouseholds->map(fn ($r) => [
                'id'               => $r->id,
                'full_name'        => $r->full_name,
                'household_number' => $r->household_number,
                'household_size'   => $r->household_size,
                'children_count'   => $r->children_count,
                'adults_count'     => $r->adults_count,
                'seniors_count'    => $r->seniors_count,
                'bags_needed'      => $ruleset ? $ruleset->getBagsFor($r->household_size) : null,
            ])->all();
        }

        return $payload;
    }
}
