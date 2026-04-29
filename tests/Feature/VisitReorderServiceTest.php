<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Visit;
use App\Services\VisitReorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 1.1.c.2 — verifies VisitReorderService applies a batch of (lane,
 * queue_position) moves atomically, with optimistic version locking, and
 * uses a NULL-stage strategy so two-row swaps don't transiently collide
 * on the unique (event_id, lane, queue_position) index from Phase 1.1.a.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.1 (queue-position race / reorder transaction).
 *
 * HTTP feature tests for `POST /ed/{event}/reorder` are deferred to Phase 5
 * (need session auth-code scaffolding); the controller is a thin wrapper
 * around this service so service-level coverage proves the hard parts.
 */
class VisitReorderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private VisitReorderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name'  => 'Phase 1.1.c.2 Test Event',
            'date'  => '2026-05-01',
            'lanes' => 2,
        ]);

        $this->service = app(VisitReorderService::class);
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

    public function test_reorder_updates_positions_within_one_call(): void
    {
        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(1, 2);
        $c = $this->makeVisit(1, 3);

        $this->service->reorder($this->event, [
            ['id' => $a->id, 'lane' => 1, 'queue_position' => 3],
            ['id' => $b->id, 'lane' => 1, 'queue_position' => 1],
            ['id' => $c->id, 'lane' => 1, 'queue_position' => 2],
        ]);

        $this->assertSame(3, $a->fresh()->queue_position);
        $this->assertSame(1, $b->fresh()->queue_position);
        $this->assertSame(2, $c->fresh()->queue_position);
    }

    /**
     * The headline reason 1.1.c.2 has to NULL-stage: applying
     * [{id:A, pos:2}, {id:B, pos:1}] sequentially trips the unique index
     * at the intermediate `pos:2` step. After the fix, swap works.
     */
    public function test_reorder_can_swap_two_positions_in_same_lane(): void
    {
        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(1, 2);

        $this->service->reorder($this->event, [
            ['id' => $a->id, 'lane' => 1, 'queue_position' => 2],
            ['id' => $b->id, 'lane' => 1, 'queue_position' => 1],
        ]);

        $this->assertSame(2, $a->fresh()->queue_position);
        $this->assertSame(1, $b->fresh()->queue_position);
    }

    public function test_reorder_can_move_visit_across_lanes(): void
    {
        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(2, 1);

        // Move A from lane 1 → lane 2 pos 2; move B from lane 2 pos 1 → lane 1 pos 1.
        $this->service->reorder($this->event, [
            ['id' => $a->id, 'lane' => 2, 'queue_position' => 2],
            ['id' => $b->id, 'lane' => 1, 'queue_position' => 1],
        ]);

        $a->refresh(); $b->refresh();
        $this->assertSame([2, 2], [$a->lane, $a->queue_position]);
        $this->assertSame([1, 1], [$b->lane, $b->queue_position]);
    }

    public function test_reorder_with_matching_updated_at_succeeds(): void
    {
        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(1, 2);

        $this->service->reorder($this->event, [
            ['id' => $a->id, 'lane' => 1, 'queue_position' => 2, 'updated_at' => $a->updated_at->toIso8601String()],
            ['id' => $b->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $b->updated_at->toIso8601String()],
        ]);

        $this->assertSame(2, $a->fresh()->queue_position);
        $this->assertSame(1, $b->fresh()->queue_position);
    }

    public function test_reorder_with_stale_updated_at_throws_version_mismatch(): void
    {
        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(1, 2);

        // Simulate another writer touching A after the client read it.
        $stale = $a->updated_at->copy()->subMinute()->toIso8601String();

        try {
            $this->service->reorder($this->event, [
                ['id' => $a->id, 'lane' => 1, 'queue_position' => 2, 'updated_at' => $stale],
                ['id' => $b->id, 'lane' => 1, 'queue_position' => 1, 'updated_at' => $b->updated_at->toIso8601String()],
            ]);
            $this->fail('Expected RuntimeException for version mismatch');
        } catch (RuntimeException $e) {
            $this->assertSame(VisitReorderService::ERR_VERSION_MISMATCH, $e->getMessage());
        }

        // Atomic rollback: neither row should have moved.
        $this->assertSame(1, $a->fresh()->queue_position);
        $this->assertSame(2, $b->fresh()->queue_position);
    }

    public function test_reorder_rejects_visits_not_in_event(): void
    {
        $other = Event::create([
            'name'  => 'Other Event',
            'date'  => '2026-05-02',
            'lanes' => 1,
        ]);

        $a       = $this->makeVisit(1, 1);
        $foreign = $this->makeVisit(1, 1, $other->id);

        try {
            $this->service->reorder($this->event, [
                ['id' => $a->id,       'lane' => 1, 'queue_position' => 2],
                ['id' => $foreign->id, 'lane' => 1, 'queue_position' => 1],
            ]);
            $this->fail('Expected RuntimeException for scope mismatch');
        } catch (RuntimeException $e) {
            $this->assertSame(VisitReorderService::ERR_SCOPE_MISMATCH, $e->getMessage());
        }

        // Atomic rollback: A keeps its original position, foreign visit untouched.
        $this->assertSame(1, $a->fresh()->queue_position);
        $this->assertSame(1, $foreign->fresh()->queue_position);
        $this->assertSame($other->id, $foreign->fresh()->event_id);
    }

    public function test_empty_moves_array_is_a_no_op(): void
    {
        $a = $this->makeVisit(1, 1);
        $original = $a->updated_at;

        $this->service->reorder($this->event, []);

        $a->refresh();
        $this->assertSame(1, $a->queue_position);
        $this->assertTrue($original->equalTo($a->updated_at));
    }

    /**
     * Concurrency proxy: the service is the only writer to (event_id, lane,
     * queue_position) for active visits during a reorder. After it runs,
     * the unique index from Phase 1.1.a still holds — i.e. the NULL-stage
     * doesn't leave any duplicates behind.
     */
    public function test_reorder_does_not_violate_unique_index(): void
    {
        $a = $this->makeVisit(1, 1);
        $b = $this->makeVisit(1, 2);
        $c = $this->makeVisit(1, 3);

        $this->service->reorder($this->event, [
            ['id' => $a->id, 'lane' => 1, 'queue_position' => 3],
            ['id' => $b->id, 'lane' => 1, 'queue_position' => 1],
            ['id' => $c->id, 'lane' => 1, 'queue_position' => 2],
        ]);

        $positions = Visit::where('event_id', $this->event->id)
            ->where('lane', 1)
            ->orderBy('queue_position')
            ->pluck('queue_position')
            ->all();

        $this->assertSame([1, 2, 3], $positions);
    }
}
