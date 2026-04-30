<?php

namespace Tests\Feature;

use App\Models\AllocationRuleset;
use App\Models\AllocationRulesetComponent;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use App\Services\EventCheckInService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP feature tests for VisitMonitorController::transition() — Phase 2.1.d.
 *
 * When the supervisor advances a visit to 'loaded' via the monitor, the same
 * DistributionPostingService::postForVisit() call must fire (same transaction
 * pattern as EventDayController::markLoaded in Phase 2.1.c). Transitions to
 * other statuses ('queued', 'exited') must NOT trigger distribution.
 *
 * Refs: AUDIT_REPORT.md Part 13 §2.1.d.
 */
class VisitMonitorTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $adminRole = Role::create([
            'name'         => 'ADMIN',
            'display_name' => 'Administrator',
            'description'  => 'Full access',
        ]);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);

        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $adminRole->id,
            'email_verified_at' => now(),
        ]);

        $this->event = Event::create([
            'name'  => 'Monitor Transition Test',
            'date'  => '2026-06-01',
            'lanes' => 1,
        ]);
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
            'household_number' => 'MT' . str_pad((string) $h, 5, '0', STR_PAD_LEFT),
            'first_name'       => 'Test',
            'last_name'        => "MT{$h}",
            'household_size'   => $size,
            'adults_count'     => $size,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
    }

    // ─── Test 1 — Happy path: supervisor loaded transition posts movement ─────

    /**
     * Supervisor advances queued → loaded via the monitor. Movement must be
     * created and stock decremented, exactly as via the loader tablet (2.1.c).
     */
    public function test_supervisor_loaded_transition_posts_movement(): void
    {
        $ruleset = AllocationRuleset::create([
            'name'               => 'Test',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 10,
            'rules'              => [['min' => 1, 'max' => null, 'bags' => 1]],
        ]);
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $item = $this->makeItem(10);
        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item->id,
            'qty_per_bag'           => 2,
        ]);

        $household = $this->makeHouseholdOfSize(3);
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);
        $visit->update(['visit_status' => 'queued']);

        $this->actingAs($this->admin)
             ->patch(route('monitor.transition', [$this->event, $visit]), ['status' => 'loaded'])
             ->assertOk()
             ->assertJson(['ok' => true]);

        $this->assertSame('loaded', $visit->fresh()->visit_status);
        $this->assertSame(1, InventoryMovement::count());
        $this->assertSame(-2, InventoryMovement::first()->quantity);
        $this->assertSame(8, $item->fresh()->quantity_on_hand);
    }

    // ─── Test 2 — Insufficient stock rolls back ───────────────────────────────

    /**
     * If stock is short, the transition must be rolled back (visit stays
     * 'queued'), no movement created, 422 returned with shortage context.
     */
    public function test_insufficient_stock_on_supervisor_transition_rolls_back(): void
    {
        $ruleset = AllocationRuleset::create([
            'name'               => 'Test',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 10,
            'rules'              => [['min' => 1, 'max' => null, 'bags' => 1]],
        ]);
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $item = $this->makeItem(0);
        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item->id,
            'qty_per_bag'           => 1,
        ]);

        $household = $this->makeHouseholdOfSize(2);
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);
        $visit->update(['visit_status' => 'queued']);

        $this->actingAs($this->admin)
             ->patch(route('monitor.transition', [$this->event, $visit]), ['status' => 'loaded'])
             ->assertStatus(422)
             ->assertJsonFragment(['error' => 'insufficient_stock']);

        $this->assertSame('queued', $visit->fresh()->visit_status);
        $this->assertSame(0, InventoryMovement::count());
    }

    // ─── Test 3 — Non-loaded transitions do not trigger distribution ──────────

    /**
     * Advancing checked_in → queued must never call postForVisit, even
     * when the event has a ruleset with components.
     */
    public function test_transition_to_queued_does_not_post_movement(): void
    {
        $ruleset = AllocationRuleset::create([
            'name'               => 'Test',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 10,
            'rules'              => [['min' => 1, 'max' => null, 'bags' => 1]],
        ]);
        $this->event->update(['ruleset_id' => $ruleset->id]);

        $item = $this->makeItem(10);
        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item->id,
            'qty_per_bag'           => 3,
        ]);

        $household = $this->makeHouseholdOfSize(2);
        $visit = app(EventCheckInService::class)->checkIn($this->event, $household, lane: 1);
        // visit is 'checked_in' after checkIn

        $this->actingAs($this->admin)
             ->patch(route('monitor.transition', [$this->event, $visit]), ['status' => 'queued'])
             ->assertOk();

        $this->assertSame('queued', $visit->fresh()->visit_status);
        $this->assertSame(0, InventoryMovement::count(), 'queued transition must not post inventory');
        $this->assertSame(10, $item->fresh()->quantity_on_hand, 'stock must be unchanged');
    }
}
