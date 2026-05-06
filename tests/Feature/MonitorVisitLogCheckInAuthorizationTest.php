<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 2 — Visit Monitor + Visit Log + CheckIn admin routes.
 * Reuses the existing checkin.{view,scan} permission keys (the catalog
 * already had these pre-Tier-1; they were never wired up to anything).
 *
 * Layering:
 *   - GET /checkin{,/queue}                   → checkin.view
 *   - POST /checkin/quick-add                 → checkin.scan
 *   - PATCH /checkin/{visit}/done             → checkin.scan
 *   - GET /monitor                            → checkin.view
 *   - GET /monitor/{event}/data               → checkin.view
 *   - POST /monitor/{event}/reorder           → checkin.scan
 *   - PATCH /monitor/{event}/visits/{v}/transition → checkin.scan
 *   - GET /visit-log{,/print,/export}         → checkin.view
 *
 * Note: the public-shared /checkin POST + /checkin/search etc. that sit in
 * the event-day-or-auth middleware group are intentionally NOT covered
 * here — they auth via event-day session (kiosk QR/code flow), not user
 * permission, and adding permission middleware would break the public
 * intake kiosk path.
 */
class MonitorVisitLogCheckInAuthorizationTest extends TestCase
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

    // ─── Reads — checkin.view ────────────────────────────────────────────────

    public function test_user_without_checkin_view_blocked_at_admin_checkin(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/checkin')->assertForbidden();
    }

    public function test_user_without_checkin_view_blocked_at_monitor(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/monitor')->assertForbidden();
    }

    public function test_user_without_checkin_view_blocked_at_visit_log(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');
        $this->actingAs($intake)->get('/visit-log')->assertForbidden();
    }

    public function test_checkin_view_grantee_can_see_admin_checkin(): void
    {
        $supervisor = $this->makeUser($this->makeRole('SUPERVISOR', ['checkin.view']), 'sup@test.local');
        $this->actingAs($supervisor)->get('/checkin')->assertOk();
    }

    public function test_checkin_view_grantee_can_see_monitor(): void
    {
        $supervisor = $this->makeUser($this->makeRole('SUPERVISOR', ['checkin.view']), 'sup@test.local');
        $this->actingAs($supervisor)->get('/monitor')->assertOk();
    }

    public function test_checkin_view_grantee_passes_visit_log_middleware(): void
    {
        // Visit-log render needs an event in scope to compute KPIs; we only
        // care about the route middleware here so we assert "not 403".
        $supervisor = $this->makeUser($this->makeRole('SUPERVISOR', ['checkin.view']), 'sup@test.local');
        $response = $this->actingAs($supervisor)->get('/visit-log');
        $this->assertNotSame(403, $response->status());
    }

    // ─── Writes — checkin.scan ───────────────────────────────────────────────

    public function test_checkin_view_alone_cannot_quick_add(): void
    {
        $supervisor = $this->makeUser($this->makeRole('SUPERVISOR', ['checkin.view']), 'sup@test.local');

        $response = $this->actingAs($supervisor)->post('/checkin/quick-add', []);
        $response->assertForbidden();
    }

    public function test_checkin_view_alone_cannot_reorder_monitor(): void
    {
        $event = $this->makeEvent();
        $supervisor = $this->makeUser($this->makeRole('SUPERVISOR', ['checkin.view']), 'sup@test.local');

        $response = $this->actingAs($supervisor)
                         ->postJson("/monitor/{$event->id}/reorder", ['moves' => []]);
        $response->assertForbidden();
    }

    public function test_checkin_scan_grantee_passes_quick_add_middleware(): void
    {
        $scanner = $this->makeUser(
            $this->makeRole('SCANNER', ['checkin.view', 'checkin.scan']),
            'scan@test.local'
        );

        $response = $this->actingAs($scanner)->post('/checkin/quick-add', []);
        // Will fail validation (missing fields) but not the permission gate.
        $this->assertNotSame(403, $response->status());
    }

    // ─── Admin wildcard + unauth ─────────────────────────────────────────────

    public function test_admin_wildcard_can_access_all(): void
    {
        $admin = $this->makeUser($this->makeRole('ADMIN', ['*']), 'admin@test.local');

        $this->actingAs($admin)->get('/checkin')->assertOk();
        $this->actingAs($admin)->get('/monitor')->assertOk();

        // Visit-log render assumes an event in scope; we only assert the
        // middleware passes (status != 403).
        $r = $this->actingAs($admin)->get('/visit-log');
        $this->assertNotSame(403, $r->status());
    }

    public function test_unauthenticated_admin_routes_redirect_to_login(): void
    {
        $event = $this->makeEvent();
        $this->get('/checkin')->assertRedirect(route('login'));
        $this->get('/monitor')->assertRedirect(route('login'));
        $this->get('/visit-log')->assertRedirect(route('login'));
    }
}
