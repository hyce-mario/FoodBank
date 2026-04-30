<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Event;
use App\Models\EventInventoryAllocation;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Visit;
use App\Services\DistributionPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for DistributionPostingService::postForVisit().
 *
 * Phase 2.1.a: service skeleton + unit tests. The bag-composition resolver
 * is stubbed (returns []) in production; tests use an anonymous subclass to
 * inject explicit compositions so the transaction and stock-check logic can be
 * exercised before Phase 2.1.b fills in the real resolver.
 *
 * Four contracts pinned here:
 *   - Empty composition is a no-op (no movements, no exceptions).
 *   - Happy path: correct movement created, quantity_on_hand decremented,
 *     EventInventoryAllocation.distributed_quantity incremented.
 *   - Insufficient stock: InsufficientStockException thrown with correct fields;
 *     transaction ensures no movement is created and no stock is changed.
 *   - Transaction atomicity: a failure on the second component (non-existent
 *     item ID) rolls back the first component's movement and stock decrement.
 *
 * Refs: AUDIT_REPORT.md Part 13 §2.1.a.
 */
class DistributionPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name'  => 'Distribution Test Event',
            'date'  => '2026-06-01',
            'lanes' => 1,
        ]);

        $this->visit = Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => null,
            'visit_status'   => 'loaded',
            'start_time'     => now(),
        ]);
    }

    /**
     * Create a minimal InventoryItem with the given starting stock level.
     */
    private function makeItem(int $quantityOnHand): InventoryItem
    {
        static $counter = 0;
        $counter++;

        return InventoryItem::create([
            'name'             => "Test Item {$counter}",
            'unit_type'        => 'box',
            'quantity_on_hand' => $quantityOnHand,
            'reorder_level'    => 0,
        ]);
    }

    /**
     * Return a DistributionPostingService subclass whose resolveBagComposition()
     * returns the given fixed array. This allows exercising the transaction and
     * stock-check logic without waiting for Phase 2.1.b's real resolver.
     */
    private function serviceWithComposition(array $composition): DistributionPostingService
    {
        return new class($composition) extends DistributionPostingService {
            public function __construct(private readonly array $composition) {}

            protected function resolveBagComposition(Visit $visit): array
            {
                return $this->composition;
            }
        };
    }

    // ─── Test 1 — Empty composition ──────────────────────────────────────────

    /**
     * When resolveBagComposition returns [] (the 2.1.a production stub),
     * postForVisit must be a no-op: no movements created, no exceptions thrown,
     * no stock modified.
     */
    public function test_empty_composition_is_a_no_op(): void
    {
        $service = app(DistributionPostingService::class);

        $service->postForVisit($this->visit);

        $this->assertSame(0, InventoryMovement::count());
    }

    // ─── Test 2 — Happy path ──────────────────────────────────────────────────

    /**
     * Given a single-item composition, postForVisit must:
     *   - create one event_distributed InventoryMovement with quantity = -needed,
     *     linked to the correct event and item
     *   - decrement InventoryItem.quantity_on_hand by the posted quantity
     *   - increment EventInventoryAllocation.distributed_quantity when an
     *     allocation row already exists for this item + event
     */
    public function test_happy_path_creates_movement_and_decrements_stock(): void
    {
        $item = $this->makeItem(quantityOnHand: 10);

        $allocation = EventInventoryAllocation::create([
            'event_id'             => $this->event->id,
            'inventory_item_id'    => $item->id,
            'allocated_quantity'   => 10,
            'distributed_quantity' => 0,
            'returned_quantity'    => 0,
        ]);

        $service = $this->serviceWithComposition([
            ['inventory_item_id' => $item->id, 'quantity' => 3],
        ]);

        $service->postForVisit($this->visit);

        $this->assertSame(1, InventoryMovement::count());

        $movement = InventoryMovement::first();
        $this->assertSame('event_distributed', $movement->movement_type);
        $this->assertSame(-3, $movement->quantity);
        $this->assertSame($this->event->id, $movement->event_id);
        $this->assertSame($item->id, $movement->inventory_item_id);

        $this->assertSame(7, $item->fresh()->quantity_on_hand);

        $this->assertSame(3, $allocation->fresh()->distributed_quantity);
    }

    // ─── Test 3 — Insufficient stock ─────────────────────────────────────────

    /**
     * When quantity_on_hand < needed, postForVisit must throw
     * InsufficientStockException carrying the correct contextual fields.
     * The DB::transaction must ensure no movement is written and no stock
     * is changed before the exception propagates.
     */
    public function test_insufficient_stock_throws_and_does_not_post_movement(): void
    {
        $item = $this->makeItem(quantityOnHand: 5);

        $service = $this->serviceWithComposition([
            ['inventory_item_id' => $item->id, 'quantity' => 10],
        ]);

        try {
            $service->postForVisit($this->visit);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame($this->event->id, $e->eventId);
            $this->assertSame($item->id, $e->inventoryItemId);
            $this->assertSame(10, $e->needed);
            $this->assertSame(5, $e->available);
        }

        $this->assertSame(0, InventoryMovement::count(), 'no movement must be created on stock shortage');
        $this->assertSame(5, $item->fresh()->quantity_on_hand, 'stock must be unchanged on exception');
    }

    // ─── Test 4 — Transaction atomicity ──────────────────────────────────────

    /**
     * When a later component in the loop fails (here: a non-existent item ID
     * causes InventoryItem::findOrFail to throw), the DB::transaction must
     * roll back everything already applied earlier in the same call — including
     * the first component's movement creation and stock decrement.
     */
    public function test_transaction_rolls_back_all_changes_on_partial_failure(): void
    {
        $item1 = $this->makeItem(quantityOnHand: 10);

        $service = $this->serviceWithComposition([
            ['inventory_item_id' => $item1->id, 'quantity' => 3],  // valid — would succeed
            ['inventory_item_id' => 99999,       'quantity' => 1],  // non-existent → findOrFail throws
        ]);

        try {
            $service->postForVisit($this->visit);
            $this->fail('Expected an exception for non-existent inventory item');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame(
            0,
            InventoryMovement::count(),
            'first movement must be rolled back when a later component fails'
        );

        $this->assertSame(
            10,
            $item1->fresh()->quantity_on_hand,
            'stock decrement for the first item must be rolled back'
        );
    }
}
