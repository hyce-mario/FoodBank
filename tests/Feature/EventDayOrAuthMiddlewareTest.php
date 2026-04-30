<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the EventDayOrAuth middleware lets a request through when EITHER
 * the user is admin-authenticated OR the session carries an active event-day
 * intake session (`ed_{N}_intake = true`). Exercises the contract via the
 * /checkin/store endpoint, which is the most-used route in the new group.
 *
 * Admin auth path is regression-tested by CheckInControllerStoreTest; this
 * file pins the NEW behaviour: tablet with event-day session + no admin
 * login can still call /checkin/* endpoints.
 *
 * Refs: discovered while planning Phase 2 — the public intake page tried to
 * call admin-only /checkin/* and got 302→/login.
 */
class EventDayOrAuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $this->event = Event::create([
            'name'  => 'Event Day Or Auth Test',
            'date'  => '2026-05-01',
            'lanes' => 1,
        ]);
    }

    private function makeHousehold(): Household
    {
        static $counter = 0;
        $counter++;

        return Household::create([
            'household_number' => 'EDA' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'first_name'       => 'Eda',
            'last_name'        => 'Test',
            'household_size'   => 1,
            'adults_count'     => 1,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
    }

    public function test_event_day_intake_session_can_call_checkin_store(): void
    {
        $h = $this->makeHousehold();

        // Simulate a public tablet that's already passed the 4-digit code:
        // EventDayController::submitAuth() sets `ed_{eventId}_intake = true`
        // in the session. No admin user, no admin session.
        $response = $this->withSession([
            "ed_{$this->event->id}_intake" => true,
        ])->postJson(route('checkin.store'), [
            'event_id'     => $this->event->id,
            'household_id' => $h->id,
            'lane'         => 1,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('visit.lane', 1);
        $response->assertJsonPath('visit.household.id', $h->id);
    }

    public function test_event_day_session_for_one_event_can_act_on_another_event(): void
    {
        // The middleware accepts ANY active intake session — the 4-digit
        // code per-event is the actual security boundary. A tablet
        // authorized for event A can call /checkin/* targeting event B.
        // This is by design (documented in EventDayOrAuth's docblock);
        // pinning it as a test so a future maintainer doesn't tighten
        // it without realizing the design intent.
        $otherEvent = Event::create([
            'name'  => 'Other Event',
            'date'  => '2026-05-02',
            'lanes' => 1,
        ]);
        $h = $this->makeHousehold();

        $response = $this->withSession([
            "ed_{$this->event->id}_intake" => true,
        ])->postJson(route('checkin.store'), [
            'event_id'     => $otherEvent->id,
            'household_id' => $h->id,
            'lane'         => 1,
        ]);

        $response->assertStatus(201);
    }

    public function test_no_auth_at_all_returns_401_on_checkin_endpoints(): void
    {
        $h = $this->makeHousehold();

        $response = $this->postJson(route('checkin.store'), [
            'event_id'     => $this->event->id,
            'household_id' => $h->id,
            'lane'         => 1,
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'Unauthorised');
    }

    public function test_scanner_session_alone_does_not_authorize_checkin_endpoints(): void
    {
        // Principle of least privilege: scanner / loader / exit roles are
        // NOT supposed to call /checkin/*. Only intake sessions pass.
        // The middleware regex `^ed_\d+_intake$` enforces this.
        $h = $this->makeHousehold();

        $response = $this->withSession([
            "ed_{$this->event->id}_scanner" => true,
        ])->postJson(route('checkin.store'), [
            'event_id'     => $this->event->id,
            'household_id' => $h->id,
            'lane'         => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_falsy_intake_session_value_does_not_authorize(): void
    {
        // Defensive: only `=== true` counts. A session key set to 1, 'true',
        // 'yes', etc. should NOT authorize — Laravel's session sometimes
        // serializes booleans inconsistently with extension code.
        $h = $this->makeHousehold();

        $response = $this->withSession([
            "ed_{$this->event->id}_intake" => 1,
        ])->postJson(route('checkin.store'), [
            'event_id'     => $this->event->id,
            'household_id' => $h->id,
            'lane'         => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_event_day_intake_session_can_call_checkin_log(): void
    {
        // GET endpoints pass the middleware too. Using /checkin/log
        // because /checkin/search uses MySQL-only CONCAT() which
        // doesn't run on the in-memory sqlite test DB (pre-existing
        // portability bug unrelated to this middleware).
        $response = $this->withSession([
            "ed_{$this->event->id}_intake" => true,
        ])->getJson(route('checkin.log', ['event_id' => $this->event->id]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['log']);
    }
}
