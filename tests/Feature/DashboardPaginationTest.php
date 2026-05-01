<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\InventoryItem;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard table pagination contract.
 *
 * Both dashboard tables (Recent Events, Inventory Alerts) cap at 7 rows per
 * page and use independent ?<table>_page=N query strings so navigating one
 * doesn't reset the other. The Inventory Alerts header counts are computed
 * from totals (not the current page) so the summary stays accurate as the
 * user paginates.
 */
class DashboardPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $role = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => '*']);
        $this->user = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_dashboard_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
             ->get('/')
             ->assertOk();
    }

    public function test_recent_events_table_caps_at_seven_per_page(): void
    {
        // Seed 10 past events; page 1 should show 7.
        for ($i = 1; $i <= 10; $i++) {
            Event::create([
                'name'   => "Past Event {$i}",
                'date'   => now()->subDays($i)->toDateString(),
                'status' => 'past',
                'lanes'  => 1,
            ]);
        }

        $response = $this->actingAs($this->user)->get('/')->assertOk();

        // Page 1 shows the 7 most recent (1..7 days ago by date desc)
        for ($i = 1; $i <= 7; $i++) {
            $response->assertSee("Past Event {$i}");
        }
        // Day 8/9/10 events spill onto page 2
        $response->assertDontSee('Past Event 8');
    }

    public function test_recent_events_page_two_shows_remaining_rows(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            Event::create([
                'name'   => "Past Event {$i}",
                'date'   => now()->subDays($i)->toDateString(),
                'status' => 'past',
                'lanes'  => 1,
            ]);
        }

        $response = $this->actingAs($this->user)
                         ->get('/?events_page=2')
                         ->assertOk();

        // Page 2 contains the 3 oldest events
        $response->assertSee('Past Event 8');
        $response->assertSee('Past Event 9');
        $response->assertSee('Past Event 10');
        // …and not the 7 newest
        $response->assertDontSee('Past Event 1<');
    }

    public function test_stock_alerts_cap_at_seven_per_page_with_out_of_stock_first(): void
    {
        // 3 out-of-stock + 6 low-stock = 9 alerts. Page 1 should be the 3
        // out-of-stock items + the first 4 low-stock items, in that order.
        for ($i = 1; $i <= 3; $i++) {
            InventoryItem::create([
                'name'             => "OUT-{$i}",
                'unit_type'        => 'Case',
                'quantity_on_hand' => 0,
                'reorder_level'    => 5,
                'is_active'        => true,
            ]);
        }
        for ($i = 1; $i <= 6; $i++) {
            InventoryItem::create([
                'name'             => "LOW-{$i}",
                'unit_type'        => 'Case',
                'quantity_on_hand' => 1,
                'reorder_level'    => 5,
                'is_active'        => true,
            ]);
        }

        $response = $this->actingAs($this->user)->get('/')->assertOk();

        // All 3 out-of-stock items appear on page 1
        $response->assertSee('OUT-1');
        $response->assertSee('OUT-2');
        $response->assertSee('OUT-3');
        // First 4 low-stock items appear on page 1
        $response->assertSee('LOW-1');
        $response->assertSee('LOW-4');
        // The 5th and 6th low-stock spill onto page 2
        $response->assertDontSee('LOW-5');
        $response->assertDontSee('LOW-6');
    }

    public function test_stock_alerts_header_counts_reflect_totals_not_current_page(): void
    {
        // Seed enough to span multiple pages so we can prove the header
        // counts don't shrink as the user paginates.
        for ($i = 1; $i <= 4; $i++) {
            InventoryItem::create([
                'name'             => "OUT-{$i}",
                'unit_type'        => 'Case',
                'quantity_on_hand' => 0,
                'reorder_level'    => 5,
                'is_active'        => true,
            ]);
        }
        for ($i = 1; $i <= 6; $i++) {
            InventoryItem::create([
                'name'             => "LOW-{$i}",
                'unit_type'        => 'Case',
                'quantity_on_hand' => 1,
                'reorder_level'    => 5,
                'is_active'        => true,
            ]);
        }

        // Page 1 header shows 4 out + 6 low
        $this->actingAs($this->user)->get('/')->assertOk()
             ->assertSee('4 out of stock');
        // Page 2 header still shows 4 out + 6 low (header is not page-bound)
        $this->actingAs($this->user)->get('/?stock_page=2')->assertOk()
             ->assertSee('4 out of stock');
    }

    public function test_event_pagination_does_not_reset_stock_pagination(): void
    {
        // Seed enough rows on both tables to span multiple pages.
        for ($i = 1; $i <= 10; $i++) {
            Event::create([
                'name'   => "PE-{$i}",
                'date'   => now()->subDays($i)->toDateString(),
                'status' => 'past',
                'lanes'  => 1,
            ]);
        }
        for ($i = 1; $i <= 10; $i++) {
            InventoryItem::create([
                'name'             => "STK-{$i}",
                'unit_type'        => 'Case',
                'quantity_on_hand' => 0,
                'reorder_level'    => 5,
                'is_active'        => true,
            ]);
        }

        // events_page=2 + stock_page=2 should land each table on its second
        // page independently — cross-pagination must be stable.
        $response = $this->actingAs($this->user)
                         ->get('/?events_page=2&stock_page=2')
                         ->assertOk();

        // Recent Events page 2 has the older events
        $response->assertSee('PE-8');
        // Stock alerts page 2 contains STK-7/8/9 (alphabetic sort puts STK-10
        // between STK-1 and STK-2, so it lives on page 1).
        $response->assertSee('STK-7');
        $response->assertSee('STK-8');
        $response->assertSee('STK-9');
    }

    public function test_inventory_alerts_block_hidden_when_disabled(): void
    {
        // When inventory.dashboard_low_stock_alert is off, the entire alerts
        // section is suppressed regardless of how many out-of-stock items exist.
        SettingService::set('inventory.dashboard_low_stock_alert', false);

        InventoryItem::create([
            'name'             => 'Hidden Item',
            'unit_type'        => 'Case',
            'quantity_on_hand' => 0,
            'reorder_level'    => 5,
            'is_active'        => true,
        ]);

        $this->actingAs($this->user)->get('/')->assertOk()
             ->assertDontSee('Inventory Alerts')
             ->assertDontSee('Hidden Item');
    }
}
