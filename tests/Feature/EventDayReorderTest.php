<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Visit;
use App\Services\SettingService;
use App\Services\VisitReorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP feature tests for EventDayController::reorder()
 *
 * The event-day reorder endpoint sits at POST /ed/{event}/reorder. Auth is
 * the per-event session-key model (any of intake/scanner/loader/exit). The
 * controller validates the moves payload, delegates to VisitReorderService
 * inside its lockForUpdate transaction, and translates service-layer
 * RuntimeExceptions to:
 *   - ERR_VERSION_MISMATCH → 409 (someone else moved it first)
 *   - ERR_SCOPE_MISMATCH   → 422 (visit doesn't belong to this event)
 *
 * Service-layer race / NULL-stage / version logic is exhaustively covered
 * in VisitReorderServiceTest. These HTTP tests pin the controller's edges:
 *
 *   1. Auth — no session returns 401
 *   2. Validation — empty moves array returns 422
 *   3. Validation — garbage updated_at returns 422
 *   4. Happy path — same-lane swap returns 200 + fresh updated_at echoes
 *   5. Stale token — translated to 409 with code=version_mismatch
 *   6. Cross-event id — translated to 422 with code=scope_mismatch
 *   7. Cross-lane drag — works (lane=2 → lane=1)
 */
class EventDayReorderTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $this->event = Event::create([
            'name'  => 'Reorder Test Event',
            'date'  => '2026-06-01',
            'lanes' => 2,
        ]);
    }

    /**
     * Reorder requires ANY of the four event-day roles. Loader is convenient.
     */
    private function reorderSession(): array
    {
        return ['ed_' . $this->event->id . '_loader' => true];
    }

    private function postReorder(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withSession($this->reorderSession())
                    ->postJson("/ed/{$this->event->id}/reorder", $payload);
    }

    private function makeVisit(int $lane, int $position, ?Event $event = null): Visit
    {
        $event ??= $this->event;
        return Visit::create([
            'event_id'       => $event->id,
            'lane'           => $lane,
            'queue_position' => $position,
            'visit_status'   => 'checked_in',
            'start_time'     => now(),
        ]);
    }

    // ─── Test 1 — Auth gate ──────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson("/ed/{$this->event->id}/reorder", [
            'moves' => [],
        ]);

        $response->assertStatus(401)
                 ->assertJson(['error' => 'Unauthorised']);
    }

    // ─── Test 2 — Empty moves rejected by validator ──────────────────────────

    public function test_empty_moves_array_returns_422(): void
    {
        $response = $this->postReorder(['moves' => []]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['moves']);
    }

    // ─── Test 3 — Garbage updated_at rejected by validator ───────────────────

    public function test_garbage_updated_at_returns_422_validation_error(): void
    {
        $a = $this->makeVisit(lane: 1, position: 1);
        $b = $this->makeVisit(lane: 1, position: 2);

        $response = $this->postReorder([
            'moves' => [
                ['id' => $a->id, 'lane' => 1, 'queue_position' => 2, 'updated_at' => 'not-a-date'],
                ['id' => $b->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => 'also-garbage'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['moves.0.updated_at', 'moves.1.updated_at']);
    }

    // ─── Test 4 — Happy path same-lane swap ──────────────────────────────────

    public function test_same_lane_swap_succeeds_and_echoes_fresh_updated_at(): void
    {
        $a = $this->makeVisit(lane: 1, position: 1);
        $b = $this->makeVisit(lane: 1, position: 2);

        $response = $this->postReorder([
            'moves' => [
                ['id' => $a->id, 'lane' => 1, 'queue_position' => 2, 'updated_at' => $a->updated_at?->toIso8601String()],
                ['id' => $b->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $b->updated_at?->toIso8601String()],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertCount(2, $response->json('visits'),
            'response.visits should echo one entry per affected visit');

        $this->assertSame(2, (int) $a->fresh()->queue_position);
        $this->assertSame(1, (int) $b->fresh()->queue_position);
    }

    // ─── Test 5 — Stale token → 409 ──────────────────────────────────────────

    public function test_stale_updated_at_token_returns_409_version_mismatch(): void
    {
        $a = $this->makeVisit(lane: 1, position: 1);
        $b = $this->makeVisit(lane: 1, position: 2);

        // Simulate "someone else moved it first" by submitting an updated_at
        // that's an hour behind the actual row.
        $stale = now()->subHour()->toIso8601String();

        $response = $this->postReorder([
            'moves' => [
                ['id' => $a->id, 'lane' => 1, 'queue_position' => 2, 'updated_at' => $stale],
                ['id' => $b->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $stale],
            ],
        ]);

        $response->assertStatus(409);
        $response->assertJson(['code' => 'version_mismatch']);

        // Positions must NOT have moved.
        $this->assertSame(1, (int) $a->fresh()->queue_position);
        $this->assertSame(2, (int) $b->fresh()->queue_position);
    }

    // ─── Test 6 — Cross-event scope → 422 ────────────────────────────────────

    public function test_visit_id_belonging_to_different_event_returns_422_scope_mismatch(): void
    {
        $other = Event::create([
            'name'  => 'Other Event',
            'date'  => '2026-06-15',
            'lanes' => 1,
        ]);
        $foreign = $this->makeVisit(lane: 1, position: 1, event: $other);

        $response = $this->postReorder([
            'moves' => [
                ['id' => $foreign->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $foreign->updated_at?->toIso8601String()],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['code' => 'scope_mismatch']);

        // Foreign visit position must NOT have moved.
        $this->assertSame(1, (int) $foreign->fresh()->queue_position);
    }

    // ─── Test 7 — Cross-lane drag ────────────────────────────────────────────

    public function test_drag_across_lanes_succeeds(): void
    {
        $a = $this->makeVisit(lane: 1, position: 1);
        $b = $this->makeVisit(lane: 2, position: 1);

        // Move A to lane 2, position 2; B stays at lane 2, position 1.
        $response = $this->postReorder([
            'moves' => [
                ['id' => $a->id, 'lane' => 2, 'queue_position' => 2, 'updated_at' => $a->updated_at?->toIso8601String()],
            ],
        ]);

        $response->assertOk();
        $fresh = $a->fresh();
        $this->assertSame(2, (int) $fresh->lane,         'lane should have changed from 1 to 2');
        $this->assertSame(2, (int) $fresh->queue_position);
        $this->assertSame(2, (int) $b->fresh()->lane,    'unmoved visit B should still be in lane 2');
    }
}
