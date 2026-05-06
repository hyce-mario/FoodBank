<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePledgeRequest;
use App\Http\Requests\UpdatePledgeRequest;
use App\Models\Event;
use App\Models\FinanceCategory;
use App\Models\Household;
use App\Models\Pledge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Phase 7.4.c — admin CRUD for pledge rows.
 *
 * Lists, creates, updates, deletes Pledge records. The Pledge / AR Aging
 * report (FinanceReportController::pledgeAging) reads from this table.
 *
 * Routing/auth: gated on finance.{view,edit} per Tier 2; sits at
 * /finance/pledges to group with the rest of the finance module.
 */
class PledgeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Pledge::with(['household:id,first_name,last_name', 'category:id,name', 'event:id,name,date'])
            ->orderByDesc('expected_at');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where('source_or_payee', 'like', "%{$search}%");
        }

        $pledges    = $query->paginate(25)->withQueryString();
        $households = Household::orderBy('last_name')->limit(200)->get(['id', 'first_name', 'last_name']);
        $categories = FinanceCategory::active()->where('type', 'income')->orderBy('name')->get(['id', 'name']);
        $events     = Event::orderByDesc('date')->limit(50)->get(['id', 'name', 'date']);

        return view('finance.pledges.index', compact('pledges', 'households', 'categories', 'events'));
    }

    public function create(): View
    {
        $households = Household::orderBy('last_name')->limit(200)->get(['id', 'first_name', 'last_name']);
        $categories = FinanceCategory::active()->where('type', 'income')->orderBy('name')->get(['id', 'name']);
        $events     = Event::orderByDesc('date')->limit(50)->get(['id', 'name', 'date']);

        return view('finance.pledges.create', compact('households', 'categories', 'events'));
    }

    public function store(StorePledgeRequest $request): RedirectResponse
    {
        $data               = $request->validated();
        $data['created_by'] = Auth::id();

        // If household_id is set and source_or_payee is empty, auto-fill from household.
        if (! empty($data['household_id']) && empty($data['source_or_payee'])) {
            $hh = Household::find($data['household_id']);
            if ($hh) {
                $data['source_or_payee'] = trim("{$hh->first_name} {$hh->last_name}");
            }
        }

        Pledge::create($data);

        return redirect()->route('finance.pledges.index')->with('success', 'Pledge saved.');
    }

    public function edit(Pledge $pledge): View
    {
        $households = Household::orderBy('last_name')->limit(200)->get(['id', 'first_name', 'last_name']);
        $categories = FinanceCategory::active()->where('type', 'income')->orderBy('name')->get(['id', 'name']);
        $events     = Event::orderByDesc('date')->limit(50)->get(['id', 'name', 'date']);

        return view('finance.pledges.edit', compact('pledge', 'households', 'categories', 'events'));
    }

    public function update(UpdatePledgeRequest $request, Pledge $pledge): RedirectResponse
    {
        $pledge->update($request->validated());

        return redirect()->route('finance.pledges.index')->with('success', 'Pledge updated.');
    }

    public function destroy(Pledge $pledge): RedirectResponse
    {
        $pledge->delete();

        return redirect()->route('finance.pledges.index')->with('success', 'Pledge removed.');
    }
}
