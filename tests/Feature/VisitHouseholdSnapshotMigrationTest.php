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

    public function test_snapshot_columns_are_nullable_after_attach_without_pivot_data(): void
    {
        // The 1.2.b service change will populate the snapshot on attach.
        // Until then, plain attach() without pivot args MUST still succeed —
        // i.e. the columns are truly nullable, not NOT NULL with no default.
        $event = Event::create(['name' => '1.2.a', 'date' => '2026-05-01', 'lanes' => 1]);
        $household = Household::create([
            'household_number' => 'TST0001',
            'first_name'       => 'Snap',
            'last_name'        => 'Test',
            'household_size'   => 4,
            'adults_count'     => 2,
            'children_count'   => 1,
            'seniors_count'    => 1,
            'vehicle_make'     => 'Toyota',
            'vehicle_color'    => 'Blue',
        ]);
        $visit = Visit::create([
            'event_id'     => $event->id,
            'lane'         => 1,
            'queue_position' => 1,
            'visit_status' => 'checked_in',
            'start_time'   => now(),
        ]);

        $visit->households()->attach($household->id);

        $row = DB::table('visit_households')
            ->where('visit_id', $visit->id)
            ->where('household_id', $household->id)
            ->first();

        $this->assertNotNull($row, 'attach() must create a pivot row');
        $this->assertNull($row->household_size, 'pre-1.2.b attach() leaves snapshot columns NULL');
        $this->assertNull($row->vehicle_make);
    }

    /**
     * Mirrors the migration's backfill SQL on rows we created after migration
     * ran. Proves the correlated subquery is SQL-portable (works on sqlite
     * just as on MySQL) and that the snapshot columns hold the source values
     * losslessly. The real backfill in production runs once at migration
     * time; this test is the ongoing regression pin for the SQL shape.
     */
    public function test_backfill_correlated_subquery_copies_household_values(): void
    {
        $event = Event::create(['name' => '1.2.a backfill', 'date' => '2026-05-02', 'lanes' => 1]);
        $h1 = Household::create([
            'household_number' => 'TST0010', 'first_name' => 'Bf', 'last_name' => 'One',
            'household_size'   => 3, 'adults_count' => 2, 'children_count' => 1, 'seniors_count' => 0,
            'vehicle_make'     => 'Honda', 'vehicle_color' => 'Red',
        ]);
        $h2 = Household::create([
            'household_number' => 'TST0011', 'first_name' => 'Bf', 'last_name' => 'Two',
            'household_size'   => 5, 'adults_count' => 2, 'children_count' => 2, 'seniors_count' => 1,
            'vehicle_make'     => null, 'vehicle_color' => null,
        ]);
        $v1 = Visit::create(['event_id' => $event->id, 'lane' => 1, 'queue_position' => 1, 'visit_status' => 'checked_in', 'start_time' => now()]);
        $v2 = Visit::create(['event_id' => $event->id, 'lane' => 1, 'queue_position' => 2, 'visit_status' => 'checked_in', 'start_time' => now()]);

        $v1->households()->attach($h1->id);
        $v2->households()->attach($h2->id);

        // Run the same SQL the migration runs.
        DB::statement(<<<'SQL'
            UPDATE visit_households
            SET
                household_size = (SELECT household_size FROM households WHERE households.id = visit_households.household_id),
                children_count = (SELECT children_count FROM households WHERE households.id = visit_households.household_id),
                adults_count   = (SELECT adults_count   FROM households WHERE households.id = visit_households.household_id),
                seniors_count  = (SELECT seniors_count  FROM households WHERE households.id = visit_households.household_id),
                vehicle_make   = (SELECT vehicle_make   FROM households WHERE households.id = visit_households.household_id),
                vehicle_color  = (SELECT vehicle_color  FROM households WHERE households.id = visit_households.household_id)
        SQL);

        $r1 = DB::table('visit_households')->where('visit_id', $v1->id)->first();
        $r2 = DB::table('visit_households')->where('visit_id', $v2->id)->first();

        $this->assertSame(3, (int) $r1->household_size);
        $this->assertSame(2, (int) $r1->adults_count);
        $this->assertSame(1, (int) $r1->children_count);
        $this->assertSame(0, (int) $r1->seniors_count);
        $this->assertSame('Honda', $r1->vehicle_make);
        $this->assertSame('Red',   $r1->vehicle_color);

        $this->assertSame(5, (int) $r2->household_size);
        $this->assertNull($r2->vehicle_make);
        $this->assertNull($r2->vehicle_color);
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
