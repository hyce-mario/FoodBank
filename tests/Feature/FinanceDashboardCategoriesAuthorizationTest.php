<?php

namespace Tests\Feature;

use App\Models\FinanceCategory;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 2 — Finance dashboard + categories authorization. Pre-fix the entire
 * /finance prefix sat behind `auth` only, so any authenticated user could
 * read the dashboard, mutate finance categories, and (via the next commit)
 * mutate finance transactions.
 *
 * Layering:
 *   - GET /finance              → permission:finance.view
 *   - /finance/categories       → finance.view (read), finance.edit (writes)
 *   - StoreFinanceCategoryRequest::authorize → finance.edit (defense in depth)
 *   - UpdateFinanceCategoryRequest::authorize → finance.edit
 *   - DELETE /finance/categories/{id} → permission:finance.edit middleware
 */
class FinanceDashboardCategoriesAuthorizationTest extends TestCase
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

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function test_user_without_finance_view_cannot_access_dashboard(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $this->actingAs($intake)->get('/finance')->assertForbidden();
    }

    public function test_user_with_finance_view_passes_dashboard_middleware(): void
    {
        // Positive middleware test — dashboard render uses DATE_FORMAT which
        // sqlite can't evaluate, so we only assert the middleware passes
        // (response is anything other than 403). Render correctness is
        // covered by the existing finance dashboard tests (MySQL-only).
        $accountant = $this->makeUser(
            $this->makeRole('ACCOUNTANT', ['finance.view']),
            'acc@test.local'
        );

        $response = $this->actingAs($accountant)->get('/finance');

        $this->assertNotSame(403, $response->status(),
            'finance.view should pass the route middleware');
    }

    public function test_admin_wildcard_passes_dashboard_middleware(): void
    {
        $admin = $this->makeUser($this->makeRole('ADMIN', ['*']), 'admin@test.local');

        $response = $this->actingAs($admin)->get('/finance');

        $this->assertNotSame(403, $response->status());
    }

    // ─── Categories — read ────────────────────────────────────────────────────

    public function test_user_without_finance_view_cannot_index_categories(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $this->actingAs($intake)->get('/finance/categories')->assertForbidden();
    }

    public function test_finance_view_grantee_can_index_categories(): void
    {
        $accountant = $this->makeUser(
            $this->makeRole('ACCOUNTANT', ['finance.view']),
            'acc@test.local'
        );

        $this->actingAs($accountant)->get('/finance/categories')->assertOk();
    }

    // ─── Categories — write ───────────────────────────────────────────────────

    public function test_finance_view_only_user_cannot_create_category(): void
    {
        $accountant = $this->makeUser(
            $this->makeRole('ACCOUNTANT', ['finance.view']),
            'acc@test.local'
        );

        $response = $this->actingAs($accountant)->post('/finance/categories', [
            'name'      => 'New Category',
            'type'      => 'income',
            'is_active' => true,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('finance_categories', ['name' => 'New Category']);
    }

    public function test_finance_edit_grantee_can_create_category(): void
    {
        $finance = $this->makeUser(
            $this->makeRole('FINANCE', ['finance.view', 'finance.edit']),
            'fin@test.local'
        );

        $response = $this->actingAs($finance)->post('/finance/categories', [
            'name'      => 'New Category',
            'type'      => 'income',
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('finance_categories', ['name' => 'New Category']);
    }

    public function test_finance_view_only_user_cannot_update_category(): void
    {
        $cat = FinanceCategory::create(['name' => 'Original', 'type' => 'income', 'is_active' => true]);
        $accountant = $this->makeUser(
            $this->makeRole('ACCOUNTANT', ['finance.view']),
            'acc@test.local'
        );

        $response = $this->actingAs($accountant)->put("/finance/categories/{$cat->id}", [
            'name'      => 'Renamed',
            'type'      => 'income',
            'is_active' => true,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('finance_categories', ['id' => $cat->id, 'name' => 'Original']);
    }

    public function test_finance_view_only_user_cannot_delete_category(): void
    {
        $cat = FinanceCategory::create(['name' => 'Doomed', 'type' => 'income', 'is_active' => true]);
        $accountant = $this->makeUser(
            $this->makeRole('ACCOUNTANT', ['finance.view']),
            'acc@test.local'
        );

        $response = $this->actingAs($accountant)->delete("/finance/categories/{$cat->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('finance_categories', ['id' => $cat->id]);
    }

    public function test_finance_edit_grantee_can_delete_category(): void
    {
        $cat = FinanceCategory::create(['name' => 'Doomed', 'type' => 'income', 'is_active' => true]);
        $finance = $this->makeUser(
            $this->makeRole('FINANCE', ['finance.view', 'finance.edit']),
            'fin@test.local'
        );

        $response = $this->actingAs($finance)->delete("/finance/categories/{$cat->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('finance_categories', ['id' => $cat->id]);
    }

    public function test_unauthenticated_finance_request_redirects_to_login(): void
    {
        $this->get('/finance')->assertRedirect(route('login'));
        $this->get('/finance/categories')->assertRedirect(route('login'));
    }
}
