<?php

namespace Tests\Feature;

use App\Http\Resources\VisitResource;
use App\Models\Event;
use App\Models\Household;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 6.8 — pin the per-role field-exposure contract on VisitResource.
 * Loader and exit roles must NOT receive household full names; intake and
 * scanner do. Every other field is identical across roles.
 */
class VisitResourceTest extends TestCase
{
    use RefreshDatabase;

    private function setupVisit(): Visit
    {
        $event = Event::create([
            'name'   => 'Resource Test',
            'date'   => now()->toDateString(),
            'lanes'  => 1,
            'status' => 'current',
        ]);

        $primary = Household::create([
            'household_number' => 'PRIM01',
            'first_name'       => 'Linda',
            'last_name'        => 'Smith',
            'household_size'   => 3,
            'children_count'   => 1,
            'adults_count'     => 2,
            'seniors_count'    => 0,
            'qr_token'         => str_repeat('a', 32),
        ]);

        $rep = Household::create([
            'household_number' => 'REP01',
            'first_name'       => 'Bob',
            'last_name'        => 'Jones',
            'household_size'   => 2,
            'children_count'   => 0,
            'adults_count'     => 2,
            'seniors_count'    => 0,
            'qr_token'         => str_repeat('b', 32),
            'representative_household_id' => $primary->id,
        ]);

        $visit = Visit::create([
            'event_id'       => $event->id,
            'lane'           => 1,
            'queue_position' => 1,
            'visit_status'   => 'queued',
            'start_time'     => now(),
        ]);
        $visit->households()->attach($primary->id, $primary->toVisitPivotSnapshot());
        $visit->households()->attach($rep->id, $rep->toVisitPivotSnapshot());

        return $visit->load('households');
    }

    public function test_intake_role_includes_full_names(): void
    {
        $visit  = $this->setupVisit();
        $row    = (new VisitResource($visit))->forRole('intake')->toArray(new Request());

        $this->assertSame('Linda Smith', $row['household']['full_name']);
        $this->assertSame('Bob Jones',   $row['represented_households'][0]['full_name']);
    }

    public function test_scanner_role_includes_full_names(): void
    {
        $visit = $this->setupVisit();
        $row   = (new VisitResource($visit))->forRole('scanner')->toArray(new Request());

        $this->assertSame('Linda Smith', $row['household']['full_name']);
        $this->assertSame('Bob Jones',   $row['represented_households'][0]['full_name']);
    }

    public function test_loader_role_strips_full_names(): void
    {
        $visit = $this->setupVisit();
        $row   = (new VisitResource($visit))->forRole('loader')->toArray(new Request());

        $this->assertArrayNotHasKey('full_name', $row['household']);
        $this->assertArrayNotHasKey('full_name', $row['represented_households'][0]);
    }

    public function test_exit_role_strips_full_names(): void
    {
        $visit = $this->setupVisit();
        $row   = (new VisitResource($visit))->forRole('exit')->toArray(new Request());

        $this->assertArrayNotHasKey('full_name', $row['household']);
        $this->assertArrayNotHasKey('full_name', $row['represented_households'][0]);
    }

    public function test_unknown_role_falls_back_to_intake_safe_default(): void
    {
        $visit = $this->setupVisit();
        $row   = (new VisitResource($visit))->forRole('hacker')->toArray(new Request());

        // Unknown role coerces to 'intake' — names are exposed on intake by spec
        $this->assertSame('Linda Smith', $row['household']['full_name']);
    }

    public function test_household_number_visible_to_all_roles(): void
    {
        $visit = $this->setupVisit();

        foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
            $row = (new VisitResource($visit))->forRole($role)->toArray(new Request());
            $this->assertSame('PRIM01', $row['household']['household_number'], "Role {$role} must see household_number");
            $this->assertSame(3, $row['household']['household_size'], "Role {$role} must see household_size");
        }
    }

    public function test_demographic_counts_visible_to_all_roles(): void
    {
        // The family-tag tooltip on intake and scanner cards needs the
        // children/adults/seniors breakdown. These are aggregate counts,
        // not PII, so they ship to every role — including loader/exit
        // even though those views don't currently render them.
        $visit = $this->setupVisit();

        foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
            $row = (new VisitResource($visit))->forRole($role)->toArray(new Request());
            $this->assertSame(1, $row['household']['children_count'], "Role {$role} must see children_count");
            $this->assertSame(2, $row['household']['adults_count'],   "Role {$role} must see adults_count");
            $this->assertSame(0, $row['household']['seniors_count'],  "Role {$role} must see seniors_count");
        }
    }
}
