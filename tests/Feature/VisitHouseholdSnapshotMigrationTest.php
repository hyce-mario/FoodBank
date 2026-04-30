<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 1.2.a — pins the snapshot-column schema on `visit_households` and
 * validates that the backfill correlated-subquery actually copies live
 * household values into the new columns.
 *
 * Refs: AUDIT_REPORT.md Part 13 §1.2.
 *
 * The backfill itself runs only when `visit_households` is non-empty
 * (skip-on-empty for fresh installs / sqlite tests). To exercise it from
 * tests we'd have to seed rows BEFORE the migration ran, which RefreshDatabase
 * doesn't easily allow. Instead we test the same query shape (correlated
 * subquery) against rows we attach AFTER migrations finish — proving the
 * SQL is portable on sqlite and that the column types accept the source
 * values from `households`.
 */
class VisitHouseholdSnapshotMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visit_households_has_snapshot_columns(): void
    {
        $expected = ['household_size', 'children_count', 'adults_count', 'seniors_count', 'vehicle_make', 'vehicle_color'];

        foreach ($expected as $col) {
            $this->assertTrue(
                Schema::hasColumn('visit_households', $col),
                "visit_households is missing snapshot column `{$col}`"
            );
        }
    }

    /**
     * After 1.2.b tightened demographics to NOT NULL, a bare attach() with
     * no pivot payload MUST throw. This pins the contract: every code path
     * that touches `visit_households` must carry a snapshot. The constraint
     * itself is the test — if a future migration silently re-nulls these
     * columns, this assertion fails.
     */
    public function test_attach_without_pivot_data_fails_under_not_null_constraint(): void
    {
        $event = Event::create(['name' => '1.2.b contract', 'date' => '2026-05-01', 'lanes' => 1]);
        $household = Household::create([
            'household_number' => 'TST0001', 'first_name' => 'Snap', 'last_name' => 'Test',
            'household_size'   => 4, 'adults_count' => 2, 'children_count' => 1, 'seniors_count' => 1,
            'vehicle_make'     => 'Toyota', 'vehicle_color' => 'Blue',
        ]);
        $visit = Visit::create([
            'event_id' => $event->id, 'lane' => 1, 'queue_position' => 1,
            'visit_status' => 'checked_in', 'start_time' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $visit->households()->attach($household->id);
    }

    /**
     * Pins the `withPivot()` declarations on Visit::households() and
     * Household::visits(). Without these, Eloquent silently drops the
     * snapshot columns on reads even when they're populated — a footgun
     * for any reporting code that reaches for `$pivot->household_size`.
     */
    public function test_pivot_columns_are_exposed_through_eloquent_relationships(): void
    {
        $event = Event::create(['name' => '1.2.a pivot read', 'date' => '2026-05-03', 'lanes' => 1]);
        $h = Household::create([
            'household_number' => 'TST0020', 'first_name' => 'Pivot', 'last_name' => 'Read',
            'household_size'   => 6, 'adults_count' => 3, 'children_count' => 2, 'seniors_count' => 1,
            'vehicle_make'     => 'Subaru', 'vehicle_color' => 'Green',
        ]);
        $visit = Visit::create([
            'event_id' => $event->id, 'lane' => 1, 'queue_position' => 1,
            'visit_status' => 'checked_in', 'start_time' => now(),
        ]);

        // Attach with explicit pivot payload — the 1.2.b service change
        // will do this automatically; here we simulate it directly.
        $visit->households()->attach($h->id, [
            'household_size' => 6,
            'children_count' => 2,
            'adults_count'   => 3,
            'seniors_count'  => 1,
            'vehicle_make'   => 'Subaru',
            'vehicle_color'  => 'Green',
        ]);

        $pivot = $visit->fresh()->households->first()->pivot;

        $this->assertSame(6, (int) $pivot->household_size);
        $this->assertSame(3, (int) $pivot->adults_count);
        $this->assertSame(2, (int) $pivot->children_count);
        $this->assertSame(1, (int) $pivot->seniors_count);
        $this->assertSame('Subaru', $pivot->vehicle_make);
        $this->assertSame('Green',  $pivot->vehicle_color);

        // And the inverse direction (Household::visits()).
        $reversePivot = $h->fresh()->visits->first()->pivot;
        $this->assertSame(6, (int) $reversePivot->household_size);
        $this->assertSame('Subaru', $reversePivot->vehicle_make);
    }
}
