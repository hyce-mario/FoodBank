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
 * Phase 7.3.a — Donor / Source Analysis — service computations +
 * filter pass-through + print/PDF/CSV exports + hub catalog flip.
 */
class FinanceReportDonorAnalysisTest extends TestCase
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

    private function income(FinanceCategory $cat, float $amount, string $date, ?string $source = 'Test Donor', string $title = 'Gift', string $status = 'completed'): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => 'income',
            'title'            => $title,
            'category_id'      => $cat->id,
            'amount'           => $amount,
            'transaction_date' => $date,
            'source_or_payee'  => $source,
            'status'           => $status,
        ]);
    }

    public function test_unauth_redirected_to_login(): void
    {
        foreach (['', '/print', '/pdf', '/csv'] as $suffix) {
            $this->get('/finance/reports/donor-analysis' . $suffix)->assertRedirect('/login');
        }
    }

    public function test_screen_shows_kpi_strip_top_donors_and_insights(): void
    {
        $this->income($this->donations, 5000, '2026-04-05', 'Acme Foundation');
        $this->income($this->donations, 1000, '2026-04-08', 'Jane Smith');
        $this->income($this->grants,    7500, '2026-04-12', 'Springfield CF');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis')
                         ->assertOk();

        $response->assertSeeText('$13,500.00');           // total raised KPI
        $response->assertSeeText('Springfield CF');       // largest = first
        $response->assertSeeText('Acme Foundation');
        $response->assertSeeText('Jane Smith');
        $response->assertSeeText('Top donor: Springfield CF');
    }

    public function test_donors_sorted_by_total_descending_with_alphabetical_tiebreak(): void
    {
        $this->income($this->donations, 1000, '2026-04-05', 'Charlie');
        $this->income($this->donations, 1000, '2026-04-06', 'Alpha');
        $this->income($this->donations, 1000, '2026-04-07', 'Bravo');
        $this->income($this->donations, 5000, '2026-04-08', 'Top Donor');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis')
                         ->assertOk();

        $html = $response->getContent();
        // Top Donor first, then Alpha < Bravo < Charlie alphabetically
        $posTop     = strpos($html, 'Top Donor');
        $posAlpha   = strpos($html, 'Alpha');
        $posBravo   = strpos($html, 'Bravo');
        $posCharlie = strpos($html, 'Charlie');

        $this->assertNotFalse($posTop);
        $this->assertLessThan($posAlpha, $posTop);
        $this->assertLessThan($posBravo, $posAlpha);
        $this->assertLessThan($posCharlie, $posBravo);
    }

    public function test_anonymous_groups_empty_and_whitespace_sources(): void
    {
        // The schema requires source_or_payee NOT NULL, so the only
        // anonymous paths in practice are empty string + whitespace-only.
        // The service still trims + null-coalesces defensively.
        $this->income($this->donations,  200, '2026-04-06', '',      'Empty source');
        $this->income($this->donations,  300, '2026-04-07', '   ',   'Whitespace source');
        $this->income($this->donations, 1000, '2026-04-08', 'Named', 'Named gift');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis')
                         ->assertOk();

        $response->assertSeeText('(Anonymous)');
        $response->assertSeeText('Named');
        // Anonymous total = 500
        $response->assertSeeText('$500.00');
    }

    public function test_only_completed_status_counts(): void
    {
        $this->income($this->donations, 1000, '2026-04-05', 'A', 'A1', 'completed');
        $this->income($this->donations, 9999, '2026-04-06', 'B', 'B1', 'pending');
        $this->income($this->donations, 8888, '2026-04-07', 'C', 'C1', 'cancelled');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis')
                         ->assertOk();

        $response->assertSeeText('$1,000.00');
        $response->assertDontSeeText('$9,999.00');
        $response->assertDontSeeText('$8,888.00');
    }

    public function test_category_filter_narrows_results(): void
    {
        $this->income($this->donations, 1000, '2026-04-05', 'DonateOnly');
        $this->income($this->grants,    7500, '2026-04-10', 'GrantOnly');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis?category_id=' . $this->donations->id)
                         ->assertOk();

        $response->assertSee('DonateOnly');
        $response->assertDontSee('GrantOnly');
    }

    public function test_source_filter_searches_donor_name(): void
    {
        $this->income($this->donations, 500, '2026-04-05', 'Springfield Foundation');
        $this->income($this->donations, 200, '2026-04-08', 'Random Pledger');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis?source=Springfield')
                         ->assertOk();

        $response->assertSee('Springfield Foundation');
        $response->assertDontSee('Random Pledger');
    }

    public function test_compare_attaches_delta_lapsed_new_and_retention(): void
    {
        // Current period (April)
        $this->income($this->donations, 1000, '2026-04-05', 'Alpha');     // returning, +0%
        $this->income($this->donations, 1500, '2026-04-08', 'Bravo');     // returning, +50%
        $this->income($this->donations,  500, '2026-04-10', 'NewbieDonor'); // new

        // Prior period (March)
        $this->income($this->donations, 1000, '2026-03-05', 'Alpha');     // returns, same
        $this->income($this->donations, 1000, '2026-03-08', 'Bravo');     // returns, +50%
        $this->income($this->donations,  800, '2026-03-12', 'OldGuard');  // lapsed

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis?compare=prior')
                         ->assertOk();

        $response->assertSeeText('March 2026');               // compare label

        // Delta visible — Bravo went 1000 → 1500 = +50%
        $response->assertSeeText('+50%');

        // Lapsed callout
        $response->assertSeeText('1 Lapsed Donor');
        $response->assertSee('OldGuard');

        // New donor flagged
        $response->assertSee('NewbieDonor');
        $response->assertSee('NEW');

        // Retention rate: Alpha + Bravo retained out of 3 prior → 67%
        $response->assertSeeText('67%');
    }

    public function test_top_10_cap_with_show_all_link(): void
    {
        // 12 distinct donors so 2 are off the top-10
        for ($i = 1; $i <= 12; $i++) {
            $this->income($this->donations, 1000 - $i, '2026-04-05', 'Donor' . $i);
        }

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis')
                         ->assertOk();

        $response->assertSeeText('(of 12)');           // count surfaced in header
        $response->assertSeeText('Show all 12');       // toggle link
        $response->assertSee('Donor1');                 // top-10 visible
        $response->assertSee('Donor11');                // hidden row still in HTML (Alpine x-show)
    }

    public function test_sparkline_window_anchored_at_period_to_not_today(): void
    {
        // Today is 2026-04-15 (test setUp). Run a report for "Last Year" (2025).
        // Sparkline should show 2025-01..2025-12, NOT 2025-05..2026-04.
        // We assert this by giving the donor a gift in March 2025 — which is
        // INSIDE the 2025 sparkline window but OUTSIDE the rolling-12 window
        // (March 2025 is more than 12 months before April 2026? Actually Mar
        // 2025 is 13 months before Apr 2026, so it's outside rolling-12.)
        // If sparkline anchored at $to = 2025-12-31, March 2025 → bucket 2.
        $this->income($this->donations, 5000, '2025-03-15', 'YearlyGiver');
        $this->income($this->donations, 2000, '2025-12-20', 'YearlyGiver'); // confirms in-period

        $service = app(\App\Services\FinanceReportService::class);
        $data = $service->donorAnalysis(
            Carbon::parse('2025-01-01')->startOfDay(),
            Carbon::parse('2025-12-31')->endOfDay(),
        );

        $this->assertNotEmpty($data['donors']);
        $row = $data['donors'][0];
        $this->assertSame('YearlyGiver', $row['name']);
        $this->assertCount(12, $row['sparkline']);

        // March 2025 → index 2 (Jan=0, Feb=1, Mar=2). 5000 should land there.
        $this->assertEqualsWithDelta(5000.0, $row['sparkline'][2], 0.01);

        // December 2025 → index 11.
        $this->assertEqualsWithDelta(2000.0, $row['sparkline'][11], 0.01);
    }

    public function test_pdf_returns_pdf_with_magic_bytes_and_filename(): void
    {
        $this->income($this->donations, 1000, '2026-04-05');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis/pdf')
                         ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('donor-analysis-', $response->headers->get('Content-Disposition'));
    }

    public function test_print_renders_branded_html_with_sparkline_svg(): void
    {
        $this->income($this->donations, 1000, '2026-04-05', 'Alpha');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis/print')
                         ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('DONOR / SOURCE ANALYSIS', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    public function test_csv_streams_with_bom_metadata_and_full_donor_list(): void
    {
        $this->income($this->donations, 5000, '2026-04-05', 'Acme');
        $this->income($this->grants,    7500, '2026-04-12', 'Springfield CF');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/donor-analysis/csv')
                         ->assertOk();

        $body = $response->streamedContent();
        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));

        $stripped = substr($body, 3);
        $this->assertStringContainsString('Donor / Source Analysis', $stripped);
        $this->assertStringContainsString('Period,"April 2026"', $stripped);
        $this->assertStringContainsString('CONTRIBUTORS', $stripped);
        $this->assertStringContainsString('Acme', $stripped);
        $this->assertStringContainsString('Springfield CF', $stripped);
        $this->assertStringContainsString('5000.00', $stripped);
        $this->assertStringContainsString('7500.00', $stripped);
    }

    public function test_csv_includes_lapsed_section_when_comparing(): void
    {
        $this->income($this->donations, 1000, '2026-04-05', 'Active');
        $this->income($this->donations,  800, '2026-03-10', 'OldGuard'); // prior, lapses

        $body = $this->actingAs($this->admin)
                     ->get('/finance/reports/donor-analysis/csv?compare=prior')
                     ->assertOk()
                     ->streamedContent();

        $stripped = substr($body, 3);
        $this->assertStringContainsString('LAPSED DONORS', $stripped);
        $this->assertStringContainsString('OldGuard', $stripped);
        $this->assertStringContainsString('800.00', $stripped);
    }

    public function test_hub_marks_donor_analysis_as_live(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports')
                         ->assertOk();

        $response->assertSee(route('finance.reports.donor-analysis'));
        $response->assertSee('Donor / Source Analysis');
    }
}
