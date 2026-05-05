<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 3c — StorePurchaseOrderRequest::authorize() now checks the dedicated
 * purchase_orders.create permission instead of the legacy "isAdmin OR
 * inventory.edit" fallback. ADMIN keeps access via Gate::before's '*' match.
 *
 * Tier 2 will additionally gate the route group on purchase_orders.view; this
 * test exercises only the FormRequest layer.
 */
class PurchaseOrderAuthorizationTest extends TestCase
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

    private function payload(): array
    {
        $item = InventoryItem::create([
            'name'             => 'Beans',
            'unit_type'        => 'can',
            'quantity_on_hand' => 0,
            'reorder_level'    => 5,
            'is_active'        => true,
        ]);

        return [
            'supplier_name' => 'ACME',
            'order_date'    => '2026-04-30',
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 1.50],
            ],
        ];
    }

    public function test_admin_wildcard_can_store_purchase_order(): void
    {
        $admin = $this->makeUser($this->makeRole('ADMIN', ['*']), 'admin@test.local');

        $response = $this->actingAs($admin)->post('/purchase-orders', $this->payload());

        $response->assertRedirect();
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_user_with_purchase_orders_create_can_store(): void
    {
        $buyer = $this->makeUser(
            $this->makeRole('BUYER', ['purchase_orders.view', 'purchase_orders.create']),
            'buyer@test.local'
        );

        $response = $this->actingAs($buyer)->post('/purchase-orders', $this->payload());

        $response->assertRedirect();
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_user_with_only_inventory_edit_is_denied(): void
    {
        // Tier 3c — the previous "isAdmin OR inventory.edit" fallback is gone.
        // inventory.edit alone no longer authorizes PO creation; the user
        // must hold the dedicated purchase_orders.create permission.
        $warehouse = $this->makeUser(
            $this->makeRole('WAREHOUSE', ['inventory.view', 'inventory.edit']),
            'warehouse@test.local'
        );

        $response = $this->actingAs($warehouse)->post('/purchase-orders', $this->payload());

        $response->assertForbidden();
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_user_with_no_relevant_perms_is_denied(): void
    {
        $intake = $this->makeUser(
            $this->makeRole('INTAKE', ['households.view']),
            'intake@test.local'
        );

        $response = $this->actingAs($intake)->post('/purchase-orders', $this->payload());

        $response->assertForbidden();
        $this->assertDatabaseCount('purchase_orders', 0);
    }
}
