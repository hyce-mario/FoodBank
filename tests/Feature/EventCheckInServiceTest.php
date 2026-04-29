<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Models\Visit;
use App\Services\EventCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1.1.b — verifies EventCheckInService::checkIn uses a transaction
 * with lockForUpdate so two concurrent check-ins on the same lane cannot
 * silently produce duplicate queue positions.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.1.b, Part 3 #1.
 *
 * SQLite tests cannot reproduce a real cross-process race (PHPUnit runs
 * sequentially and SQLite serializes writes anyway), but we can prove:
 *   - sequential calls produce monotonically increasing positions
 *   - the active-check is consistent with the position read (transaction)
 *   - failed paths roll back without leaving orphan visits
 *   - the unique index from Phase 1.1.a is never tripped by valid usage
 */
class EventCheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private EventCheckInService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name'  => 'Phase 1.1.b Test Event',
            'date'  => '2026-05-01',
            'lanes' => 2,
        ]);

        $this->service = app(EventCheckInService::class);
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

    public function test_sequential_check_ins_produce_sequential_positions(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');
        $h2 = $this->makeHousehold('Ben', 'Brown');
        $h3 = $this->makeHousehold('Cal', 'Cohen');

        $v1 = $this->service->checkIn($this->event, $h1, 1);
        $v2 = $this->service->checkIn($this->event, $h2, 1);
        $v3 = $this->service->checkIn($this->event, $h3, 1);

        $this->assertSame(1, $v1->queue_position);
        $this->assertSame(2, $v2->queue_position);
        $this->assertSame(3, $v3->queue_position);
    }

    public function test_positions_are_independent_per_lane(): void
    {
        $h1 = $this->makeHousehold('Ann',  'Adams');
        $h2 = $this->makeHousehold('Ben',  'Brown');
        $h3 = $this->makeHousehold('Cal',  'Cohen');
        $h4 = $this->makeHousehold('Dana', 'Day');

        $v1 = $this->service->checkIn($this->event, $h1, 1); // lane 1, pos 1
        $v2 = $this->service->checkIn($this->event, $h2, 2); // lane 2, pos 1
        $v3 = $this->service->checkIn($this->event, $h3, 1); // lane 1, pos 2
        $v4 = $this->service->checkIn($this->event, $h4, 2); // lane 2, pos 2

        $this->assertSame([1, 1], [$v1->queue_position, $v1->lane]);
        $this->assertSame([1, 2], [$v2->queue_position, $v2->lane]);
        $this->assertSame([2, 1], [$v3->queue_position, $v3->lane]);
        $this->assertSame([2, 2], [$v4->queue_position, $v4->lane]);
    }

    public function test_already_active_check_in_throws_and_does_not_create_a_second_visit(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');

        $first = $this->service->checkIn($this->event, $h1, 1);
        $this->assertSame(1, Visit::count(), 'first check-in should create exactly one visit');

        try {
            $this->service->checkIn($this->event, $h1, 1);
            $this->fail('Expected RuntimeException for already-active check-in');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(1, Visit::count(), 'failed re-check-in must not leave an orphan visit');
        $this->assertSame($first->id, Visit::first()->id);
    }

    /**
     * Once a visit is exited (end_time set), the same household may be
     * re-checked-in. The Phase 1.3 work will tighten this with a
     * "one-visit-per-event" guard with explicit override; this test pins
     * the current behavior so the 1.1.b changes don't silently alter it.
     */
    public function test_re_check_in_after_exit_succeeds_with_next_position(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');

        $first = $this->service->checkIn($this->event, $h1, 1);
        $first->update(['end_time' => now(), 'visit_status' => 'exited']);

        $second = $this->service->checkIn($this->event, $h1, 1);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $second->queue_position);
    }

    /**
     * If a household attach fails inside the transaction (FK violation from a
     * non-existent represented_id), the visit creation must roll back. This
     * proves DB::transaction() is actually wrapping the work — without it,
     * the visit row would persist with no households attached.
     */
    public function test_failed_attach_rolls_back_visit_creation(): void
    {
        $h1 = $this->makeHousehold('Ann', 'Adams');

        $initialVisitCount = Visit::count();

        try {
            // 99999 is a non-existent household id; attach() fails on the FK.
            $this->service->checkIn($this->event, $h1, 1, [99999]);
            $this->fail('Expected an exception from attaching a non-existent household');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame(
            $initialVisitCount,
            Visit::count(),
            'visit must be rolled back when a downstream attach fails'
        );
    }
}
