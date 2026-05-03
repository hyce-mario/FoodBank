<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventInventoryAllocation;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D — Atomic bulk allocation endpoint pinned end-to-end.
 *
 * Pins the contract for POST /events/{event}/inventory/bulk:
 *   - Per-row validation (exists, integer, min 0)
 *   - Duplicate item ids in one payload return 422
 *   - Bulk submit is ADD-ONLY: each non-zero row pulls quantity from the
 *     shelf and adds to the event's allocation total. Returning surplus
 *     after the event is a separate flow (per-row Return action).
 *   - Insufficient stock → row SKIPPED and reported via flash; other rows
 *     in the same batch still process.
 *   - Every processed row records an event_allocated InventoryMovement.
 */
class EventInventoryBulkAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $role = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    private function makeEvent(): Event
    {
        return Event::create([
            'name'   => 'Bulk Allocation Event',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);
    }

    private function makeItem(string $name, int $stock = 100, int $reorder = 10): InventoryItem
    {
        return InventoryItem::create([
            'name'             => $name,
            'unit_type'        => 'box',
            'quantity_on_hand' => $stock,
            'reorder_level'    => $reorder,
            'is_active'        => true,
        ]);
    }

    private function makeExistingAllocation(Event $event, InventoryItem $item, int $alloc): EventInventoryAllocation
    {
        return EventInventoryAllocation::create([
            'event_id'             => $event->id,
            'inventory_item_id'    => $item->id,
            'allocated_quantity'   => $alloc,
            'distributed_quantity' => 0,
            'returned_quantity'    => 0,
        ]);
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A');

        $this->post(route('events.inventory.bulk', $event), [
            'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 5]],
        ])->assertRedirect(route('login'));
    }

    public function test_empty_items_array_returns_422(): void
    {
        $event = $this->makeEvent();

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [],
             ])
             ->assertSessionHasErrors(['items']);
    }

    public function test_negative_quantity_returns_422(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A');

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => -1]],
             ])
             ->assertSessionHasErrors(['items.0.allocated_quantity']);
    }

    public function test_duplicate_item_in_payload_returns_422(): void
    {
        $event = $this->makeEvent();
        $a = $this->makeItem('A');

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [
                     ['inventory_item_id' => $a->id, 'allocated_quantity' => 5],
                     ['inventory_item_id' => $a->id, 'allocated_quantity' => 3],
                 ],
             ])
             ->assertSessionHasErrors(['items']);
    }

    // ─── Happy path ──────────────────────────────────────────────────────────

    public function test_creates_allocations_and_decrements_stock(): void
    {
        $event = $this->makeEvent();
        $a = $this->makeItem('A', stock: 100);
        $b = $this->makeItem('B', stock: 50);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [
                     ['inventory_item_id' => $a->id, 'allocated_quantity' => 10],
                     ['inventory_item_id' => $b->id, 'allocated_quantity' => 5],
                 ],
             ])
             ->assertRedirect(route('events.show', $event));

        $this->assertDatabaseHas('event_inventory_allocations', [
            'event_id' => $event->id, 'inventory_item_id' => $a->id, 'allocated_quantity' => 10,
        ]);
        $this->assertDatabaseHas('event_inventory_allocations', [
            'event_id' => $event->id, 'inventory_item_id' => $b->id, 'allocated_quantity' => 5,
        ]);
        // Stock decremented.
        $this->assertSame(90, $a->fresh()->quantity_on_hand);
        $this->assertSame(45, $b->fresh()->quantity_on_hand);
        // One movement per row.
        $this->assertSame(2, InventoryMovement::where('movement_type', 'event_allocated')->count());
    }

    public function test_zero_quantity_rows_are_skipped_silently(): void
    {
        $event = $this->makeEvent();
        $a = $this->makeItem('A', stock: 100);
        $b = $this->makeItem('B', stock: 50);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [
                     ['inventory_item_id' => $a->id, 'allocated_quantity' => 10],
                     ['inventory_item_id' => $b->id, 'allocated_quantity' => 0], // blank row
                 ],
             ])
             ->assertSessionMissing('alloc_warning');

        // Only one allocation row created.
        $this->assertSame(1, EventInventoryAllocation::count());
    }

    public function test_existing_allocation_is_incremented_not_replaced(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A', stock: 100);
        $this->makeExistingAllocation($event, $item, alloc: 10);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 5]],
             ])
             ->assertRedirect();

        $this->assertSame(15, EventInventoryAllocation::where('inventory_item_id', $item->id)->value('allocated_quantity'));
        $this->assertSame(95, $item->fresh()->quantity_on_hand); // 100 - 5 (only the new pull)
    }

    public function test_full_stock_can_be_allocated_via_max_button_value(): void
    {
        // Pins the "MAX" UX path: a row submitted with allocated_quantity
        // equal to quantity_on_hand fully empties the shelf.
        $event = $this->makeEvent();
        $item  = $this->makeItem('A', stock: 42);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 42]],
             ])
             ->assertRedirect();

        $this->assertSame(0, $item->fresh()->quantity_on_hand);
        $this->assertSame(42, EventInventoryAllocation::where('inventory_item_id', $item->id)->value('allocated_quantity'));
    }

    // ─── Insufficient stock skip ─────────────────────────────────────────────

    public function test_insufficient_stock_skips_row_and_processes_others(): void
    {
        $event = $this->makeEvent();
        $tight = $this->makeItem('Tight', stock: 3);     // can't fit 100
        $ok    = $this->makeItem('OK',    stock: 100);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [
                     ['inventory_item_id' => $tight->id, 'allocated_quantity' => 100],
                     ['inventory_item_id' => $ok->id,    'allocated_quantity' => 5],
                 ],
             ])
             ->assertRedirect(route('events.show', $event))
             ->assertSessionHas('alloc_warning')
             ->assertSessionHas('success');

        $this->assertDatabaseMissing('event_inventory_allocations', ['inventory_item_id' => $tight->id]);
        $this->assertDatabaseHas('event_inventory_allocations', [
            'inventory_item_id' => $ok->id, 'allocated_quantity' => 5,
        ]);
        $this->assertSame(3, $tight->fresh()->quantity_on_hand);   // untouched
        $this->assertSame(95, $ok->fresh()->quantity_on_hand);
    }

    public function test_skipped_row_warning_names_the_offending_item(): void
    {
        $event = $this->makeEvent();
        $tight = $this->makeItem('CannedTomato', stock: 1);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [['inventory_item_id' => $tight->id, 'allocated_quantity' => 50]],
             ])
             ->assertSessionHas('alloc_warning', function ($msg) {
                 return str_contains($msg, 'CannedTomato')
                     && str_contains($msg, 'insufficient stock');
             });
    }

    public function test_mixed_rows_are_processed_independently(): void
    {
        $event = $this->makeEvent();
        $a = $this->makeItem('A', stock: 100);
        $b = $this->makeItem('B', stock: 2);
        $c = $this->makeItem('C', stock: 50);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [
                     ['inventory_item_id' => $a->id, 'allocated_quantity' => 10], // OK
                     ['inventory_item_id' => $b->id, 'allocated_quantity' => 50], // skip (insufficient)
                     ['inventory_item_id' => $c->id, 'allocated_quantity' => 5],  // OK
                 ],
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('event_inventory_allocations', ['inventory_item_id' => $a->id]);
        $this->assertDatabaseMissing('event_inventory_allocations', ['inventory_item_id' => $b->id]);
        $this->assertDatabaseHas('event_inventory_allocations', ['inventory_item_id' => $c->id]);
    }
}
