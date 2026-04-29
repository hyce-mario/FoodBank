<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1.1.c.3 — verifies VisitMonitorController::reorder delegates to
 * VisitReorderService and translates the service's exceptions to the
 * correct HTTP status codes.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.1.
 *
 * The monitor endpoint sits behind the standard `auth` admin middleware
 * (not the per-event session-code system used by event-day routes), so
 * HTTP-level coverage is appropriate here. Service-level race / NULL-stage
 * /version-mismatch logic is exhaustively tested in VisitReorderServiceTest.
 */
class VisitMonitorReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'name'         => 'ADMIN',
            'display_name' => 'Administrator',
            'description'  => 'Full access',
        ]);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);

        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $adminRole->id,
            'email_verified_at' => now(),
        ]);

        $this->event = Event::create([
            'name'  => 'Phase 1.1.c.3 Test Event',
            'date'  => '2026-05-01',
            'lanes' => 2,
        ]);
    }

    private function makeVisit(int $lane, int $position, ?int $eventId = null): Visit
    {
        return Visit::create([
            'event_id'       => $eventId ?? $this->event->id,
            'lane'           => $lane,
            'queue_position' => $position,
            'visit_status'   => 'checked_in',
            'start_time'     => now(),
        ]);
    }

    public function test_unauthenticated_request_is_redirected(): void
    {
        $response = $this->postJson(route('monitor.reorder', $this->event), [
            'moves' => [],
        ]);

        // postJson against an `auth` middleware returns 401 for JSON requests.
        $response->assertStatus(401);
    }

    public function test_admin_can_swap_two_positions_in_same_lane(): void
    {
        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(1, 2);

        $response = $this->actingAs($this->admin)->postJson(
            route('monitor.reorder', $this->event),
            [
                'moves' => [
                    ['id' => $a->id, 'lane' => 1, 'queue_position' => 2, 'updated_at' => $a->updated_at->toIso8601String()],
                    ['id' => $b->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $b->updated_at->toIso8601String()],
                ],
            ]
        );

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertSame(2, $a->fresh()->queue_position);
        $this->assertSame(1, $b->fresh()->queue_position);
    }

    public function test_response_echoes_fresh_updated_at_per_visit(): void
    {
        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(1, 2);

        $response = $this->actingAs($this->admin)->postJson(
            route('monitor.reorder', $this->event),
            [
                'moves' => [
                    ['id' => $a->id, 'lane' => 1, 'queue_position' => 2, 'updated_at' => $a->updated_at->toIso8601String()],
                    ['id' => $b->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $b->updated_at->toIso8601String()],
                ],
            ]
        );

        $response->assertOk();
        $payload = $response->json();
        $this->assertArrayHasKey('visits', $payload);
        $this->assertCount(2, $payload['visits']);
        foreach ($payload['visits'] as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('updated_at', $row);
            $this->assertNotEmpty($row['updated_at']);
        }
    }

    public function test_stale_updated_at_returns_409(): void
    {
        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(1, 2);

        $stale = $a->updated_at->copy()->subMinute()->toIso8601String();

        $response = $this->actingAs($this->admin)->postJson(
            route('monitor.reorder', $this->event),
            [
                'moves' => [
                    ['id' => $a->id, 'lane' => 1, 'queue_position' => 2, 'updated_at' => $stale],
                    ['id' => $b->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $b->updated_at->toIso8601String()],
                ],
            ]
        );

        $response->assertStatus(409);
        $response->assertJson(['code' => 'version_mismatch']);
        // Atomic rollback: neither row moved.
        $this->assertSame(1, $a->fresh()->queue_position);
        $this->assertSame(2, $b->fresh()->queue_position);
    }

    public function test_visit_outside_event_returns_422(): void
    {
        $other = Event::create([
            'name'  => 'Other Event',
            'date'  => '2026-05-02',
            'lanes' => 1,
        ]);

        $a       = $this->makeVisit(1, 1);
        $foreign = $this->makeVisit(1, 1, $other->id);

        $response = $this->actingAs($this->admin)->postJson(
            route('monitor.reorder', $this->event),
            [
                'moves' => [
                    ['id' => $a->id,       'lane' => 1, 'queue_position' => 2, 'updated_at' => $a->updated_at->toIso8601String()],
                    ['id' => $foreign->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $foreign->updated_at->toIso8601String()],
                ],
            ]
        );

        $response->assertStatus(422);
        $response->assertJson(['code' => 'scope_mismatch']);
        $this->assertSame(1, $a->fresh()->queue_position);
        $this->assertSame($other->id, $foreign->fresh()->event_id);
    }

    public function test_malformed_updated_at_is_rejected_at_validator(): void
    {
        $a = $this->makeVisit(1, 1);

        $response = $this->actingAs($this->admin)->postJson(
            route('monitor.reorder', $this->event),
            [
                'moves' => [
                    ['id' => $a->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => 'not-a-date'],
                ],
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['moves.0.updated_at']);
    }

    /**
     * The monitor has a setting (`event_queue.allow_queue_reorder`) that the
     * event-day endpoint doesn't. This is the only behavior unique to the
     * monitor variant; pin it so a future refactor that consolidates the
     * two controllers doesn't lose this guard.
     */
    public function test_403_when_allow_queue_reorder_setting_is_off(): void
    {
        SettingService::set('event_queue.allow_queue_reorder', false);

        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(1, 2);

        $response = $this->actingAs($this->admin)->postJson(
            route('monitor.reorder', $this->event),
            [
                'moves' => [
                    ['id' => $a->id, 'lane' => 1, 'queue_position' => 2, 'updated_at' => $a->updated_at->toIso8601String()],
                    ['id' => $b->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $b->updated_at->toIso8601String()],
                ],
            ]
        );

        $response->assertStatus(403);
        // Atomic: the disabled setting must short-circuit before any UPDATE runs.
        $this->assertSame(1, $a->fresh()->queue_position);
        $this->assertSame(2, $b->fresh()->queue_position);
    }
}
