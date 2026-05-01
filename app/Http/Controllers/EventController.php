<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\AllocationRuleset;
use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\InventoryItem;
use App\Models\Volunteer;
use App\Models\VolunteerGroup;
use App\Services\FinanceService;
use App\Services\HouseholdService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EventController extends Controller
{
    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Event::class);
        $query = Event::query()
                      ->withCount(['preRegistrations', 'assignedVolunteers'])
                      ->with('volunteerGroup', 'ruleset');

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $filter = $request->get('filter', 'current');

        match ($filter) {
            'upcoming' => $query->upcoming()->orderBy('date'),
            'past'     => $query->past()->orderBy('date', 'desc'),
            default    => (function () use ($query, &$filter) {
                $query->current()->orderBy('date');
                $filter = 'current';
            })(),
        };

        $events = $query->paginate(20)->withQueryString();

        $upcomingCount = Event::upcoming()->count();
        $currentCount  = Event::current()->count();
        $pastCount     = Event::past()->count();

        return view('events.index', compact('events', 'filter', 'upcomingCount', 'currentCount', 'pastCount'));
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        $this->authorize('create', Event::class);
        [$allGroups, $groupMap, $allVolunteers, $rulesets] = $this->formData();
        $defaultLaneCount = max(1, (int) SettingService::get('event_queue.default_lane_count', 1));
        return view('events.create', compact('allGroups', 'groupMap', 'allVolunteers', 'rulesets', 'defaultLaneCount'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $this->authorize('create', Event::class);
        $data = $request->validated();
        $volunteerIds = $data['volunteer_ids'] ?? [];
        unset($data['volunteer_ids']);

        $data['status'] = Event::deriveStatus(Carbon::parse($data['date']));

        $newCodes = [];
        if (SettingService::get('public_access.auto_generate_codes', true)) {
            foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
                $code                      = Event::generateAuthCode();
                $newCodes[$role]           = $code;
                $data["{$role}_auth_code"] = $code;
            }
        }

        $event = Event::create($data);
        $event->assignedVolunteers()->sync($volunteerIds);

        return redirect()
            ->route('events.show', $event)
            ->with('success', "Event \"{$event->name}\" created successfully.")
            ->with('new_auth_codes', $newCodes ?: null);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Event $event): View
    {
        $this->authorize('view', $event);
        $event->loadMissing('volunteerGroup', 'assignedVolunteers');
        $event->load([
            'preRegistrations.household',
            'preRegistrations.potentialHousehold',
            'media',
            'reviews'              => fn($q) => $q->where('is_visible', true)->latest(),
            'inventoryAllocations' => fn($q) => $q->with('item.category')->orderBy('created_at'),
            'volunteerCheckIns'    => fn($q) => $q->with('volunteer')->orderBy('checked_in_at'),
        ]);

        $inventoryItems = InventoryItem::active()
            ->with('category')
            ->orderBy('name')
            ->get();

        // Finance tab data
        $financeService       = app(FinanceService::class);
        $eventFinanceKpis     = $financeService->eventKpis($event->id);
        $eventTransactions    = FinanceTransaction::forEvent($event->id)
            ->with('category')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();
        $financeCategories    = FinanceCategory::active()->orderBy('type')->orderBy('name')->get();

        $showAverageRating        = (bool) SettingService::get('reviews.show_average_rating', true);
        $enableEventAllocations   = (bool) SettingService::get('inventory.enable_event_allocations', true);
        $enableEventFinanceMetrics= (bool) SettingService::get('finance.enable_event_metrics', true);

        // Media upload config — driven by the general settings group so an
        // admin can tune the limit and format whitelist without a deploy.
        // The view uses the mimes list as the file picker's accept attribute
        // and the max-mb in the JS error copy when a 413 / 422 fires.
        $mediaUploadConfig = [
            'max_mb' => max(1, min(500, (int) SettingService::get('general.max_upload_size_mb', 50))),
            'mimes'  => (array) SettingService::get('general.allowed_upload_formats', [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'video/mp4',  'video/quicktime', 'video/x-msvideo', 'video/webm',
                'application/pdf',
            ]),
        ];
        if (empty($mediaUploadConfig['mimes'])) {
            $mediaUploadConfig['mimes'] = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'video/mp4',  'video/quicktime', 'video/x-msvideo', 'video/webm',
                'application/pdf',
            ];
        }

        // Live stat-card data for the Details tab. Each metric is computed
        // straight from the source-of-truth tables — no caching / no eager
        // collection accidentally double-counting represented households.
        $eventStats = [
            // Food Packs (renamed from "Food Bundles") — total bags handed
            // out, summed across every exited visit at this event.
            'packs_served'      => (int) $event->visits()
                ->where('visit_status', 'exited')
                ->sum('served_bags'),
            // Distinct households served. Counts every household ATTACHED to
            // an exited visit, including represented households (so the
            // number reflects every family actually fed at this event).
            'households_served' => (int) DB::table('visit_households')
                ->join('visits', 'visit_households.visit_id', '=', 'visits.id')
                ->where('visits.event_id', $event->id)
                ->where('visits.visit_status', 'exited')
                ->distinct()
                ->count('visit_households.household_id'),
            // Volunteers who actually showed up (not just assigned).
            'volunteers_served' => $event->volunteerCheckIns->count(),
            // Already-live count, surfaced through the same array for parity.
            'attendees_pre_reg' => $event->preRegistrations->count(),
        ];

        return view('events.show', compact(
            'event', 'inventoryItems',
            'eventFinanceKpis', 'eventTransactions', 'financeCategories',
            'showAverageRating', 'enableEventAllocations', 'enableEventFinanceMetrics',
            'eventStats', 'mediaUploadConfig'
        ));
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(Event $event): View
    {
        $this->authorize('update', $event);
        abort_if($event->isLocked(), 403, 'Past events cannot be edited.');

        [$allGroups, $groupMap, $allVolunteers, $rulesets] = $this->formData();
        $event->loadMissing('assignedVolunteers');
        return view('events.edit', compact('event', 'allGroups', 'groupMap', 'allVolunteers', 'rulesets'));
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_if($event->isLocked(), 403, 'Past events cannot be edited.');

        $data = $request->validated();
        $volunteerIds = $data['volunteer_ids'] ?? [];
        unset($data['volunteer_ids']);

        // Re-derive status only if the date is being changed and event is not already past
        $newDate = Carbon::parse($data['date']);
        if (! $event->date->equalTo($newDate)) {
            $data['status'] = Event::deriveStatus($newDate);
        }

        $event->update($data);
        $event->assignedVolunteers()->sync($volunteerIds);

        return redirect()
            ->route('events.show', $event)
            ->with('success', 'Event updated successfully.');
    }

    // ─── Update status (AJAX) ─────────────────────────────────────────────────

    public function updateStatus(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);
        $request->validate([
            'status' => ['required', 'string', 'in:upcoming,current,past,undo'],
        ]);

        $requested = $request->input('status');

        // Resolve 'undo' to a concrete status based on the event date
        if ($requested === 'undo') {
            if ($event->status !== 'past') {
                return response()->json(['error' => 'Only past events can be undone.'], 422);
            }
            $new = $event->date->isFuture() ? 'upcoming' : 'current';
        } else {
            $new = $requested;

            $allowed = match ($event->status) {
                'upcoming' => ['current'],
                'current'  => ['past'],
                default    => [],
            };

            if (! in_array($new, $allowed)) {
                return response()->json(
                    ['error' => "Cannot transition from \"{$event->status}\" to \"{$new}\"."],
                    422
                );
            }
        }

        $event->update(['status' => $new]);

        return response()->json([
            'status'       => $event->status,
            'statusLabel'  => $event->statusLabel(),
            'badgeClasses' => $event->statusBadgeClasses(),
        ]);
    }

    // ─── Shared form data ────────────────────────────────────────────────────

    private function formData(): array
    {
        $allGroups = VolunteerGroup::with('volunteers')->orderBy('name')->get();
        $groupMap  = $allGroups->mapWithKeys(fn($g) => [
            $g->id => $g->volunteers->map(fn($v) => ['id' => $v->id, 'name' => $v->full_name]),
        ]);
        $allVolunteers = Volunteer::orderBy('last_name')->orderBy('first_name')->get();
        $rulesets      = AllocationRuleset::orderByDesc('is_active')->orderBy('name')->get();
        return [$allGroups, $groupMap, $allVolunteers, $rulesets];
    }

    // ─── Attendee: match to existing household ────────────────────────────────

    public function matchAttendee(Event $event, EventPreRegistration $attendee): RedirectResponse
    {
        $this->authorize('update', $event);
        $attendee->update([
            'household_id' => $attendee->potential_household_id,
            'match_status' => 'matched',
        ]);

        return back()->with('success', 'Attendee matched to existing household.');
    }

    // ─── Attendee: dismiss potential match ────────────────────────────────────

    public function dismissAttendee(Event $event, EventPreRegistration $attendee): RedirectResponse
    {
        $this->authorize('update', $event);
        $attendee->update([
            'potential_household_id' => null,
            'match_status'           => null,
        ]);

        return back()->with('success', 'Potential match dismissed — attendee marked as new.');
    }

    // ─── Attendee: register as new household ─────────────────────────────────

    public function registerAttendee(Event $event, EventPreRegistration $attendee): RedirectResponse
    {
        $this->authorize('update', $event);

        // Phase 6.5.b: never create a duplicate household. Prefer existing links.

        // 1. Already linked to an existing household? Just mark matched.
        if ($attendee->household_id && \App\Models\Household::whereKey($attendee->household_id)->exists()) {
            $attendee->update(['match_status' => 'matched']);
            return back()->with('success', 'Linked to existing household.');
        }

        // 2. A potential match was detected? Use it instead of creating new.
        if ($attendee->potential_household_id && \App\Models\Household::whereKey($attendee->potential_household_id)->exists()) {
            $attendee->update([
                'household_id' => $attendee->potential_household_id,
                'match_status' => 'matched',
            ]);
            return back()->with('success', 'Linked to existing household via potential match.');
        }

        // 3. Truly new — create the household.
        $household = app(HouseholdService::class)->create([
            'first_name'     => $attendee->first_name,
            'last_name'      => $attendee->last_name,
            'email'          => $attendee->email,
            'city'           => $attendee->city,
            'state'          => $attendee->state,
            'zip'            => $attendee->zipcode,
            'children_count' => $attendee->children_count ?? 0,
            'adults_count'   => $attendee->adults_count   ?? 0,
            'seniors_count'  => $attendee->seniors_count  ?? 0,
        ]);

        $attendee->update([
            'household_id' => $household->id,
            'match_status' => 'matched',
        ]);

        return back()->with('success', "New household #{$household->household_number} created and linked.");
    }

    // ─── Attendee: delete ─────────────────────────────────────────────────────

    public function deleteAttendee(Event $event, EventPreRegistration $attendee): RedirectResponse
    {
        $this->authorize('update', $event);
        $attendee->delete();
        return back()->with('success', 'Attendee removed.');
    }

    // ─── Detach volunteer ─────────────────────────────────────────────────────

    public function detachVolunteer(Event $event, Volunteer $volunteer): JsonResponse
    {
        $this->authorize('update', $event);
        $event->assignedVolunteers()->detach($volunteer->id);

        return response()->json([
            'ok'    => true,
            'count' => $event->assignedVolunteers()->count(),
        ]);
    }

    // ─── Regenerate auth codes ────────────────────────────────────────────────

    public function regenerateCodes(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($event->isCurrent(), 403, 'Auth codes can only be regenerated for active events.');
        abort_unless(
            SettingService::get('public_access.allow_code_regeneration', true),
            403,
            'Auth code regeneration is disabled in settings.'
        );
        $codes = $event->regenerateAuthCodes();

        return back()
            ->with('success', 'Auth codes regenerated.')
            ->with('new_auth_codes', $codes);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        $name = $event->name;
        $event->delete();

        return redirect()
            ->route('events.index')
            ->with('success', "\"{$name}\" has been deleted.");
    }
}
