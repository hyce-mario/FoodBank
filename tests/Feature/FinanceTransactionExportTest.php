<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5.13 — Finance transactions list-level Print + CSV exports.
 *
 * Pins:
 *   - auth gate: unauth → /login (admin route group)
 *   - print: branded HTML, contains transaction titles + KPI strip
 *   - csv: UTF-8 BOM, header + data rows, accountant-friendly columns
 *   - active filters (type / category / status / date range / search)
 *     apply to the export so the exported set matches the screen
 *
 * No PDF — explicit module scope decision (CSV is the universal
 * accountant import format; PDF wasn't in the scope).
 */
class FinanceTransactionExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FinanceCategory $donations;
    private FinanceCategory $supplies;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);

        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $adminRole->id,
            'email_verified_at' => now(),
        ]);

        $this->donations = FinanceCategory::create([
            'name' => 'Donations', 'type' => 'income', 'is_active' => true,
        ]);
        $this->supplies = FinanceCategory::create([
            'name' => 'Food Supplies', 'type' => 'expense', 'is_active' => true,
        ]);
    }

    private function makeIncome(string $title, float $amount, array $extra = []): FinanceTransaction
    {
        return FinanceTransaction::create(array_merge([
            'transaction_type' => 'income',
            'title'            => $title,
            'category_id'      => $this->donations->id,
            'amount'           => $amount,
            'transaction_date' => now()->toDateString(),
            'source_or_payee'  => 'Test Donor',
            'status'           => 'completed',
        ], $extra));
    }

    private function makeExpense(string $title, float $amount, array $extra = []): FinanceTransaction
    {
        return FinanceTransaction::create(array_merge([
            'transaction_type' => 'expense',
            'title'            => $title,
            'category_id'      => $this->supplies->id,
            'amount'           => $amount,
            'transaction_date' => now()->toDateString(),
            'source_or_payee'  => 'Test Vendor',
            'status'           => 'completed',
        ], $extra));
    }

    // ─── Auth gate ────────────────────────────────────────────────────────────

    public function test_unauth_redirects_to_login_for_both_endpoints(): void
    {
        foreach (['print', 'csv'] as $kind) {
            $this->get(route("finance.transactions.export.{$kind}"))->assertRedirect('/login');
        }
    }

    // ─── Print ────────────────────────────────────────────────────────────────

    public function test_print_renders_branded_html_with_transactions_and_totals(): void
    {
        $this->makeIncome('Q2 Grant',         2500.00);
        $this->makeExpense('Costco Restock',  847.32);

        $response = $this->actingAs($this->admin)
                         ->get(route('finance.transactions.export.print'))
                         ->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('Finance Transaction Report', $html);
        $this->assertStringContainsString('Q2 Grant',        $html);
        $this->assertStringContainsString('Costco Restock',  $html);

        // KPI strip: filtered Income / Expenses / Net
        $this->assertStringContainsString('Income (filtered)',   $html);
        $this->assertStringContainsString('Expenses (filtered)', $html);
        $this->assertStringContainsString('Net (filtered)',      $html);
        $this->assertStringContainsString('$2,500.00', $html);
        $this->assertStringContainsString('$847.32',   $html);

        // Auto-print fires
        $this->assertStringContainsString('window.print()', $html);
    }

    public function test_print_filter_strip_surfaces_applied_filters(): void
    {
        $this->makeIncome('Q2 Grant', 2500.00);

        $html = $this->actingAs($this->admin)
                     ->get(route('finance.transactions.export.print', [
                         'type' => 'income',
                         'search' => 'Grant',
                     ]))
                     ->assertOk()
                     ->getContent();

        $this->assertStringContainsString('Filters applied:', $html);
        $this->assertStringContainsString('Type: Income', $html);
        $this->assertStringContainsString('Search: &quot;Grant&quot;', $html);
    }

    // ─── CSV ──────────────────────────────────────────────────────────────────

    public function test_csv_streams_with_utf8_bom_and_header_plus_data_rows(): void
    {
        $this->makeIncome('Q2 Grant',         2500.00);
        $this->makeExpense('Costco Restock',  847.32);

        $response = $this->actingAs($this->admin)
                         ->get(route('finance.transactions.export.csv'))
                         ->assertOk();

        $body = $response->streamedContent();

        // UTF-8 BOM
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));

        $rows = array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3))));
        $this->assertSame(
            ['Date', 'Type', 'Title', 'Category', 'Source / Payee',
             'Amount', 'Status', 'Payment Method', 'Reference', 'Event', 'Notes'],
            $rows[0],
        );

        $titles = collect(array_slice($rows, 1))->pluck(2)->all();
        $this->assertContains('Q2 Grant', $titles);
        $this->assertContains('Costco Restock', $titles);
    }

    public function test_csv_amounts_are_formatted_two_decimals_no_thousands(): void
    {
        $this->makeIncome('Big Grant', 12345.67);

        $body = $this->actingAs($this->admin)
                     ->get(route('finance.transactions.export.csv'))
                     ->assertOk()
                     ->streamedContent();

        $rows = array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3))));
        $row = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'Big Grant');
        $this->assertNotNull($row);
        // Amount column is index 5 — accountant-friendly formatting
        $this->assertSame('12345.67', $row[5]);
    }

    public function test_csv_filename_includes_transactions_prefix_and_date(): void
    {
        $disposition = $this->actingAs($this->admin)
                            ->get(route('finance.transactions.export.csv'))
                            ->assertOk()
                            ->headers->get('Content-Disposition');

        $this->assertStringContainsString('transactions-', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
    }

    // ─── Filters apply to exports ─────────────────────────────────────────────

    public function test_type_filter_applies_to_csv(): void
    {
        $this->makeIncome('Donation A',  500.00);
        $this->makeExpense('Vendor A',  200.00);

        $body = $this->actingAs($this->admin)
                     ->get(route('finance.transactions.export.csv', ['type' => 'income']))
                     ->assertOk()
                     ->streamedContent();

        $titles = collect(array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3)))))
            ->slice(1)
            ->pluck(2)
            ->all();

        $this->assertContains('Donation A', $titles);
        $this->assertNotContains('Vendor A', $titles);
    }

    public function test_category_filter_applies_to_csv(): void
    {
        $this->makeIncome('Donation A',  500.00);
        $this->makeExpense('Vendor A',  200.00);

        $body = $this->actingAs($this->admin)
                     ->get(route('finance.transactions.export.csv', ['category_id' => $this->supplies->id]))
                     ->assertOk()
                     ->streamedContent();

        $titles = collect(array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3)))))
            ->slice(1)
            ->pluck(2)
            ->all();

        $this->assertContains('Vendor A', $titles);
        $this->assertNotContains('Donation A', $titles);
    }

    public function test_search_filter_applies_to_csv(): void
    {
        $this->makeIncome('Q2 Grant', 2500.00);
        $this->makeIncome('Bake Sale', 320.00);

        $body = $this->actingAs($this->admin)
                     ->get(route('finance.transactions.export.csv', ['search' => 'Grant']))
                     ->assertOk()
                     ->streamedContent();

        $titles = collect(array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3)))))
            ->slice(1)
            ->pluck(2)
            ->all();

        $this->assertContains('Q2 Grant', $titles);
        $this->assertNotContains('Bake Sale', $titles);
    }

    public function test_date_range_filter_applies_to_csv(): void
    {
        // Older row outside the range
        $this->makeIncome('Old Donation',  100.00, ['transaction_date' => '2026-01-15']);
        // In-range row
        $this->makeIncome('Recent Donation', 200.00, ['transaction_date' => '2026-04-15']);

        $body = $this->actingAs($this->admin)
                     ->get(route('finance.transactions.export.csv', [
                         'date_from' => '2026-04-01',
                         'date_to'   => '2026-04-30',
                     ]))
                     ->assertOk()
                     ->streamedContent();

        $titles = collect(array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3)))))
            ->slice(1)
            ->pluck(2)
            ->all();

        $this->assertContains('Recent Donation', $titles);
        $this->assertNotContains('Old Donation', $titles);
    }

    public function test_event_name_appears_in_csv_event_column(): void
    {
        $event = Event::create([
            'name' => 'Spring Distribution', 'date' => now()->toDateString(),
            'status' => 'past', 'lanes' => 1,
        ]);
        $this->makeIncome('Event Donation', 250.00, ['event_id' => $event->id]);

        $body = $this->actingAs($this->admin)
                     ->get(route('finance.transactions.export.csv'))
                     ->assertOk()
                     ->streamedContent();

        $rows = array_map('str_getcsv', preg_split('/\R/', trim(substr($body, 3))));
        $row = collect($rows)->first(fn ($r) => ($r[2] ?? '') === 'Event Donation');
        $this->assertNotNull($row);
        // Event name column is index 9
        $this->assertSame('Spring Distribution', $row[9]);
    }
}
