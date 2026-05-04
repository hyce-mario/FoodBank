<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVolunteerGroupRequest;
use App\Http\Requests\UpdateGroupMembersRequest;
use App\Http\Requests\UpdateVolunteerGroupRequest;
use App\Models\Volunteer;
use App\Models\VolunteerGroup;
use App\Services\VolunteerGroupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VolunteerGroupController extends Controller
{
    public function __construct(private readonly VolunteerGroupService $groupService) {}

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorize('viewAny', VolunteerGroup::class);

        $query = VolunteerGroup::withCount('volunteers');

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $perPage = in_array((int) $request->get('per_page', 15), [15, 25, 50])
            ? (int) $request->get('per_page', 15) : 15;

        $groups = $query->orderBy('name')
                        ->paginate($perPage)
                        ->withQueryString();

        return view('volunteer-groups.index', compact('groups'));
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        $this->authorize('create', VolunteerGroup::class);

        return view('volunteer-groups.create');
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreVolunteerGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', VolunteerGroup::class);

        $group = VolunteerGroup::create($request->validated());

        return redirect()
            ->route('volunteer-groups.show', $group)
            ->with('success', "Group \"{$group->name}\" created successfully.");
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(VolunteerGroup $volunteerGroup): View
    {
        $this->authorize('view', $volunteerGroup);

        $volunteerGroup->load(['volunteers' => fn ($q) => $q->orderBy('last_name')->orderBy('first_name')]);

        return view('volunteer-groups.show', compact('volunteerGroup'));
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(VolunteerGroup $volunteerGroup): View
    {
        $this->authorize('update', $volunteerGroup);

        return view('volunteer-groups.edit', compact('volunteerGroup'));
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateVolunteerGroupRequest $request, VolunteerGroup $volunteerGroup): RedirectResponse
    {
        $this->authorize('update', $volunteerGroup);

        $volunteerGroup->update($request->validated());

        return redirect()
            ->route('volunteer-groups.show', $volunteerGroup)
            ->with('success', 'Group updated successfully.');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(VolunteerGroup $volunteerGroup): RedirectResponse
    {
        $this->authorize('delete', $volunteerGroup);

        $name = $volunteerGroup->name;
        $volunteerGroup->delete();

        return redirect()
            ->route('volunteer-groups.index')
            ->with('success', "\"{$name}\" has been deleted.");
    }

    // ─── Edit Members ─────────────────────────────────────────────────────────

    public function editMembers(VolunteerGroup $volunteerGroup): View
    {
        $this->authorize('manageMembers', $volunteerGroup);

        $currentIds = $volunteerGroup->volunteers()->pluck('volunteers.id')->toArray();

        $allVolunteers = Volunteer::orderBy('last_name')
                                  ->orderBy('first_name')
                                  ->get();

        return view('volunteer-groups.members', compact('volunteerGroup', 'allVolunteers', 'currentIds'));
    }

    // ─── Update Members ───────────────────────────────────────────────────────

    public function updateMembers(UpdateGroupMembersRequest $request, VolunteerGroup $volunteerGroup): RedirectResponse
    {
        $this->authorize('manageMembers', $volunteerGroup);

        $ids = $request->validated()['volunteer_ids'] ?? [];

        $this->groupService->syncMembers($volunteerGroup, array_map('intval', $ids));

        return redirect()
            ->route('volunteer-groups.show', $volunteerGroup)
            ->with('success', 'Group members updated successfully.');
    }
}
