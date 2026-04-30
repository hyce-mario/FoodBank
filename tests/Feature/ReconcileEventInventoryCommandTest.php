<?php

namespace Tests\Feature;

use App\Models\AllocationRuleset;
use App\Models\AllocationRulesetComponent;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Visit;
use App\Services\DistributionPostingService;
use App\Services\EventCheckInService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the inventory:reconcile {event} artisan command — Phase 2.1.f.
 *
 * Acceptance criterion: "inventory:reconcile {event} produces 0 delta on a
 * clean event" (i.e. one where all movements have been posted correctly).
 *
 * Additional contracts:
 *   - Missing movements are reported in dry-run (default) mode.
 *   - --post writes the missing movement and decrements stock.
 *   - Invalid event ID returns exit code FAILURE.
 *
 * Refs: AUDIT_REPORT.md Part 13 §2.1.f.
 */
class ReconcileEventInventoryCommandTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private AllocationRuleset $ruleset;
    private InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $this->ruleset = AllocationRuleset::create([
            'name'               => 'Test Ruleset',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 10,
            'rules'              => [['min' => 1, 'max' => null, 'bags' => 1]],
        ]);

        $this->event = Event::create([
            'name'       => 'Reconcile Test Event',
            'date'       => '2026-06-01',
            'lanes'      => 1,
            'ruleset_id' => $this->ruleset->id,
        ]);

        $this->item = InventoryItem::create([
            'name'             => 'Canned Goods',
            'unit_type'        => 'can',
            'quantity_on_hand' => 100,
            'reorder_level'    => 0,
        ]);

        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $this->ruleset->id,
            'inventory_item_id'     => $this->item->id,
            'qty_per_bag'           => 3,
        ]);
    }

    /**
     * Create a household of the given size, check it in, post inventory,
     * and mark it exited — a complete "clean" visit lifecycle.
     */
    private function makeCleanVisit(int $householdSize): Visit
    {
        static $c = 0;
        $c++;

        $household = \App\Models\Household::create([
            'household_number' => 'RC' . str_pad((string) $c, 5, '0', STR_PAD_LEFT),
            'first_name'       => 'Test',
            'last_name'        => "Rec{$c}",
            'household_size'   => $householdSize,
            'adults_count'     => $householdSize,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);

        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);

        // Simulate markLoaded: post inventory then update status
        app(DistributionPostingService::class)->postForVisit($visit);
        $visit->update(['visit_status' => 'loaded', 'loading_completed_at' => now()]);

        // Simulate markExited
        $visit->update([
            'visit_status'   => 'exited',
            'exited_at'      => now(),
            'end_time'       => now(),
            'queue_position' => null,
        ]);

        return $visit;
    }

    // ─── Test 1 — Clean event produces zero delta ─────────────────────────────

    /**
     * Primary acceptance criterion: after a complete event where all movements
     * were posted, the command reports delta = 0 for all items and exits clean.
     *
     * Household size 2 → 1 bag × 3 qty_per_bag = 3 units.
     */
    public function test_clean_event_produces_zero_delta(): void
    {
        $this->makeCleanVisit(householdSize: 2);

        // 1 movement should exist: -3 units
        $this->assertSame(1, InventoryMovement::count());

        $this->artisan('inventory:reconcile', ['event' => $this->event->id])
             ->assertExitCode(0)
             ->expectsOutputToContain('balanced');
    }

    // ─── Test 2 — Missing movements reported in dry-run ───────────────────────

    /**
     * When movements are absent (event ran before Phase 2 was deployed),
     * the command reports the gap but does NOT write any movement in default
     * (dry-run) mode.
     */
    public function test_missing_movements_reported_in_dry_run(): void
    {
        // Create an exited visit WITHOUT posting inventory (simulates pre-Phase-2 event)
        $household = \App\Models\Household::create([
            'household_number' => 'RC00099',
            'first_name'       => 'Pre',
            'last_name'        => 'Phase2',
            'household_size'   => 2,
            'adults_count'     => 2,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);
        $visit->update([
            'visit_status'   => 'exited',
            'end_time'       => now(),
            'queue_position' => null,
        ]);

        $this->assertSame(0, InventoryMovement::count());

        $this->artisan('inventory:reconcile', ['event' => $this->event->id])
             ->assertExitCode(0)
             ->expectsOutputToContain('Gaps found');

        $this->assertSame(0, InventoryMovement::count(), 'dry-run must not write any movements');
        $this->assertSame(100, $this->item->fresh()->quantity_on_hand, 'stock must be unchanged in dry-run');
    }

    // ─── Test 3 — --post writes missing movement ──────────────────────────────

    /**
     * With --post, the command writes the missing movement and decrements stock.
     * Same scenario as test 2 but with the flag set.
     */
    public function test_post_flag_writes_missing_movement(): void
    {
        $household = \App\Models\Household::create([
            'household_number' => 'RC00098',
            'first_name'       => 'Post',
            'last_name'        => 'Flag',
            'household_size'   => 2,
            'adults_count'     => 2,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);
        $visit->update([
            'visit_status'   => 'exited',
            'end_time'       => now(),
            'queue_position' => null,
        ]);

        $this->artisan('inventory:reconcile', [
            'event'  => $this->event->id,
            '--post' => true,
        ])->assertExitCode(0)
          ->expectsOutputToContain('Backfilled');

        // size 2 → 1 bag × 3 = 3 units
        $this->assertSame(1, InventoryMovement::count());
        $movement = InventoryMovement::first();
        $this->assertSame('event_distributed', $movement->movement_type);
        $this->assertSame(-3, $movement->quantity);
        $this->assertSame('Backfill via inventory:reconcile', $movement->notes);

        $this->assertSame(97, $this->item->fresh()->quantity_on_hand);
    }

    // ─── Test 4 — Invalid event ID ────────────────────────────────────────────

    public function test_invalid_event_id_returns_failure(): void
    {
        $this->artisan('inventory:reconcile', ['event' => 99999])
             ->assertExitCode(1);
    }
}
