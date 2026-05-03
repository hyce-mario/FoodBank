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
 *   - Mode is one of add / subtract / replace (422 otherwise)
 *   - Duplicate item ids in one payload return 422
 *   - Rows are processed atomically: insufficient stock or replace-down
 *     below already-distributed → row is SKIPPED and reported via flash;
 *     other rows in the same batch still process.
 *   - History-preserving subtract: the allocation row's `returned_quantity`
 *     increments, never `allocated_quantity`.
 *   - Every processed row records an InventoryMovement of the right type.
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

    private function makeExistingAllocation(Event $event, InventoryItem $item, int $alloc, int $distributed = 0, int $returned = 0): EventInventoryAllocation
    {
        return EventInventoryAllocation::create([
            'event_id'             => $event->id,
            'inventory_item_id'    => $item->id,
            'allocated_quantity'   => $alloc,
            'distributed_quantity' => $distributed,
            'returned_quantity'    => $returned,
        ]);
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A');

        $this->post(route('events.inventory.bulk', $event), [
            'mode'  => 'add',
            'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 5]],
        ])->assertRedirect(route('login'));
    }

    public function test_missing_mode_returns_422(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A');

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 5]],
             ])
             ->assertSessionHasErrors(['mode']);
    }

    public function test_invalid_mode_returns_422(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A');

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'multiply',
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 5]],
             ])
             ->assertSessionHasErrors(['mode']);
    }

    public function test_empty_items_array_returns_422(): void
    {
        $event = $this->makeEvent();

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'add',
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
                 'mode'  => 'add',
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
                 'mode'  => 'add',
                 'items' => [
                     ['inventory_item_id' => $a->id, 'allocated_quantity' => 5],
                     ['inventory_item_id' => $a->id, 'allocated_quantity' => 3],
                 ],
             ])
             ->assertSessionHasErrors(['items']);
    }

    // ─── Add mode (happy path) ───────────────────────────────────────────────

    public function test_add_mode_creates_allocations_and_movements(): void
    {
        $event = $this->makeEvent();
        $a = $this->makeItem('A', stock: 100);
        $b = $this->makeItem('B', stock: 50);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'add',
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
                 'mode'  => 'add',
                 'items' => [
                     ['inventory_item_id' => $a->id, 'allocated_quantity' => 10],
                     ['inventory_item_id' => $b->id, 'allocated_quantity' => 0], // blank row
                 ],
             ])
             ->assertSessionMissing('alloc_warning');

        // Only one allocation row created.
        $this->assertSame(1, EventInventoryAllocation::count());
    }

    public function test_insufficient_stock_skips_row_and_processes_others(): void
    {
        $event = $this->makeEvent();
        $tight = $this->makeItem('Tight', stock: 3);     // can't fit 100
        $ok    = $this->makeItem('OK',    stock: 100);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'add',
                 'items' => [
                     ['inventory_item_id' => $tight->id, 'allocated_quantity' => 100],
                     ['inventory_item_id' => $ok->id,    'allocated_quantity' => 5],
                 ],
             ])
             ->assertRedirect(route('events.show', $event))
             ->assertSessionHas('alloc_warning')
             ->assertSessionHas('success');

        // Tight skipped, OK processed.
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
                 'mode'  => 'add',
                 'items' => [['inventory_item_id' => $tight->id, 'allocated_quantity' => 50]],
             ])
             ->assertSessionHas('alloc_warning', function ($msg) {
                 return str_contains($msg, 'CannedTomato')
                     && str_contains($msg, 'insufficient stock');
             });
    }

    public function test_add_mode_increments_existing_allocation(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A', stock: 100);
        $this->makeExistingAllocation($event, $item, alloc: 10);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'add',
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 5]],
             ])
             ->assertRedirect();

        $this->assertSame(15, EventInventoryAllocation::where('inventory_item_id', $item->id)->value('allocated_quantity'));
        $this->assertSame(95, $item->fresh()->quantity_on_hand); // 100 - 5 (new pull only)
    }

    // ─── Subtract mode ───────────────────────────────────────────────────────

    public function test_subtract_mode_increments_returned_quantity_not_allocated(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A', stock: 90);
        $this->makeExistingAllocation($event, $item, alloc: 10);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'subtract',
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 4]],
             ])
             ->assertRedirect();

        $row = EventInventoryAllocation::where('inventory_item_id', $item->id)->first();
        // History-preserving: allocated stays at 10, returned increments to 4.
        $this->assertSame(10, $row->allocated_quantity);
        $this->assertSame(4, $row->returned_quantity);
        // Stock returns to shelf.
        $this->assertSame(94, $item->fresh()->quantity_on_hand);
        // Movement is event_returned.
        $this->assertSame(1, InventoryMovement::where('movement_type', 'event_returned')->count());
    }

    public function test_subtract_with_no_existing_allocation_skips_with_reason(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('Unallocated', stock: 100);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'subtract',
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 5]],
             ])
             ->assertSessionHas('alloc_warning', function ($msg) {
                 return str_contains($msg, 'Unallocated')
                     && str_contains($msg, 'nothing to subtract');
             });

        $this->assertSame(0, EventInventoryAllocation::count());
        $this->assertSame(100, $item->fresh()->quantity_on_hand);
    }

    public function test_subtract_clamps_at_existing_allocation(): void
    {
        // Operator submits 100 but only 5 was allocated. Clamp at 5.
        $event = $this->makeEvent();
        $item  = $this->makeItem('A', stock: 95);
        $this->makeExistingAllocation($event, $item, alloc: 5);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'subtract',
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 100]],
             ])
             ->assertRedirect();

        $row = EventInventoryAllocation::where('inventory_item_id', $item->id)->first();
        $this->assertSame(5, $row->allocated_quantity);
        $this->assertSame(5, $row->returned_quantity);
        $this->assertSame(100, $item->fresh()->quantity_on_hand); // 95 + 5 returned
    }

    // ─── Replace mode ────────────────────────────────────────────────────────

    public function test_replace_mode_with_higher_value_allocates_delta(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A', stock: 90);
        $this->makeExistingAllocation($event, $item, alloc: 10);

        // Replace 10 → 25 = pull 15 more.
        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'replace',
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 25]],
             ])
             ->assertRedirect();

        $row = EventInventoryAllocation::where('inventory_item_id', $item->id)->first();
        $this->assertSame(25, $row->allocated_quantity);
        $this->assertSame(0, $row->returned_quantity);
        $this->assertSame(75, $item->fresh()->quantity_on_hand);
    }

    public function test_replace_mode_with_lower_value_returns_surplus(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A', stock: 80);
        $this->makeExistingAllocation($event, $item, alloc: 20);

        // Replace 20 → 5 = return 15 to shelf.
        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'replace',
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 5]],
             ])
             ->assertRedirect();

        $row = EventInventoryAllocation::where('inventory_item_id', $item->id)->first();
        // History-preserving: allocated stays at 20, returned = 15.
        $this->assertSame(20, $row->allocated_quantity);
        $this->assertSame(15, $row->returned_quantity);
        $this->assertSame(95, $item->fresh()->quantity_on_hand); // 80 + 15
    }

    public function test_replace_down_below_distributed_floor_skips_with_reason(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A', stock: 100);
        // 20 allocated, 12 already distributed → can return at most 8.
        $this->makeExistingAllocation($event, $item, alloc: 20, distributed: 12);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'replace',
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 5]], // would need to return 15
             ])
             ->assertSessionHas('alloc_warning', function ($msg) {
                 return str_contains($msg, 'already-distributed')
                     || str_contains($msg, 'already handed out');
             });

        $row = EventInventoryAllocation::where('inventory_item_id', $item->id)->first();
        $this->assertSame(20, $row->allocated_quantity); // unchanged
        $this->assertSame(0,  $row->returned_quantity);  // unchanged
        $this->assertSame(100, $item->fresh()->quantity_on_hand);
    }

    public function test_replace_with_same_value_is_a_noop(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem('A', stock: 90);
        $this->makeExistingAllocation($event, $item, alloc: 10);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'replace',
                 'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 10]],
             ])
             ->assertRedirect();

        // No movement records.
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(90, $item->fresh()->quantity_on_hand);
    }

    // ─── Bulk semantics across multiple rows ─────────────────────────────────

    public function test_mixed_rows_are_processed_independently(): void
    {
        $event = $this->makeEvent();
        $a = $this->makeItem('A', stock: 100);
        $b = $this->makeItem('B', stock: 2);
        $c = $this->makeItem('C', stock: 50);

        $this->actingAs($this->admin)
             ->post(route('events.inventory.bulk', $event), [
                 'mode'  => 'add',
                 'items' => [
                     ['inventory_item_id' => $a->id, 'allocated_quantity' => 10], // OK
                     ['inventory_item_id' => $b->id, 'allocated_quantity' => 50], // skip (insufficient)
                     ['inventory_item_id' => $c->id, 'allocated_quantity' => 5],  // OK
                 ],
             ])
             ->assertRedirect();

        // A and C land, B skipped.
        $this->assertDatabaseHas('event_inventory_allocations', ['inventory_item_id' => $a->id]);
        $this->assertDatabaseMissing('event_inventory_allocations', ['inventory_item_id' => $b->id]);
        $this->assertDatabaseHas('event_inventory_allocations', ['inventory_item_id' => $c->id]);
    }
}
