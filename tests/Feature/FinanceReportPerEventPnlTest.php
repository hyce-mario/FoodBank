<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FinanceCategory;
use App\Models\FinanceTransaction;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 7.3.c — Per-Event P&L. Validates the bespoke event-picker
 * shape (no period filter), event-scoped finance aggregation,
 * cost-per-beneficiary calculation, and — critically — that
 * households/people served come from the visit_households SNAPSHOT
 * pivot (Phase 1.2.c), not live `households.household_size`. Editing
 * a household after the visit must NOT silently rewrite this report.
 */
class FinanceReportPerEventPnlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private FinanceCategory $donations;
    private FinanceCategory $supplies;
    private Event $event;

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
        $this->supplies  = FinanceCategory::create(['name' => 'Supplies',  'type' => 'expense', 'is_active' => true]);

        $this->event = Event::create([
            'name'   => 'Spring Distribution',
            'date'   => '2026-04-10',
            'status' => 'past',
            'lanes'  => 2,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeIncome(float $amount, ?int $eventId, string $title = 'Gift', string $status = 'completed'): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => 'income', 'title' => $title,
            'category_id' => $this->donations->id, 'amount' => $amount,
            'transaction_date' => '2026-04-08', 'source_or_payee' => 'X',
            'status' => $status, 'event_id' => $eventId,
        ]);
    }

    private function makeExpense(float $amount, ?int $eventId, string $title = 'Spend', string $status = 'completed'): FinanceTransaction
    {
        return FinanceTransaction::create([
            'transaction_type' => 'expense', 'title' => $title,
            'category_id' => $this->supplies->id, 'amount' => $amount,
            'transaction_date' => '2026-04-08', 'source_or_payee' => 'Vendor',
            'status' => $status, 'event_id' => $eventId,
        ]);
    }

    private function makeHousehold(int $size): Household
    {
        return Household::create([
            'household_number' => 'HH-' . uniqid(),
            'first_name' => 'Test', 'last_name' => 'Family',
            'household_size' => $size,
            'children_count' => 0, 'adults_count' => $size, 'seniors_count' => 0,
        ]);
    }

    /**
     * Attach a household to an exited visit at the event with the
     * given snapshot size baked in. Mirrors what the service layer
     * does in production (Phase 1.2.b) but expressed directly so
     * tests can vary the snapshot vs live values.
     */
    private function attachExitedVisit(Household $household, int $snapshotSize, int $eventId = null): Visit
    {
        $visit = Visit::create([
            'event_id'    => $eventId ?? $this->event->id,
            'lane'        => 1,
            'visit_status'=> 'exited',
            'start_time'  => '2026-04-10 09:00:00',
            'end_time'    => '2026-04-10 09:30:00',
            'exited_at'   => '2026-04-10 09:30:00',
            'served_bags' => 1,
        ]);
        DB::table('visit_households')->insert([
            'visit_id'      => $visit->id,
            'household_id'  => $household->id,
            'household_size'=> $snapshotSize,
            'children_count'=> 0,
            'adults_count'  => $snapshotSize,
            'seniors_count' => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        return $visit;
    }

    public function test_unauth_redirected_to_login(): void
    {
        foreach (['', '/print', '/pdf', '/csv'] as $suffix) {
            $this->get('/finance/reports/per-event-pnl' . $suffix . '?event_id=' . $this->event->id)
                 ->assertRedirect('/login');
        }
    }

    public function test_screen_shows_empty_state_when_no_event_selected(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/per-event-pnl')
                         ->assertOk();

        $response->assertSeeText('Pick an event from the dropdown above');
        $response->assertSee('Spring Distribution');           // event in dropdown
    }

    public function test_screen_renders_event_summary_when_event_selected(): void
    {
        $this->makeIncome(1000,  $this->event->id, 'Major Donation');
        $this->makeExpense( 600, $this->event->id, 'Food Cost');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/per-event-pnl?event_id=' . $this->event->id)
                         ->assertOk();

        $response->assertSeeText('Spring Distribution');
        $response->assertSeeText('$1,000.00');     // Income
        $response->assertSeeText('$600.00');       // Expense
        $response->assertSeeText('+$400.00');      // Net
        $response->assertSeeText('Major Donation');
        $response->assertSeeText('Food Cost');
    }

    public function test_only_event_scoped_transactions_appear(): void
    {
        $other = Event::create(['name' => 'Other Event', 'date' => '2026-04-11', 'status' => 'past', 'lanes' => 1]);

        $this->makeIncome(1000, $this->event->id, 'Tied to Spring');
        $this->makeIncome(2222, $other->id,       'Tied to Other');
        $this->makeIncome(3333, null,             'Unattached');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/per-event-pnl?event_id=' . $this->event->id)
                         ->assertOk();

        $response->assertSeeText('Tied to Spring');
        $response->assertDontSee('Tied to Other');
        $response->assertDontSee('Unattached');
        $response->assertSeeText('$1,000.00');
        $response->assertDontSeeText('$2,222.00');
    }

    public function test_only_completed_status_counts(): void
    {
        $this->makeIncome(1000, $this->event->id, 'OK', 'completed');
        $this->makeIncome(9999, $this->event->id, 'Pending', 'pending');
        $this->makeIncome(8888, $this->event->id, 'Cancelled', 'cancelled');

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/per-event-pnl?event_id=' . $this->event->id)
                         ->assertOk();

        $response->assertSeeText('$1,000.00');
        $response->assertDontSeeText('$9,999.00');
        $response->assertDontSeeText('$8,888.00');
    }

    public function test_households_and_people_served_from_snapshot_not_live(): void
    {
        // Critical Phase 1.2.c semantics: editing the household after
        // the event must NOT change historical reports.
        $h1 = $this->makeHousehold(4);   // live size at attach: 4
        $h2 = $this->makeHousehold(2);   // live size at attach: 2
        $this->attachExitedVisit($h1, snapshotSize: 4);
        $this->attachExitedVisit($h2, snapshotSize: 2);

        // Now edit the live household sizes. Snapshot must hold.
        $h1->update(['household_size' => 99]);
        $h2->update(['household_size' => 99]);

        $this->makeExpense(600, $this->event->id, 'Food Cost'); // for cost-per-person calc

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/per-event-pnl?event_id=' . $this->event->id)
                         ->assertOk();

        // Households served = 2 (distinct count)
        // People served = 4 + 2 = 6 (snapshot, NOT 99 + 99 = 198)
        // Cost per person = 600 / 6 = $100.00; cost per household = 600 / 2 = $300.00
        $response->assertSeeText('Households Served');
        $response->assertSeeText('People Served');
        $response->assertSeeText('$100.00');   // cost per person
        $response->assertSeeText('$300.00');   // cost per household
    }

    public function test_only_exited_visits_count_for_beneficiaries(): void
    {
        $hExited = $this->makeHousehold(3);
        $hQueued = $this->makeHousehold(5);

        $this->attachExitedVisit($hExited, 3);

        // Build a still-queued visit that should NOT count
        $queued = Visit::create([
            'event_id' => $this->event->id, 'lane' => 1,
            'visit_status' => 'queued',
            'start_time' => '2026-04-10 09:00:00',
            'served_bags' => 0,
        ]);
        DB::table('visit_households')->insert([
            'visit_id'       => $queued->id,
            'household_id'   => $hQueued->id,
            'household_size' => 5,
            'children_count' => 0,
            'adults_count'   => 5,
            'seniors_count'  => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $service = app(\App\Services\FinanceReportService::class);
        $data = $service->perEventPnl($this->event->id);

        $this->assertSame(1, $data['households_served']);  // not 2
        $this->assertSame(3, $data['people_served']);      // not 8
    }

    public function test_cost_per_beneficiary_returns_null_when_no_exited_visits(): void
    {
        $this->makeExpense(600, $this->event->id, 'Food Cost');
        // No visits at all

        $service = app(\App\Services\FinanceReportService::class);
        $data = $service->perEventPnl($this->event->id);

        $this->assertNull($data['cost_per_household']);
        $this->assertNull($data['cost_per_person']);
    }

    public function test_invalid_event_id_returns_404(): void
    {
        $this->actingAs($this->admin)
             ->get('/finance/reports/per-event-pnl?event_id=99999')
             ->assertNotFound();
    }

    public function test_pdf_returns_pdf_with_event_filename(): void
    {
        $this->makeIncome(1000, $this->event->id);

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/per-event-pnl/pdf?event_id=' . $this->event->id)
                         ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('event-pnl-' . $this->event->id, $response->headers->get('Content-Disposition'));
    }

    public function test_print_renders_branded_html(): void
    {
        $this->makeIncome(1000, $this->event->id);

        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports/per-event-pnl/print?event_id=' . $this->event->id)
                         ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('PER-EVENT P&amp;L', $html);
        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('Spring Distribution', $html);
    }

    public function test_print_requires_event_id(): void
    {
        $this->actingAs($this->admin)
             ->get('/finance/reports/per-event-pnl/print')
             ->assertStatus(400);
    }

    public function test_csv_streams_with_bom_and_full_breakdown(): void
    {
        $this->makeIncome(1000,  $this->event->id, 'Gift A');
        $this->makeExpense(600,  $this->event->id, 'Food Cost');
        $h = $this->makeHousehold(4);
        $this->attachExitedVisit($h, 4);

        $body = $this->actingAs($this->admin)
                     ->get('/finance/reports/per-event-pnl/csv?event_id=' . $this->event->id)
                     ->assertOk()
                     ->streamedContent();

        $this->assertSame("\xEF\xBB\xBF", substr($body, 0, 3));
        $stripped = substr($body, 3);

        $this->assertStringContainsString('Per-Event P&L', $stripped);
        $this->assertStringContainsString('Spring Distribution', $stripped);
        $this->assertStringContainsString('SUMMARY', $stripped);
        $this->assertStringContainsString('Total Income', $stripped);
        $this->assertStringContainsString('Total Expense', $stripped);
        $this->assertStringContainsString('Households served', $stripped);
        $this->assertStringContainsString('1000.00', $stripped);
        $this->assertStringContainsString('600.00', $stripped);
        $this->assertStringContainsString('INCOME BY CATEGORY', $stripped);
        $this->assertStringContainsString('EXPENSE BY CATEGORY', $stripped);
        $this->assertStringContainsString('ALL TRANSACTIONS', $stripped);
        $this->assertStringContainsString('Gift A', $stripped);
        $this->assertStringContainsString('Food Cost', $stripped);
    }

    public function test_hub_marks_per_event_pnl_as_live(): void
    {
        $response = $this->actingAs($this->admin)
                         ->get('/finance/reports')
                         ->assertOk();

        $response->assertSee(route('finance.reports.per-event-pnl'));
        $response->assertSeeText('Per-Event P&L');
    }
}
