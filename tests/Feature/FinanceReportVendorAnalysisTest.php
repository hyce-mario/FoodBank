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
 * Phase 7.3.b — Vendor / Payee Analysis. Engine is shared with Donor
 * Analysis (FinanceReportService::stakeholderAnalysis), so this suite
 * focuses on vendor-specific routing, expense-type isolation, the
 * relabelled KPIs ("Total Spent" / "Avg Payment"), CSV section names,
 * and the hub flip.
 */
class FinanceReportVendorAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FinanceCategory $supplies;
    private FinanceCategory $rent;
    private FinanceCategory $donations;

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

        $this->supplies  = FinanceCategory::create(['name' => 'Food Supplies', 'type' => 'expense', 'is_active' => true]);
        $this->rent      = FinanceCategory::create(['name' => 'Rent',          'type' => 'expense', 'is_active' => true]);
        $this->donations = FinanceCategory::create(['name' => 'Donations',     'type' => 'income',  'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function expense(FinanceCategory $cat, float $amount, string $date, string $payee = 'Test Vendor', string $title = 'Payment', string $status = 'completed'): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => 'expense',
            'title'            => $title,
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => $payee,
            'status'           => $status,
        ]);
    }

    private function income(FinanceCategory $cat, float $amount, string $date, string $source = 'Test Donor'): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => 'income',
            'title'            => 'Gift',
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => $source,
            'status'           => 'completed',
        ]);
    }

    public function test_unauth_redirected_to_login(): void
    {
        foreach (['', '/print', '/pdf', '/csv'] as $suffix) {
            $this->get('/finance/reports/vendor-analysis' . $suffix)->assertRedirect('/login');
        }
    }

    public function test_screen_relabels_kpis_for_expense_side(): void
    {
        $this->expense($this->supplies, 5000, '2026-04-05', 'Costco');
        $this->expense($this->rent,     2000, '2026-04-08', 'Landlord LLC');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/vendor-analysis')
                         ->assertOk();

        $response->assertSeeText('Total Spent');     // not "Total Raised"
        $response->assertSeeText('Avg Payment');     // not "Avg Gift"
        $response->assertSeeText('Costco');
        $response->assertSeeText('Landlord LLC');
        $response->assertSeeText('$7,000.00');       // total spent
        $response->assertSeeText('Top vendor: Costco');
    }

    public function test_only_expense_transactions_appear(): void
    {
        $this->expense($this->supplies, 5000, '2026-04-05', 'Costco');
        $this->income($this->donations,  9999, '2026-04-08', 'Acme Foundation');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/vendor-analysis')
                         ->assertOk();

        $response->assertSee('Costco');
        $response->assertDontSee('Acme Foundation');
        $response->assertDontSeeText('$9,999.00');
    }

    public function test_only_completed_status_counts(): void
    {
        $this->expense($this->supplies, 1000, '2026-04-05', 'A', 'A1', 'completed');
        $this->expense($this->supplies, 9999, '2026-04-06', 'B', 'B1', 'pending');
        $this->expense($this->supplies, 8888, '2026-04-07', 'C', 'C1', 'cancelled');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/vendor-analysis')
                         ->assertOk();

        $response->assertSeeText('$1,000.00');
        $response->assertDontSeeText('$9,999.00');
        $response->assertDontSeeText('$8,888.00');
    }

    public function test_category_filter_lists_only_expense_categories(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/vendor-analysis')
                         ->assertOk();

        $response->assertSee('Food Supplies');
        $response->assertSee('Rent');
        $response->assertDontSee('Donations</option>'); // income category not in dropdown
    }

    public function test_pdf_returns_pdf_with_vendor_filename(): void
    {
        $this->expense($this->supplies, 1000, '2026-04-05', 'Costco');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/vendor-analysis/pdf')
                         ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('vendor-analysis-', $response->headers->get('Content-Disposition'));
    }

    public function test_print_renders_branded_html_with_vendor_title(): void
    {
        $this->expense($this->supplies, 1000, '2026-04-05', 'Costco');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/vendor-analysis/print')
                         ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('VENDOR / PAYEE ANALYSIS', $html);
        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('Costco', $html);
    }

    public function test_csv_uses_vendor_section_label(): void
    {
        $this->expense($this->supplies, 5000, '2026-04-05', 'Costco');
        $this->expense($this->rent,     2000, '2026-04-12', 'Landlord LLC');

        $body = $this->actingAs($this->admin)
                     ->get('/finance/reports/vendor-analysis/csv')
                     ->assertOk()
                     ->streamedContent();

        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));
        $stripped = substr($body, 3);

        $this->assertStringContainsString('Vendor / Payee Analysis', $stripped);
        $this->assertStringContainsString('PAYEES', $stripped);            // not 'CONTRIBUTORS'
        $this->assertStringContainsString('Total Spent', $stripped);
        $this->assertStringContainsString('Costco', $stripped);
        $this->assertStringContainsString('Landlord LLC', $stripped);
    }

    public function test_compare_lapsed_vendors_uses_payment_phrasing(): void
    {
        $this->expense($this->supplies, 1000, '2026-04-05', 'CurrentVendor');
        $this->expense($this->rent,      800, '2026-03-10', 'OldVendor');   // lapses

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/vendor-analysis?compare=prior')
                         ->assertOk();

        $response->assertSeeText('1 Lapsed Vendor');
        $response->assertSee('OldVendor');
        // Insight bullet uses "been paid" not "given" for vendors
        $response->assertSeeText("haven't been paid this period");
    }

    public function test_hub_marks_vendor_analysis_as_live(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports')
                         ->assertOk();

        $response->assertSee(route('finance.reports.vendor-analysis'));
        $response->assertSee('Vendor / Payee Analysis');
    }
}
