<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4.1 — Resource policies for Household, Event, and Volunteer.
 *
 * Policies bridge the project's dot-notation permission system into Laravel's
 * Gate/Policy infrastructure so controllers can call $this->authorize().
 *
 * Primary acceptance criterion: INTAKE-role user gets 403 trying to PUT
 * /households/{id} (no households.edit permission).
 *
 * Refs: AUDIT_REPORT.md Part 13 §4.1.
 */
class ResourcePoliciesTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $intakeUser;
    private User $viewOnlyUser;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        // ADMIN — wildcard *
        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'Administrator', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->adminUser = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('p'), 'role_id' => $adminRole->id, 'email_verified_at' => now(),
        ]);

        // INTAKE — households.view + checkin.view + checkin.scan + events.view
        $intakeRole = Role::create(['name' => 'INTAKE', 'display_name' => 'Intake', 'description' => '']);
        foreach (['households.view', 'checkin.view', 'checkin.scan', 'events.view'] as $perm) {
            RolePermission::create(['role_id' => $intakeRole->id, 'permission' => $perm]);
        }
        $this->intakeUser = User::create([
            'name' => 'Intake', 'email' => 'intake@test.local',
            'password' => bcrypt('p'), 'role_id' => $intakeRole->id, 'email_verified_at' => now(),
        ]);

        // VIEW-ONLY — households.view only
        $viewRole = Role::create(['name' => 'REPORTS', 'display_name' => 'Reports', 'description' => '']);
        RolePermission::create(['role_id' => $viewRole->id, 'permission' => 'households.view']);
        $this->viewOnlyUser = User::create([
            'name' => 'Reports', 'email' => 'reports@test.local',
            'password' => bcrypt('p'), 'role_id' => $viewRole->id, 'email_verified_at' => now(),
        ]);
    }

    private function makeHousehold(): Household
    {
        return Household::create([
            'household_number' => 'P0001',
            'first_name'       => 'Test',
            'last_name'        => 'Policy',
            'household_size'   => 1,
            'adults_count'     => 1,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);
    }

    // ─── Household policy ─────────────────────────────────────────────────────

    /** Acceptance criterion: INTAKE-role → 403 on PUT /households/{id}. */
    public function test_intake_user_cannot_update_household(): void
    {
        $household = $this->makeHousehold();
        $this->actingAs($this->intakeUser)
             ->put(route('households.update', $household), [
                 'first_name' => 'Changed', 'last_name' => 'Name',
                 'household_size' => 1, 'adults_count' => 1,
             ])
             ->assertStatus(403);
    }

    public function test_intake_user_cannot_delete_household(): void
    {
        $household = $this->makeHousehold();
        $this->actingAs($this->intakeUser)
             ->delete(route('households.destroy', $household))
             ->assertStatus(403);
    }

    public function test_intake_user_can_view_household(): void
    {
        $household = $this->makeHousehold();
        $this->actingAs($this->intakeUser)
             ->get(route('households.show', $household))
             ->assertStatus(200);
    }

    public function test_admin_can_update_household(): void
    {
        $household = $this->makeHousehold();
        $this->actingAs($this->adminUser)
             ->put(route('households.update', $household), [
                 'first_name' => 'Changed', 'last_name' => 'Name',
                 'household_size' => 1, 'adults_count' => 1,
             ])
             ->assertRedirect();
    }

    public function test_view_only_user_cannot_create_household(): void
    {
        $this->actingAs($this->viewOnlyUser)
             ->post(route('households.store'), [
                 'first_name' => 'Test', 'last_name' => 'New',
                 'household_size' => 1, 'adults_count' => 1,
             ])
             ->assertStatus(403);
    }

    // ─── Event policy ─────────────────────────────────────────────────────────

    public function test_intake_user_cannot_create_event(): void
    {
        $this->actingAs($this->intakeUser)
             ->post(route('events.store'), [
                 'name' => 'New Event', 'date' => '2026-07-01', 'lanes' => 1,
             ])
             ->assertStatus(403);
    }

    public function test_intake_user_can_view_event_list(): void
    {
        $this->actingAs($this->intakeUser)
             ->get(route('events.index'))
             ->assertStatus(200);
    }

    // ─── Volunteer policy ─────────────────────────────────────────────────────

    public function test_intake_user_cannot_delete_volunteer(): void
    {
        $volunteer = Volunteer::create([
            'first_name' => 'Vol', 'last_name' => 'Test', 'role' => 'Loader',
        ]);
        $this->actingAs($this->intakeUser)
             ->delete(route('volunteers.destroy', $volunteer))
             ->assertStatus(403);
    }
}
