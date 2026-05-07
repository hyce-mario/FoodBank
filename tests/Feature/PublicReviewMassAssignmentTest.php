<?php

namespace Tests\Feature;

use App\Http\Middleware\BotDefense;
use App\Models\Event;
use App\Models\EventReview;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.3 — Mass-assignment cleanup for EventReview.
 *
 * is_visible was in EventReview::$fillable, allowing a public POST to /review/
 * with is_visible=1 to potentially bypass the moderation setting. Removing it
 * from $fillable and using direct property assignment in controllers closes the
 * gap. The acceptance criterion: "Public POST /review with is_visible=1 is
 * ignored."
 *
 * Refs: AUDIT_REPORT.md Part 13 §3.3.
 */
class PublicReviewMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $this->event = Event::create([
            'name'   => 'Review Mass Assignment Test',
            'date'   => now()->subDay()->toDateString(),
            'status' => 'past',
            'lanes'  => 1,
        ]);

        SettingService::set('reviews.enable_reviews', '1');
        SettingService::set('reviews.min_review_length', '5');
        SettingService::set('reviews.max_review_length', '2000');
        SettingService::set('reviews.email_optional', '1');
    }

    // ─── Test 1 — is_visible in POST body is ignored ──────────────────────────

    /**
     * Acceptance criterion: a public POST to /review/ with is_visible=1 in the
     * body must NOT result in a visible review when the moderation setting is on.
     */
    public function test_public_post_with_is_visible_true_is_ignored_when_moderation_on(): void
    {
        SettingService::set('reviews.require_moderation', '1');
        SettingService::set('reviews.default_visibility', 'hidden');

        $this->post(route('public.reviews.store'), [
            'event_id'    => $this->event->id,
            'rating'      => 5,
            'review_text' => 'Great event overall!',
            'is_visible'  => '1',  // attacker trying to bypass moderation
            '_form_ts'    => BotDefense::signedTimestamp(time() - 5),
        ])->assertRedirect();

        $review = EventReview::first();
        $this->assertNotNull($review);
        $this->assertFalse($review->is_visible, 'is_visible must be false when moderation is on regardless of POST body');
    }

    // ─── Test 2 — Default visibility from settings still works ───────────────

    /**
     * Without moderation, the review should be visible per the default setting —
     * the fix must not break the legitimate code path.
     */
    public function test_review_is_visible_when_moderation_off_and_default_visible(): void
    {
        SettingService::set('reviews.require_moderation', '0');
        SettingService::set('reviews.default_visibility', 'visible');

        $this->post(route('public.reviews.store'), [
            'event_id'    => $this->event->id,
            'rating'      => 4,
            'review_text' => 'Very helpful staff!',
            '_form_ts'    => BotDefense::signedTimestamp(time() - 5),
        ])->assertRedirect();

        $this->assertTrue(EventReview::first()->is_visible);
    }

    // ─── Test 3 — is_visible removed from $fillable ───────────────────────────

    /**
     * Direct EventReview::create() with is_visible in the array must not set
     * the field — mass-assignment protection must block it.
     */
    public function test_is_visible_is_not_mass_assignable(): void
    {
        $review = EventReview::create([
            'event_id'    => $this->event->id,
            'rating'      => 3,
            'review_text' => 'Good but could improve.',
            'is_visible'  => true,
        ]);

        // DB default is true, but mass-assignment must be blocked. The review
        // should still be created; is_visible falls back to the DB default (true).
        // The key assertion is that explicitly passing is_visible via create()
        // is NOT honoured — if the model changes this in future, the test flags it.
        $this->assertFalse(
            in_array('is_visible', (new EventReview())->getFillable()),
            'is_visible must not be in $fillable'
        );
    }
}
