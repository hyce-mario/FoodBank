<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP feature tests for InventoryItemController::quickStore() — the JSON
 * endpoint used by the Purchase Order line-item picker to create a new
 * inventory item via modal without leaving the form.
 *
 * Contract:
 *   - Auth required (mirrors all other inventory write endpoints).
 *   - Validates name + unit_type + optional category_id + optional description.
 *   - Stock starts at 0 (PO will fill it on receipt).
 *   - reorder_level defaults from inventory.low_stock_threshold setting.
 *   - 422 returns Laravel's standard {message, errors: {field: [msg]}} shape
 *     so the Alpine modal can show field-level errors.
 *   - 201 returns the new item with eager-loaded category for client-side
 *     selection without a follow-up GET.
 */
class InventoryItemQuickStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        // Tier 2 — quick-create now requires inventory.edit (route mw +
        // FormRequest authorize). Phase 6.6 originally used a phantom
        // 'inventory.create' permission that didn't match the catalog;
        // the catalog actually only ships inventory.{view,edit}.
        $role = Role::create(['name' => 'STAFF', 'display_name' => 'Staff', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => 'inventory.view']);
        RolePermission::create(['role_id' => $role->id, 'permission' => 'inventory.edit']);
        $this->user = User::create([
            'name'              => 'Staff',
            'email'             => 'staff@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_unauthenticated_returns_redirect_to_login(): void
    {
        $this->postJson('/inventory/items/quick-create', [
            'name'      => 'Beans',
            'unit_type' => 'Case',
        ])->assertStatus(401);
    }

    public function test_creates_item_with_minimal_fields(): void
    {
        $this->actingAs($this->user)
             ->postJson('/inventory/items/quick-create', [
                 'name'      => 'Black Beans',
                 'unit_type' => 'Case',
             ])
             ->assertCreated()
             ->assertJsonStructure(['item' => ['id', 'name', 'unit_type', 'category']]);

        $this->assertDatabaseHas('inventory_items', [
            'name'             => 'Black Beans',
            'unit_type'        => 'Case',
            'quantity_on_hand' => 0,
            'is_active'        => 1,
        ]);
    }

    public function test_creates_item_with_category(): void
    {
        $cat = InventoryCategory::create(['name' => 'Canned Goods']);

        $response = $this->actingAs($this->user)
             ->postJson('/inventory/items/quick-create', [
                 'name'        => 'Tomato Soup',
                 'unit_type'   => 'Case',
                 'category_id' => $cat->id,
                 'description' => '12-pack',
             ]);

        $response->assertCreated();
        $payload = $response->json('item');

        $this->assertSame('Tomato Soup', $payload['name']);
        $this->assertSame('Canned Goods', $payload['category']['name']);
        $this->assertSame($cat->id,       $payload['category']['id']);

        $this->assertDatabaseHas('inventory_items', [
            'name'        => 'Tomato Soup',
            'description' => '12-pack',
            'category_id' => $cat->id,
        ]);
    }

    public function test_reorder_level_defaults_from_settings(): void
    {
        SettingService::set('inventory.low_stock_threshold', 25);

        $this->actingAs($this->user)
             ->postJson('/inventory/items/quick-create', [
                 'name'      => 'Rice',
                 'unit_type' => 'Bag',
             ])
             ->assertCreated();

        $this->assertDatabaseHas('inventory_items', [
            'name'          => 'Rice',
            'reorder_level' => 25,
        ]);
    }

    public function test_missing_name_returns_422_with_field_error(): void
    {
        $this->actingAs($this->user)
             ->postJson('/inventory/items/quick-create', [
                 'unit_type' => 'Case',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['name']);
    }

    public function test_missing_unit_type_returns_422_with_field_error(): void
    {
        $this->actingAs($this->user)
             ->postJson('/inventory/items/quick-create', [
                 'name' => 'Pasta',
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['unit_type']);
    }

    public function test_invalid_category_id_returns_422(): void
    {
        $this->actingAs($this->user)
             ->postJson('/inventory/items/quick-create', [
                 'name'        => 'Pasta',
                 'unit_type'   => 'Box',
                 'category_id' => 99999,
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['category_id']);
    }

    public function test_created_item_appears_in_active_listing(): void
    {
        // Pin that the new item is immediately pickable from the PO form's
        // items query (active() scope, ordered by name).
        $this->actingAs($this->user)
             ->postJson('/inventory/items/quick-create', [
                 'name'      => 'Quick Item',
                 'unit_type' => 'Each',
             ])
             ->assertCreated();

        $this->assertTrue(
            InventoryItem::active()->where('name', 'Quick Item')->exists(),
            'Quick-created item must be returned by the active() scope used on the PO form.'
        );
    }
}
