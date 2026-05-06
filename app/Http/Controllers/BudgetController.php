<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Event;
use App\Models\FinanceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Phase 7.4.b — admin CRUD for budget rows.
 *
 * Lists, creates, updates, deletes Budget records. The Budget vs. Actual
 * report (FinanceReportController::budgetVsActual) reads from this table —
 * without budgets seeded, the report shows 0 budget across the board.
 *
 * Routing/auth: gated on finance.{view,edit} per Tier 2; sits at
 * /finance/budgets so it groups with the rest of the finance module.
 */
class BudgetController extends Controller
{
    public function index(Request $request): View
    {
        $query = Budget::with(['category:id,name,type', 'event:id,name,date', 'creator:id,name'])
            ->orderByDesc('period_start')
            ->orderBy('category_id');

        if ($categoryId = $request->integer('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($scope = $request->get('scope')) {
            if ($scope === 'org')        $query->whereNull('event_id');
            elseif ($scope === 'event')  $query->whereNotNull('event_id');
        }

        $budgets    = $query->paginate(25)->withQueryString();
        $categories = FinanceCategory::active()->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);
        $events     = Event::orderByDesc('date')->limit(50)->get(['id', 'name', 'date']);

        return view('finance.budgets.index', compact('budgets', 'categories', 'events'));
    }

    public function create(): View
    {
        $categories = FinanceCategory::active()->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);
        $events     = Event::orderByDesc('date')->limit(50)->get(['id', 'name', 'date']);

        return view('finance.budgets.create', compact('categories', 'events'));
    }

    public function store(StoreBudgetRequest $request): RedirectResponse
    {
        $data               = $request->validated();
        $data['period_type'] = 'monthly';
        $data['created_by']  = Auth::id();

        Budget::create($data);

        return redirect()->route('finance.budgets.index')
            ->with('success', 'Budget saved.');
    }

    public function edit(Budget $budget): View
    {
        $categories = FinanceCategory::active()->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);
        $events     = Event::orderByDesc('date')->limit(50)->get(['id', 'name', 'date']);

        return view('finance.budgets.edit', compact('budget', 'categories', 'events'));
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): RedirectResponse
    {
        $data                = $request->validated();
        $data['period_type'] = 'monthly';

        $budget->update($data);

        return redirect()->route('finance.budgets.index')
            ->with('success', 'Budget updated.');
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $budget->delete();

        return redirect()->route('finance.budgets.index')
            ->with('success', 'Budget removed.');
    }
}
