<?php

namespace Tests\Feature;

use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 2 — Finance transactions authorization. Layering:
 *   - GET /finance/transactions (index/show)              → permission:finance.view
 *   - GET /finance/transactions/export/{print,csv}        → permission:finance.view
 *   - GET /finance/transactions/{tx}/attachment           → permission:finance.view
 *   - POST/PUT writes via FormRequest::authorize          → finance.create / finance.edit
 *   - DELETE /finance/transactions/{tx}                   → permission:finance.delete
 *   - DELETE /finance/transactions/{tx}/attachment        → permission:finance.delete
 *
 * Pre-fix: any authenticated user could create / mutate / delete finance
 * transactions. The exports leaked the same data via CSV / print without
 * any permission check.
 */
class FinanceTransactionAuthorizationTest extends TestCase
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

    private function makeTx(): FinanceTransaction
    {
        $cat = FinanceCategory::create(['name' => 'Donations', 'type' => 'income', 'is_active' => true]);
        return FinanceTransaction::create([
            'transaction_type' => 'income',
            'title'            => 'Test Tx',
            'category_id'      => $cat->id,
            'amount'           => 100.00,
            'transaction_date' => '2026-04-01',
            'source_or_payee'  => 'Donor X',
            'status'           => 'completed',
        ]);
    }

    private function txPayload(): array
    {
        $cat = FinanceCategory::firstOrCreate(
            ['name' => 'Donations'],
            ['type' => 'income', 'is_active' => true]
        );
        return [
            'transaction_type' => 'income',
            'title'            => 'New Tx',
            'category_id'      => $cat->id,
            'amount'           => 50.00,
            'transaction_date' => '2026-04-01',
            'source_or_payee'  => 'Donor Y',
            'status'           => 'completed',
        ];
    }

    // ─── Reads ────────────────────────────────────────────────────────────────

    public function test_user_without_finance_view_blocked_at_index(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $this->actingAs($intake)->get('/finance/transactions')->assertForbidden();
    }

    public function test_finance_view_grantee_can_index(): void
    {
        $accountant = $this->makeUser($this->makeRole('ACC', ['finance.view']), 'acc@test.local');

        $this->actingAs($accountant)->get('/finance/transactions')->assertOk();
    }

    public function test_user_without_finance_view_cannot_export_print(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $this->actingAs($intake)->get('/finance/transactions/export/print')->assertForbidden();
    }

    public function test_user_without_finance_view_cannot_export_csv(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $this->actingAs($intake)->get('/finance/transactions/export/csv')->assertForbidden();
    }

    // ─── Writes — create ──────────────────────────────────────────────────────

    public function test_finance_view_only_user_cannot_create_transaction(): void
    {
        $accountant = $this->makeUser($this->makeRole('ACC', ['finance.view']), 'acc@test.local');

        $response = $this->actingAs($accountant)->post('/finance/transactions', $this->txPayload());

        $response->assertForbidden();
        $this->assertDatabaseMissing('finance_transactions', ['title' => 'New Tx']);
    }

    public function test_finance_create_grantee_can_create_transaction(): void
    {
        $bookkeeper = $this->makeUser(
            $this->makeRole('BOOKKEEPER', ['finance.view', 'finance.create']),
            'book@test.local'
        );

        $response = $this->actingAs($bookkeeper)->post('/finance/transactions', $this->txPayload());

        $response->assertRedirect();
        $this->assertDatabaseHas('finance_transactions', ['title' => 'New Tx']);
    }

    // ─── Writes — update ──────────────────────────────────────────────────────

    public function test_finance_create_only_user_cannot_update_transaction(): void
    {
        $tx = $this->makeTx();
        $bookkeeper = $this->makeUser(
            $this->makeRole('BOOKKEEPER', ['finance.view', 'finance.create']),
            'book@test.local'
        );

        $payload = $this->txPayload();
        $payload['title'] = 'Renamed';
        $response = $this->actingAs($bookkeeper)->put("/finance/transactions/{$tx->id}", $payload);

        $response->assertForbidden();
        $this->assertDatabaseHas('finance_transactions', ['id' => $tx->id, 'title' => 'Test Tx']);
    }

    public function test_finance_edit_grantee_can_update_transaction(): void
    {
        $tx = $this->makeTx();
        $editor = $this->makeUser(
            $this->makeRole('EDITOR', ['finance.view', 'finance.edit']),
            'edit@test.local'
        );

        $payload = $this->txPayload();
        $payload['title'] = 'Renamed';
        $response = $this->actingAs($editor)->put("/finance/transactions/{$tx->id}", $payload);

        $response->assertRedirect();
        $tx->refresh();
        $this->assertSame('Renamed', $tx->title);
    }

    // ─── Writes — delete ──────────────────────────────────────────────────────

    public function test_finance_edit_user_cannot_delete_transaction(): void
    {
        // finance.edit alone should NOT be able to delete (Tier 2 splits
        // edit and delete into separate keys).
        $tx = $this->makeTx();
        $editor = $this->makeUser(
            $this->makeRole('EDITOR', ['finance.view', 'finance.edit']),
            'edit@test.local'
        );

        $response = $this->actingAs($editor)->delete("/finance/transactions/{$tx->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('finance_transactions', ['id' => $tx->id]);
    }

    public function test_finance_delete_grantee_can_delete_transaction(): void
    {
        $tx = $this->makeTx();
        $auditor = $this->makeUser(
            $this->makeRole('AUDITOR', ['finance.view', 'finance.delete']),
            'aud@test.local'
        );

        $response = $this->actingAs($auditor)->delete("/finance/transactions/{$tx->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('finance_transactions', ['id' => $tx->id]);
    }

    public function test_finance_edit_user_cannot_remove_attachment(): void
    {
        $tx = $this->makeTx();
        $editor = $this->makeUser(
            $this->makeRole('EDITOR', ['finance.view', 'finance.edit']),
            'edit@test.local'
        );

        $response = $this->actingAs($editor)->delete("/finance/transactions/{$tx->id}/attachment");

        $response->assertForbidden();
    }

    public function test_admin_wildcard_can_do_everything(): void
    {
        $admin = $this->makeUser($this->makeRole('ADMIN', ['*']), 'admin@test.local');

        $this->actingAs($admin)->get('/finance/transactions')->assertOk();
        $this->actingAs($admin)->post('/finance/transactions', $this->txPayload())->assertRedirect();
        $this->actingAs($admin)->get('/finance/transactions/export/csv')->assertOk();
    }

    public function test_unauthenticated_finance_transaction_request_redirects_to_login(): void
    {
        $this->get('/finance/transactions')->assertRedirect(route('login'));
    }
}
