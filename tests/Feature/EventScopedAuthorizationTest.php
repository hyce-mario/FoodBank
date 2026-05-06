<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventReview;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 2 — Event-scoped routes (volunteer-checkins + media + reviews) +
 * RoleSeeder demo roles (FINANCE + WAREHOUSE).
 *
 * Layering:
 *   - POST /events/{e}/volunteer-checkins{,/bulk,/bulk-checkout}      → volunteers.edit
 *   - PATCH /events/{e}/volunteer-checkins/{c}/checkout               → volunteers.edit
 *   - StoreEventVolunteerCheckInRequest::authorize                    → volunteers.edit (defense in depth)
 *   - POST /events/{e}/media + DELETE /events/{e}/media/{m}           → events.edit
 *   - GET /reviews                                                    → reviews.view
 *   - PATCH /reviews/{r}/toggle-visibility                            → reviews.moderate
 *
 * RoleSeeder additionally seeds:
 *   - FINANCE: finance.{view,create,edit,delete} + finance_reports.{view,export}
 *   - WAREHOUSE: inventory.{view,edit} + purchase_orders.{view,create,receive,cancel}
 */
class EventScopedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeRole(string $name, array $perms): Role
    {
        $role = Role::create(['name' => $name, 'display_name' => $name, 'description' => '']);
        foreach ($perms as $p) {
            RolePermission::create(['role_id' => $role->id, 'permission' => $p]);
        }
        return $role;
    }

    private function makeUser(Role $role, string $email): User
    {
        return User::create([
            'name'              => $role->name,
            'email'             => $email,
            'password'          => bcrypt('password'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    private function makeEvent(): Event
    {
        return Event::create(['name' => 'Test Event', 'date' => '2026-06-01', 'lanes' => 1]);
    }

    // ─── Volunteer check-ins (event-scoped) ──────────────────────────────────

    public function test_user_without_volunteers_edit_blocked_at_event_volunteer_checkin(): void
    {
        $event = $this->makeEvent();
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $response = $this->actingAs($intake)
                         ->post("/events/{$event->id}/volunteer-checkins", []);
        $response->assertForbidden();
    }

    public function test_volunteers_edit_grantee_passes_event_volunteer_checkin_middleware(): void
    {
        $event = $this->makeEvent();
        $manager = $this->makeUser(
            $this->makeRole('VOL_MANAGER', ['volunteers.view', 'volunteers.edit']),
            'vm@test.local'
        );

        // Empty payload trips validation (422), not 403 — proves middleware
        // and FormRequest authorize both pass.
        $response = $this->actingAs($manager)
                         ->post("/events/{$event->id}/volunteer-checkins", []);
        $this->assertNotSame(403, $response->status());
    }

    // ─── Event media ─────────────────────────────────────────────────────────

    public function test_user_without_events_edit_cannot_upload_media(): void
    {
        $event = $this->makeEvent();
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $response = $this->actingAs($intake)
                         ->post("/events/{$event->id}/media", []);
        $response->assertForbidden();
    }

    public function test_user_without_events_edit_cannot_delete_media(): void
    {
        // Media row needs to exist or route model binding 404s before the
        // permission middleware runs.
        $event = $this->makeEvent();
        $media = \App\Models\EventMedia::create([
            'event_id'   => $event->id,
            'name'       => 'test.jpg',
            'path'       => 'event-media/test.jpg',
            'mime_type'  => 'image/jpeg',
            'size'       => 1024,
            'type'       => 'image',
            'sort_order' => 1,
        ]);

        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $response = $this->actingAs($intake)
                         ->delete("/events/{$event->id}/media/{$media->id}");
        $response->assertForbidden();
        $this->assertDatabaseHas('event_media', ['id' => $media->id]);
    }

    // ─── Reviews ─────────────────────────────────────────────────────────────

    public function test_user_without_reviews_view_blocked_at_reviews_index(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/reviews')->assertForbidden();
    }

    public function test_reviews_view_grantee_can_index(): void
    {
        $moderator = $this->makeUser($this->makeRole('REVIEWER', ['reviews.view']), 'rev@test.local');
        $this->actingAs($moderator)->get('/reviews')->assertOk();
    }

    public function test_reviews_view_alone_cannot_toggle_visibility(): void
    {
        $event = $this->makeEvent();
        $review = EventReview::create([
            'event_id'      => $event->id,
            'reviewer_name' => 'Anon',
            'rating'        => 5,
            'review_text'   => 'Great event',
            'is_visible'    => true,
        ]);
        $reader = $this->makeUser($this->makeRole('READER', ['reviews.view']), 'rd@test.local');

        $response = $this->actingAs($reader)
                         ->patch("/reviews/{$review->id}/toggle-visibility");
        $response->assertForbidden();
        $review->refresh();
        $this->assertTrue($review->is_visible, 'visibility should be unchanged');
    }

    public function test_reviews_moderate_grantee_can_toggle_visibility(): void
    {
        $event = $this->makeEvent();
        $review = EventReview::create([
            'event_id'      => $event->id,
            'reviewer_name' => 'Anon',
            'rating'        => 5,
            'review_text'   => 'Great event',
            'is_visible'    => true,
        ]);
        $moderator = $this->makeUser(
            $this->makeRole('MODERATOR', ['reviews.view', 'reviews.moderate']),
            'mod@test.local'
        );

        $response = $this->actingAs($moderator)
                         ->patch("/reviews/{$review->id}/toggle-visibility");
        $response->assertRedirect();
        $review->refresh();
        $this->assertFalse($review->is_visible);
    }

    // ─── RoleSeeder demo roles ───────────────────────────────────────────────

    public function test_role_seeder_seeds_finance_role(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $finance = Role::where('name', 'FINANCE')->first();
        $this->assertNotNull($finance, 'FINANCE role should be seeded');

        $perms = $finance->permissions->pluck('permission')->toArray();
        $this->assertContains('finance.view', $perms);
        $this->assertContains('finance.create', $perms);
        $this->assertContains('finance.edit', $perms);
        $this->assertContains('finance.delete', $perms);
        $this->assertContains('finance_reports.view', $perms);
        $this->assertContains('finance_reports.export', $perms);
    }

    public function test_role_seeder_seeds_warehouse_role(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $warehouse = Role::where('name', 'WAREHOUSE')->first();
        $this->assertNotNull($warehouse);

        $perms = $warehouse->permissions->pluck('permission')->toArray();
        $this->assertContains('inventory.view', $perms);
        $this->assertContains('inventory.edit', $perms);
        $this->assertContains('purchase_orders.view', $perms);
        $this->assertContains('purchase_orders.create', $perms);
        $this->assertContains('purchase_orders.receive', $perms);
        $this->assertContains('purchase_orders.cancel', $perms);
    }

    // ─── Unauth ──────────────────────────────────────────────────────────────

    public function test_unauthenticated_event_scoped_routes_redirect_to_login(): void
    {
        $event = $this->makeEvent();
        $this->get('/reviews')->assertRedirect(route('login'));
        $this->post("/events/{$event->id}/volunteer-checkins", [])->assertRedirect(route('login'));
        $this->post("/events/{$event->id}/media", [])->assertRedirect(route('login'));
    }
}
