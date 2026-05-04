<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7.1.e–f — Statement of Activities (P&L) — service computations
 * + HTTP rendering + print/PDF/CSV exports.
 */
class FinanceReportStatementOfActivitiesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FinanceCategory $donations;
    private FinanceCategory $grants;
    private FinanceCategory $supplies;
    private FinanceCategory $rent;

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

        $this->donations = FinanceCategory::create(['name' => 'Donations',    'type' => 'income',  'is_active' => true]);
        $this->grants    = FinanceCategory::create(['name' => 'Grants',       'type' => 'income',  'is_active' => true]);
        $this->supplies  = FinanceCategory::create(['name' => 'Food Supplies','type' => 'expense', 'is_active' => true]);
        $this->rent      = FinanceCategory::create(['name' => 'Rent',         'type' => 'expense', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tx(string $type, FinanceCategory $cat, float $amount, string $date, string $status = 'completed', string $title = 'Test'): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => $type,
            'title'            => $title,
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => $type === 'income' ? 'Donor' : 'Vendor',
            'status'           => $status,
        ]);
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    public function test_unauth_redirected_to_login_for_all_endpoints(): void
    {
        foreach (['', '/print', '/pdf', '/csv'] as $suffix) {
            $this->get('/finance/reports/statement-of-activities' . $suffix)
                 ->assertRedirect('/login');
        }
    }

    // ─── HTTP render (screen) ─────────────────────────────────────────────────

    public function test_screen_renders_kpi_strip_and_donut_legend(): void
    {
        $this->tx('income',  $this->donations, 7500, '2026-04-05');
        $this->tx('income',  $this->grants,    5000, '2026-04-10');
        $this->tx('expense', $this->supplies,  5200, '2026-04-12');
        $this->tx('expense', $this->rent,      2500, '2026-04-13');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/statement-of-activities')
                         ->assertOk();

        // KPI strip totals
        $response->assertSeeText('$12,500.00');  // income
        $response->assertSeeText('$7,700.00');   // expenses
        $response->assertSeeText('$4,800.00');   // net change

        // Detail rows
        $response->assertSee('Donations');
        $response->assertSee('Grants');
        $response->assertSee('Food Supplies');
        $response->assertSee('Rent');

        // Insights bullets present
        $response->assertSeeText('Donations was the largest income source');
        $response->assertSeeText('Food Supplies was the largest expense');
    }

    public function test_pending_and_cancelled_transactions_excluded_from_statement(): void
    {
        $this->tx('income',  $this->donations, 1000, '2026-04-05');
        $this->tx('income',  $this->donations, 9999, '2026-04-06', 'pending');
        $this->tx('income',  $this->donations, 8888, '2026-04-07', 'cancelled');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/statement-of-activities')
                         ->assertOk();

        // Only the $1,000 completed transaction counts toward the
        // Statement of Activities totals.
        $response->assertSeeText('$1,000.00');
        $response->assertDontSeeText('$9,999.00');
        $response->assertDontSeeText('$8,888.00');
    }

    public function test_compare_prior_period_renders_side_by_side_columns(): void
    {
        // April income
        $this->tx('income', $this->donations, 1000, '2026-04-05');
        // March income
        $this->tx('income', $this->donations, 800, '2026-03-15');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/statement-of-activities?compare=prior')
                         ->assertOk();

        $response->assertSeeText('March 2026');         // prior period label
        $response->assertSeeText('Side-by-side');       // table sub-header
        $response->assertSeeText('Income is up 25%');   // insights bullet
    }

    public function test_empty_period_shows_helpful_insight(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/statement-of-activities')
                         ->assertOk();

        $response->assertSeeText('No completed transactions were recorded for this period.');
    }

    // ─── Hub ──────────────────────────────────────────────────────────────────

    public function test_hub_lists_all_eleven_reports_with_one_live(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports')
                         ->assertOk();

        $response->assertSee('Statement of Activities');
        $response->assertSee('Income Detail Report');
        $response->assertSee('Expense Detail Report');
        $response->assertSee('Donor / Source Analysis');
        $response->assertSee('Vendor / Payee Analysis');
        $response->assertSee('Per-Event P&amp;L', false);
        $response->assertSee('Category Trend Report');
        $response->assertSee('General Ledger');
        $response->assertSee('Statement of Functional Expenses');
        $response->assertSee('Budget vs. Actual');
        $response->assertSee('Pledge / AR Aging');

        // Live badge for SoA, Coming Soon for the other 10
        $response->assertSee('Live');
        $response->assertSee('Coming soon');
    }

    // ─── Print export ─────────────────────────────────────────────────────────

    public function test_print_renders_branded_html_with_chart(): void
    {
        $this->tx('income', $this->donations, 1000, '2026-04-05');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/statement-of-activities/print')
                         ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('STATEMENT OF ACTIVITIES', $html);
        // SVG chart embedded inline
        $this->assertStringContainsString('<svg', $html);
        // Auto-print fires
        $this->assertStringContainsString('window.print()', $html);
    }

    // ─── PDF export ───────────────────────────────────────────────────────────

    public function test_pdf_returns_pdf_content_type_with_pdf_magic_bytes(): void
    {
        $this->tx('income', $this->donations, 1000, '2026-04-05');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/statement-of-activities/pdf')
                         ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('statement-of-activities-', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    // ─── CSV export ───────────────────────────────────────────────────────────

    public function test_csv_streams_with_utf8_bom_revenue_and_expense_sections(): void
    {
        $this->tx('income',  $this->donations, 7500, '2026-04-05');
        $this->tx('expense', $this->supplies,  5200, '2026-04-12');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/statement-of-activities/csv')
                         ->assertOk();

        $body = $response->streamedContent();
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));

        $stripped = substr($body, 3);
        $this->assertStringContainsString('Statement of Activities', $stripped);
        $this->assertStringContainsString('REVENUE', $stripped);
        $this->assertStringContainsString('Donations', $stripped);
        $this->assertStringContainsString('7500.00', $stripped);
        $this->assertStringContainsString('Total Revenue', $stripped);
        $this->assertStringContainsString('EXPENSES', $stripped);
        $this->assertStringContainsString('Food Supplies', $stripped);
        $this->assertStringContainsString('5200.00', $stripped);
        $this->assertStringContainsString('CHANGE IN NET ASSETS', $stripped);
        $this->assertStringContainsString('2300.00', $stripped); // 7500 - 5200
    }

    public function test_csv_includes_compare_columns_when_compare_flag_set(): void
    {
        $this->tx('income', $this->donations, 1000, '2026-04-05');
        $this->tx('income', $this->donations,  800, '2026-03-15');

        $body = $this->actingAs($this->admin)
                     ->get('/finance/reports/statement-of-activities/csv?compare=prior')
                     ->assertOk()
                     ->streamedContent();

        $stripped = substr($body, 3);
        $this->assertStringContainsString('Compare to', $stripped);
        $this->assertStringContainsString('Prior Period', $stripped);
        $this->assertStringContainsString('25.0%', $stripped);
    }

    public function test_csv_period_filter_passes_through(): void
    {
        $this->tx('income', $this->donations, 1000, '2026-04-05'); // April
        $this->tx('income', $this->donations,  500, '2026-03-05'); // March

        $body = $this->actingAs($this->admin)
                     ->get('/finance/reports/statement-of-activities/csv?period=last_month')
                     ->assertOk()
                     ->streamedContent();

        // Last month = March → only the March $500 row counts
        $stripped = substr($body, 3);
        $this->assertStringContainsString('500.00', $stripped);
        $this->assertStringNotContainsString('1000.00', $stripped);
    }
}
