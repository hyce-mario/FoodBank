<?php

namespace Tests\Feature;

use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7.3.d — Category Trend Report. Validates monthly bucketing,
 * top-6 + Other rollup, direction toggle (income / expense / both),
 * top grower / top shrinker calc, line-chart rendering, and exports.
 */
class FinanceReportCategoryTrendTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FinanceCategory $donations;
    private FinanceCategory $grants;
    private FinanceCategory $supplies;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-15'));

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('p'), 'role_id' => $adminRole->id,
            'email_verified_at' => now(),
        ]);

        $this->donations = FinanceCategory::create(['name' => 'Donations', 'type' => 'income',  'is_active' => true]);
        $this->grants    = FinanceCategory::create(['name' => 'Grants',    'type' => 'income',  'is_active' => true]);
        $this->supplies  = FinanceCategory::create(['name' => 'Supplies',  'type' => 'expense', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tx(FinanceCategory $cat, float $amount, string $date, string $type = 'income', string $status = 'completed'): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => $type,
            'title'            => 'Test',
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => 'X',
            'status'           => $status,
        ]);
    }

    public function test_unauth_redirected_to_login(): void
    {
        foreach (['', '/print', '/pdf', '/csv'] as $suffix) {
            $this->get('/finance/reports/category-trend' . $suffix)->assertRedirect('/login');
        }
    }

    public function test_default_period_is_last_12_months(): void
    {
        // Today is 2026-04-15 → last 12 months = 2025-04-15 → 2026-04-15
        $this->tx($this->donations, 1000, '2025-05-10');
        $this->tx($this->donations, 2000, '2026-04-10');
        $this->tx($this->donations, 9999, '2024-01-01'); // outside

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/category-trend')
                         ->assertOk();

        $response->assertSeeText('$3,000.00');           // 1000 + 2000 (within window)
        $response->assertDontSeeText('$9,999.00');
    }

    public function test_screen_renders_kpi_strip_with_grower_and_shrinker(): void
    {
        // Donations grows: $100 in Feb → $500 in Apr (+400%)
        $this->tx($this->donations, 100, '2026-02-05');
        $this->tx($this->donations, 500, '2026-04-05');

        // Grants shrinks: $1000 in Feb → $200 in Apr (-80%)
        $this->tx($this->grants, 1000, '2026-02-10');
        $this->tx($this->grants,  200, '2026-04-10');

        // Custom range spanning the data (Feb–Apr 2026); this_quarter
        // would be Q2 = Apr–Jun and exclude the February rows.
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/category-trend?period=custom&from=2026-02-01&to=2026-04-30')
                         ->assertOk();

        $response->assertSeeText('Top Grower');
        $response->assertSeeText('Donations');
        $response->assertSeeText('Top Shrinker');
        $response->assertSeeText('Grants');
        $response->assertSeeText('▲ 400%');     // delta on the grower
        $response->assertSeeText('▼ 80%');      // delta on the shrinker
    }

    public function test_only_completed_status_counts(): void
    {
        $this->tx($this->donations, 1000, '2026-04-05', 'income', 'completed');
        $this->tx($this->donations, 9999, '2026-04-06', 'income', 'pending');
        $this->tx($this->donations, 8888, '2026-04-07', 'income', 'cancelled');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/category-trend')
                         ->assertOk();

        $response->assertSeeText('$1,000.00');
        $response->assertDontSeeText('$9,999.00');
        $response->assertDontSeeText('$8,888.00');
    }

    public function test_direction_income_excludes_expenses(): void
    {
        $this->tx($this->donations, 1000, '2026-04-05', 'income');
        $this->tx($this->supplies,   500, '2026-04-08', 'expense');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/category-trend?direction=income')
                         ->assertOk();

        $response->assertSee('Donations');
        $response->assertDontSee('Supplies');
    }

    public function test_direction_expense_includes_only_expense_categories(): void
    {
        $this->tx($this->donations, 1000, '2026-04-05', 'income');
        $this->tx($this->supplies,   500, '2026-04-08', 'expense');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/category-trend?direction=expense')
                         ->assertOk();

        $response->assertSee('Supplies');
        $response->assertDontSee('Donations');
    }

    public function test_direction_both_includes_income_and_expense(): void
    {
        $this->tx($this->donations, 1000, '2026-04-05', 'income');
        $this->tx($this->supplies,   500, '2026-04-08', 'expense');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/category-trend?direction=both')
                         ->assertOk();

        $response->assertSee('Donations');
        $response->assertSee('Supplies');
    }

    public function test_top_6_plus_other_rollup_when_more_than_6_categories(): void
    {
        // Create 8 income categories with descending totals
        $cats = [];
        for ($i = 1; $i <= 8; $i++) {
            $cats[$i] = FinanceCategory::create([
                'name' => 'Cat' . $i, 'type' => 'income', 'is_active' => true,
            ]);
            $this->tx($cats[$i], 1000 * (10 - $i), '2026-04-05', 'income'); // $9k, $8k, ..., $2k
        }

        $service = app(\App\Services\FinanceReportService::class);
        $data = $service->categoryTrend(
            Carbon::parse('2026-04-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
            'income',
        );

        // 6 named series + 1 "Other" rollup
        $this->assertCount(7, $data['series']);
        $names = array_column($data['series'], 'name');
        $this->assertSame('Cat1', $names[0]);  // largest first
        $this->assertSame('Cat6', $names[5]);
        $this->assertStringContainsString('Other', $names[6]);
        $this->assertStringContainsString('2 categories', $names[6]); // Cat7 + Cat8 collapsed
    }

    public function test_monthly_buckets_align_with_period(): void
    {
        // 3-month period — Feb / Mar / Apr 2026
        $this->tx($this->donations, 100, '2026-02-15');
        $this->tx($this->donations, 200, '2026-03-15');
        $this->tx($this->donations, 300, '2026-04-15');

        $service = app(\App\Services\FinanceReportService::class);
        $data = $service->categoryTrend(
            Carbon::parse('2026-02-01')->startOfDay(),
            Carbon::parse('2026-04-30')->endOfDay(),
            'income',
        );

        $this->assertSame(['2026-02', '2026-03', '2026-04'], $data['months']);
        $this->assertCount(1, $data['series']);
        $this->assertSame([100.0, 200.0, 300.0], $data['series'][0]['monthly']);
        $this->assertSame(600.0, $data['series'][0]['total']);
        $this->assertEqualsWithDelta(2.0, $data['series'][0]['delta'], 0.01); // (300-100)/100 = 2.0
    }

    public function test_pdf_returns_pdf_in_landscape(): void
    {
        $this->tx($this->donations, 1000, '2026-04-05');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/category-trend/pdf')
                         ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('category-trend-income-', $response->headers->get('Content-Disposition'));
    }

    public function test_print_renders_branded_html_with_line_chart(): void
    {
        $this->tx($this->donations, 1000, '2026-04-05');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/category-trend/print')
                         ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('CATEGORY TREND', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    public function test_csv_streams_with_bom_and_wide_format(): void
    {
        $this->tx($this->donations, 100, '2026-02-15');
        $this->tx($this->donations, 200, '2026-04-15');

        $body = $this->actingAs($this->admin)
                     ->get('/finance/reports/category-trend/csv?period=custom&from=2026-02-01&to=2026-04-30')
                     ->assertOk()
                     ->streamedContent();

        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));
        $stripped = substr($body, 3);

        $this->assertStringContainsString('Category Trend Report', $stripped);
        $this->assertStringContainsString('Direction,Income', $stripped);
        $this->assertStringContainsString('Donations', $stripped);
        $this->assertStringContainsString('100.00', $stripped);
        $this->assertStringContainsString('200.00', $stripped);
        $this->assertStringContainsString('TOTAL', $stripped);
    }

    public function test_hub_marks_category_trend_as_live(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports')
                         ->assertOk();

        $response->assertSee(route('finance.reports.category-trend'));
        $response->assertSee('Category Trend Report');
    }
}
