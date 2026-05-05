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
 * Phase 7.2.b — Expense Detail Report — same shape as Income Detail
 * but for expense-side transactions. Vendor/payee instead of donor.
 */
class FinanceReportExpenseDetailTest extends TestCase
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

    private function expense(FinanceCategory $cat, float $amount, string $date, string $payee = 'Test Vendor', string $title = 'Test Expense', string $status = 'completed', ?int $eventId = null): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => 'expense',
            'title'            => $title,
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => $payee,
            'status'           => $status,
            'event_id'         => $eventId,
        ]);
    }

    public function test_unauth_redirected_to_login(): void
    {
        foreach (['', '/print', '/pdf', '/csv'] as $suffix) {
            $this->get('/finance/reports/expense-detail' . $suffix)->assertRedirect('/login');
        }
    }

    public function test_screen_shows_kpi_strip_and_top_vendor(): void
    {
        $this->expense($this->supplies, 5000, '2026-04-05', 'Costco',           'Pantry Restock');
        $this->expense($this->supplies, 1200, '2026-04-08', 'Sams Club',        'Diapers');
        $this->expense($this->rent,     2500, '2026-04-01', 'Property LLC',     'Monthly Rent');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/expense-detail')
                         ->assertOk();

        $response->assertSeeText('$8,700.00');
        $response->assertSeeText('Pantry Restock');
        $response->assertSeeText('Diapers');
        $response->assertSeeText('Monthly Rent');
        // Top vendor by total = Costco at $5000
        $response->assertSee('Costco');
        $response->assertSeeText('Top Vendor');
        $response->assertSeeText('Top vendor: Costco');
    }

    public function test_income_transactions_excluded(): void
    {
        $this->expense($this->supplies, 1000, '2026-04-05', 'Costco', 'Real Expense');
        // An income tx in the same period — must NOT show up
        FinanceTransaction::create([
            'transaction_type' => 'income',
            'title' => 'A Donation',
            'category_id' => $this->donations->id,
            'amount' => 9999,
            'transaction_date' => '2026-04-05',
            'source_or_payee' => 'Donor',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/expense-detail')
                         ->assertOk();

        $response->assertSee('Real Expense');
        $response->assertDontSee('A Donation');
        $response->assertDontSeeText('$9,999.00');
    }

    public function test_status_filter_all_includes_pending(): void
    {
        // Use unique titles to avoid collision with dropdown option text
        $this->expense($this->supplies, 1000, '2026-04-05', 'V', 'CompletedXyz', 'completed');
        $this->expense($this->supplies, 5000, '2026-04-06', 'V', 'PendingXyz',   'pending');

        // Default — only completed
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/expense-detail')
                         ->assertOk();
        $response->assertSee('CompletedXyz');
        $response->assertDontSee('PendingXyz');

        // status=all — both included
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/expense-detail?status=all')
                         ->assertOk();
        $response->assertSee('CompletedXyz');
        $response->assertSee('PendingXyz');
    }

    public function test_payee_filter_searches_vendor(): void
    {
        $this->expense($this->supplies, 1000, '2026-04-05', 'Costco Wholesale', 'Pantry');
        $this->expense($this->supplies,  500, '2026-04-08', 'Local Grocer',     'Misc');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/expense-detail?source=Costco')
                         ->assertOk();

        $response->assertSee('Pantry');
        $response->assertDontSee('Misc');
    }

    public function test_pdf_returns_pdf_with_proper_filename(): void
    {
        $this->expense($this->supplies, 1000, '2026-04-05');
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/expense-detail/pdf')
                         ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('expense-detail-', $response->headers->get('Content-Disposition'));
    }

    public function test_print_renders_branded_html(): void
    {
        $this->expense($this->supplies, 1000, '2026-04-05');
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/expense-detail/print')
                         ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('EXPENSE DETAIL REPORT', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    public function test_csv_streams_with_payee_column_label(): void
    {
        $this->expense($this->supplies, 1000, '2026-04-05', 'Costco', 'Pantry');

        $body = $this->actingAs($this->admin)
                     ->get('/finance/reports/expense-detail/csv')
                     ->assertOk()
                     ->streamedContent();

        $stripped = substr($body, 3);
        $this->assertStringContainsString('Expense Detail Report', $stripped);
        $this->assertStringContainsString('Payee', $stripped); // column header
        $this->assertStringContainsString('Costco', $stripped);
        $this->assertStringContainsString('Pantry', $stripped);
        $this->assertStringContainsString('1000.00', $stripped);
    }

    public function test_hub_marks_expense_detail_as_live(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports')
                         ->assertOk();

        $response->assertSee(route('finance.reports.expense-detail'));
        $response->assertSee('Expense Detail Report');
    }
}
