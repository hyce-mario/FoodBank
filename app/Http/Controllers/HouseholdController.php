<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHouseholdRequest;
use App\Http\Requests\UpdateHouseholdRequest;
use App\Models\Household;
use App\Services\HouseholdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HouseholdController extends Controller
{
    public function __construct(private readonly HouseholdService $householdService) {}

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->get('per_page', 10), [10, 25, 50, 100])
            ? (int) $request->get('per_page', 10)
            : 10;

        $query = Household::query();

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($zip = $request->get('zip')) {
            $query->where('zip', $zip);
        }

        if ($size = $request->get('size')) {
            $query->where('household_size', $size);
        }

        // Sorting
        $sort      = in_array($request->get('sort'), ['household_number', 'first_name', 'household_size', 'created_at'])
            ? $request->get('sort')
            : 'created_at';
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $direction);

        $households = $query->paginate($perPage)->withQueryString();

        // Zip list for filter dropdown
        $zipCodes = Household::whereNotNull('zip')
            ->distinct()
            ->orderBy('zip')
            ->pluck('zip');

        // Sizes for filter dropdown
        $sizes = Household::distinct()
            ->orderBy('household_size')
            ->pluck('household_size');

        return view('households.index', compact('households', 'zipCodes', 'sizes'));
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('households.create');
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreHouseholdRequest $request): RedirectResponse
    {
        $household = $this->householdService->create($request->validated());

        return redirect()
            ->route('households.show', $household)
            ->with('success', "Household #{$household->household_number} created successfully.");
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Household $household): View
    {
        return view('households.show', compact('household'));
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(Household $household): View
    {
        return view('households.edit', compact('household'));
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateHouseholdRequest $request, Household $household): RedirectResponse
    {
        $this->householdService->update($household, $request->validated());

        return redirect()
            ->route('households.show', $household)
            ->with('success', 'Household updated successfully.');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(Household $household): RedirectResponse
    {
        $household->delete();

        return redirect()
            ->route('households.index')
            ->with('success', 'Household deleted successfully.');
    }

    // ─── Regenerate QR ───────────────────────────────────────────────────────

    public function regenerateQr(Household $household): RedirectResponse
    {
        $this->householdService->regenerateQrToken($household);

        return back()->with('success', 'QR code regenerated successfully.');
    }
}
