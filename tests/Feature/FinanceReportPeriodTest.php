<?php

namespace Tests\Feature;

use App\Services\FinanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 7.1.a — Period resolution contract for the finance reports
 * suite. Pinning these so all 11 reports stay in lockstep on what
 * "This Quarter" / "YTD" / etc. mean, and so the URL contract
 * (?period=last_month&compare=prior) doesn't drift over time.
 */
class FinanceReportPeriodTest extends TestCase
{
    private FinanceReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FinanceReportService();
        // Freeze "today" so date math in tests is deterministic.
        Carbon::setTestNow(Carbon::parse('2026-04-15'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_resolves_to_this_month(): void
    {
        $r = $this->service->resolvePeriod(new Request());
        $this->assertSame('this_month', $r['preset']);
        $this->assertSame('2026-04-01', $r['from']->toDateString());
        $this->assertSame('2026-04-30', $r['to']->toDateString());
        $this->assertNull($r['compare_from']);
        $this->assertNull($r['compare_to']);
    }

    public function test_last_month_preset(): void
    {
        $r = $this->service->resolvePeriod(new Request(['period' => 'last_month']));
        $this->assertSame('2026-03-01', $r['from']->toDateString());
        $this->assertSame('2026-03-31', $r['to']->toDateString());
    }

    public function test_this_quarter_preset(): void
    {
        $r = $this->service->resolvePeriod(new Request(['period' => 'this_quarter']));
        // April 15 is in Q2 (Apr-Jun)
        $this->assertSame('2026-04-01', $r['from']->toDateString());
        $this->assertSame('2026-06-30', $r['to']->toDateString());
    }

    public function test_last_quarter_preset(): void
    {
        $r = $this->service->resolvePeriod(new Request(['period' => 'last_quarter']));
        $this->assertSame('2026-01-01', $r['from']->toDateString());
        $this->assertSame('2026-03-31', $r['to']->toDateString());
    }

    public function test_ytd_preset(): void
    {
        $r = $this->service->resolvePeriod(new Request(['period' => 'ytd']));
        $this->assertSame('2026-01-01', $r['from']->toDateString());
        $this->assertSame('2026-04-15', $r['to']->toDateString());
    }

    public function test_last_year_preset(): void
    {
        $r = $this->service->resolvePeriod(new Request(['period' => 'last_year']));
        $this->assertSame('2025-01-01', $r['from']->toDateString());
        $this->assertSame('2025-12-31', $r['to']->toDateString());
    }

    public function test_last_12_months_preset(): void
    {
        $r = $this->service->resolvePeriod(new Request(['period' => 'last_12_months']));
        $this->assertSame('2025-04-15', $r['from']->toDateString());
        $this->assertSame('2026-04-15', $r['to']->toDateString());
    }

    public function test_custom_range_preset(): void
    {
        $r = $this->service->resolvePeriod(new Request([
            'period' => 'custom',
            'from'   => '2026-02-01',
            'to'     => '2026-02-28',
        ]));
        $this->assertSame('custom', $r['preset']);
        $this->assertSame('2026-02-01', $r['from']->toDateString());
        $this->assertSame('2026-02-28', $r['to']->toDateString());
    }

    public function test_custom_range_with_dates_reversed_is_corrected(): void
    {
        // User typed end-date in the start picker by mistake; service
        // swaps them rather than 500ing or returning empty data.
        $r = $this->service->resolvePeriod(new Request([
            'period' => 'custom',
            'from'   => '2026-02-28',
            'to'     => '2026-02-01',
        ]));
        $this->assertSame('2026-02-01', $r['from']->toDateString());
        $this->assertSame('2026-02-28', $r['to']->toDateString());
    }

    public function test_custom_range_with_garbage_falls_back_to_this_month(): void
    {
        $r = $this->service->resolvePeriod(new Request([
            'period' => 'custom',
            'from'   => 'not-a-date',
            'to'     => 'also-bad',
        ]));
        $this->assertSame('this_month', $r['preset']);
    }

    public function test_unknown_preset_falls_back_to_this_month(): void
    {
        $r = $this->service->resolvePeriod(new Request(['period' => 'made_up_preset']));
        $this->assertSame('this_month', $r['preset']);
    }

    public function test_compare_prior_for_this_month_returns_last_month(): void
    {
        $r = $this->service->resolvePeriod(new Request([
            'period'  => 'this_month',
            'compare' => 'prior',
        ]));
        $this->assertSame('2026-03-01', $r['compare_from']->toDateString());
        $this->assertSame('2026-03-31', $r['compare_to']->toDateString());
    }

    public function test_compare_prior_for_this_quarter_returns_last_quarter(): void
    {
        $r = $this->service->resolvePeriod(new Request([
            'period'  => 'this_quarter',
            'compare' => 'prior',
        ]));
        $this->assertSame('2026-01-01', $r['compare_from']->toDateString());
        $this->assertSame('2026-03-31', $r['compare_to']->toDateString());
    }

    public function test_compare_prior_for_custom_steps_back_by_range_duration(): void
    {
        // Apr 1 - Apr 10 (10 days) → prior should be Mar 22 - Mar 31
        $r = $this->service->resolvePeriod(new Request([
            'period'  => 'custom',
            'from'    => '2026-04-01',
            'to'      => '2026-04-10',
            'compare' => 'prior',
        ]));
        $this->assertSame('2026-03-22', $r['compare_from']->toDateString());
        $this->assertSame('2026-03-31', $r['compare_to']->toDateString());
    }

    public function test_label_for_full_calendar_month_is_just_month_year(): void
    {
        $r = $this->service->resolvePeriod(new Request(['period' => 'this_month']));
        $this->assertSame('April 2026', $r['label']);
    }

    public function test_label_for_full_calendar_year_is_just_year(): void
    {
        $r = $this->service->resolvePeriod(new Request(['period' => 'last_year']));
        $this->assertSame('2025', $r['label']);
    }

    public function test_label_for_partial_range_uses_compact_format(): void
    {
        $r = $this->service->resolvePeriod(new Request([
            'period' => 'custom',
            'from'   => '2026-04-05',
            'to'     => '2026-04-12',
        ]));
        $this->assertSame('Apr 5 – Apr 12, 2026', $r['label']);
    }

    public function test_usd_helper_formats_with_thousands_and_two_decimals(): void
    {
        $this->assertSame('$1,234.56', FinanceReportService::usd(1234.56));
        $this->assertSame('$0.00',     FinanceReportService::usd(0));
        $this->assertSame('-$1,234.56', FinanceReportService::usd(-1234.56));
        $this->assertSame('$12,345.00', FinanceReportService::usd(12345));
    }

    public function test_color_for_returns_stable_palette_choice(): void
    {
        $a1 = FinanceReportService::colorFor('Donations');
        $a2 = FinanceReportService::colorFor('Donations');
        $b  = FinanceReportService::colorFor('Food Supplies');

        // Same input → same output every time
        $this->assertSame($a1, $a2);
        // Different inputs CAN collide (palette is 8 colors) but the
        // assertion that matters is determinism — checked above.
        $this->assertContains($a1, FinanceReportService::PALETTE);
        $this->assertContains($b,  FinanceReportService::PALETTE);
    }

    public function test_compare_flag_via_boolean_request_input_is_recognized(): void
    {
        // The period filter checkbox sends ?compare=prior; the boolean()
        // helper also accepts compare=1 / compare=true. Both paths must
        // enable comparison.
        foreach (['prior', '1', 'true'] as $val) {
            $r = $this->service->resolvePeriod(new Request([
                'period'  => 'this_month',
                'compare' => $val,
            ]));
            $this->assertNotNull($r['compare_from'], "compare={$val} did not enable comparison");
        }
    }
}
