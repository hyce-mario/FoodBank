<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Visit;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1.1.a — verifies the database-level unique constraint on
 * (event_id, lane, queue_position).
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.1.a, Part 3 #1.
 *
 * Before this constraint, two concurrent check-ins could both compute the
 * same MAX+1 and silently insert duplicate positions. The unique index makes
 * such a duplicate fail loudly with a QueryException, which the service
 * layer (Phase 1.1.b) translates into a retry/lock-and-recompute flow.
 */
class VisitQueuePositionUniqueTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name'  => 'Phase 1.1.a Test Event',
            'date'  => '2026-05-01',
            'lanes' => 2,
        ]);
    }

    public function test_first_visit_at_a_position_inserts_cleanly(): void
    {
        $visit = Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => 1,
            'visit_status'   => 'checked_in',
            'start_time'     => now(),
        ]);

        $this->assertNotNull($visit->id);
        $this->assertDatabaseHas('visits', [
            'id'             => $visit->id,
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => 1,
        ]);
    }

    /**
     * The headline regression for the queue-position race: a duplicate
     * (event_id, lane, queue_position) tuple must be rejected by the
     * database, not silently accepted.
     */
    public function test_duplicate_position_in_same_event_lane_is_rejected(): void
    {
        Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => 1,
            'visit_status'   => 'checked_in',
            'start_time'     => now(),
        ]);

        $this->expectException(QueryException::class);

        Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => 1,
            'visit_status'   => 'checked_in',
            'start_time'     => now(),
        ]);
    }

    public function test_same_position_allowed_on_different_lanes(): void
    {
        Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => 1,
            'visit_status'   => 'checked_in',
            'start_time'     => now(),
        ]);

        $second = Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 2,
            'queue_position' => 1,
            'visit_status'   => 'checked_in',
            'start_time'     => now(),
        ]);

        $this->assertNotNull($second->id);
    }

    public function test_same_position_allowed_in_different_events(): void
    {
        $otherEvent = Event::create([
            'name'  => 'Different Event',
            'date'  => '2026-05-02',
            'lanes' => 1,
        ]);

        Visit::create([
            'event_id'       => $this->event->id,
            'lane'           => 1,
            'queue_position' => 1,
            'visit_status'   => 'checked_in',
            'start_time'     => now(),
        ]);

        $second = Visit::create([
            'event_id'       => $otherEvent->id,
            'lane'           => 1,
            'queue_position' => 1,
            'visit_status'   => 'checked_in',
            'start_time'     => now(),
        ]);

        $this->assertNotNull($second->id);
    }
}
