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
 * Phase 7.2.c — General Ledger — chronological list of every
 * transaction with running-balance column. Auditor's landing page.
 */
class FinanceReportGeneralLedgerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FinanceCategory $donations;
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

        $this->donations = FinanceCategory::create(['name' => 'Donations',     'type' => 'income',  'is_active' => true]);
        $this->supplies  = FinanceCategory::create(['name' => 'Food Supplies', 'type' => 'expense', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tx(string $type, FinanceCategory $cat, float $amount, string $date, string $title = 'T', string $status = 'completed'): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => $type,
            'title'            => $title,
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => 'Test',
            'status'           => $status,
        ]);
    }

    public function test_unauth_redirected_to_login(): void
    {
        foreach (['', '/print', '/pdf', '/csv'] as $suffix) {
            $this->get('/finance/reports/general-ledger' . $suffix)->assertRedirect('/login');
        }
    }

    public function test_screen_lists_transactions_chronologically_with_running_balance(): void
    {
        $this->tx('income',  $this->donations, 1000, '2026-04-05', 'Apr5Donation');
        $this->tx('expense', $this->supplies,   300, '2026-04-08', 'Apr8Expense');
        $this->tx('income',  $this->donations,  500, '2026-04-12', 'Apr12Donation');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/general-ledger')
                         ->assertOk();

        $response->assertSee('Apr5Donation');
        $response->assertSee('Apr8Expense');
        $response->assertSee('Apr12Donation');

        // KPI totals: $1500 in, $300 out, +$1200 net, $1200 closing
        $response->assertSeeText('$1,500.00');
        $response->assertSeeText('$300.00');
        $response->assertSeeText('+$1,200.00');
        // Closing balance row
        $response->assertSeeText('Closing Balance');
    }

    public function test_pending_and_cancelled_rows_listed_but_not_in_running_balance(): void
    {
        $this->tx('income',  $this->donations, 1000, '2026-04-05', 'CompletedRow');
        $this->tx('income',  $this->donations, 9999, '2026-04-06', 'PendingRowXYZ',   'pending');
        $this->tx('income',  $this->donations, 8888, '2026-04-07', 'CancelledRowXYZ', 'cancelled');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/general-ledger')
                         ->assertOk();

        // All three rows appear in the ledger (including their amounts —
        // auditors want to see pending + cancelled rows; the running
        // balance is what excludes them, not the row visibility).
        $response->assertSee('CompletedRow');
        $response->assertSee('PendingRowXYZ');
        $response->assertSee('CancelledRowXYZ');

        // Totals exclude pending + cancelled. Total Inflow = $1,000 only —
        // not $10,999 (which would be 1000 + 9999) and not $19,887.
        $response->assertSeeText('$1,000.00');
        $response->assertDontSeeText('$10,999.00');
        $response->assertDontSeeText('$19,887.00');

        // Insights mention pending watch
        $response->assertSeeText('1 pending transaction');
    }

    public function test_type_filter_narrows_to_income_only(): void
    {
        $this->tx('income',  $this->donations, 1000, '2026-04-05', 'IncomeRow');
        $this->tx('expense', $this->supplies,   500, '2026-04-08', 'ExpenseRow');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/general-ledger?type=income')
                         ->assertOk();

        $response->assertSee('IncomeRow');
        $response->assertDontSee('ExpenseRow');
    }

    public function test_status_filter_narrows_to_completed_only(): void
    {
        $this->tx('income', $this->donations, 1000, '2026-04-05', 'CompletedXyz', 'completed');
        $this->tx('income', $this->donations, 5000, '2026-04-06', 'PendingXyz',   'pending');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/general-ledger?status=completed')
                         ->assertOk();

        $response->assertSee('CompletedXyz');
        $response->assertDontSee('PendingXyz');
    }

    public function test_pdf_returns_pdf_with_proper_filename(): void
    {
        $this->tx('income', $this->donations, 1000, '2026-04-05');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/general-ledger/pdf')
                         ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('general-ledger-', $response->headers->get('Content-Disposition'));
    }

    public function test_print_renders_branded_html(): void
    {
        $this->tx('income', $this->donations, 1000, '2026-04-05');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/general-ledger/print')
                         ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('GENERAL LEDGER', $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    public function test_csv_streams_with_running_balance_column_and_signed_amounts(): void
    {
        $this->tx('income',  $this->donations, 1000, '2026-04-05', 'IncomeOne');
        $this->tx('expense', $this->supplies,   300, '2026-04-08', 'ExpenseOne');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/general-ledger/csv')
                         ->assertOk();

        $body = $response->streamedContent();
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));

        $stripped = substr($body, 3);
        // PHP fputcsv quotes any field containing a space — so the
        // metadata band labels appear with surrounding quotes.
        $this->assertStringContainsString('General Ledger', $stripped);
        $this->assertStringContainsString('"Total Inflow",1000.00',  $stripped);
        $this->assertStringContainsString('"Total Outflow",300.00',  $stripped);
        $this->assertStringContainsString('"Net Change",700.00',     $stripped);

        // Header row contains Running Balance column
        $this->assertStringContainsString('Running Balance', $stripped);

        // Income row → +1000.00, expense row → -300.00 (signed)
        $this->assertStringContainsString('1000.00', $stripped);
        $this->assertStringContainsString('-300.00', $stripped);

        // Running balance: 1000 after income, 700 after expense
        $this->assertStringContainsString('700.00', $stripped);
    }

    public function test_hub_marks_general_ledger_as_live(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports')
                         ->assertOk();

        $response->assertSee(route('finance.reports.general-ledger'));
        $response->assertSee('General Ledger');
    }
}
