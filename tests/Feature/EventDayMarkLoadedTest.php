<?php

namespace Tests\Feature;

use App\Models\AllocationRuleset;
use App\Models\AllocationRulesetComponent;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Visit;
use App\Services\EventCheckInService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP feature tests for EventDayController::markLoaded() — Phase 2.1.c.
 *
 * The controller now wraps the visit status flip and DistributionPostingService
 * ::postForVisit() in a single DB::transaction so:
 *   - Happy path: visit becomes 'loaded' and event_distributed movements land.
 *   - Insufficient stock: visit stays 'queued', no movements, structured 422.
 *   - No ruleset / no components: visit becomes 'loaded', no movements (no-op).
 *
 * Auth model: session key ed_{event_id}_loader = true.
 *
 * Refs: AUDIT_REPORT.md Part 13 §2.1.c.
 */
class EventDayMarkLoadedTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $this->event = Event::create([
            'name'  => 'Mark Loaded Test Event',
            'date'  => '2026-06-01',
            'lanes' => 1,
        ]);
    }

    private function loaderSession(): array
    {
        return ['ed_' . $this->event->id . '_loader' => true];
    }

    private function patchLoaded(Visit $visit): \Illuminate\Testing\TestResponse
    {
        return $this->withSession($this->loaderSession())
                    ->patch("/ed/{$this->event->id}/visits/{$visit->id}/loaded");
    }

    private function makeItem(int $qty): InventoryItem
    {
        static $c = 0;
        $c++;
        return InventoryItem::create([
            'name'             => "Item {$c}",
            'unit_type'        => 'box',
            'quantity_on_hand' => $qty,
            'reorder_level'    => 0,
        ]);
    }

    private function makeHouseholdOfSize(int $size): \App\Models\Household
    {
        static $h = 0;
        $h++;
        return \App\Models\Household::create([
            'household_number' => 'ML' . str_pad((string) $h, 5, '0', STR_PAD_LEFT),
            'first_name'       => 'Test',
            'last_name'        => "ML{$h}",
            'household_size'   => $size,
            'adults_count'     => $size,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
    }

    // ─── Test 1 — Auth guard ──────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $visit = Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => 1,
            'visit_status'   => 'queued',
            'start_time'     => now(),
        ]);

        $this->patch("/ed/{$this->event->id}/visits/{$visit->id}/loaded")
             ->assertStatus(401);
    }

    // ─── Test 2 — Happy path ──────────────────────────────────────────────────

    /**
     * Ruleset rule: size 1–3 → 1 bag. Component: 3 units per bag.
     * Household size 2 → 1 bag × 3 = 3 units posted.
     * Visit transitions to 'loaded'. Stock decremented.
     */
    public function test_happy_path_marks_loaded_and_posts_movement(): void
    {
        $ruleset = AllocationRuleset::create([
            'name'               => 'Test',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 10,
            'rules'              => [['min' => 1, 'max' => null, 'bags' => 1]],
        ]);
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $item = $this->makeItem(20);
        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item->id,
            'qty_per_bag'           => 3,
        ]);

        $household = $this->makeHouseholdOfSize(2);
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);
        $visit->update(['visit_status' => 'queued']);

        $this->patchLoaded($visit)->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('loaded', $visit->fresh()->visit_status);
        $this->assertSame(1, InventoryMovement::count());

        $movement = InventoryMovement::first();
        $this->assertSame('event_distributed', $movement->movement_type);
        $this->assertSame(-3, $movement->quantity);
        $this->assertSame($this->event->id, $movement->event_id);

        $this->assertSame(17, $item->fresh()->quantity_on_hand);
    }

    // ─── Test 3 — Insufficient stock ─────────────────────────────────────────

    /**
     * When stock runs out, the visit must stay 'queued' (transaction rolled
     * back), no movement is created, and a structured 422 is returned with
     * the shortage context the future 2.1.e modal will consume.
     */
    public function test_insufficient_stock_rolls_back_status_and_returns_422(): void
    {
        $ruleset = AllocationRuleset::create([
            'name'               => 'Test',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 10,
            'rules'              => [['min' => 1, 'max' => null, 'bags' => 1]],
        ]);
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $item = $this->makeItem(0);  // zero stock
        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item->id,
            'qty_per_bag'           => 2,
        ]);

        $household = $this->makeHouseholdOfSize(2);
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);
        $visit->update(['visit_status' => 'queued']);

        $response = $this->patchLoaded($visit);

        $response->assertStatus(422)
                 ->assertJsonFragment(['error' => 'insufficient_stock'])
                 ->assertJsonFragment(['inventory_item_id' => $item->id])
                 ->assertJsonFragment(['needed' => 2])
                 ->assertJsonFragment(['available' => 0]);

        $this->assertSame('queued', $visit->fresh()->visit_status, 'status must not change on failure');
        $this->assertSame(0, InventoryMovement::count(), 'no movement must be created on failure');
    }

    // ─── Test 4 — No ruleset → no-op ─────────────────────────────────────────

    /**
     * When the event has no ruleset (or no components), postForVisit is a no-op.
     * The visit still transitions to 'loaded' cleanly — nothing to deduct.
     */
    public function test_no_ruleset_marks_loaded_with_no_movement(): void
    {
        // $this->event has no ruleset_id.
        $visit = Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => 1,
            'visit_status'   => 'queued',
            'start_time'     => now(),
        ]);

        $this->patchLoaded($visit)->assertOk();

        $this->assertSame('loaded', $visit->fresh()->visit_status);
        $this->assertSame(0, InventoryMovement::count());
    }

    // ─── Test 5 — Wrong status ────────────────────────────────────────────────

    /**
     * A visit that is already 'loaded' or 'exited' must not be re-transitioned.
     */
    public function test_wrong_visit_status_returns_422(): void
    {
        $visit = Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => null,
            'visit_status'   => 'loaded',
            'start_time'     => now(),
        ]);

        $this->patchLoaded($visit)->assertStatus(422);
        $this->assertSame(0, InventoryMovement::count());
    }
}
