<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 6.3 — pin the cycle-prevention contract for HouseholdService::attach.
 * Three shapes of forbidden cycles, plus a happy-path linear chain.
 */
class HouseholdAttachCycleTest extends TestCase
{
    use RefreshDatabase;

    private function makeHousehold(string $first): Household
    {
        return Household::create([
            'household_number' => substr(md5($first . microtime(true)), 0, 6),
            'first_name'       => $first,
            'last_name'        => 'Test',
            'household_size'   => 1,
            'children_count'   => 0,
            'adults_count'     => 1,
            'seniors_count'    => 0,
            'qr_token'         => substr(md5($first . random_int(0, 99999)), 0, 32),
        ]);
    }

    public function test_self_attach_rejected(): void
    {
        $a = $this->makeHousehold('A');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot represent itself');

        app(HouseholdService::class)->attach($a, $a);
    }

    public function test_already_linked_rejected(): void
    {
        $a = $this->makeHousehold('A');
        $b = $this->makeHousehold('B');
        $c = $this->makeHousehold('C');
        $b->update(['representative_household_id' => $a->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already linked');

        app(HouseholdService::class)->attach($c, $b);
    }

    public function test_two_node_cycle_rejected(): void
    {
        $a = $this->makeHousehold('A');
        $b = $this->makeHousehold('B');
        $a->update(['representative_household_id' => $b->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('circular');

        app(HouseholdService::class)->attach($a, $b);
    }

    public function test_three_node_cycle_rejected(): void
    {
        $a = $this->makeHousehold('A');
        $b = $this->makeHousehold('B');
        $c = $this->makeHousehold('C');
        $a->update(['representative_household_id' => $b->id]);
        $b->update(['representative_household_id' => $c->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('circular');

        app(HouseholdService::class)->attach($a, $c);
    }

    public function test_linear_chain_allowed(): void
    {
        $a = $this->makeHousehold('A');
        $b = $this->makeHousehold('B');
        $c = $this->makeHousehold('C');

        app(HouseholdService::class)->attach($a, $b);
        app(HouseholdService::class)->attach($a, $c);

        $this->assertSame($a->id, $b->fresh()->representative_household_id);
        $this->assertSame($a->id, $c->fresh()->representative_household_id);
    }
}
