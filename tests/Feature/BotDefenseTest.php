<?php

namespace Tests\Feature;

use App\Http\Middleware\BotDefense;
use App\Models\Event;
use App\Models\EventPreRegistration;
use App\Models\EventReview;
use App\Models\Household;
use App\Models\Volunteer;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * BotDefense middleware on the two public-write endpoints — verifies the
 * honeypot + HMAC time trap silently drop bot submissions while letting
 * properly-signed human submissions through.
 *
 * Endpoints under test:
 *   POST /register/{event}  (PublicEventController@submit)
 *   POST /review            (PublicReviewController@store)
 */
class BotDefenseTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();
        RateLimiter::clear('');

        $this->event = Event::create([
            'name'   => 'Bot Defense Test Event',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);

        SettingService::set('reviews.enable_reviews', '1');
        SettingService::set('reviews.min_review_length', '5');
        SettingService::set('reviews.max_review_length', '2000');
        SettingService::set('reviews.email_optional', '1');
    }

    // ─── Honeypot ─────────────────────────────────────────────────────────────

    public function test_honeypot_filled_blocks_review_submission(): void
    {
        $this->post(route('public.reviews.store'), [
            'event_id'                    => $this->event->id,
            'rating'                      => 5,
            'review_text'                 => 'Real-looking spam content',
            '_form_ts'                    => BotDefense::signedTimestamp(time() - 5),
            BotDefense::HONEYPOT_FIELD    => 'http://spammer.example/promo',
        ])->assertRedirect();

        $this->assertSame(0, EventReview::count(), 'Bot submission must not create a review');
    }

    public function test_honeypot_filled_blocks_event_registration(): void
    {
        $this->post(route('public.submit', $this->event), [
            'first_name'                  => 'Spam',
            'last_name'                   => 'Bot',
            'email'                       => 'bot@example.com',
            'children_count'              => 0,
            'adults_count'                => 1,
            'seniors_count'               => 0,
            '_form_ts'                    => BotDefense::signedTimestamp(time() - 5),
            BotDefense::HONEYPOT_FIELD    => 'autofilled-by-bot',
        ])->assertRedirect();

        $this->assertSame(0, EventPreRegistration::count(), 'Bot submission must not create a pre-registration');
        $this->assertSame(0, Household::count(), 'Bot submission must not create a household');
    }

    // ─── Time trap ────────────────────────────────────────────────────────────

    public function test_missing_timestamp_blocks_review_submission(): void
    {
        $this->post(route('public.reviews.store'), [
            'event_id'    => $this->event->id,
            'rating'      => 4,
            'review_text' => 'Content without _form_ts',
        ])->assertRedirect();

        $this->assertSame(0, EventReview::count());
    }

    public function test_invalid_hmac_signature_blocks_review_submission(): void
    {
        $this->post(route('public.reviews.store'), [
            'event_id'    => $this->event->id,
            'rating'      => 4,
            'review_text' => 'Content with forged _form_ts',
            '_form_ts'    => (string) (time() - 5) . '.deadbeef0000',
        ])->assertRedirect();

        $this->assertSame(0, EventReview::count());
    }

    public function test_too_recent_timestamp_blocks_review_submission(): void
    {
        // Render-stamp is "now" — middleware enforces ≥3s between render and
        // submit, so the same-second submit must be rejected.
        $this->post(route('public.reviews.store'), [
            'event_id'    => $this->event->id,
            'rating'      => 4,
            'review_text' => 'Submitted instantly after render',
            '_form_ts'    => BotDefense::signedTimestamp(),
        ])->assertRedirect();

        $this->assertSame(0, EventReview::count());
    }

    public function test_too_recent_timestamp_blocks_event_registration(): void
    {
        $this->post(route('public.submit', $this->event), [
            'first_name'     => 'Fast',
            'last_name'      => 'Bot',
            'email'          => 'fast@example.com',
            'children_count' => 0,
            'adults_count'   => 1,
            'seniors_count'  => 0,
            '_form_ts'       => BotDefense::signedTimestamp(),
        ])->assertRedirect();

        $this->assertSame(0, EventPreRegistration::count());
        $this->assertSame(0, Household::count());
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_valid_review_submission_succeeds(): void
    {
        $this->post(route('public.reviews.store'), [
            'event_id'    => $this->event->id,
            'rating'      => 5,
            'review_text' => 'Real human review of the event',
            '_form_ts'    => BotDefense::signedTimestamp(time() - 5),
        ])->assertRedirect();

        $this->assertSame(1, EventReview::count());
    }

    public function test_valid_event_registration_succeeds(): void
    {
        $this->post(route('public.submit', $this->event), [
            'first_name'     => 'Real',
            'last_name'      => 'Human',
            'email'          => 'human@example.com',
            'children_count' => 1,
            'adults_count'   => 2,
            'seniors_count'  => 0,
            '_form_ts'       => BotDefense::signedTimestamp(time() - 5),
        ])->assertRedirect();

        $this->assertSame(1, EventPreRegistration::count());
        $this->assertSame(1, Household::count());
    }

    // ─── Volunteer signup (JSON / AJAX path) ──────────────────────────────────

    public function test_honeypot_filled_blocks_volunteer_signup_with_json_response(): void
    {
        $response = $this->postJson(route('volunteer-checkin.signup'), [
            'first_name'                  => 'Spam',
            'last_name'                   => 'Bot',
            'phone'                       => '5551234567',
            '_form_ts'                    => BotDefense::signedTimestamp(time() - 5),
            BotDefense::HONEYPOT_FIELD    => 'http://spam.example/promo',
        ]);

        // JSON path must return 422 with the generic shape — never a 302
        // redirect (that would break the kiosk JS).
        $response->assertStatus(422)->assertJson(['ok' => false]);

        $this->assertSame(0, Volunteer::count(), 'Bot signup must not create a volunteer record');
    }

    public function test_too_recent_timestamp_blocks_volunteer_signup_with_json_response(): void
    {
        $response = $this->postJson(route('volunteer-checkin.signup'), [
            'first_name' => 'Fast',
            'last_name'  => 'Bot',
            'phone'      => '5559999999',
            '_form_ts'   => BotDefense::signedTimestamp(),
        ]);

        $response->assertStatus(422)->assertJson(['ok' => false]);

        $this->assertSame(0, Volunteer::count());
    }

    // ─── Logging ──────────────────────────────────────────────────────────────

    public function test_blocked_attempts_are_logged(): void
    {
        Log::spy();

        $this->post(route('public.reviews.store'), [
            'event_id'                    => $this->event->id,
            'rating'                      => 5,
            'review_text'                 => 'Bot content',
            '_form_ts'                    => BotDefense::signedTimestamp(time() - 5),
            BotDefense::HONEYPOT_FIELD    => 'http://spam.example',
        ]);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message, $context) =>
                $message === 'bot-defense.blocked'
                && ($context['reason'] ?? null) === 'honeypot'
            );
    }
}
