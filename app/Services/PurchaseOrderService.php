<?php

namespace App\Services;

use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phase 6.6 — handles the receive-goods workflow that ties inventory
 * movements and finance transactions together for inventory purchases.
 *
 * `create()` saves a draft PO with line items.
 * `markReceived()` is the atomic bridge: inside one DB transaction it
 *   - writes one InventoryMovement(stock_in) per line item and stores the
 *     FK back on the line, and
 *   - writes a single FinanceTransaction(expense) for the total and stores
 *     the FK back on the PO header.
 * If anything throws inside the transaction, neither side lands and the PO
 * stays in 'draft' so staff can retry.
 *
 * Non-inventory finance transactions (staff payments, rent, etc.) continue
 * to use FinanceTransaction directly — POs are an additive, opt-in path.
 */
class PurchaseOrderService
{
    /**
     * Create a draft PO with line items. Computes line_total per row and the
     * PO total_amount; auto-generates the PO number if not supplied.
     *
     * @param  array{
     *   supplier_name: string,
     *   order_date: string,
     *   notes?: string|null,
     *   po_number?: string|null,
     *   items: array<int, array{inventory_item_id: int, quantity: int, unit_cost: float}>
     * }  $data
     */
    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];
            if (empty($items)) {
                throw new RuntimeException('A purchase order must have at least one line item.');
            }

            $total = 0.0;
            foreach ($items as $row) {
                $total += ((int) $row['quantity']) * ((float) $row['unit_cost']);
            }

            $po = PurchaseOrder::create([
                'po_number'     => $data['po_number'] ?? PurchaseOrder::generatePoNumber(),
                'supplier_name' => trim($data['supplier_name']),
                'order_date'    => $data['order_date'],
                'status'        => 'draft',
                'total_amount'  => round($total, 2),
                'notes'         => $data['notes'] ?? null,
                'created_by'    => Auth::id(),
            ]);

            foreach ($items as $row) {
                $qty       = (int) $row['quantity'];
                $unitCost  = (float) $row['unit_cost'];
                $lineTotal = round($qty * $unitCost, 2);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'inventory_item_id' => (int) $row['inventory_item_id'],
                    'quantity'          => $qty,
                    'unit_cost'         => $unitCost,
                    'line_total'        => $lineTotal,
                ]);
            }

            return $po->load('items.item');
        });
    }

    /**
     * Mark a draft PO as received. Atomically writes one InventoryMovement
     * per line and one FinanceTransaction for the total, and back-links
     * both into the PO + lines.
     *
     * Throws RuntimeException if the PO is not in 'draft' status.
     */
    public function markReceived(PurchaseOrder $po, ?int $financeCategoryId = null, ?Carbon $receivedDate = null): PurchaseOrder
    {
        if (! $po->isDraft()) {
            throw new RuntimeException("Only draft purchase orders can be received (current status: {$po->status}).");
        }

        $receivedDate ??= now();

        return DB::transaction(function () use ($po, $financeCategoryId, $receivedDate) {
            $po->loadMissing('items.item');

            // 1) Stock-in movement per line item
            foreach ($po->items as $line) {
                $movement = InventoryMovement::create([
                    'inventory_item_id' => $line->inventory_item_id,
                    'movement_type'     => 'stock_in',
                    'quantity'          => $line->quantity,
                    'user_id'           => Auth::id(),
                    'notes'             => "Received via {$po->po_number} — {$po->supplier_name}",
                ]);
                $line->update(['inventory_movement_id' => $movement->id]);
            }

            // 2) Single expense transaction for the PO total
            $tx = FinanceTransaction::create([
                'transaction_type'  => 'expense',
                'title'             => "Inventory purchase — {$po->po_number}",
                'category_id'       => $financeCategoryId ?: $this->resolveDefaultInventoryCategoryId(),
                'amount'            => $po->total_amount,
                'transaction_date'  => $receivedDate->toDateString(),
                'source_or_payee'   => $po->supplier_name,
                'reference_number'  => $po->po_number,
                'status'            => 'completed',
                'created_by'        => Auth::id(),
                'notes'             => "Auto-generated from purchase order {$po->po_number}.",
            ]);

            // 3) Update the PO header
            $po->update([
                'status'                 => 'received',
                'received_date'          => $receivedDate->toDateString(),
                'finance_transaction_id' => $tx->id,
            ]);

            return $po->fresh(['items.item', 'items.inventoryMovement', 'financeTransaction']);
        });
    }

    /**
     * Cancel a draft PO. Received POs cannot be cancelled (would orphan
     * inventory and finance records); they can only be archived.
     */
    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        if (! $po->isDraft()) {
            throw new RuntimeException("Only draft purchase orders can be cancelled (current status: {$po->status}).");
        }

        $po->update(['status' => 'cancelled']);
        return $po->fresh();
    }

    /**
     * Resolve an "Inventory Purchases" finance category id; create one if
     * the install doesn't have it yet so received POs always have a sensible
     * categorisation in the finance ledger.
     */
    private function resolveDefaultInventoryCategoryId(): int
    {
        $existing = FinanceCategory::where('type', 'expense')
            ->whereRaw("LOWER(name) IN ('inventory purchases', 'inventory', 'food purchases')")
            ->orderByRaw("LOWER(name) = 'inventory purchases' DESC")
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $created = FinanceCategory::create([
            'name'      => 'Inventory Purchases',
            'type'      => 'expense',
            'is_active' => true,
        ]);

        return $created->id;
    }
}
