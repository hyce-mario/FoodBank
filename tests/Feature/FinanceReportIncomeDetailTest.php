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
 * Phase 7.2.a — Income Detail Report — service computations + HTTP
 * rendering + filter pass-through + print/PDF/CSV exports.
 */
class FinanceReportIncomeDetailTest extends TestCase
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

        $this->donations = FinanceCategory::create(['name' => 'Donations',     'type' => 'income',  'is_active' => true]);
        $this->grants    = FinanceCategory::create(['name' => 'Grants',        'type' => 'income',  'is_active' => true]);
        $this->supplies  = FinanceCategory::create(['name' => 'Food Supplies', 'type' => 'expense', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function income(FinanceCategory $cat, float $amount, string $date, string $source = 'Test Donor', string $title = 'Test Donation', string $status = 'completed', ?int $eventId = null): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => 'income',
            'title'            => $title,
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => $source,
            'status'           => $status,
            'event_id'         => $eventId,
        ]);
    }

    public function test_unauth_redirected_to_login(): void
    {
        foreach (['', '/print', '/pdf', '/csv'] as $suffix) {
            $this->get('/finance/reports/income-detail' . $suffix)->assertRedirect('/login');
        }
    }

    public function test_screen_shows_kpi_strip_table_and_insights(): void
    {
        $this->income($this->donations, 5000, '2026-04-05', 'Acme Foundation', 'Q2 Grant Match');
        $this->income($this->donations, 1000, '2026-04-08', 'Jane Smith',      'Monthly Pledge');
        $this->income($this->grants,    7500, '2026-04-12', 'Springfield CF',  'Spring Grant');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/income-detail')
                         ->assertOk();

        $response->assertSeeText('$13,500.00');             // total
        $response->assertSeeText('Q2 Grant Match');         // row title
        $response->assertSeeText('Monthly Pledge');
        $response->assertSeeText('Spring Grant');
        $response->assertSee('Springfield CF');             // top donor

        // Top donor insight
        $response->assertSeeText('Top donor: Springfield CF');
        $response->assertSeeText('Largest single transaction');
    }

    public function test_only_completed_status_counts_by_default(): void
    {
        $this->income($this->donations, 1000, '2026-04-05', 'A', 'A1', 'completed');
        $this->income($this->donations, 9999, '2026-04-06', 'B', 'B1', 'pending');
        $this->income($this->donations, 8888, '2026-04-07', 'C', 'C1', 'cancelled');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/income-detail')
                         ->assertOk();

        $response->assertSeeText('$1,000.00');
        $response->assertDontSeeText('$9,999.00');
        $response->assertDontSeeText('$8,888.00');
    }

    public function test_category_filter_narrows_results(): void
    {
        $this->income($this->donations, 1000, '2026-04-05', 'A', 'Donation A');
        $this->income($this->grants,    7500, '2026-04-10', 'B', 'Grant B');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/income-detail?category_id=' . $this->donations->id)
                         ->assertOk();

        $response->assertSee('Donation A');
        $response->assertDontSee('Grant B');
    }

    public function test_source_filter_searches_donor(): void
    {
        $this->income($this->donations, 500, '2026-04-05', 'Springfield Foundation', 'Foundation Grant');
        $this->income($this->donations, 200, '2026-04-08', 'Anon Individual',         'Pledge');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/income-detail?source=Springfield')
                         ->assertOk();

        $response->assertSee('Foundation Grant');
        $response->assertDontSee('Pledge');
    }

    public function test_event_filter_narrows_results(): void
    {
        $event = Event::create([
            'name' => 'Spring Distro', 'date' => now()->toDateString(),
            'status' => 'past', 'lanes' => 1,
        ]);
        $this->income($this->donations, 1000, '2026-04-05', 'A', 'Event-linked', 'completed', $event->id);
        $this->income($this->donations, 2000, '2026-04-10', 'B', 'Generic');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/income-detail?event_id=' . $event->id)
                         ->assertOk();

        $response->assertSee('Event-linked');
        $response->assertDontSee('Generic');
    }

    public function test_compare_period_renders_side_by_side(): void
    {
        $this->income($this->donations, 1000, '2026-04-05');  // April
        $this->income($this->donations,  800, '2026-03-15');  // March

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/income-detail?compare=prior')
                         ->assertOk();

        $response->assertSeeText('March 2026');
        $response->assertSeeText('+25.0% vs prior'); // (1000 - 800)/800
    }

    // ── PDF ──────────────────────────────────────────────────────────────

    public function test_pdf_returns_pdf_with_magic_bytes_and_proper_filename(): void
    {
        $this->income($this->donations, 1000, '2026-04-05');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/income-detail/pdf')
                         ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('income-detail-', $response->headers->get('Content-Disposition'));
    }

    // ── Print ────────────────────────────────────────────────────────────

    public function test_print_renders_branded_html_with_chart(): void
    {
        $this->income($this->donations, 1000, '2026-04-05');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/income-detail/print')
                         ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('INCOME DETAIL REPORT', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    // ── CSV ──────────────────────────────────────────────────────────────

    public function test_csv_streams_with_bom_metadata_rows_and_category_rollup(): void
    {
        $this->income($this->donations, 1000, '2026-04-05', 'Acme', 'Q2 Match');
        $this->income($this->grants,    7500, '2026-04-12', 'CF',   'Spring Grant');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/income-detail/csv')
                         ->assertOk();

        $body = $response->streamedContent();
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));

        $stripped = substr($body, 3);
        $this->assertStringContainsString('Income Detail Report', $stripped);
        $this->assertStringContainsString('Period,"April 2026"', $stripped);
        $this->assertStringContainsString('TRANSACTIONS', $stripped);
        $this->assertStringContainsString('Q2 Match', $stripped);
        $this->assertStringContainsString('Spring Grant', $stripped);
        $this->assertStringContainsString('1000.00', $stripped);
        $this->assertStringContainsString('7500.00', $stripped);
        $this->assertStringContainsString('BY CATEGORY', $stripped);
        $this->assertStringContainsString('TOTAL', $stripped);
        $this->assertStringContainsString('8500.00', $stripped); // grand total
    }

    public function test_csv_filter_pass_through(): void
    {
        $this->income($this->donations, 1000, '2026-04-05', 'A', 'Match');
        $this->income($this->donations,  500, '2026-03-05', 'B', 'Old');

        $body = $this->actingAs($this->admin)
                     ->get('/finance/reports/income-detail/csv?period=last_month')
                     ->assertOk()
                     ->streamedContent();

        $stripped = substr($body, 3);
        $this->assertStringContainsString('Old', $stripped);
        $this->assertStringNotContainsString('Match', $stripped);
    }

    // ── Hub ──────────────────────────────────────────────────────────────

    public function test_hub_marks_income_detail_as_live(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports')
                         ->assertOk();

        // The Live cards on the hub are wrapped in <a href="..."> while
        // Coming-Soon cards use <div>. So a route URL appearing in the
        // hub HTML means the card is Live (and clickable).
        $response->assertSee(route('finance.reports.income-detail'));
        $response->assertSee('Income Detail Report');
    }
}
