<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryCategoryController extends Controller
{
    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $categories = InventoryCategory::withCount('items')
            ->orderBy('name')
            ->get();

        return view('inventory.categories.index', compact('categories'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:inventory_categories,name',
            'description' => 'nullable|string|max:500',
        ]);

        InventoryCategory::create($data);

        return redirect()
            ->route('inventory.categories.index')
            ->with('success', "\"{$data['name']}\" category created.");
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, InventoryCategory $inventoryCategory): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:inventory_categories,name,' . $inventoryCategory->id,
            'description' => 'nullable|string|max:500',
        ]);

        $inventoryCategory->update($data);

        return redirect()
            ->route('inventory.categories.index')
            ->with('success', "\"{$inventoryCategory->name}\" updated.");
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(InventoryCategory $inventoryCategory): RedirectResponse
    {
        if ($inventoryCategory->items()->exists()) {
            return redirect()
                ->route('inventory.categories.index')
                ->with('error', "Cannot delete \"{$inventoryCategory->name}\" — it has items assigned. Reassign or delete those items first.");
        }

        $name = $inventoryCategory->name;
        $inventoryCategory->delete();

        return redirect()
            ->route('inventory.categories.index')
            ->with('success', "\"{$name}\" category deleted.");
    }
}
