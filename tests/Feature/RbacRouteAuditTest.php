<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Static RBAC audit — fails the build when a new authenticated route is
 * added without any permission gate.
 *
 * Every route reachable behind `auth` (or the kiosk-shared
 * `event-day-or-auth`) is classified as one of:
 *
 *   • GATED   — has at least one `permission:*` route middleware.
 *   • POLICY  — controller method body contains `$this->authorize(...)`,
 *               or the typed FormRequest's source contains `hasPermission(`.
 *               (Heuristic, not a behavioural check — pair with
 *               RbacNoPermissionSmokeTest for runtime verification.)
 *   • LEAK    — neither of the above. Must appear in {@see self::ALLOWLIST}
 *               or the test fails.
 *
 * Adding a new admin route now produces a clear failure mode: either gate
 * it (route middleware preferred, controller authorize as backup) or
 * explicitly list it as a "deliberately accessible to any authenticated
 * user" exception. No silent regressions.
 */
class RbacRouteAuditTest extends TestCase
{
    /**
     * Routes deliberately accessible to ANY authenticated user.
     *
     * Match by route name. Anything not in this list MUST have either
     * route middleware OR a controller `$this->authorize()` call.
     *
     * Categories:
     *   • Auth-shell routes (every user): dashboard, profile, logout
     *   • Event-day kiosk shared routes (auth via `event-day-or-auth`):
     *     /checkin/* sub-routes are intentionally open because the same
     *     UI serves both admin staff (session login) and the public
     *     intake kiosk (4-digit event-day code). Enforcing user-permission
     *     gates here would break the kiosk.
     */
    private const ALLOWLIST = [
        // Auth shell — every authenticated user
        'dashboard',
        'logout',
        'profile',
        'profile.info',
        'profile.password',

        // /checkin/* shared with public intake kiosk (event-day-or-auth)
        'checkin.search',
        'checkin.log',
        'checkin.store',
        'checkin.quickCreate',
        'checkin.updateVehicle',
        'checkin.searchRepresented',
        'checkin.attachRepresented',
        'checkin.createRepresented',
    ];

    public function test_every_authenticated_route_is_gated_or_explicitly_allowlisted(): void
    {
        $leaks = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            // Only audit auth-protected routes (skip public registration etc.)
            $isAuthRoute = in_array('auth', $middleware, true)
                || in_array('event-day-or-auth', $middleware, true);
            if (! $isAuthRoute) {
                continue;
            }

            // Has route-level permission middleware → GATED.
            if ($this->hasPermissionMiddleware($middleware)) {
                continue;
            }

            // Controller calls $this->authorize OR FormRequest checks
            // hasPermission → POLICY.
            if ($this->controllerOrRequestEnforces($route)) {
                continue;
            }

            $name = $route->getName() ?: '(unnamed) ' . $route->uri();
            $leaks[] = $name;
        }

        $unexpected = array_values(array_diff($leaks, self::ALLOWLIST));
        $obsolete   = array_values(array_diff(self::ALLOWLIST, $leaks));

        $message = '';
        if ($unexpected) {
            $message .= "\n\nNew authenticated routes without a permission gate:\n  - "
                . implode("\n  - ", $unexpected)
                . "\n\nFix one of:\n"
                . "  1. Add `->middleware('permission:<resource>.<action>')` to the route.\n"
                . "  2. Call \$this->authorize(...) in the controller method.\n"
                . "  3. If the route is intentionally open to all authenticated users,\n"
                . "     add it to ALLOWLIST in this test with a justifying comment.";
        }
        if ($obsolete) {
            $message .= "\n\nRoute names in ALLOWLIST that no longer exist or are now\n"
                . "gated (remove them from the list to keep it tidy):\n  - "
                . implode("\n  - ", $obsolete);
        }

        $this->assertEmpty($unexpected, $message);
        $this->assertEmpty($obsolete, $message);
    }

    private function hasPermissionMiddleware(array $middleware): bool
    {
        foreach ($middleware as $m) {
            if (is_string($m) && str_starts_with($m, 'permission:')) {
                return true;
            }
        }
        return false;
    }

    private function controllerOrRequestEnforces(IlluminateRoute $route): bool
    {
        $action = $route->getAction();
        $controller = $action['controller'] ?? null;
        if (! is_string($controller) || ! str_contains($controller, '@')) {
            return false;
        }

        [$class, $method] = explode('@', $controller, 2);

        try {
            $r = new ReflectionMethod($class, $method);
        } catch (\Throwable) {
            return false;
        }

        if ($this->reflectedMethodCallsAuthorize($r)) {
            return true;
        }

        // Inspect any FormRequest typed parameter — if its source contains
        // a hasPermission() call (i.e. authorize() does the gate) we trust
        // it as POLICY-enforced.
        foreach ($r->getParameters() as $param) {
            $type = $param->getType();
            if (! $type || ! method_exists($type, 'getName')) {
                continue;
            }
            $typeName = $type->getName();
            if (! str_ends_with($typeName, 'Request')) {
                continue;
            }
            try {
                $reqClass = new ReflectionClass($typeName);
            } catch (\Throwable) {
                continue;
            }
            $file = $reqClass->getFileName();
            if (! $file || ! is_file($file)) {
                continue;
            }
            $src = file_get_contents($file);
            if (str_contains($src, 'hasPermission(')) {
                return true;
            }
        }

        return false;
    }

    private function reflectedMethodCallsAuthorize(ReflectionMethod $r): bool
    {
        $file = $r->getFileName();
        $startLine = $r->getStartLine();
        $endLine = $r->getEndLine();
        if (! $file || ! is_file($file) || ! $startLine || ! $endLine) {
            return false;
        }
        $lines = file($file);
        $body = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
        return str_contains($body, '$this->authorize');
    }
}
