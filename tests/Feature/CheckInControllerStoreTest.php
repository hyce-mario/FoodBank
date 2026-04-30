<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\EventCheckInService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1.3.b — verifies CheckInController::store translates the service's
 * exceptions into the correct HTTP responses, including the new
 * HouseholdAlreadyServedException → 422 JSON shape that the override modal
 * consumes.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.3.
 *
 * Service-level race / policy-matrix logic is exhaustively tested in
 * EventCheckInServiceTest. These HTTP tests pin the controller's
 * translation layer: validator rules, exception → status mapping, and
 * the JSON payload shape consumed by the upcoming 1.3.c modal.
 */
class CheckInControllerStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        SettingService::flush();

        // The route is gated only by `auth` middleware (no permission check).
        // We attach a permission anyway so the test models a realistic intake
        // staff member — but the test would also pass with a permissionless
        // role. When Phase 4 introduces a CheckInPolicy / permission gate,
        // the permission line below becomes load-bearing.
        $role = Role::create([
            'name'         => 'INTAKE',
            'display_name' => 'Intake Staff',
            'description'  => 'Front-desk check-in',
        ]);
        RolePermission::create(['role_id' => $role->id, 'permission' => 'checkin.scan']);

        $this->user = User::create([
            'name'              => 'Front Desk',
            'email'             => 'desk@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);

        $this->event = Event::create([
            'name'  => 'Phase 1.3.b Test Event',
            'date'  => '2026-05-01',
            'lanes' => 2,
        ]);
    }

    private function makeHousehold(string $first, string $last): Household
    {
        static $counter = 0;
        $counter++;

        return Household::create([
            'household_number' => 'TST' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'first_name'       => $first,
            'last_name'        => $last,
            'household_size'   => 1,
            'adults_count'     => 1,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $h = $this->makeHousehold('Una', 'Auth');

        $response = $this->postJson(route('checkin.store'), [
            'event_id'     => $this->event->id,
            'household_id' => $h->id,
            'lane'         => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_clean_check_in_returns_201_with_visit_payload(): void
    {
        $h = $this->makeHousehold('Cleo', 'Clean');

        $response = $this->actingAs($this->user)->postJson(route('checkin.store'), [
            'event_id'     => $this->event->id,
            'household_id' => $h->id,
            'lane'         => 1,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('visit.lane', 1);
        $response->assertJsonPath('visit.household.id', $h->id);
        $response->assertJsonPath('visit.household.household_number', $h->household_number);
    }

    public function test_already_served_under_override_policy_returns_422_with_household_payload(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'override');

        $h = $this->makeHousehold('Renee', 'Returner');

        // Prime the conflict: check in, then exit.
        $first = app(EventCheckInService::class)->checkIn($this->event, $h, 1);
        app(EventCheckInService::class)->markDone($first);

        $response = $this->actingAs($this->user)->postJson(route('checkin.store'), [
            'event_id'     => $this->event->id,
            'household_id' => $h->id,
            'lane'         => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'household_already_served');
        $response->assertJsonPath('allow_override', true);
        $response->assertJsonPath('event_id', $this->event->id);
        $response->assertJsonPath('households.0.id', $h->id);
        $response->assertJsonPath('households.0.household_number', $h->household_number);
        $response->assertJsonPath('households.0.full_name', $h->full_name);
    }

    public function test_already_served_with_force_and_reason_under_override_policy_succeeds(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'override');

        $h = $this->makeHousehold('Override', 'Allowed');

        $first = app(EventCheckInService::class)->checkIn($this->event, $h, 1);
        app(EventCheckInService::class)->markDone($first);

        $response = $this->actingAs($this->user)->postJson(route('checkin.store'), [
            'event_id'        => $this->event->id,
            'household_id'    => $h->id,
            'lane'            => 1,
            'force'           => 1,
            'override_reason' => 'forgot a bag — supervisor confirmed',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('visit.household.id', $h->id);
    }

    public function test_already_served_under_deny_policy_returns_422_with_allow_override_false_even_with_force(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'deny');

        $h = $this->makeHousehold('Deny', 'Mode');

        $first = app(EventCheckInService::class)->checkIn($this->event, $h, 1);
        app(EventCheckInService::class)->markDone($first);

        $response = $this->actingAs($this->user)->postJson(route('checkin.store'), [
            'event_id'        => $this->event->id,
            'household_id'    => $h->id,
            'lane'            => 1,
            'force'           => 1,
            'override_reason' => 'attempting to bypass deny',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'household_already_served');
        $response->assertJsonPath('allow_override', false);
    }

    public function test_force_without_override_reason_fails_validation(): void
    {
        SettingService::set('event_queue.re_checkin_policy', 'override');

        $h = $this->makeHousehold('Bare', 'Force');

        $first = app(EventCheckInService::class)->checkIn($this->event, $h, 1);
        app(EventCheckInService::class)->markDone($first);

        $response = $this->actingAs($this->user)->postJson(route('checkin.store'), [
            'event_id'     => $this->event->id,
            'household_id' => $h->id,
            'lane'         => 1,
            'force'        => 1,
            // intentionally no override_reason
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['override_reason']);
    }

    public function test_active_already_checked_in_returns_422_without_override_payload(): void
    {
        // The active-duplicate path stays a plain RuntimeException, NOT
        // HouseholdAlreadyServedException. The controller's broader catch
        // returns 422 with just `message` — no `allow_override` field, no
        // `households` array. This pins the precedence ordering documented
        // in EventCheckInService::checkIn.
        $h = $this->makeHousehold('Activ', 'Eblock');

        // First check-in succeeds and stays active.
        $this->actingAs($this->user)->postJson(route('checkin.store'), [
            'event_id'     => $this->event->id,
            'household_id' => $h->id,
            'lane'         => 1,
        ])->assertStatus(201);

        // Second check-in for the same active household → plain 422, no override path.
        $response = $this->actingAs($this->user)->postJson(route('checkin.store'), [
            'event_id'        => $this->event->id,
            'household_id'    => $h->id,
            'lane'            => 1,
            'force'           => 1,
            'override_reason' => 'should not be able to override an active duplicate',
        ]);

        $response->assertStatus(422);
        $response->assertJsonMissing(['allow_override' => true]);
        $response->assertJsonMissing(['allow_override' => false]);
        $this->assertStringContainsString('active check-in', $response->json('message') ?? '');
    }
}
