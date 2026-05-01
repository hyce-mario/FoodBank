<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHouseholdRequest;
use App\Http\Requests\UpdateHouseholdRequest;
use App\Models\Household;
use App\Services\HouseholdService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function __construct(private readonly HouseholdService $householdService) {}

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Household::class);
        $defaultPerPage = (int) SettingService::get('general.records_per_page', 25);
        $perPage = in_array((int) $request->get('per_page', $defaultPerPage), [10, 25, 50, 100])
            ? (int) $request->get('per_page', $defaultPerPage)
            : $defaultPerPage;

        // Phase 6.7: events_attended_count is now a cached column on
        // households (maintained by EventCheckInService + Visit observer).
        // Only first_event_date still needs a correlated subquery — caching
        // it would require recomputation every time a Visit is deleted.
        $firstEventDateSub = DB::table('visit_households as vh2')
            ->join('visits as v2', 'vh2.visit_id', '=', 'v2.id')
            ->join('events as e2', 'v2.event_id', '=', 'e2.id')
            ->whereColumn('vh2.household_id', 'households.id')
            ->selectRaw('MIN(e2.date)');

        $query = Household::query()
            ->select('households.*')
            ->selectSub($firstEventDateSub, 'first_event_date');

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($zip = $request->get('zip')) {
            $query->where('zip', $zip);
        }

        if ($size = $request->get('size')) {
            $query->where('household_size', $size);
        }

        // Phase 6.7: filter on the cached column (no subquery)
        $attendance = $request->get('attendance');
        if ($attendance === 'first_timer') {
            $query->where('events_attended_count', 1);
        } elseif ($attendance === 'returning') {
            $query->where('events_attended_count', '>', 1);
        }

        $sort      = in_array($request->get('sort'), ['household_number', 'first_name', 'household_size', 'created_at'])
            ? $request->get('sort')
            : 'created_at';
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $direction);

        $households = $query->paginate($perPage)->withQueryString();

        $zipCodes = Household::whereNotNull('zip')->distinct()->orderBy('zip')->pluck('zip');
        $sizes    = Household::distinct()->orderBy('household_size')->pluck('household_size');

        return view('households.index', compact('households', 'zipCodes', 'sizes'));
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        $this->authorize('create', Household::class);
        $householdSettings = $this->householdFormSettings();
        return view('households.create', compact('householdSettings'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreHouseholdRequest $request): RedirectResponse
    {
        $this->authorize('create', Household::class);
        $data = $request->validated();

        // Phase 6.5.c: duplicate check unless staff has explicitly confirmed.
        // force_create=1 is set by the "Create anyway" button on the warning panel.
        if (! $request->boolean('force_create')) {
            $duplicates = $this->householdService->findPotentialDuplicates($data);
            if ($duplicates->isNotEmpty()) {
                return redirect()
                    ->route('households.create')
                    ->withInput()
                    ->with('potential_duplicates', $duplicates);
            }
        }

        $household = $this->householdService->create($data);

        return redirect()
            ->route('households.show', $household)
            ->with('success', "Household #{$household->household_number} created successfully.");
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Household $household): View
    {
        $this->authorize('view', $household);
        $household->load(['representative', 'representedHouseholds']);

        // Candidate households for the attach modal (not yet linked, not self)
        $attachCandidates = Household::whereNull('representative_household_id')
            ->where('id', '!=', $household->id)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'household_number']);

        return view('households.show', compact('household', 'attachCandidates'));
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(Household $household): View
    {
        $this->authorize('update', $household);
        $household->load('representedHouseholds');
        $householdSettings = $this->householdFormSettings();
        return view('households.edit', compact('household', 'householdSettings'));
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateHouseholdRequest $request, Household $household): RedirectResponse
    {
        $this->authorize('update', $household);
        $this->householdService->update($household, $request->validated());

        return redirect()
            ->route('households.show', $household)
            ->with('success', 'Household updated successfully.');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(Household $household): RedirectResponse
    {
        $this->authorize('delete', $household);
        $household->delete();

        return redirect()
            ->route('households.index')
            ->with('success', 'Household deleted successfully.');
    }

    // ─── Regenerate QR ───────────────────────────────────────────────────────

    public function regenerateQr(Household $household): RedirectResponse
    {
        $this->authorize('update', $household);
        $this->householdService->regenerateQrToken($household);

        return back()->with('success', 'QR code regenerated successfully.');
    }

    // ─── Attach a household to this representative ────────────────────────────

    public function attach(Request $request, Household $household): RedirectResponse
    {
        $this->authorize('update', $household);
        $data = $request->validate([
            'represented_id' => ['required', 'integer', 'exists:households,id'],
        ]);

        $represented = Household::findOrFail($data['represented_id']);

        try {
            $this->householdService->attach($household, $represented);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "\"{$represented->full_name}\" is now linked to this household.");
    }

    // ─── Detach a represented household ──────────────────────────────────────

    public function detach(Household $household, Household $represented): RedirectResponse
    {
        $this->authorize('update', $household);
        if ($represented->representative_household_id !== $household->id) {
            return back()->with('error', 'That household is not linked to this representative.');
        }

        $this->householdService->detach($represented);

        return back()->with('success', "\"{$represented->full_name}\" has been unlinked.");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function householdFormSettings(): array
    {
        return [
            'require_phone'          => (bool) SettingService::get('households.require_phone',           false),
            'require_address'        => (bool) SettingService::get('households.require_address',         false),
            'require_vehicle_info'   => (bool) SettingService::get('households.require_vehicle_info',    false),
            'warn_duplicate_email'   => (bool) SettingService::get('households.warn_duplicate_email',    true),
            'warn_duplicate_phone'   => (bool) SettingService::get('households.warn_duplicate_phone',    true),
            'auto_generate_number'   => (bool) SettingService::get('households.auto_generate_household_number', true),
        ];
    }
}
