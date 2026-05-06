<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Event;
use App\Models\FinanceCategory;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7.4.b — admin CRUD for budgets. Pins the schema + the
 * finance.{view,edit} gates per Tier 2.
 */
class BudgetCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FinanceCategory $cat;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'A', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name' => 'A', 'email' => 'a@test.local', 'password' => bcrypt('p'),
            'role_id' => $adminRole->id, 'email_verified_at' => now(),
        ]);

        $this->cat = FinanceCategory::create([
            'name' => 'Food', 'type' => 'expense', 'is_active' => true,
            'function_classification' => 'program',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id'  => $this->cat->id,
            'period_start' => '2026-04-01',
            'period_end'   => '2026-04-30',
            'amount'       => 1000.00,
            'event_id'     => null,
        ], $overrides);
    }

    // ─── Schema + model ──────────────────────────────────────────────────────

    public function test_budgets_table_has_expected_columns(): void
    {
        $b = Budget::create($this->payload() + ['period_type' => 'monthly']);
        $fresh = $b->fresh();
        $this->assertSame(1000.00, (float) $fresh->amount);
        $this->assertSame('monthly', $fresh->period_type);
        $this->assertSame('2026-04-01', $fresh->period_start->format('Y-m-d'));
        $this->assertNull($fresh->event_id);
    }

    public function test_budget_unique_constraint_blocks_duplicate_per_event(): void
    {
        // SQLite + MySQL 8 both treat NULL as DISTINCT in unique indexes,
        // so two org-wide budget rows (event_id=NULL) for the same
        // (category, period) coexist by design — the report SUMs them.
        // Non-null event_id duplicates ARE blocked.
        $event = Event::create(['name' => 'E', 'date' => '2026-04-15', 'lanes' => 1]);

        Budget::create($this->payload(['event_id' => $event->id]) + ['period_type' => 'monthly']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Budget::create($this->payload(['event_id' => $event->id]) + ['period_type' => 'monthly']);
    }

    public function test_budget_unique_does_not_block_different_event_scope(): void
    {
        $event = Event::create(['name' => 'E', 'date' => '2026-04-15', 'lanes' => 1]);

        Budget::create($this->payload() + ['period_type' => 'monthly']);
        Budget::create($this->payload(['event_id' => $event->id]) + ['period_type' => 'monthly']);

        $this->assertSame(2, Budget::count());
    }

    // ─── HTTP — index / create / edit / destroy ──────────────────────────────

    public function test_admin_can_view_budgets_index(): void
    {
        $this->actingAs($this->admin)->get('/finance/budgets')->assertOk()->assertSeeText('Budgets');
    }

    public function test_admin_can_create_budget(): void
    {
        $response = $this->actingAs($this->admin)->post('/finance/budgets', $this->payload());

        $response->assertRedirect();
        $this->assertDatabaseHas('budgets', [
            'category_id' => $this->cat->id,
            'amount'      => 1000.00,
        ]);
    }

    public function test_create_records_creator_user(): void
    {
        $this->actingAs($this->admin)->post('/finance/budgets', $this->payload());
        $b = Budget::first();
        $this->assertSame($this->admin->id, $b->created_by);
    }

    public function test_admin_can_edit_budget(): void
    {
        $b = Budget::create($this->payload() + ['period_type' => 'monthly']);

        $response = $this->actingAs($this->admin)
                         ->put("/finance/budgets/{$b->id}", $this->payload(['amount' => 2500]));

        $response->assertRedirect();
        $this->assertSame(2500.00, (float) $b->fresh()->amount);
    }

    public function test_admin_can_destroy_budget(): void
    {
        $b = Budget::create($this->payload() + ['period_type' => 'monthly']);

        $response = $this->actingAs($this->admin)->delete("/finance/budgets/{$b->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('budgets', ['id' => $b->id]);
    }

    // ─── Tier 2 gates ────────────────────────────────────────────────────────

    public function test_user_without_finance_view_blocked_at_index(): void
    {
        $intakeRole = Role::create(['name' => 'INTAKE', 'display_name' => 'I', 'description' => '']);
        RolePermission::create(['role_id' => $intakeRole->id, 'permission' => 'households.view']);
        $intake = User::create([
            'name' => 'I', 'email' => 'i@test.local', 'password' => bcrypt('p'),
            'role_id' => $intakeRole->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($intake)->get('/finance/budgets')->assertForbidden();
    }

    public function test_user_with_finance_view_only_cannot_create_budget(): void
    {
        $viewerRole = Role::create(['name' => 'V', 'display_name' => 'V', 'description' => '']);
        RolePermission::create(['role_id' => $viewerRole->id, 'permission' => 'finance.view']);
        $viewer = User::create([
            'name' => 'V', 'email' => 'v@test.local', 'password' => bcrypt('p'),
            'role_id' => $viewerRole->id, 'email_verified_at' => now(),
        ]);

        // Index allowed, store rejected.
        $this->actingAs($viewer)->get('/finance/budgets')->assertOk();
        $this->actingAs($viewer)->post('/finance/budgets', $this->payload())->assertForbidden();
    }

    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->get('/finance/budgets')->assertRedirect(route('login'));
    }
}
