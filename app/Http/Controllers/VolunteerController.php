<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVolunteerRequest;
use App\Http\Requests\UpdateVolunteerRequest;
use App\Models\Volunteer;
use App\Services\VolunteerCheckInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VolunteerController extends Controller
{
    public function __construct(
        protected VolunteerCheckInService $checkInService,
    ) {}

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\Volunteer::class);
        $query = Volunteer::withCount(['groups', 'checkIns']);

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        $sort      = in_array($request->get('sort'), ['first_name', 'last_name', 'role', 'created_at'])
            ? $request->get('sort') : 'created_at';
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $perPage = in_array((int) $request->get('per_page', 15), [15, 25, 50])
            ? (int) $request->get('per_page', 15) : 15;

        $volunteers = $query->orderBy($sort, $direction)
                            ->paginate($perPage)
                            ->withQueryString();

        $roles = Volunteer::ROLES;

        return view('volunteers.index', compact('volunteers', 'roles'));
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        $this->authorize('create', \App\Models\Volunteer::class);
        $roles = Volunteer::ROLES;
        return view('volunteers.create', compact('roles'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreVolunteerRequest $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\Volunteer::class);
        $volunteer = Volunteer::create($request->validated());

        return redirect()
            ->route('volunteers.show', $volunteer)
            ->with('success', "Volunteer {$volunteer->full_name} created successfully.");
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Volunteer $volunteer): View
    {
        $this->authorize('view', $volunteer);
        $volunteer->loadMissing('groups');
        $stats = $this->checkInService->stats($volunteer);

        return view('volunteers.show', compact('volunteer', 'stats'));
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(Volunteer $volunteer): View
    {
        $this->authorize('update', $volunteer);
        $roles = Volunteer::ROLES;
        return view('volunteers.edit', compact('volunteer', 'roles'));
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateVolunteerRequest $request, Volunteer $volunteer): RedirectResponse
    {
        $this->authorize('update', $volunteer);
        $volunteer->update($request->validated());

        return redirect()
            ->route('volunteers.show', $volunteer)
            ->with('success', 'Volunteer updated successfully.');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(Volunteer $volunteer): RedirectResponse
    {
        $this->authorize('delete', $volunteer);
        $name = $volunteer->full_name;
        $volunteer->delete();

        return redirect()
            ->route('volunteers.index')
            ->with('success', "{$name} has been removed.");
    }
}
