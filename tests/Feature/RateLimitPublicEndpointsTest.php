<?php

namespace Tests\Feature;

use App\Http\Middleware\BotDefense;
use App\Models\Event;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Phase 3.1 — Rate limits on public POST endpoints.
 *
 * Verifies that the 6th attempt within a 1-minute window returns 429 on all
 * throttled public forms, and that the auth-code endpoint is similarly blocked
 * after 5 failed attempts.
 *
 * Using CACHE_STORE=array (set in phpunit.xml), each test starts with a clean
 * rate-limiter cache. RateLimiter::clear() is called after each test to prevent
 * any bleed between tests in the same process.
 *
 * Refs: AUDIT_REPORT.md Part 13 §3.1.
 */
class RateLimitPublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Event $currentEvent;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $this->currentEvent = Event::create([
            'name'   => 'Rate Limit Test Event',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);

        // Enable reviews so the /review/ endpoints are reachable
        SettingService::set('reviews.enable_reviews', '1');
    }

    protected function tearDown(): void
    {
        // Clear all rate limiter buckets so counters don't leak between tests.
        RateLimiter::clear('');
        parent::tearDown();
    }

    // ─── Review submission ────────────────────────────────────────────────────

    /**
     * The 6th POST to /review/ from the same IP returns 429.
     * Attempts 1–5 may succeed or fail validation — only the status code matters.
     */
    public function test_sixth_review_submission_returns_429(): void
    {
        $payload = [
            'event_id'    => $this->currentEvent->id,
            'rating'      => 5,
            'review_text' => 'Great event, thank you so much!',
            '_form_ts'    => BotDefense::signedTimestamp(time() - 5),
        ];

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post(route('public.reviews.store'), $payload);
            $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate-limited");
        }

        $this->post(route('public.reviews.store'), $payload)->assertStatus(429);
    }

    // ─── Public event registration ────────────────────────────────────────────

    /**
     * The 6th POST to /register/{event} from the same IP returns 429.
     */
    public function test_sixth_event_registration_returns_429(): void
    {
        $payload = [
            'first_name'     => 'Test',
            'last_name'      => 'User',
            'email'          => 'test@example.com',
            'children_count' => 0,
            'adults_count'   => 1,
            'seniors_count'  => 0,
            '_form_ts'       => BotDefense::signedTimestamp(time() - 5),
        ];

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post(route('public.submit', $this->currentEvent), $payload);
            $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate-limited");
        }

        $this->post(route('public.submit', $this->currentEvent), $payload)->assertStatus(429);
    }

    // ─── Auth-code endpoint ───────────────────────────────────────────────────

    /**
     * The 6th POST to /{role}/{event}/auth from the same IP for the same event
     * returns 429.
     */
    public function test_sixth_auth_code_attempt_returns_429(): void
    {
        $url = route('event-day.intake.auth', $this->currentEvent);

        for ($i = 1; $i <= 5; $i++) {
            // Wrong code — will get a redirect-back-with-error, but that's fine;
            // we only care that it's NOT 429.
            $response = $this->post($url, ['code' => '0000']);
            $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate-limited");
        }

        $this->post($url, ['code' => '0000'])->assertStatus(429);
    }

    // ─── Volunteer check-in ───────────────────────────────────────────────────

    /**
     * The 6th POST to /volunteer-checkin/checkin returns 429.
     */
    public function test_sixth_volunteer_checkin_attempt_returns_429(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson(route('volunteer-checkin.checkin'), ['volunteer_id' => 99999]);
            $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate-limited");
        }

        $this->postJson(route('volunteer-checkin.checkin'), ['volunteer_id' => 99999])->assertStatus(429);
    }
}
