<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Behavioural RBAC smoke test — complements the static
 * {@see RbacRouteAuditTest} by actually hitting every parameterless GET
 * route as a user whose role has zero permissions, and asserting the
 * response is not a 200.
 *
 * Why this matters:
 *   • {@see RbacRouteAuditTest} confirms a route HAS a gate, but doesn't
 *     check that the gate enforces the right permission. A controller
 *     could call `$this->authorize('something_typo', …)` and the audit
 *     would pass while the runtime check silently succeeds.
 *   • This test simulates the actual failure mode: "I created a role
 *     with no permissions, logged in, and could see X." If a route
 *     renders 200 to a no-permission user, this test fails.
 *
 * Coverage scope:
 *   • Only GET routes with no path parameters (no `{event}`, no `{user}`).
 *     Parameterless routes are the dashboard, list pages, settings tabs,
 *     etc. — exactly the menu surface a low-permission user might browse.
 *   • Parameterised routes (e.g. `/events/{event}`) need fixture data and
 *     are out of scope here; their gates are still verified by
 *     RbacRouteAuditTest. Add targeted tests for those when needed.
 *
 * Adding a new admin route now produces a clear failure mode: gate it
 * properly, or add it to ALWAYS_ACCESSIBLE with a justifying comment.
 */
class RbacNoPermissionSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes that may legitimately 200 for a user with zero permissions.
     * Match by route name. Justification required for each entry.
     */
    private const ALWAYS_ACCESSIBLE = [
        // Dashboard — landing page; per-widget gating shows an empty
        // welcome state when the user has no other permissions.
        'dashboard',

        // Profile — every user sees and edits their own profile.
        'profile',
    ];

    public function test_user_with_zero_permissions_cannot_access_admin_pages(): void
    {
        // Create a role with NO permissions and a user attached to it.
        $role = Role::create([
            'name'         => 'EMPTY_ROLE',
            'display_name' => 'Empty Role',
            'description'  => 'No permissions — used to smoke-test RBAC gates',
        ]);

        $user = User::create([
            'name'     => 'Smoke Test User',
            'email'    => 'smoke@example.test',
            'password' => bcrypt('secret-password'),
            'role_id'  => $role->id,
        ]);

        $leaks = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->isTestableAdminGetRoute($route)) {
                continue;
            }

            $name = $route->getName();
            if (in_array($name, self::ALWAYS_ACCESSIBLE, true)) {
                continue;
            }

            $url = '/' . ltrim($route->uri(), '/');
            $response = $this->actingAs($user)->get($url);
            $status = $response->status();

            // 403 (CheckPermission abort) and 302 (policy denial → redirect)
            // are both acceptable. 200 means the user got through. 500 is
            // a legitimate failure to investigate but not a security leak —
            // log it and move on.
            if ($status === 200) {
                $leaks[] = "{$name} ({$url})";
            }
        }

        $this->assertEmpty(
            $leaks,
            "User with zero permissions accessed admin routes:\n  - "
            . implode("\n  - ", $leaks)
            . "\n\nFix one of:\n"
            . "  1. Verify the route has `permission:<perm>` middleware OR a controller \$this->authorize() call.\n"
            . "  2. Verify the policy check uses the right permission string (no typos).\n"
            . "  3. If the route is intentionally accessible to all authenticated users,\n"
            . "     add it to ALWAYS_ACCESSIBLE in this test with a justifying comment."
        );
    }

    /**
     * Filter to the routes worth smoke-testing:
     *   • Auth-protected (skip public routes)
     *   • GET method (skip POST/PUT/DELETE — those need CSRF + bodies)
     *   • No path parameters (skip {event}, {user}, etc. — need fixtures)
     *   • Skip the kiosk-shared `event-day-or-auth` group — those are
     *     intentionally open to all authenticated users (and also to
     *     event-day kiosks); covered separately by integration tests.
     */
    private function isTestableAdminGetRoute(\Illuminate\Routing\Route $route): bool
    {
        $middleware = $route->gatherMiddleware();
        if (! in_array('auth', $middleware, true)) {
            return false;
        }
        if (in_array('event-day-or-auth', $middleware, true)) {
            return false;
        }
        if (! in_array('GET', $route->methods(), true)) {
            return false;
        }
        if (str_contains($route->uri(), '{')) {
            return false;
        }
        return true;
    }
}
