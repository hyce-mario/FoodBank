<?php

namespace Tests\Feature;

use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\FinanceReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7.4.a — Statement of Functional Expenses.
 *
 * Pin contracts:
 *   - function_classification column exists on finance_categories with the
 *     three IRS-990 enum values, defaulting to 'program'
 *   - functionalExpenses() service method buckets expenses by function and
 *     computes the program ratio + per-function shares
 *   - Income transactions are NEVER included (definition of the report)
 *   - Compare-mode adds prior totals, prior program ratio, and per-function
 *     deltas
 *   - Categories with unknown function fall back to 'program' (defensive)
 *   - HTTP endpoints (screen + print + pdf + csv) all gate behind
 *     finance_reports.{view,export} per Tier 2
 *   - CSV is UTF-8 BOM, accountant-friendly
 */
class FinanceReportFunctionalExpensesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FinanceCategory $programCat;
    private FinanceCategory $mgmtCat;
    private FinanceCategory $fundraisingCat;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name' => 'A', 'email' => 'a@test.local', 'password' => bcrypt('p'),
            'role_id' => $adminRole->id, 'email_verified_at' => now(),
        ]);

        $this->programCat = FinanceCategory::create([
            'name' => 'Food & Supplies', 'type' => 'expense',
            'function_classification' => 'program', 'is_active' => true,
        ]);
        $this->mgmtCat = FinanceCategory::create([
            'name' => 'Administrative', 'type' => 'expense',
            'function_classification' => 'management_general', 'is_active' => true,
        ]);
        $this->fundraisingCat = FinanceCategory::create([
            'name' => 'Marketing & Outreach', 'type' => 'expense',
            'function_classification' => 'fundraising', 'is_active' => true,
        ]);
    }

    private function tx(FinanceCategory $cat, float $amount, string $date = '2026-04-15', string $type = 'expense', string $status = 'completed'): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => $type,
            'title'            => "Tx {$amount}",
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => 'X',
            'status'           => $status,
        ]);
    }

    // ─── Schema ──────────────────────────────────────────────────────────────

    public function test_function_classification_column_exists_with_default(): void
    {
        $cat = FinanceCategory::create([
            'name' => 'Defaulted', 'type' => 'expense', 'is_active' => true,
        ]);

        $this->assertSame('program', $cat->fresh()->function_classification);
    }

    public function test_function_classification_accepts_all_three_values(): void
    {
        foreach (['program', 'management_general', 'fundraising'] as $val) {
            $cat = FinanceCategory::create([
                'name' => "C-{$val}", 'type' => 'expense',
                'function_classification' => $val, 'is_active' => true,
            ]);
            $this->assertSame($val, $cat->fresh()->function_classification);
        }
    }

    // ─── Service: shape ──────────────────────────────────────────────────────

    public function test_service_returns_three_function_buckets(): void
    {
        $svc = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $this->assertArrayHasKey('program', $data['by_function']);
        $this->assertArrayHasKey('management_general', $data['by_function']);
        $this->assertArrayHasKey('fundraising', $data['by_function']);
        $this->assertSame('Program', $data['by_function']['program']['label']);
        $this->assertSame('Management & General', $data['by_function']['management_general']['label']);
        $this->assertSame('Fundraising', $data['by_function']['fundraising']['label']);
    }

    public function test_service_buckets_expenses_by_function(): void
    {
        $this->tx($this->programCat,    1000);
        $this->tx($this->mgmtCat,        300);
        $this->tx($this->fundraisingCat, 200);

        $svc  = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $this->assertSame(1000.0, $data['by_function']['program']['total']);
        $this->assertSame(300.0,  $data['by_function']['management_general']['total']);
        $this->assertSame(200.0,  $data['by_function']['fundraising']['total']);
        $this->assertSame(1500.0, $data['total']);
    }

    public function test_service_computes_program_ratio(): void
    {
        $this->tx($this->programCat,    750);
        $this->tx($this->mgmtCat,       150);
        $this->tx($this->fundraisingCat, 100);

        $svc  = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $this->assertEqualsWithDelta(0.75, $data['program_ratio'], 0.001);
    }

    public function test_service_excludes_income_transactions(): void
    {
        // Income with the same category accidentally classified — must NOT show up.
        $incomeCat = FinanceCategory::create([
            'name' => 'Donations', 'type' => 'income',
            'function_classification' => 'program', 'is_active' => true,
        ]);
        $this->tx($incomeCat, 500, '2026-04-15', 'income');
        $this->tx($this->programCat, 100);

        $svc  = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $this->assertSame(100.0, $data['total'], 'Income transactions must not be counted in functional expenses.');
    }

    public function test_service_excludes_pending_and_cancelled(): void
    {
        $this->tx($this->programCat, 100, '2026-04-15', 'expense', 'completed');
        $this->tx($this->programCat, 200, '2026-04-15', 'expense', 'pending');
        $this->tx($this->programCat, 300, '2026-04-15', 'expense', 'cancelled');

        $svc  = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $this->assertSame(100.0, $data['total']);
    }

    public function test_service_excludes_out_of_period(): void
    {
        $this->tx($this->programCat, 100, '2026-03-15');  // before
        $this->tx($this->programCat, 200, '2026-04-15');  // in
        $this->tx($this->programCat, 300, '2026-05-15');  // after

        $svc  = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $this->assertSame(200.0, $data['total']);
    }

    public function test_service_groups_categories_within_function(): void
    {
        $other = FinanceCategory::create([
            'name' => 'Venue & Facilities', 'type' => 'expense',
            'function_classification' => 'program', 'is_active' => true,
        ]);
        $this->tx($this->programCat, 500);
        $this->tx($other, 300);

        $svc  = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $cats = $data['by_function']['program']['categories'];
        $this->assertCount(2, $cats);
        // Sorted descending by amount
        $this->assertSame('Food & Supplies',    $cats[0]['name']);
        $this->assertSame(500.0,                $cats[0]['amount']);
        $this->assertSame('Venue & Facilities', $cats[1]['name']);
        $this->assertSame(300.0,                $cats[1]['amount']);
    }

    public function test_service_compare_mode_adds_prior_totals_and_deltas(): void
    {
        // Prior period: $400 total
        $this->tx($this->programCat, 400, '2026-03-15');
        // Current period: $1000 total
        $this->tx($this->programCat, 1000, '2026-04-15');

        $svc  = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(
            Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'),
            Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'),
        );

        $this->assertSame(400.0,  $data['prior_total']);
        $this->assertSame(1000.0, $data['total']);
        $this->assertSame(400.0,  $data['by_function']['program']['prior_total']);
        $this->assertEqualsWithDelta(1.5, $data['by_function']['program']['delta'], 0.001);
    }

    public function test_service_compare_mode_includes_prior_program_ratio(): void
    {
        // Prior: 80% program
        $this->tx($this->programCat, 800, '2026-03-15');
        $this->tx($this->mgmtCat,    200, '2026-03-15');
        // Current: 50% program
        $this->tx($this->programCat, 500, '2026-04-15');
        $this->tx($this->mgmtCat,    500, '2026-04-15');

        $svc = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(
            Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'),
            Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'),
        );

        $this->assertEqualsWithDelta(0.50, $data['program_ratio'], 0.001);
        $this->assertEqualsWithDelta(0.80, $data['prior_program_ratio'], 0.001);
    }

    public function test_service_handles_zero_expenses_gracefully(): void
    {
        $svc  = app(FinanceReportService::class);
        $data = $svc->functionalExpenses(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'));

        $this->assertSame(0.0, $data['total']);
        $this->assertSame(0.0, $data['program_ratio']);
        $this->assertSame(['No completed expenses recorded for this period.'], $data['insights']);
    }

    // ─── HTTP — screen ───────────────────────────────────────────────────────

    public function test_screen_renders_for_admin(): void
    {
        $this->tx($this->programCat, 1000);

        $this->actingAs($this->admin)
             ->get('/finance/reports/functional-expenses')
             ->assertOk()
             ->assertSeeText('Statement of Functional Expenses')
             ->assertSeeText('Program Ratio');
    }

    public function test_hub_marks_functional_expenses_as_live(): void
    {
        $this->actingAs($this->admin)
             ->get('/finance/reports')
             ->assertOk()
             ->assertSeeText('Statement of Functional Expenses');
    }

    // ─── HTTP — exports ──────────────────────────────────────────────────────

    public function test_csv_export_has_bom_and_correct_columns(): void
    {
        $this->tx($this->programCat,    1000);
        $this->tx($this->mgmtCat,        500);

        // Custom range that covers the seeded April transactions regardless
        // of test-runner clock — preset names like 'this_month' depend on
        // today's date and would skip April when the suite runs in May.
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/functional-expenses/csv?period=custom&from=2026-04-01&to=2026-04-30');

        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'CSV should start with UTF-8 BOM');
        $this->assertStringContainsString('Statement of Functional Expenses', $body);
        $this->assertStringContainsString('Total Expenses', $body);
        $this->assertStringContainsString('Program Ratio', $body);
        $this->assertStringContainsString('Food & Supplies', $body);
        $this->assertStringContainsString('Administrative', $body);
        $this->assertStringContainsString('GRAND TOTAL', $body);
    }

    public function test_print_export_renders_branded_html(): void
    {
        $this->tx($this->programCat, 1000);

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/functional-expenses/print');

        $response->assertOk();
        $this->assertStringContainsString('Statement of Functional Expenses', $response->getContent());
    }

    public function test_unauth_blocked_on_screen_and_exports(): void
    {
        // Tier 2 — finance_reports.view + .export gates apply.
        $intakeRole = Role::create(['name' => 'INTAKE', 'display_name' => 'Intake', 'description' => '']);
        RolePermission::create(['role_id' => $intakeRole->id, 'permission' => 'households.view']);
        $intake = User::create([
            'name' => 'I', 'email' => 'i@test.local', 'password' => bcrypt('p'),
            'role_id' => $intakeRole->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($intake)->get('/finance/reports/functional-expenses')->assertForbidden();
        $this->actingAs($intake)->get('/finance/reports/functional-expenses/csv')->assertForbidden();

        // finance_reports.view alone should still 403 on /csv (export gate).
        $viewerRole = Role::create(['name' => 'V', 'display_name' => 'V', 'description' => '']);
        RolePermission::create(['role_id' => $viewerRole->id, 'permission' => 'finance_reports.view']);
        $viewer = User::create([
            'name' => 'V', 'email' => 'v@test.local', 'password' => bcrypt('p'),
            'role_id' => $viewerRole->id, 'email_verified_at' => now(),
        ]);
        $this->actingAs($viewer)->get('/finance/reports/functional-expenses/csv')->assertForbidden();
        $this->actingAs($viewer)->get('/finance/reports/functional-expenses')->assertOk();
    }
}
