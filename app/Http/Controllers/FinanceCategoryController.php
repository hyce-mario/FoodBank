<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinanceCategoryRequest;
use App\Http\Requests\UpdateFinanceCategoryRequest;
use App\Models\FinanceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinanceCategoryController extends Controller
{
    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $categories = FinanceCategory::withCount('transactions')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        // Phase 7.4.a — function options for the Add/Edit modals.
        $functionOptions = FinanceCategory::FUNCTION_LABELS;

        return view('finance.categories.index', compact('categories', 'functionOptions'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreFinanceCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        FinanceCategory::create($data);

        return redirect()
            ->route('finance.categories.index')
            ->with('success', 'Category created successfully.');
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateFinanceCategoryRequest $request, FinanceCategory $category): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $category->update($data);

        return redirect()
            ->route('finance.categories.index')
            ->with('success', "\"{$category->name}\" updated.");
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(FinanceCategory $category): RedirectResponse
    {
        if ($category->transactions()->exists()) {
            return redirect()
                ->route('finance.categories.index')
                ->with('error', 'Cannot delete a category that has transactions linked to it.');
        }

        $name = $category->name;
        $category->delete();

        return redirect()
            ->route('finance.categories.index')
            ->with('success', "\"{$name}\" deleted.");
    }
}
