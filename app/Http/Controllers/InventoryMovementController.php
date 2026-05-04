<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryMovementRequest;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class InventoryMovementController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Record a manual stock movement (add / remove / adjust).
     * Called from the item show page modals.
     */
    public function store(StoreInventoryMovementRequest $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $data   = $request->validated();
        $action = $data['action'];
        $qty    = (int) $data['quantity'];
        $notes  = $data['notes'] ?? null;
        $userId = auth()->id();

        try {
            match ($action) {
                'add' => $this->inventory->addStock(
                    item:     $inventoryItem,
                    quantity: $qty,
                    notes:    $notes,
                    userId:   $userId,
                ),

                'remove' => $this->inventory->removeStock(
                    item:     $inventoryItem,
                    quantity: $qty,
                    type:     $data['movement_type'],
                    notes:    $notes,
                    userId:   $userId,
                ),

                'adjust' => $this->inventory->adjustStock(
                    item:      $inventoryItem,
                    targetQty: $qty,
                    notes:     $notes,
                    userId:    $userId,
                ),
            };
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->with('movement_error', $e->getMessage());
        }

        $label = match ($action) {
            'add'    => 'Stock added successfully.',
            'remove' => 'Stock removed.',
            'adjust' => 'Stock adjusted to ' . number_format($qty) . ' ' . $inventoryItem->unit_type . '.',
        };

        return redirect()
            ->route('inventory.items.show', $inventoryItem)
            ->with('success', $label);
    }
}
