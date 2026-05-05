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

    // ─── Tier 2 — route middleware on read / receive / cancel ────────────────

    public function test_user_without_purchase_orders_view_blocked_at_index(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/purchase-orders')->assertForbidden();
    }

    public function test_purchase_orders_view_grantee_can_index(): void
    {
        $viewer = $this->makeUser(
            $this->makeRole('VIEWER', ['purchase_orders.view']),
            'view@test.local'
        );
        $this->actingAs($viewer)->get('/purchase-orders')->assertOk();
    }

    public function test_purchase_orders_view_alone_cannot_create(): void
    {
        $viewer = $this->makeUser(
            $this->makeRole('VIEWER', ['purchase_orders.view']),
            'view@test.local'
        );

        // create-form GET hits the create middleware
        $this->actingAs($viewer)->get('/purchase-orders/create')->assertForbidden();
        // POST hits both create middleware AND FormRequest
        $this->actingAs($viewer)->post('/purchase-orders', $this->payload())->assertForbidden();
    }

    public function test_only_purchase_orders_create_can_create(): void
    {
        $buyer = $this->makeUser(
            $this->makeRole('BUYER', ['purchase_orders.view', 'purchase_orders.create']),
            'buy@test.local'
        );

        $this->actingAs($buyer)->get('/purchase-orders/create')->assertOk();
    }

    public function test_purchase_orders_create_alone_cannot_receive(): void
    {
        // Even if you can create POs, receiving them (which posts inventory
        // and a finance transaction) is a separate permission.
        $po = \App\Services\PurchaseOrderService::class;
        $po = app($po)->create($this->payload());

        $buyer = $this->makeUser(
            $this->makeRole('BUYER', ['purchase_orders.view', 'purchase_orders.create']),
            'buy@test.local'
        );

        $this->actingAs($buyer)
             ->post("/purchase-orders/{$po->id}/receive")
             ->assertForbidden();
    }

    public function test_purchase_orders_receive_grantee_can_receive(): void
    {
        $po = app(\App\Services\PurchaseOrderService::class)->create($this->payload());

        $receiver = $this->makeUser(
            $this->makeRole('RECEIVER', ['purchase_orders.view', 'purchase_orders.receive']),
            'rec@test.local'
        );

        $response = $this->actingAs($receiver)->post("/purchase-orders/{$po->id}/receive");
        $this->assertNotSame(403, $response->status());
    }

    public function test_purchase_orders_create_alone_cannot_cancel(): void
    {
        $po = app(\App\Services\PurchaseOrderService::class)->create($this->payload());

        $buyer = $this->makeUser(
            $this->makeRole('BUYER', ['purchase_orders.view', 'purchase_orders.create']),
            'buy@test.local'
        );

        $this->actingAs($buyer)
             ->post("/purchase-orders/{$po->id}/cancel")
             ->assertForbidden();
    }

    public function test_purchase_orders_cancel_grantee_can_cancel(): void
    {
        $po = app(\App\Services\PurchaseOrderService::class)->create($this->payload());

        $canceler = $this->makeUser(
            $this->makeRole('CANCELER', ['purchase_orders.view', 'purchase_orders.cancel']),
            'can@test.local'
        );

        $response = $this->actingAs($canceler)->post("/purchase-orders/{$po->id}/cancel");
        $this->assertNotSame(403, $response->status());
    }

    public function test_unauthenticated_purchase_orders_redirects_to_login(): void
    {
        $this->get('/purchase-orders')->assertRedirect(route('login'));
    }
}
