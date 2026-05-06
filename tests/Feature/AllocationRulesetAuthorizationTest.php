<?php

namespace Tests\Feature;

use App\Models\AllocationRuleset;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 2 — Allocation rulesets + Event Inventory Allocation routes.
 * Both reuse inventory.edit since they mutate stock and are conceptually
 * inventory-side concerns.
 *
 * Routes covered:
 *   - /allocation-rulesets/* (resource + preview)         → inventory.edit
 *   - /events/{event}/inventory/* (5 routes)              → inventory.edit
 */
class AllocationRulesetAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeRole(string $name, array $perms): Role
    {
        $role = Role::create(['name' => $name, 'display_name' => $name, 'description' => '']);
        foreach ($perms as $p) {
            RolePermission::create(['role_id' => $role->id, 'permission' => $p]);
        }
        return $role;
    }

    private function makeUser(Role $role, string $email): User
    {
        return User::create([
            'name'              => $role->name,
            'email'             => $email,
            'password'          => bcrypt('password'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    private function makeEvent(): Event
    {
        return Event::create(['name' => 'Test Event', 'date' => '2026-06-01', 'lanes' => 1]);
    }

    private function makeItem(): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'Beans', 'unit_type' => 'can',
            'quantity_on_hand' => 100, 'reorder_level' => 10, 'is_active' => true,
        ]);
    }

    // ─── Allocation rulesets ──────────────────────────────────────────────────

    public function test_user_without_inventory_edit_blocked_at_rulesets_index(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/allocation-rulesets')->assertForbidden();
    }

    public function test_user_with_inventory_view_only_blocked_at_rulesets(): void
    {
        // Rulesets reuse inventory.edit; view alone is not enough.
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');
        $this->actingAs($viewer)->get('/allocation-rulesets')->assertForbidden();
    }

    public function test_inventory_edit_grantee_can_index_rulesets(): void
    {
        $editor = $this->makeUser(
            $this->makeRole('EDITOR', ['inventory.view', 'inventory.edit']),
            'edit@test.local'
        );
        $this->actingAs($editor)->get('/allocation-rulesets')->assertOk();
    }

    public function test_user_without_inventory_edit_cannot_create_ruleset(): void
    {
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');
        $response = $this->actingAs($viewer)->post('/allocation-rulesets', [
            'name'  => 'New Ruleset',
            'rules' => [['min' => 1, 'max' => 1, 'bags' => 1]],
        ]);
        $response->assertForbidden();
        $this->assertDatabaseMissing('allocation_rulesets', ['name' => 'New Ruleset']);
    }

    public function test_user_without_inventory_edit_cannot_preview_ruleset(): void
    {
        $ruleset = AllocationRuleset::create([
            'name' => 'Existing', 'rules' => [['min' => 1, 'max' => 1, 'bags' => 1]], 'is_active' => true,
        ]);
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');
        $this->actingAs($viewer)->get("/allocation-rulesets/{$ruleset->id}/preview")->assertForbidden();
    }

    // ─── Event Inventory Allocation ──────────────────────────────────────────

    public function test_user_without_inventory_edit_cannot_allocate(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem();
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');

        $response = $this->actingAs($viewer)->post("/events/{$event->id}/inventory", [
            'inventory_item_id'  => $item->id,
            'allocated_quantity' => 5,
        ]);

        $response->assertForbidden();
    }

    public function test_inventory_edit_grantee_can_allocate(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem();
        $editor = $this->makeUser(
            $this->makeRole('EDITOR', ['inventory.view', 'inventory.edit']),
            'edit@test.local'
        );

        $response = $this->actingAs($editor)->post("/events/{$event->id}/inventory", [
            'inventory_item_id'  => $item->id,
            'allocated_quantity' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('event_inventory_allocations', [
            'event_id' => $event->id, 'inventory_item_id' => $item->id,
        ]);
    }

    public function test_user_without_inventory_edit_cannot_bulk_allocate(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem();
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');

        $response = $this->actingAs($viewer)->post("/events/{$event->id}/inventory/bulk", [
            'items' => [['inventory_item_id' => $item->id, 'allocated_quantity' => 1]],
        ]);
        $response->assertForbidden();
    }

    public function test_admin_wildcard_can_do_everything(): void
    {
        $event = $this->makeEvent();
        $item  = $this->makeItem();
        $admin = $this->makeUser($this->makeRole('ADMIN', ['*']), 'admin@test.local');

        $this->actingAs($admin)->get('/allocation-rulesets')->assertOk();
        $this->actingAs($admin)->post("/events/{$event->id}/inventory", [
            'inventory_item_id'  => $item->id,
            'allocated_quantity' => 5,
        ])->assertRedirect();
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $event = $this->makeEvent();
        $this->get('/allocation-rulesets')->assertRedirect(route('login'));
        $this->post("/events/{$event->id}/inventory", [])->assertRedirect(route('login'));
    }
}
