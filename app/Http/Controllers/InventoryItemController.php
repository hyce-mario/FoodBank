<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    /**
     * Phase 6.6 (server-side combobox): JSON endpoint for typeahead search.
     * Returns up to 50 active items matching the query string. Used by the
     * Purchase Order create form's line-item picker so we don't ship the
     * entire item catalog into every page render.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $query = InventoryItem::active()->with('category');

        if ($term = trim((string) $request->get('q', ''))) {
            $query->search($term);
        }

        $items = $query->orderBy('name')->limit(50)->get();

        return response()->json([
            'results' => $items->map(fn ($i) => [
                'id'       => $i->id,
                'name'     => $i->name,
                'category' => $i->category ? ['name' => $i->category->name] : null,
            ])->all(),
        ]);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $query = InventoryItem::query()->with('category');

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($categoryId = $request->get('category')) {
            $query->where('category_id', $categoryId);
        }

        // Respect show_inactive_items setting for the default (no status filter) view
        $showInactiveByDefault = (bool) SettingService::get('inventory.show_inactive_items', false);

        $status = $request->get('status');
        match ($status) {
            'low'           => $query->active()->lowStock(),
            'out'           => $query->active()->outOfStock(),
            'expiring_soon' => $query->active()->expiringSoon(),
            'expired'       => $query->active()->expired(),
            'inactive'      => $query->where('is_active', false),
            default         => $showInactiveByDefault ? $query : $query->active(),
        };

        $perPage    = (int) SettingService::get('general.records_per_page', 25);
        $items      = $query->orderBy('name')->paginate($perPage)->withQueryString();
        $categories = InventoryCategory::orderBy('name')->get();

        // Summary counts (always across all active items)
        $totalActive    = InventoryItem::active()->count();
        $lowStockCount  = InventoryItem::active()->lowStock()->count();
        $outOfStock     = InventoryItem::active()->outOfStock()->count();
        $expiringSoon   = InventoryItem::active()->expiringSoon()->count();
        $expiredCount   = InventoryItem::active()->expired()->count();

        return view('inventory.items.index', compact(
            'items', 'categories', 'status',
            'totalActive', 'lowStockCount', 'outOfStock',
            'expiringSoon', 'expiredCount'
        ));
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        $categories          = InventoryCategory::orderBy('name')->get();
        $defaultReorderLevel = (int) SettingService::get('inventory.low_stock_threshold', 10);
        return view('inventory.items.create', compact('categories', 'defaultReorderLevel'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $item = InventoryItem::create($data);

        return redirect()
            ->route('inventory.items.show', $item)
            ->with('success', "\"{$item->name}\" has been added to inventory.");
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Request $request, InventoryItem $inventoryItem): View
    {
        $inventoryItem->loadMissing('category');

        $movementQuery = $inventoryItem->movements()
            ->with(['user', 'event'])
            ->latest('created_at');

        if ($typeFilter = $request->get('movement_type')) {
            $movementQuery->where('movement_type', $typeFilter);
        }

        if ($dateFrom = $request->get('date_from')) {
            $movementQuery->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $movementQuery->whereDate('created_at', '<=', $dateTo);
        }

        $movements    = $movementQuery->paginate(20)->withQueryString();
        $movementTypes = InventoryMovement::TYPES;

        return view('inventory.items.show', [
            'item'          => $inventoryItem,
            'movements'     => $movements,
            'movementTypes' => $movementTypes,
        ]);
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(InventoryItem $inventoryItem): View
    {
        $categories = InventoryCategory::orderBy('name')->get();
        return view('inventory.items.edit', [
            'item'       => $inventoryItem,
            'categories' => $categories,
        ]);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $inventoryItem->update($data);

        return redirect()
            ->route('inventory.items.show', $inventoryItem)
            ->with('success', "\"{$inventoryItem->name}\" has been updated.");
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function destroy(InventoryItem $inventoryItem): RedirectResponse
    {
        $name = $inventoryItem->name;
        $inventoryItem->delete();

        return redirect()
            ->route('inventory.items.index')
            ->with('success', "\"{$name}\" has been removed from inventory.");
    }
}
