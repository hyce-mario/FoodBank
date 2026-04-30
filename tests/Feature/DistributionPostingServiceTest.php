<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\AllocationRuleset;
use App\Models\AllocationRulesetComponent;
use App\Models\Event;
use App\Models\EventInventoryAllocation;
use App\Models\Household;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Visit;
use App\Services\DistributionPostingService;
use App\Services\EventCheckInService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for DistributionPostingService::postForVisit().
 *
 * Phase 2.1.a + 2.1.b: service skeleton, unit tests, and resolver tests.
 *
 * Tests 1–4 (Phase 2.1.a): anonymous-subclass injection to test the transaction
 * and stock-check logic independently of the resolver.
 *   - Empty composition is a no-op (no movements, no exceptions).
 *   - Happy path: correct movement created, quantity_on_hand decremented,
 *     EventInventoryAllocation.distributed_quantity incremented.
 *   - Insufficient stock: InsufficientStockException thrown with correct fields;
 *     transaction ensures no movement is created and no stock is changed.
 *   - Transaction atomicity: a failure on the second component rolls back
 *     the first component's movement and stock decrement.
 *
 * Tests 5–10 (Phase 2.1.b): real resolver via allocation_ruleset_components
 * table (Option A schema). Uses EventCheckInService::checkIn() to create
 * visits with proper Phase 1.2 pivot snapshots.
 *   - No ruleset → no-op.
 *   - Ruleset with no components → no-op.
 *   - Single household: correct quantity via getBagsFor × qty_per_bag.
 *   - Snapshot isolation: editing household after check-in does not change
 *     the distributed quantity.
 *   - Multi-household: bags summed across representative + represented.
 *   - M5 deferred: two real items, second insufficient → first rolled back.
 *
 * Refs: AUDIT_REPORT.md Part 13 §2.1.a, §2.1.b.
 */
class DistributionPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Visit $visit;

    protected function setUp(): void
    {
        parent::setUp();

        // SettingService caches across RefreshDatabase cycles; flush so
        // EventCheckInService::checkIn() reads the correct policy defaults.
        SettingService::flush();

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

    // ─── Phase 2.1.b — Real resolver tests ───────────────────────────────────

    /**
     * Create a minimal Household with the given snapshot-ready household_size.
     * EventCheckInService::checkIn() writes these fields to the pivot snapshot
     * at attach time (Phase 1.2.b), so the size passed here is what the
     * resolver will read from $household->pivot->household_size.
     */
    private function makeHouseholdOfSize(int $size): Household
    {
        static $hCounter = 0;
        $hCounter++;

        return Household::create([
            'household_number' => 'HH' . str_pad((string) $hCounter, 5, '0', STR_PAD_LEFT),
            'first_name'       => 'Test',
            'last_name'        => "Household{$hCounter}",
            'household_size'   => $size,
            'adults_count'     => $size,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
    }

    /**
     * Create a minimal AllocationRuleset whose rules give $bagsFor1to3 bags
     * for households of size 1–3 and $bagsFor4plus for size 4+.
     */
    private function makeRuleset(int $bagsFor1to3 = 1, int $bagsFor4plus = 2): AllocationRuleset
    {
        return AllocationRuleset::create([
            'name'               => 'Test Ruleset',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 20,
            'rules'              => [
                ['min' => 1, 'max' => 3,    'bags' => $bagsFor1to3],
                ['min' => 4, 'max' => null,  'bags' => $bagsFor4plus],
            ],
        ]);
    }

    // ─── Test 5 — No ruleset on event ────────────────────────────────────────

    /**
     * When the event has no AllocationRuleset, resolveBagComposition returns []
     * and postForVisit is a no-op.
     */
    public function test_resolver_returns_empty_when_event_has_no_ruleset(): void
    {
        // $this->event has no ruleset_id.
        $service = app(DistributionPostingService::class);
        $service->postForVisit($this->visit);

        $this->assertSame(0, InventoryMovement::count());
    }

    // ─── Test 6 — Ruleset with no components ─────────────────────────────────

    /**
     * A ruleset that has no AllocationRulesetComponent rows returns [] and
     * postForVisit is a no-op even when households have been served.
     */
    public function test_resolver_returns_empty_when_ruleset_has_no_components(): void
    {
        $ruleset = $this->makeRuleset();
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $service = app(DistributionPostingService::class);
        $service->postForVisit($this->visit);

        $this->assertSame(0, InventoryMovement::count());
    }

    // ─── Test 7 — Single household, correct quantity ─────────────────────────

    /**
     * Ruleset rule: household size 1–3 → 1 bag. Component: 2 units per bag.
     * Visit has one household of size 2 → 1 bag × 2 = 2 units posted.
     */
    public function test_resolver_calculates_correct_quantity_for_single_household(): void
    {
        $ruleset = $this->makeRuleset(bagsFor1to3: 1);
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $item = $this->makeItem(quantityOnHand: 20);
        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item->id,
            'qty_per_bag'           => 2,
        ]);

        $household = $this->makeHouseholdOfSize(2);  // size 2 → 1 bag
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);

        app(DistributionPostingService::class)->postForVisit($visit);

        $this->assertSame(1, InventoryMovement::count());
        $this->assertSame(-2, InventoryMovement::first()->quantity);
        $this->assertSame(18, $item->fresh()->quantity_on_hand);
    }

    // ─── Test 8 — Snapshot isolation ─────────────────────────────────────────

    /**
     * Editing a household's size AFTER check-in must NOT change how many items
     * get posted. The resolver reads the Phase 1.2 pivot snapshot, not the live
     * household record.
     *
     * Household checked in at size 2 (1 bag, 2 units). Then bumped to size 5
     * (which would be 2 bags / 4 units under the ruleset). Distribution must
     * still post 2 units.
     */
    public function test_resolver_uses_snapshot_size_not_live_household_size(): void
    {
        $ruleset = $this->makeRuleset(bagsFor1to3: 1, bagsFor4plus: 2);
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $item = $this->makeItem(quantityOnHand: 20);
        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item->id,
            'qty_per_bag'           => 2,
        ]);

        $household = $this->makeHouseholdOfSize(2);
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);

        // Edit the household to a larger size after check-in.
        $household->update(['household_size' => 5, 'adults_count' => 5]);

        app(DistributionPostingService::class)->postForVisit($visit);

        // Snapshot was size 2 → 1 bag × 2 = 2 units, NOT size 5 → 2 bags × 2 = 4.
        $this->assertSame(-2, InventoryMovement::first()->quantity);
        $this->assertSame(18, $item->fresh()->quantity_on_hand);
    }

    // ─── Test 9 — Multi-household visit ──────────────────────────────────────

    /**
     * A representative picks up for two represented households. Bags are summed
     * across all three using each household's own snapshot size.
     *
     * Rep (size 2) → 1 bag, Rep1 (size 2) → 1 bag, Rep2 (size 5) → 2 bags.
     * Total = 4 bags. Component: 3 units per bag → 12 units posted.
     */
    public function test_resolver_sums_bags_across_multiple_households(): void
    {
        $ruleset = $this->makeRuleset(bagsFor1to3: 1, bagsFor4plus: 2);
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $item = $this->makeItem(quantityOnHand: 50);
        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item->id,
            'qty_per_bag'           => 3,
        ]);

        $rep  = $this->makeHouseholdOfSize(2);  // 1 bag
        $rep1 = $this->makeHouseholdOfSize(2);  // 1 bag
        $rep2 = $this->makeHouseholdOfSize(5);  // 2 bags

        $visit = app(EventCheckInService::class)->checkIn(
            $this->event, $rep, lane: 1,
            representedIds: [$rep1->id, $rep2->id]
        );

        app(DistributionPostingService::class)->postForVisit($visit);

        // 4 bags × 3 qty_per_bag = 12 units
        $this->assertSame(1, InventoryMovement::count());
        $this->assertSame(-12, InventoryMovement::first()->quantity);
        $this->assertSame(38, $item->fresh()->quantity_on_hand);
    }

    // ─── Test 10 — M5 deferred: two-item insufficient-stock rollback ──────────

    /**
     * Two items in the real composition. Item 1 has enough stock; item 2 does
     * not. The InsufficientStockException on item 2 must roll back item 1's
     * movement and stock decrement — proving the transaction wraps all
     * components atomically (M5 deferred from Phase 2.1.a code review).
     */
    public function test_insufficient_stock_on_second_item_rolls_back_first(): void
    {
        $ruleset = $this->makeRuleset(bagsFor1to3: 1);
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $item1 = $this->makeItem(quantityOnHand: 20);  // plenty of stock
        $item2 = $this->makeItem(quantityOnHand: 0);   // zero stock — will fail

        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item1->id,
            'qty_per_bag'           => 2,
        ]);
        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item2->id,
            'qty_per_bag'           => 1,
        ]);

        $household = $this->makeHouseholdOfSize(2);  // size 2 → 1 bag
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);

        try {
            app(DistributionPostingService::class)->postForVisit($visit);
            $this->fail('Expected InsufficientStockException for item2');
        } catch (InsufficientStockException $e) {
            $this->assertSame($item2->id, $e->inventoryItemId);
        }

        $this->assertSame(0, InventoryMovement::count(), 'item1 movement must be rolled back');
        $this->assertSame(20, $item1->fresh()->quantity_on_hand, 'item1 stock must be unchanged');
    }
}
