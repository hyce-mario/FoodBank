<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP feature tests for the new event-day landing routes.
 *
 * The four role pages (/intake, /scanner, /loader, /exit) are bookmarked on
 * physical tablets at the event venue. The flow is fixed:
 *
 *   bookmark → /{role} (picker) → tap event → /{role}/{event} (auth code) →
 *     role view → logout → returns to /{role} (picker again)
 *
 * Picker MUST always render — never auto-skip on single event — so the
 * bookmark behaves the same every event day, every time. Logout MUST land
 * on /{role}, not /{role}/{event}, so the bookmarked URL is the durable
 * entry point regardless of which event was used.
 */
class EventDayLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_renders_picker_with_current_events(): void
    {
        $current = Event::create([
            'name'   => 'Saturday Drive-Through',
            'date'   => '2026-05-02',
            'status' => 'current',
            'lanes'  => 2,
        ]);

        $this->get('/loader')
             ->assertOk()
             ->assertSee($current->name)
             ->assertSee('Loader Station');
    }

    public function test_landing_renders_empty_state_when_no_current_events(): void
    {
        // Intentionally seed only an upcoming event — should be invisible to
        // the picker because authCodesActive() is false on upcoming.
        Event::create([
            'name'   => 'Next Saturday',
            'date'   => '2026-05-09',
            'status' => 'upcoming',
            'lanes'  => 1,
        ]);

        $this->get('/intake')
             ->assertOk()
             ->assertSee('No events running right now');
    }

    public function test_landing_excludes_past_and_upcoming_events(): void
    {
        $past = Event::create([
            'name'   => 'Past Event',
            'date'   => '2026-04-01',
            'status' => 'past',
            'lanes'  => 1,
        ]);
        $upcoming = Event::create([
            'name'   => 'Future Event',
            'date'   => '2026-06-01',
            'status' => 'upcoming',
            'lanes'  => 1,
        ]);
        $current = Event::create([
            'name'   => 'Today Event',
            'date'   => '2026-04-30',
            'status' => 'current',
            'lanes'  => 1,
        ]);

        $response = $this->get('/scanner')->assertOk();
        $response->assertSee($current->name);
        $response->assertDontSee($past->name);
        $response->assertDontSee($upcoming->name);
    }

    public function test_landing_does_NOT_auto_skip_on_single_current_event(): void
    {
        // Critical bookmark-flow contract: even with one current event the
        // picker MUST render, not redirect. Bookmarked tablets rely on the
        // landing URL behaving identically every visit.
        $only = Event::create([
            'name'   => 'Only Event Today',
            'date'   => '2026-04-30',
            'status' => 'current',
            'lanes'  => 1,
        ]);

        $this->get('/exit')
             ->assertOk()
             ->assertSee($only->name)
             // confirm we did NOT redirect to /exit/{id}
             ->assertDontSee('Enter the 4-digit access code');
    }

    public function test_invalid_role_returns_404(): void
    {
        // The foreach in routes/web.php registers exactly intake/scanner/loader/exit.
        // Anything else falls through to other route handlers or a 404. This
        // pins that /supervisor (or similar) isn't accidentally a landing page.
        $this->get('/supervisor')->assertNotFound();
    }

    public function test_all_four_roles_render_their_landing_page(): void
    {
        Event::create([
            'name'   => 'Active Event',
            'date'   => '2026-04-30',
            'status' => 'current',
            'lanes'  => 1,
        ]);

        foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
            $this->get("/{$role}")
                 ->assertOk()
                 ->assertSee('Active Event');
        }
    }

    public function test_logout_redirects_to_landing_not_to_event_specific_url(): void
    {
        $event = Event::create([
            'name'   => 'Logout Test Event',
            'date'   => '2026-04-30',
            'status' => 'current',
            'lanes'  => 1,
        ]);

        $this->withSession(["ed_{$event->id}_loader" => true])
             ->post("/loader/{$event->id}/out")
             ->assertRedirect('/loader');
    }

    public function test_logout_clears_only_its_own_session_key(): void
    {
        // Tablet authed as both intake and loader for the same event (rare
        // multi-purpose use) — logging out of one role must not log the other
        // out. Pin the session-key scoping.
        $event = Event::create([
            'name'   => 'Multi-Role Tablet',
            'date'   => '2026-04-30',
            'status' => 'current',
            'lanes'  => 1,
        ]);

        $this->withSession([
                "ed_{$event->id}_intake" => true,
                "ed_{$event->id}_loader" => true,
            ])
             ->post("/loader/{$event->id}/out")
             ->assertRedirect('/loader');

        // The intake session must still be live.
        $this->assertTrue(session()->get("ed_{$event->id}_intake") === true);
        $this->assertNull(session()->get("ed_{$event->id}_loader"));
    }
}
