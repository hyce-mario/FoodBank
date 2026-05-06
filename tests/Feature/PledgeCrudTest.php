<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Pledge;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7.4.c — admin CRUD for pledges. Pins schema + finance.{view,edit} gates.
 */
class PledgeCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'A', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name' => 'A', 'email' => 'a@test.local', 'password' => bcrypt('p'),
            'role_id' => $adminRole->id, 'email_verified_at' => now(),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'source_or_payee' => 'ACME Foundation',
            'amount'          => 5000.00,
            'pledged_at'      => '2026-04-01',
            'expected_at'     => '2026-05-01',
            'status'          => 'open',
        ], $overrides);
    }

    // ─── Schema ──────────────────────────────────────────────────────────────

    public function test_pledges_table_persists_all_columns(): void
    {
        $hh = Household::create([
            'household_number' => 'H1', 'first_name' => 'Jane', 'last_name' => 'Doe',
            'household_size' => 1, 'adults_count' => 1, 'children_count' => 0, 'seniors_count' => 0,
        ]);
        $p = Pledge::create($this->payload(['household_id' => $hh->id]));
        $fresh = $p->fresh();

        $this->assertSame(5000.00, (float) $fresh->amount);
        $this->assertSame('open', $fresh->status);
        $this->assertSame($hh->id, $fresh->household_id);
        $this->assertSame('ACME Foundation', $fresh->source_or_payee);
    }

    public function test_outstanding_scope_excludes_fulfilled_and_written_off(): void
    {
        Pledge::create($this->payload(['source_or_payee' => 'A', 'status' => 'open']));
        Pledge::create($this->payload(['source_or_payee' => 'B', 'status' => 'partial']));
        Pledge::create($this->payload(['source_or_payee' => 'C', 'status' => 'fulfilled']));
        Pledge::create($this->payload(['source_or_payee' => 'D', 'status' => 'written_off']));

        $outstanding = Pledge::outstanding()->get();
        $this->assertSame(2, $outstanding->count());
    }

    // ─── HTTP CRUD ───────────────────────────────────────────────────────────

    public function test_admin_can_view_index(): void
    {
        $this->actingAs($this->admin)->get('/finance/pledges')->assertOk()->assertSeeText('Pledges');
    }

    public function test_admin_can_create_pledge(): void
    {
        $response = $this->actingAs($this->admin)->post('/finance/pledges', $this->payload());

        $response->assertRedirect();
        $this->assertDatabaseHas('pledges', ['source_or_payee' => 'ACME Foundation']);
    }

    public function test_admin_can_edit_pledge(): void
    {
        $p = Pledge::create($this->payload());

        $response = $this->actingAs($this->admin)->put("/finance/pledges/{$p->id}",
            $this->payload(['amount' => 7500, 'status' => 'fulfilled', 'received_at' => '2026-04-25'])
        );

        $response->assertRedirect();
        $fresh = $p->fresh();
        $this->assertSame(7500.00, (float) $fresh->amount);
        $this->assertSame('fulfilled', $fresh->status);
    }

    public function test_admin_can_destroy_pledge(): void
    {
        $p = Pledge::create($this->payload());
        $this->actingAs($this->admin)->delete("/finance/pledges/{$p->id}")->assertRedirect();
        $this->assertDatabaseMissing('pledges', ['id' => $p->id]);
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

        $this->actingAs($intake)->get('/finance/pledges')->assertForbidden();
    }

    public function test_user_with_finance_view_only_cannot_create(): void
    {
        $viewerRole = Role::create(['name' => 'V', 'display_name' => 'V', 'description' => '']);
        RolePermission::create(['role_id' => $viewerRole->id, 'permission' => 'finance.view']);
        $viewer = User::create([
            'name' => 'V', 'email' => 'v@test.local', 'password' => bcrypt('p'),
            'role_id' => $viewerRole->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($viewer)->get('/finance/pledges')->assertOk();
        $this->actingAs($viewer)->post('/finance/pledges', $this->payload())->assertForbidden();
    }
}
