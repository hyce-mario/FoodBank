<?php

namespace Tests\Feature;

use App\Models\Pledge;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\FinanceReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7.4.c — Pledge / AR Aging report.
 *
 * Pin contracts:
 *   - Outstanding (open + partial) pledges only count toward aging
 *   - Aging buckets: current / 1-30 / 31-60 / 61-90 / 90+
 *   - Top-donors rollup sums per source_or_payee
 *   - HTTP screen + CSV + Tier 2 finance_reports.{view,export} gates
 */
class FinanceReportPledgeAgingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'A', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name' => 'A', 'email' => 'a@test.local', 'password' => bcrypt('p'),
            'role_id' => $adminRole->id, 'email_verified_at' => now(),
        ]);
    }

    private function pledge(array $overrides = []): Pledge
    {
        return Pledge::create(array_merge([
            'source_or_payee' => 'ACME',
            'amount'          => 1000,
            'pledged_at'      => '2026-01-01',
            'expected_at'     => '2026-04-01',
            'status'          => 'open',
        ], $overrides));
    }

    // ─── Service ─────────────────────────────────────────────────────────────

    public function test_service_buckets_pledges_by_age(): void
    {
        $asOf = Carbon::parse('2026-05-05');
        // Days overdue calculated from expected_at relative to as_of:
        //   future date  → current
        //   1-30 days    → 1_30
        //   31-60 days   → 31_60
        //   61-90 days   → 61_90
        //   91+ days     → over_90
        $this->pledge(['source_or_payee' => 'A', 'expected_at' => '2026-06-01']); // current (future)
        $this->pledge(['source_or_payee' => 'B', 'expected_at' => '2026-04-20']); // 15 days overdue → 1_30
        $this->pledge(['source_or_payee' => 'C', 'expected_at' => '2026-03-20']); // ~46 days → 31_60
        $this->pledge(['source_or_payee' => 'D', 'expected_at' => '2026-02-20']); // ~74 days → 61_90
        $this->pledge(['source_or_payee' => 'E', 'expected_at' => '2025-12-01']); // ~155 days → over_90

        $svc  = app(FinanceReportService::class);
        $data = $svc->pledgeAging($asOf);

        $this->assertSame(1, $data['buckets']['current']['count']);
        $this->assertSame(1, $data['buckets']['1_30']['count']);
        $this->assertSame(1, $data['buckets']['31_60']['count']);
        $this->assertSame(1, $data['buckets']['61_90']['count']);
        $this->assertSame(1, $data['buckets']['over_90']['count']);
        $this->assertSame(5, $data['count']);
        $this->assertSame(5000.0, $data['total']);
    }

    public function test_service_excludes_fulfilled_and_written_off(): void
    {
        $asOf = Carbon::parse('2026-05-05');
        $this->pledge(['source_or_payee' => 'Open',     'status' => 'open',        'expected_at' => '2026-06-01']);
        $this->pledge(['source_or_payee' => 'Fulfilled','status' => 'fulfilled',   'expected_at' => '2026-06-01']);
        $this->pledge(['source_or_payee' => 'WO',       'status' => 'written_off', 'expected_at' => '2026-06-01']);

        $svc  = app(FinanceReportService::class);
        $data = $svc->pledgeAging($asOf);

        $this->assertSame(1, $data['count'], 'Only open + partial count toward aging');
    }

    public function test_service_includes_partial_status(): void
    {
        $asOf = Carbon::parse('2026-05-05');
        $this->pledge(['source_or_payee' => 'P', 'status' => 'partial', 'expected_at' => '2026-06-01']);

        $svc  = app(FinanceReportService::class);
        $data = $svc->pledgeAging($asOf);

        $this->assertSame(1, $data['count']);
    }

    public function test_service_top_donors_rolls_up_by_source(): void
    {
        $asOf = Carbon::parse('2026-05-05');
        $this->pledge(['source_or_payee' => 'ACME', 'amount' => 1000, 'expected_at' => '2026-06-01']);
        $this->pledge(['source_or_payee' => 'ACME', 'amount' => 2000, 'expected_at' => '2026-07-01']);
        $this->pledge(['source_or_payee' => 'XYZ',  'amount' => 500,  'expected_at' => '2026-06-15']);

        $svc  = app(FinanceReportService::class);
        $data = $svc->pledgeAging($asOf);

        $this->assertSame('ACME', $data['top_donors'][0]['name']);
        $this->assertSame(3000.0, $data['top_donors'][0]['total']);
        $this->assertSame(2,      $data['top_donors'][0]['count']);
    }

    public function test_service_handles_no_pledges_gracefully(): void
    {
        $svc  = app(FinanceReportService::class);
        $data = $svc->pledgeAging();

        $this->assertSame(0,   $data['count']);
        $this->assertSame(0.0, $data['total']);
        $this->assertSame(['No outstanding pledges. AR is clean.'], $data['insights']);
    }

    // ─── HTTP ────────────────────────────────────────────────────────────────

    public function test_screen_renders_for_admin(): void
    {
        $this->pledge();
        $this->actingAs($this->admin)
             ->get('/finance/reports/pledge-aging')
             ->assertOk()
             ->assertSeeText('Pledge / AR Aging');
    }

    public function test_csv_export_has_bom_and_columns(): void
    {
        $this->pledge(['source_or_payee' => 'ACME', 'amount' => 1500, 'expected_at' => '2026-06-01']);

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/pledge-aging/csv?as_of=2026-05-05');
        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Pledge / AR Aging', $body);
        $this->assertStringContainsString('ACME', $body);
        $this->assertStringContainsString('GRAND TOTAL', $body);
    }

    public function test_hub_marks_pledge_aging_live(): void
    {
        $this->actingAs($this->admin)
             ->get('/finance/reports')
             ->assertOk()
             ->assertSeeText('Pledge / AR Aging');
    }

    public function test_unauth_blocked(): void
    {
        $intakeRole = Role::create(['name' => 'INTAKE', 'display_name' => 'I', 'description' => '']);
        RolePermission::create(['role_id' => $intakeRole->id, 'permission' => 'households.view']);
        $intake = User::create([
            'name' => 'I', 'email' => 'i@test.local', 'password' => bcrypt('p'),
            'role_id' => $intakeRole->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($intake)->get('/finance/reports/pledge-aging')->assertForbidden();
    }
}
