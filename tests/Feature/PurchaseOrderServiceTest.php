<?php

namespace Tests\Feature;

use App\Models\FinanceTransaction;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 6.6 — pin the receive-goods workflow contract.
 * markReceived() is the atomic bridge between the inventory and finance
 * domains: it must create N stock_in movements + 1 expense transaction
 * inside a single DB transaction, with FK back-links on both sides.
 */
class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(string $name = 'Beans'): InventoryItem
    {
        return InventoryItem::create([
            'name'             => $name,
            'unit_type'        => 'can',
            'quantity_on_hand' => 0,
            'reorder_level'    => 5,
            'is_active'        => true,
        ]);
    }

    public function test_create_saves_draft_with_line_items_and_total(): void
    {
        $beans = $this->makeItem('Beans');
        $rice  = $this->makeItem('Rice');

        $po = app(PurchaseOrderService::class)->create([
            'supplier_name' => 'ACME Foods',
            'order_date'    => '2026-04-30',
            'items' => [
                ['inventory_item_id' => $beans->id, 'quantity' => 100, 'unit_cost' => 1.50],
                ['inventory_item_id' => $rice->id,  'quantity' => 50,  'unit_cost' => 2.00],
            ],
        ]);

        $this->assertSame('draft', $po->status);
        $this->assertSame(2, $po->items->count());
        $this->assertEqualsWithDelta(250.00, (float) $po->total_amount, 0.001);
        $this->assertNotEmpty($po->po_number);
    }

    public function test_create_rejects_empty_items(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('at least one line item');

        app(PurchaseOrderService::class)->create([
            'supplier_name' => 'X',
            'order_date'    => '2026-04-30',
            'items'         => [],
        ]);
    }

    public function test_mark_received_creates_movements_and_transaction(): void
    {
        $beans = $this->makeItem('Beans');
        $rice  = $this->makeItem('Rice');

        $po = app(PurchaseOrderService::class)->create([
            'supplier_name' => 'ACME',
            'order_date'    => '2026-04-30',
            'items' => [
                ['inventory_item_id' => $beans->id, 'quantity' => 100, 'unit_cost' => 1.50],
                ['inventory_item_id' => $rice->id,  'quantity' => 50,  'unit_cost' => 2.00],
            ],
        ]);

        $movementsBefore = InventoryMovement::count();
        $txBefore        = FinanceTransaction::count();

        $po = app(PurchaseOrderService::class)->markReceived($po);

        $this->assertSame('received', $po->status);
        $this->assertNotNull($po->received_date);
        $this->assertNotNull($po->finance_transaction_id);

        $this->assertSame($movementsBefore + 2, InventoryMovement::count());
        $this->assertSame($txBefore + 1, FinanceTransaction::count());

        // Each line gets its movement FK set
        foreach ($po->items as $line) {
            $this->assertNotNull($line->inventory_movement_id);
            $movement = InventoryMovement::find($line->inventory_movement_id);
            $this->assertSame('stock_in', $movement->movement_type);
            $this->assertSame((int) $line->quantity, (int) $movement->quantity);
            $this->assertSame((int) $line->inventory_item_id, (int) $movement->inventory_item_id);
        }

        // The finance transaction matches the PO total + supplier
        $tx = FinanceTransaction::find($po->finance_transaction_id);
        $this->assertSame('expense', $tx->transaction_type);
        $this->assertEqualsWithDelta(250.00, (float) $tx->amount, 0.001);
        $this->assertSame('ACME', $tx->source_or_payee);
        $this->assertSame($po->po_number, $tx->reference_number);
    }

    public function test_mark_received_rejects_already_received(): void
    {
        $item = $this->makeItem();
        $po   = app(PurchaseOrderService::class)->create([
            'supplier_name' => 'X', 'order_date' => '2026-04-30',
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 1.00]],
        ]);
        app(PurchaseOrderService::class)->markReceived($po);

        $this->expectException(RuntimeException::class);
        app(PurchaseOrderService::class)->markReceived($po->fresh());
    }

    public function test_failed_receive_rolls_back_atomically(): void
    {
        $item = $this->makeItem();
        $po   = app(PurchaseOrderService::class)->create([
            'supplier_name' => 'X', 'order_date' => '2026-04-30',
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 1.00]],
        ]);

        $movementsBefore = InventoryMovement::count();
        $txBefore        = FinanceTransaction::count();

        // Force a FK violation on the FinanceTransaction insert by passing an
        // invalid finance_category_id. The inventory movement is written FIRST
        // inside the transaction; if the rollback works, that movement must be
        // gone after the failure.
        try {
            app(PurchaseOrderService::class)->markReceived($po, 999999);
            $this->fail('Expected exception was not thrown');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame($movementsBefore, InventoryMovement::count(), 'Movements rolled back');
        $this->assertSame($txBefore, FinanceTransaction::count(), 'Transaction rolled back');
        $this->assertSame('draft', $po->fresh()->status, 'PO stays draft on failure');
    }

    public function test_cancel_only_works_on_draft(): void
    {
        $item = $this->makeItem();
        $po   = app(PurchaseOrderService::class)->create([
            'supplier_name' => 'X', 'order_date' => '2026-04-30',
            'items' => [['inventory_item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 1.00]],
        ]);

        $cancelled = app(PurchaseOrderService::class)->cancel($po);
        $this->assertSame('cancelled', $cancelled->status);

        $this->expectException(RuntimeException::class);
        app(PurchaseOrderService::class)->cancel($cancelled);
    }
}
