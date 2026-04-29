<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Smoke test: the landing route is reachable. Either the public landing
     * page renders (200) or the auth middleware redirects to /login (302).
     * Both are acceptable; a 5xx is not.
     */
    public function test_landing_route_is_reachable(): void
    {
        $response = $this->get('/');

        $this->assertContains(
            $response->status(),
            [200, 302],
            "Expected 200 or 302 from /; got {$response->status()}"
        );
    }
}
