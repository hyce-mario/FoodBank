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
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        return view('events.show', compact(
            'event', 'inventoryItems',
            'eventFinanceKpis', 'eventTransactions', 'financeCategories',
            'showAverageRating', 'enableEventAllocations', 'enableEventFinanceMetrics'
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
