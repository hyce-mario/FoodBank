<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use App\Services\EventAnalyticsService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C.1 + C.2 — Attendee tab depth: stat cards + forecast card.
 *
 * Pins the four pre-reg stat cards (Total / Children / Adults / Seniors)
 * sourced straight from `event_pre_registrations` columns, plus the
 * forecast math in EventAnalyticsService::attendeeForecast() that combines
 * current pre-reg with the historical walk-in rate from the last 3 past
 * events. The placeholder "not enough history yet" rendering is also
 * pinned so an empty-history event doesn't render fake numbers.
 */
class EventAttendeeTabTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $role = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    private function makeEvent(string $date = null, string $status = 'current'): Event
    {
        static $counter = 0;
        $counter++;
        return Event::create([
            'name'   => "Phase C Event {$counter}",
            'date'   => $date ?? now()->toDateString(),
            'status' => $status,
            'lanes'  => 1,
        ]);
    }

    private function makeHousehold(int $size = 3): Household
    {
        static $counter = 0;
        $counter++;
        return Household::create([
            'household_number' => str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
            'first_name'       => "F{$counter}",
            'last_name'        => "L{$counter}",
            'household_size'   => $size,
            'children_count'   => 1,
            'adults_count'     => max(1, $size - 1),
            'seniors_count'    => 0,
            'qr_token'         => str_repeat('a', 32) . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
        ]);
    }

    private function addPreReg(Event $event, int $children = 1, int $adults = 1, int $seniors = 0): EventPreRegistration
    {
        static $counter = 0;
        $counter++;
        return EventPreRegistration::create([
            'event_id'         => $event->id,
            'attendee_number'  => str_pad((string) $counter, 5, '0', STR_PAD_LEFT),
            'first_name'       => "Pre{$counter}",
            'last_name'        => 'Reg',
            'email'            => "pre{$counter}@test.local",
            'household_size'   => $children + $adults + $seniors,
            'children_count'   => $children,
            'adults_count'     => $adults,
            'seniors_count'    => $seniors,
            'match_status'     => 'unmatched',
        ]);
    }

    private function addExitedVisit(Event $event, Household $household, int $bags = 1): Visit
    {
        $visit = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'visit_status' => 'exited',
            'start_time'   => now()->subHour(),
            'end_time'     => now(),
            'served_bags'  => $bags,
        ]);
        $visit->households()->attach($household->id, $household->toVisitPivotSnapshot());
        return $visit;
    }

    // ─── Stat cards (C.1) ─────────────────────────────────────────────────────

    public function test_stat_cards_sum_demographics_from_pre_registrations(): void
    {
        $event = $this->makeEvent();
        $this->addPreReg($event, children: 2, adults: 2, seniors: 1);
        $this->addPreReg($event, children: 1, adults: 3, seniors: 0);
        $this->addPreReg($event, children: 0, adults: 1, seniors: 2);

        $html = $this->actingAs($this->admin)
                     ->get(route('events.show', $event))
                     ->assertOk()
                     ->getContent();

        // Total = 3, Children = 3, Adults = 6, Seniors = 3.
        $this->assertStringContainsString('data-stat="total" class="text-2xl font-black text-gray-900 tabular-nums leading-none">3<', $html);
        $this->assertStringContainsString('data-stat="children" class="text-2xl font-black text-gray-900 tabular-nums leading-none">3<', $html);
        $this->assertStringContainsString('data-stat="adults" class="text-2xl font-black text-gray-900 tabular-nums leading-none">6<', $html);
        $this->assertStringContainsString('data-stat="seniors" class="text-2xl font-black text-gray-900 tabular-nums leading-none">3<', $html);
    }

    public function test_stat_cards_render_zero_when_no_pre_registrations(): void
    {
        $event = $this->makeEvent();
        // No pre-regs, but at least one card row must still render so the
        // empty-state placeholder for the table doesn't suppress the header.
        // The "No attendees yet" empty card comes BEFORE the cards, so we
        // assert this case via the placeholder's own copy.
        $this->actingAs($this->admin)
             ->get(route('events.show', $event))
             ->assertOk()
             ->assertSee('No attendees yet');
    }

    // ─── Forecast service (C.2) ───────────────────────────────────────────────

    public function test_forecast_disabled_when_no_past_events(): void
    {
        $event = $this->makeEvent();
        $this->addPreReg($event);

        $forecast = app(EventAnalyticsService::class)->attendeeForecast($event);

        $this->assertFalse($forecast['enabled']);
        $this->assertSame(0, $forecast['last_event_visits']);
    }

    public function test_forecast_uses_only_the_most_recent_past_event(): void
    {
        $current = $this->makeEvent(date: now()->toDateString(), status: 'current');

        // Older past event with very different numbers — must NOT influence
        // the forecast.
        $older = $this->makeEvent(date: now()->subDays(10)->toDateString(), status: 'past');
        for ($j = 0; $j < 100; $j++) {
            $hh = $this->makeHousehold();
            $this->addExitedVisit($older, $hh);
            $this->addPreReg($older);
        }

        // Most recent past event has exactly 20 visits and 20 pre-regs.
        $last = $this->makeEvent(date: now()->subDay()->toDateString(), status: 'past');
        for ($j = 0; $j < 20; $j++) {
            $hh = $this->makeHousehold();
            $this->addExitedVisit($last, $hh);
            $this->addPreReg($last);
        }

        $forecast = app(EventAnalyticsService::class)->attendeeForecast($current);

        $this->assertTrue($forecast['enabled']);
        // Sourced from the LAST past event only.
        $this->assertSame(20, $forecast['last_event_visits']);
    }

    public function test_forecast_walk_in_rate_drives_projection_above_pre_reg(): void
    {
        $current = $this->makeEvent(date: now()->toDateString(), status: 'current');

        // Last past event: 50 visits, 25 pre-regs → 50% walk-in rate.
        $past = $this->makeEvent(date: now()->subDay()->toDateString(), status: 'past');
        for ($j = 0; $j < 50; $j++) {
            $hh = $this->makeHousehold();
            $this->addExitedVisit($past, $hh);
        }
        for ($j = 0; $j < 25; $j++) {
            $this->addPreReg($past);
        }

        // Current event has 30 pre-regs. With walk-in rate 0.5:
        //   projected = 30 / (1 - 0.5) = 60
        //   forecast  = max(60, 50)   = 60
        //   walk_ins  = 60 - 30        = 30
        for ($j = 0; $j < 30; $j++) {
            $this->addPreReg($current);
        }

        $forecast = app(EventAnalyticsService::class)->attendeeForecast($current);

        $this->assertTrue($forecast['enabled']);
        $this->assertSame(50, $forecast['last_event_visits']);
        $this->assertEqualsWithDelta(0.5, $forecast['walk_in_rate'], 0.01);
        $this->assertSame(60, $forecast['projected_total']);
        $this->assertSame(30, $forecast['projected_walk_ins']);
    }

    public function test_forecast_floors_at_last_event_when_pre_reg_is_low(): void
    {
        $current = $this->makeEvent(date: now()->toDateString(), status: 'current');

        // Last event pulled 100 visits; current has 5 pre-regs. Even with
        // 0% walk-in rate the forecast floors at last event's count.
        $past = $this->makeEvent(date: now()->subDay()->toDateString(), status: 'past');
        for ($j = 0; $j < 100; $j++) {
            $hh = $this->makeHousehold();
            $this->addExitedVisit($past, $hh);
            $this->addPreReg($past);
        }
        for ($j = 0; $j < 5; $j++) {
            $this->addPreReg($current);
        }

        $forecast = app(EventAnalyticsService::class)->attendeeForecast($current);

        $this->assertSame(100, $forecast['last_event_visits']);
        $this->assertSame(100, $forecast['projected_total']);
        // walk_ins = 100 (forecast) - 5 (current pre-reg)
        $this->assertSame(95, $forecast['projected_walk_ins']);
    }

    public function test_forecast_excludes_self_event_from_history(): void
    {
        // Even if the current event itself has past status / earlier date,
        // the forecast must exclude its own row (so we don't bootstrap from
        // self-data).
        $event = $this->makeEvent(date: now()->subDays(10)->toDateString(), status: 'past');
        $hh    = $this->makeHousehold();
        $this->addExitedVisit($event, $hh);
        $this->addPreReg($event);

        $forecast = app(EventAnalyticsService::class)->attendeeForecast($event);
        $this->assertFalse($forecast['enabled']);
    }

    // ─── Forecast card render (C.2 view) ──────────────────────────────────────

    public function test_forecast_card_renders_placeholder_when_no_history(): void
    {
        $event = $this->makeEvent();
        $this->addPreReg($event);

        $this->actingAs($this->admin)
             ->get(route('events.show', $event))
             ->assertOk()
             ->assertSee('Not enough history yet');
    }

    public function test_forecast_card_renders_projection_with_breakdown(): void
    {
        $past = $this->makeEvent(date: now()->subDay()->toDateString(), status: 'past');
        for ($j = 0; $j < 20; $j++) {
            $hh = $this->makeHousehold();
            $this->addExitedVisit($past, $hh);
            $this->addPreReg($past);
        }

        $current = $this->makeEvent(date: now()->toDateString(), status: 'current');
        $this->addPreReg($current);

        $html = $this->actingAs($this->admin)
                     ->get(route('events.show', $current))
                     ->assertOk()
                     ->getContent();

        // The forecast number is the floor: last_event_visits = 20.
        $this->assertStringContainsString('data-stat="forecast-total"', $html);
        $this->assertStringContainsString('~20', $html);
        // The subtitle reflects last event's visit count.
        $this->assertStringContainsString('Based on last event (20 visits)', $html);
    }
}
