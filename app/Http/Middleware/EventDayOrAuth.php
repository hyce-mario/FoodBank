<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows access if EITHER the request is admin-authenticated (via the
 * standard `auth` middleware path) OR the session carries an active
 * event-day intake session for some event (`ed_{N}_intake = true`,
 * set by EventDayController::submitAuth() after a 4-digit code passes).
 *
 * Intended for the small set of /checkin/* endpoints that the public
 * intake page (event-day session, NOT admin auth) needs to call:
 * search, log, store, quick-create, vehicle update, represented/*.
 *
 * Other roles (scanner / loader / exit) are deliberately NOT accepted
 * here — those roles should never call /checkin/* and the principle of
 * least privilege keeps them out. If a future role legitimately needs
 * one of these endpoints, expand the regex consciously.
 *
 * Refs: discovered while planning Phase 2 — public intake page tried to
 * hit admin /checkin/* endpoints and got 302→/login on every fetch
 * because those routes were locked behind `auth` middleware.
 */
class EventDayOrAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Admin path — same check as the standard `auth` middleware.
        // If a real admin session exists, allow through unchanged.
        if ($request->user()) {
            return $next($request);
        }

        // Event-day intake path — accept any active intake session, for
        // any event. The 4-digit code per-event is the actual security
        // boundary; once a tablet has been authorized for one event,
        // letting it call shared /checkin/* endpoints is consistent
        // with the design (controllers downstream scope by `event_id`
        // in the request payload, so a session for event A submitting
        // a check-in for event B is naturally bound to event B's data).
        $session = $request->session();
        foreach ($session->all() as $key => $value) {
            if ($value === true && preg_match('/^ed_\d+_intake$/', (string) $key) === 1) {
                return $next($request);
            }
        }

        // Not authorized via either path.
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorised'], 401);
        }
        // route('login') instead of '/login' so subdirectory deployments
        // (e.g. http://localhost/Foodbank/public/) resolve correctly.
        return redirect()->route('login');
    }
}
