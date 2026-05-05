<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 2 — Inventory authorization. Pre-fix the entire /inventory tree
 * was readable / writable by any authenticated user.
 *
 * Layering — keys are split view/edit (catalog already had this layout
 * pre-Tier-1; Tier 1 didn't expand inventory):
 *   - GET  /inventory/items{,/print,/export.csv,/{id},/{id}/edit}  → inventory.view
 *   - GET  /inventory/categories                                    → inventory.view
 *   - POST /inventory/items{,/quick-create,/{id}/movements}         → inventory.edit
 *   - PUT  /inventory/items/{id}                                    → inventory.edit
 *   - DELETE /inventory/items/{id}                                  → inventory.edit
 *   - {POST,PUT,DELETE} /inventory/categories                       → inventory.edit
 */
class InventoryAuthorizationTest extends TestCase
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

    private function makeItem(): InventoryItem
    {
        return InventoryItem::create([
            'name'             => 'Beans',
            'unit_type'        => 'can',
            'quantity_on_hand' => 5,
            'reorder_level'    => 10,
            'is_active'        => true,
        ]);
    }

    private function itemPayload(): array
    {
        return [
            'name'             => 'New Item',
            'unit_type'        => 'box',
            'quantity_on_hand' => 0,
            'reorder_level'    => 5,
            'is_active'        => 1,
        ];
    }

    // ─── Reads ────────────────────────────────────────────────────────────────

    public function test_user_without_inventory_view_blocked_at_items_index(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/inventory/items')->assertForbidden();
    }

    public function test_inventory_view_grantee_can_index_items(): void
    {
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');
        $this->actingAs($viewer)->get('/inventory/items')->assertOk();
    }

    public function test_user_without_inventory_view_blocked_at_categories(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/inventory/categories')->assertForbidden();
    }

    public function test_user_without_inventory_view_cannot_export_print(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/inventory/items/print')->assertForbidden();
    }

    public function test_user_without_inventory_view_cannot_export_csv(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/inventory/items/export.csv')->assertForbidden();
    }

    // ─── Writes ───────────────────────────────────────────────────────────────

    public function test_view_only_user_cannot_create_item(): void
    {
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');

        $response = $this->actingAs($viewer)->post('/inventory/items', $this->itemPayload());

        $response->assertForbidden();
        $this->assertDatabaseMissing('inventory_items', ['name' => 'New Item']);
    }

    public function test_inventory_edit_grantee_can_create_item(): void
    {
        $warehouse = $this->makeUser(
            $this->makeRole('WAREHOUSE', ['inventory.view', 'inventory.edit']),
            'wh@test.local'
        );

        $response = $this->actingAs($warehouse)->post('/inventory/items', $this->itemPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_items', ['name' => 'New Item']);
    }

    public function test_view_only_user_cannot_update_item(): void
    {
        $item = $this->makeItem();
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');

        $response = $this->actingAs($viewer)->put("/inventory/items/{$item->id}", [
            'name'             => 'Renamed',
            'unit_type'        => 'can',
            'quantity_on_hand' => 5,
            'reorder_level'    => 10,
            'is_active'        => 1,
        ]);

        $response->assertForbidden();
    }

    public function test_view_only_user_cannot_delete_item(): void
    {
        $item = $this->makeItem();
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');

        $response = $this->actingAs($viewer)->delete("/inventory/items/{$item->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }

    public function test_view_only_user_cannot_quick_create_item(): void
    {
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');

        $response = $this->actingAs($viewer)->postJson('/inventory/items/quick-create', [
            'name'      => 'Beans',
            'unit_type' => 'Case',
        ]);

        $response->assertForbidden();
    }

    public function test_view_only_user_cannot_record_movement(): void
    {
        $item = $this->makeItem();
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');

        $response = $this->actingAs($viewer)->post("/inventory/items/{$item->id}/movements", [
            'action'   => 'add',
            'quantity' => 10,
        ]);

        $response->assertForbidden();
    }

    public function test_view_only_user_cannot_create_category(): void
    {
        $viewer = $this->makeUser($this->makeRole('VIEWER', ['inventory.view']), 'view@test.local');

        $response = $this->actingAs($viewer)->post('/inventory/categories', [
            'name' => 'New Cat',
        ]);

        $response->assertForbidden();
    }

    public function test_inventory_edit_grantee_can_create_category(): void
    {
        $warehouse = $this->makeUser(
            $this->makeRole('WAREHOUSE', ['inventory.view', 'inventory.edit']),
            'wh@test.local'
        );

        $response = $this->actingAs($warehouse)->post('/inventory/categories', [
            'name' => 'New Cat',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_categories', ['name' => 'New Cat']);
    }

    public function test_admin_wildcard_can_do_everything(): void
    {
        $admin = $this->makeUser($this->makeRole('ADMIN', ['*']), 'admin@test.local');

        $this->actingAs($admin)->get('/inventory/items')->assertOk();
        $this->actingAs($admin)->post('/inventory/items', $this->itemPayload())->assertRedirect();
    }

    public function test_unauthenticated_inventory_redirects_to_login(): void
    {
        $this->get('/inventory/items')->assertRedirect(route('login'));
        $this->get('/inventory/categories')->assertRedirect(route('login'));
    }
}
