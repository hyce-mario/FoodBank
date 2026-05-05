<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 2 — Finance Reports authorization. Pre-fix the entire /finance/reports
 * sub-tree was readable by any authenticated user, including bulk-export of
 * donor data via CSV / PDF.
 *
 * Layering:
 *   - GET /finance/reports                       → permission:finance_reports.view
 *   - GET /finance/reports/{report}              → permission:finance_reports.view
 *   - GET /finance/reports/{report}/{print,pdf,csv} → +permission:finance_reports.export
 *
 * Mirrors the /reports + /reports/download split from Phase 5.13: a viewer
 * can browse on screen but cannot extract the underlying data without the
 * additional export grant.
 */
class FinanceReportsAuthorizationTest extends TestCase
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

    // ─── Read endpoints (hub + 8 report screens) ─────────────────────────────

    public function test_user_without_finance_reports_view_blocked_at_hub(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $this->actingAs($intake)->get('/finance/reports')->assertForbidden();
    }

    public function test_finance_reports_view_grantee_can_read_hub(): void
    {
        $analyst = $this->makeUser(
            $this->makeRole('ANALYST', ['finance_reports.view']),
            'an@test.local'
        );

        $this->actingAs($analyst)->get('/finance/reports')->assertOk();
    }

    public function test_finance_reports_view_alone_does_not_grant_finance_view(): void
    {
        // Reports viewer should NOT transitively gain access to /finance
        // dashboard or /finance/categories — those need finance.view.
        $analyst = $this->makeUser(
            $this->makeRole('ANALYST', ['finance_reports.view']),
            'an@test.local'
        );

        $this->actingAs($analyst)->get('/finance')->assertForbidden();
        $this->actingAs($analyst)->get('/finance/categories')->assertForbidden();
    }

    /** @dataProvider reportSlugs */
    public function test_finance_reports_view_grantee_can_read_each_report_screen(string $slug): void
    {
        $analyst = $this->makeUser(
            $this->makeRole('ANALYST', ['finance_reports.view']),
            'an@test.local'
        );

        $response = $this->actingAs($analyst)->get("/finance/reports/{$slug}");

        // Some reports (per-event-pnl) render a picker stub when no event is
        // selected; others render data. Either way, NOT 403.
        $this->assertNotSame(403, $response->status(),
            "finance_reports.view should pass middleware for /{$slug}");
    }

    /** @dataProvider reportSlugs */
    public function test_user_without_finance_reports_view_blocked_at_each_report(string $slug): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $this->actingAs($intake)->get("/finance/reports/{$slug}")->assertForbidden();
    }

    // ─── Export endpoints (print / pdf / csv) ────────────────────────────────

    public function test_finance_reports_view_alone_blocked_at_export(): void
    {
        $analyst = $this->makeUser(
            $this->makeRole('ANALYST', ['finance_reports.view']),
            'an@test.local'
        );

        $this->actingAs($analyst)->get('/finance/reports/statement-of-activities/print')->assertForbidden();
        $this->actingAs($analyst)->get('/finance/reports/statement-of-activities/pdf')  ->assertForbidden();
        $this->actingAs($analyst)->get('/finance/reports/statement-of-activities/csv')  ->assertForbidden();
    }

    public function test_finance_reports_export_grantee_can_export(): void
    {
        $exporter = $this->makeUser(
            $this->makeRole('EXPORTER', ['finance_reports.view', 'finance_reports.export']),
            'ex@test.local'
        );

        // CSV endpoint returns a StreamedResponse; use getStatusCode on the
        // baseResponse rather than TestResponse::status() which trips through
        // __call to the underlying object that lacks status().
        $r = $this->actingAs($exporter)->get('/finance/reports/statement-of-activities/csv');
        $this->assertNotSame(403, $r->baseResponse->getStatusCode(),
            'finance_reports.export grantee should pass middleware on /csv');
    }

    public function test_user_without_either_perm_blocked_at_export(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $this->actingAs($intake)->get('/finance/reports/income-detail/csv')->assertForbidden();
    }

    public function test_admin_wildcard_can_access_all(): void
    {
        $admin = $this->makeUser($this->makeRole('ADMIN', ['*']), 'admin@test.local');

        $this->actingAs($admin)->get('/finance/reports')->assertOk();
        $this->actingAs($admin)->get('/finance/reports/donor-analysis')->assertOk();

        $r = $this->actingAs($admin)->get('/finance/reports/donor-analysis/csv');
        $this->assertNotSame(403, $r->baseResponse->getStatusCode());
    }

    public function test_unauthenticated_finance_reports_redirect_to_login(): void
    {
        $this->get('/finance/reports')->assertRedirect(route('login'));
        $this->get('/finance/reports/statement-of-activities')->assertRedirect(route('login'));
    }

    // ─── Data provider for the 8 report slugs ────────────────────────────────

    public static function reportSlugs(): array
    {
        return [
            'statement-of-activities' => ['statement-of-activities'],
            'income-detail'           => ['income-detail'],
            'expense-detail'          => ['expense-detail'],
            'general-ledger'          => ['general-ledger'],
            'donor-analysis'          => ['donor-analysis'],
            'vendor-analysis'         => ['vendor-analysis'],
            'per-event-pnl'           => ['per-event-pnl'],
            'category-trend'          => ['category-trend'],
        ];
    }
}
