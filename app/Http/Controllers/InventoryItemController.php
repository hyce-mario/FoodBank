<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryItemController extends Controller
{
    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        $perPage    = (int) SettingService::get('general.records_per_page', 25);
        $items      = $query->orderBy('name')->paginate($perPage)->withQueryString();
        $categories = InventoryCategory::orderBy('name')->get();

        // Summary counts (always across all active items)
        $totalActive    = InventoryItem::active()->count();
        $lowStockCount  = InventoryItem::active()->lowStock()->count();
        $outOfStock     = InventoryItem::active()->outOfStock()->count();
        $expiringSoon   = InventoryItem::active()->expiringSoon()->count();
        $expiredCount   = InventoryItem::active()->expired()->count();

        $status = $request->get('status');

        return view('inventory.items.index', compact(
            'items', 'categories', 'status',
            'totalActive', 'lowStockCount', 'outOfStock',
            'expiringSoon', 'expiredCount'
        ));
    }

    // ─── Print ────────────────────────────────────────────────────────────────
    //
    // Branded standalone HTML sheet that mirrors whatever search/category/status
    // filters the user has active on the index. Auto-fires window.print() on
    // load. Same shape as visit-log.print and volunteer service-history print.

    public function print(Request $request): View
    {
        $items   = $this->filteredQuery($request)->orderBy('name')->get();
        $filters = $this->activeFilters($request);

        // Summary across the FILTERED set (not the global counts) so the
        // printed sheet reconciles with its own table.
        $summary = [
            'total'         => $items->count(),
            'total_qty'     => $items->sum('quantity_on_hand'),
            'low_stock'     => $items->filter(fn ($i) => $i->stockStatus() === 'low')->count(),
            'out_of_stock'  => $items->filter(fn ($i) => $i->stockStatus() === 'out')->count(),
            'expiring_soon' => $items->filter(fn ($i) => $i->expiryStatus() === 'expiring_soon')->count(),
            'expired'       => $items->filter(fn ($i) => $i->expiryStatus() === 'expired')->count(),
        ];

        return view('inventory.items.print', compact('items', 'summary', 'filters'));
    }

    // ─── Export (CSV) ─────────────────────────────────────────────────────────
    //
    // Streams the filtered set as CSV. Filename is date-stamped so repeat
    // exports don't overwrite each other in the user's downloads folder.

    public function export(Request $request): StreamedResponse
    {
        $items    = $this->filteredQuery($request)->orderBy('name')->get();
        $filename = 'inventory-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Name', 'SKU', 'Category', 'Unit',
                'Quantity', 'Reorder Level', 'Stock Status',
                'Manufacturing Date', 'Expiry Date', 'Expiry Status',
                'Active',
            ]);

            foreach ($items as $item) {
                fputcsv($out, [
                    $item->name,
                    $item->sku,
                    $item->category?->name,
                    $item->unit_type,
                    $item->quantity_on_hand,
                    $item->reorder_level,
                    $item->stockLabel(),
                    $item->manufacturing_date?->format('Y-m-d'),
                    $item->expiry_date?->format('Y-m-d'),
                    $item->expiryLabel() ?? '',
                    $item->is_active ? 'Yes' : 'No',
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    /**
     * Apply the same search/category/status narrowing the index page uses, so
     * Print + CSV download mirror exactly what the user is looking at. Single
     * source of truth — index() now also calls this.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = InventoryItem::query()->with('category');

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($categoryId = $request->get('category')) {
            $query->where('category_id', $categoryId);
        }

        $showInactiveByDefault = (bool) SettingService::get('inventory.show_inactive_items', false);

        match ($request->get('status')) {
            'low'           => $query->active()->lowStock(),
            'out'           => $query->active()->outOfStock(),
            'expiring_soon' => $query->active()->expiringSoon(),
            'expired'       => $query->active()->expired(),
            'inactive'      => $query->where('is_active', false),
            default         => $showInactiveByDefault ? $query : $query->active(),
        };

        return $query;
    }

    /**
     * Human-readable summary of the active filter set for the print-sheet
     * header. Returns null when nothing is filtered so the template can hide
     * the "Filtered by" line entirely.
     */
    private function activeFilters(Request $request): ?string
    {
        $parts = [];

        if ($categoryId = $request->get('category')) {
            $name = InventoryCategory::find($categoryId)?->name;
            if ($name) {
                $parts[] = 'Category: ' . $name;
            }
        }

        if ($status = $request->get('status')) {
            $parts[] = 'Status: ' . ucwords(str_replace('_', ' ', $status));
        }

        if ($search = trim((string) $request->get('search', ''))) {
            $parts[] = 'matching "' . $search . '"';
        }

        return $parts ? implode(' · ', $parts) : null;
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

    // ─── Quick-store (JSON, used by PO line-item picker) ─────────────────────
    //
    // Lets users add a new inventory item from inside the Purchase Order
    // create form without leaving the page. Validates a slim subset of fields
    // (the PO will set on-hand quantity at receipt time, so we default qty=0
    // and reorder_level from the inventory.low_stock_threshold setting).
    // Returns the newly-created item with its category eager-loaded so the
    // PO Alpine form can push it into the local items list and select it.

    public function quickStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:150'],
            'unit_type'   => ['required', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $item = InventoryItem::create([
            'name'             => $validated['name'],
            'unit_type'        => $validated['unit_type'],
            'category_id'      => $validated['category_id'] ?? null,
            'description'      => $validated['description'] ?? null,
            'quantity_on_hand' => 0,
            'reorder_level'    => (int) SettingService::get('inventory.low_stock_threshold', 10),
            'is_active'        => true,
        ]);

        $item->load('category');

        return response()->json([
            'item' => [
                'id'        => $item->id,
                'name'      => $item->name,
                'unit_type' => $item->unit_type,
                'category'  => $item->category ? [
                    'id'   => $item->category->id,
                    'name' => $item->category->name,
                ] : null,
            ],
        ], 201);
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
