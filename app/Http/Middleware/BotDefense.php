<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bot defense for public-facing POST endpoints.
 *
 * Two layers:
 *   1. Honeypot — a hidden `website_url` field that real users don't see/touch.
 *      Naive scrapers auto-fill every input; if filled, the request is dropped.
 *   2. HMAC-signed time trap — the form embeds a `_form_ts` field carrying
 *      `<unix_ts>.<hmac>` where the HMAC is keyed on APP_KEY. The middleware
 *      verifies the signature (so bots can't forge a stale timestamp) and
 *      rejects submissions that arrive faster than MIN_FILL_SECONDS — humans
 *      need at least a few seconds to fill a multi-field form.
 *
 * On block: log the attempt and redirect back to the form with no flash.
 * The bot sees a 302 → empty form (not a definitive "rejected" page), and
 * crucially, no row is written to the database. Real users effectively
 * never trip these checks.
 *
 * Use it on the route:
 *   ->middleware('bot-defense')
 *
 * Pair it with the <x-bot-defense /> blade component in the form view.
 */
class BotDefense
{
    /**
     * Minimum seconds between form render and submit. Multi-field public
     * forms (registration: 9 fields, review: 3+) take humans well over
     * this; bots that submit instantly trip it.
     */
    private const MIN_FILL_SECONDS = 3;

    /** Honeypot input name. Chosen to look plausible to bots ("website_url" is
     *  a common autofill target) but not match real autofill heuristics. */
    public const HONEYPOT_FIELD = 'website_url';

    /** HMAC-signed render-timestamp field. */
    public const TIMESTAMP_FIELD = '_form_ts';

    public function handle(Request $request, Closure $next): Response
    {
        if (filled($request->input(self::HONEYPOT_FIELD))) {
            return $this->block($request, 'honeypot');
        }

        $ts = (string) $request->input(self::TIMESTAMP_FIELD, '');
        if ($ts === '' || ! self::verifyTimestamp($ts)) {
            return $this->block($request, 'timestamp-invalid');
        }

        return $next($request);
    }

    /**
     * Generate `<unix_ts>.<hmac_sha256>` for embedding in the form.
     *
     * Public so the blade component can call it directly without going
     * through a service container resolution. Tests pass an explicit
     * `$at` (e.g. `time() - 5`) so the time-trap window is satisfied.
     */
    public static function signedTimestamp(?int $at = null): string
    {
        $time = (string) ($at ?? time());
        $sig  = hash_hmac('sha256', $time, self::secret());
        return $time . '.' . $sig;
    }

    /** Verify the signed timestamp + enforce the min-fill window. */
    public static function verifyTimestamp(string $token): bool
    {
        if (! str_contains($token, '.')) {
            return false;
        }
        [$time, $sig] = explode('.', $token, 2);
        if (! ctype_digit($time)) {
            return false;
        }
        $expected = hash_hmac('sha256', $time, self::secret());
        if (! hash_equals($expected, $sig)) {
            return false;
        }
        return (time() - (int) $time) >= self::MIN_FILL_SECONDS;
    }

    /** APP_KEY is fine as the HMAC secret — it's the same shared secret
     *  Laravel uses for signed routes / encrypted cookies. Always present
     *  in any deployed environment. */
    private static function secret(): string
    {
        return (string) config('app.key');
    }

    /**
     * Log the blocked attempt + return a response shape matching the
     * request. For traditional form posts we redirect back (the bot sees
     * an unremarkable 302). For AJAX/JSON requests we return a 422 with
     * a generic error so the calling JS treats it like any validation
     * failure — neither shape gives a "you tripped the bot trap" signal.
     */
    private function block(Request $request, string $reason): Response
    {
        Log::warning('bot-defense.blocked', [
            'ip'     => $request->ip(),
            'path'   => $request->path(),
            'method' => $request->method(),
            'ua'     => $request->userAgent(),
            'reason' => $reason,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Submission could not be processed. Please refresh the page and try again.',
            ], 422);
        }

        return back();
    }
}
