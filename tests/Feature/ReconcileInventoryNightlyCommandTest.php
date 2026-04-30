<?php

namespace Tests\Feature;

use App\Models\AllocationRuleset;
use App\Models\AllocationRulesetComponent;
use App\Models\Event;
use App\Models\InventoryItem;
use App\Services\DistributionPostingService;
use App\Services\EventCheckInService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Mail\InventoryReconcileAlert;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests for the inventory:reconcile-nightly command — Phase 2.2.
 *
 * Acceptance criterion: "Delta > threshold emails admins."
 *
 * Contracts pinned:
 *   - Clean events produce no alert and no email.
 *   - Events with gaps trigger an email to the configured support address.
 *   - No support_email configured → gaps logged but no mail (no uncaught exception).
 *
 * Refs: AUDIT_REPORT.md Part 13 §2.2.
 */
class ReconcileInventoryNightlyCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();
        Mail::fake();
    }

    private function makeEventWithRuleset(string $name = 'Test Event'): array
    {
        $ruleset = AllocationRuleset::create([
            'name'               => 'Ruleset',
            'allocation_type'    => 'household_size',
            'is_active'          => true,
            'max_household_size' => 10,
            'rules'              => [['min' => 1, 'max' => null, 'bags' => 1]],
        ]);

        $event = Event::create([
            'name'       => $name,
            'date'       => now()->toDateString(),
            'status'     => 'current',
            'lanes'      => 1,
            'ruleset_id' => $ruleset->id,
        ]);

        $item = InventoryItem::create([
            'name'             => 'Test Item',
            'unit_type'        => 'box',
            'quantity_on_hand' => 100,
            'reorder_level'    => 0,
        ]);

        AllocationRulesetComponent::create([
            'allocation_ruleset_id' => $ruleset->id,
            'inventory_item_id'     => $item->id,
            'qty_per_bag'           => 2,
        ]);

        return compact('event', 'ruleset', 'item');
    }

    private function makeExitedVisitWithMovement(Event $event, int $size): void
    {
        static $c = 0;
        $c++;

        $household = \App\Models\Household::create([
            'household_number' => 'RN' . str_pad((string) $c, 5, '0', STR_PAD_LEFT),
            'first_name'       => 'Test',
            'last_name'        => "RN{$c}",
            'household_size'   => $size,
            'adults_count'     => $size,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);

        $visit = app(EventCheckInService::class)->checkIn($event, $household, lane: 1);
        app(DistributionPostingService::class)->postForVisit($visit);
        $visit->update(['visit_status' => 'exited', 'end_time' => now(), 'queue_position' => null]);
    }

    private function makeExitedVisitWithoutMovement(Event $event, int $size): void
    {
        static $d = 0;
        $d++;

        $household = \App\Models\Household::create([
            'household_number' => 'RX' . str_pad((string) $d, 5, '0', STR_PAD_LEFT),
            'first_name'       => 'Test',
            'last_name'        => "RX{$d}",
            'household_size'   => $size,
            'adults_count'     => $size,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);

        $visit = app(EventCheckInService::class)->checkIn($event, $household, lane: 1);
        // Intentionally NOT calling postForVisit — simulates a missed posting
        $visit->update(['visit_status' => 'exited', 'end_time' => now(), 'queue_position' => null]);
    }

    // ─── Test 1 — Clean event: no alert, no email ─────────────────────────────

    /**
     * When all movements are posted, the command exits clean and sends no email.
     */
    public function test_clean_event_sends_no_email(): void
    {
        ['event' => $event] = $this->makeEventWithRuleset();
        $this->makeExitedVisitWithMovement($event, size: 2);

        SettingService::set('notifications.support_email', 'ops@test.local');

        $this->artisan('inventory:reconcile-nightly')
             ->assertExitCode(0)
             ->expectsOutputToContain('balanced');

        Mail::assertNothingSent();
    }

    // ─── Test 2 — Gaps: email sent ────────────────────────────────────────────

    /**
     * When a current/recent event has a non-zero delta and a support email is
     * configured, the command sends one alert email.
     */
    public function test_gaps_trigger_alert_email(): void
    {
        ['event' => $event] = $this->makeEventWithRuleset();
        $this->makeExitedVisitWithoutMovement($event, size: 2);

        SettingService::set('notifications.support_email', 'ops@test.local');

        $this->artisan('inventory:reconcile-nightly')
             ->assertExitCode(0);

        Mail::assertSent(InventoryReconcileAlert::class);
    }

    // ─── Test 3 — No support email: no crash ─────────────────────────────────

    /**
     * If gaps exist but no support_email or sender_email is configured, the
     * command must complete cleanly (warning logged) without throwing.
     */
    public function test_missing_email_config_does_not_crash(): void
    {
        ['event' => $event] = $this->makeEventWithRuleset();
        $this->makeExitedVisitWithoutMovement($event, size: 2);

        // Clear email settings
        SettingService::set('notifications.support_email', '');
        SettingService::set('notifications.sender_email', '');

        $this->artisan('inventory:reconcile-nightly')
             ->assertExitCode(0);

        Mail::assertNothingSent();
    }
}
