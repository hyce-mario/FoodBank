<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Event;
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
 * Phase 7.4.b — Budget vs. Actual / Variance report.
 *
 * Pin contracts:
 *   - Service buckets budgets + actuals per category
 *   - Variance = actual - budget; status flips by direction
 *     (expense over budget = bad; income over budget = good)
 *   - Pending + cancelled transactions excluded from actuals
 *   - Categories with actuals but no budget surface as 0-budget rows
 *   - Direction filter: expense / income / both
 *   - event_id filter narrows to budgets+actuals tied to that event
 *   - HTTP screen + CSV + Tier 2 finance_reports gates
 */
class FinanceReportBudgetVsActualTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FinanceCategory $foodCat;
    private FinanceCategory $venueCat;
    private FinanceCategory $donationsCat;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'A', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name' => 'A', 'email' => 'a@test.local', 'password' => bcrypt('p'),
            'role_id' => $adminRole->id, 'email_verified_at' => now(),
        ]);

        $this->foodCat      = FinanceCategory::create(['name' => 'Food',      'type' => 'expense', 'is_active' => true, 'function_classification' => 'program']);
        $this->venueCat     = FinanceCategory::create(['name' => 'Venue',     'type' => 'expense', 'is_active' => true, 'function_classification' => 'program']);
        $this->donationsCat = FinanceCategory::create(['name' => 'Donations', 'type' => 'income',  'is_active' => true, 'function_classification' => 'program']);
    }

    private function budget(FinanceCategory $cat, float $amount, string $start = '2026-04-01', string $end = '2026-04-30', ?int $eventId = null): Budget
    {
        return Budget::create([
            'category_id'  => $cat->id,
            'period_type'  => 'monthly',
            'period_start' => $start,
            'period_end'   => $end,
            'amount'       => $amount,
            'event_id'     => $eventId,
        ]);
    }

    private function tx(FinanceCategory $cat, float $amount, string $date = '2026-04-15', string $status = 'completed', ?int $eventId = null): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => $cat->type,
            'title'            => 'Tx',
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => 'X',
            'status'           => $status,
            'event_id'         => $eventId,
        ]);
    }

    // ─── Service: shape ──────────────────────────────────────────────────────

    public function test_service_buckets_budgets_and_actuals_per_category(): void
    {
        $this->budget($this->foodCat,  1000);
        $this->budget($this->venueCat, 500);
        $this->tx($this->foodCat,      900);
        $this->tx($this->venueCat,     600);

        $svc  = app(FinanceReportService::class);
        $data = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'expense');

        $this->assertSame(1500.0, $data['totals']['budget']);
        $this->assertSame(1500.0, $data['totals']['actual']);
        $this->assertSame(0.0,    $data['totals']['variance']);
    }

    public function test_service_computes_variance_correctly(): void
    {
        $this->budget($this->foodCat, 1000);
        $this->tx($this->foodCat,     1200);

        $svc  = app(FinanceReportService::class);
        $data = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'expense');

        $foodRow = collect($data['rows'])->firstWhere('category_name', 'Food');
        $this->assertSame(200.0, $foodRow['variance']);
        $this->assertEqualsWithDelta(0.20, $foodRow['variance_pct'], 0.001);
        $this->assertSame('over', $foodRow['status']);
    }

    public function test_service_marks_under_budget_expense_status(): void
    {
        $this->budget($this->foodCat, 1000);
        $this->tx($this->foodCat,     800);

        $svc  = app(FinanceReportService::class);
        $data = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'expense');
        $r = collect($data['rows'])->firstWhere('category_name', 'Food');

        $this->assertSame(-200.0, $r['variance']);
        $this->assertSame('under', $r['status']);
    }

    public function test_service_status_semantics_flip_for_income_direction(): void
    {
        // Income: actual > budget = 'over' = good (over plan)
        $this->budget($this->donationsCat, 1000);
        $this->tx($this->donationsCat,     1500);

        $svc  = app(FinanceReportService::class);
        $data = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'income');

        $r = collect($data['rows'])->firstWhere('category_name', 'Donations');
        $this->assertSame(500.0, $r['variance']);
        $this->assertSame('over', $r['status'], 'Income: actual > budget = over plan');

        // And: income shortfall = under
        FinanceTransaction::query()->delete();
        $this->tx($this->donationsCat, 600);

        $data = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'income');
        $r = collect($data['rows'])->firstWhere('category_name', 'Donations');
        $this->assertSame(-400.0, $r['variance']);
        $this->assertSame('under', $r['status']);
    }

    public function test_service_excludes_pending_and_cancelled_actuals(): void
    {
        $this->budget($this->foodCat, 1000);
        $this->tx($this->foodCat, 100, '2026-04-15', 'completed');
        $this->tx($this->foodCat, 200, '2026-04-15', 'pending');
        $this->tx($this->foodCat, 300, '2026-04-15', 'cancelled');

        $svc  = app(FinanceReportService::class);
        $data = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'expense');

        $r = collect($data['rows'])->firstWhere('category_name', 'Food');
        $this->assertSame(100.0, $r['actual'], 'Only completed transactions count toward actuals');
    }

    public function test_service_surfaces_unbudgeted_categories_with_zero_budget(): void
    {
        // Food has actuals but NO budget — should still appear with budget=0
        $this->tx($this->foodCat, 500);

        $svc  = app(FinanceReportService::class);
        $data = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'expense');

        $r = collect($data['rows'])->firstWhere('category_name', 'Food');
        $this->assertNotNull($r);
        $this->assertSame(0.0,   $r['budget']);
        $this->assertSame(500.0, $r['actual']);
        $this->assertSame(500.0, $r['variance']);
        $this->assertNull($r['variance_pct'], 'variance_pct is null when budget is 0');
    }

    public function test_service_direction_filter_respects_category_type(): void
    {
        $this->budget($this->foodCat,      1000);
        $this->budget($this->donationsCat, 2000);
        $this->tx($this->foodCat,           800);
        $this->tx($this->donationsCat,     1500);

        $svc = app(FinanceReportService::class);

        $expense = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'expense');
        $this->assertSame(1000.0, $expense['totals']['budget']);
        $this->assertSame(800.0,  $expense['totals']['actual']);

        $income = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'income');
        $this->assertSame(2000.0, $income['totals']['budget']);
        $this->assertSame(1500.0, $income['totals']['actual']);

        $both = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'both');
        $this->assertSame(3000.0, $both['totals']['budget']);
        $this->assertSame(2300.0, $both['totals']['actual']);
    }

    public function test_service_event_filter_narrows_scope(): void
    {
        $event = Event::create(['name' => 'Picnic', 'date' => '2026-04-15', 'lanes' => 1]);

        // Org-wide budget + actual
        $this->budget($this->foodCat, 1000);
        $this->tx($this->foodCat,      500);
        // Event-specific
        $this->budget($this->foodCat, 300, '2026-04-01', '2026-04-30', $event->id);
        $this->tx($this->foodCat,      200, '2026-04-15', 'completed', $event->id);

        $svc = app(FinanceReportService::class);

        $allScopes = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'expense');
        $this->assertSame(1300.0, $allScopes['totals']['budget']);
        $this->assertSame(700.0,  $allScopes['totals']['actual']);

        $eventOnly = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'expense', $event->id);
        $this->assertSame(300.0, $eventOnly['totals']['budget']);
        $this->assertSame(200.0, $eventOnly['totals']['actual']);
    }

    public function test_service_handles_no_budgets_no_actuals_gracefully(): void
    {
        $svc  = app(FinanceReportService::class);
        $data = $svc->budgetVsActual(Carbon::parse('2026-04-01'), Carbon::parse('2026-04-30'), 'expense');

        $this->assertEmpty($data['rows']);
        $this->assertSame(0.0, $data['totals']['budget']);
        $this->assertSame(0.0, $data['totals']['actual']);
        $this->assertNotEmpty($data['insights']);
    }

    // ─── HTTP ────────────────────────────────────────────────────────────────

    public function test_screen_renders_for_admin(): void
    {
        $this->budget($this->foodCat, 1000);
        $this->tx($this->foodCat,     800);

        $this->actingAs($this->admin)
             ->get('/finance/reports/budget-vs-actual?period=custom&from=2026-04-01&to=2026-04-30')
             ->assertOk()
             ->assertSeeText('Budget vs. Actual');
    }

    public function test_csv_export_has_bom_and_columns(): void
    {
        $this->budget($this->foodCat, 1000);
        $this->tx($this->foodCat,     1200);

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/budget-vs-actual/csv?period=custom&from=2026-04-01&to=2026-04-30');
        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Budget vs. Actual', $body);
        $this->assertStringContainsString('Food', $body);
        $this->assertStringContainsString('TOTAL', $body);
    }

    public function test_hub_marks_budget_vs_actual_live(): void
    {
        $this->actingAs($this->admin)
             ->get('/finance/reports')
             ->assertOk()
             ->assertSeeText('Budget vs. Actual');
    }

    public function test_unauth_blocked_at_screen(): void
    {
        $intakeRole = Role::create(['name' => 'INTAKE', 'display_name' => 'I', 'description' => '']);
        RolePermission::create(['role_id' => $intakeRole->id, 'permission' => 'households.view']);
        $intake = User::create([
            'name' => 'I', 'email' => 'i@test.local', 'password' => bcrypt('p'),
            'role_id' => $intakeRole->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($intake)->get('/finance/reports/budget-vs-actual')->assertForbidden();
    }
}
