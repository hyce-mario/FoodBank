<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks down VolunteerGroup actions behind the existing volunteers.* perms.
 *
 * Pre-fix, every VolunteerGroupController action was unauthenticated /
 * unauthorized, and the three Form Requests returned `authorize() => true`,
 * so any logged-in viewer could create/edit/delete groups and sync members.
 * This file pins the contract:
 *   - unauthenticated → redirect to /login
 *   - authed but no perms → 403
 *   - has volunteers.view  → can list / show
 *   - has volunteers.create → can create
 *   - has volunteers.edit  → can update + manage members
 *   - has volunteers.delete → can delete
 *   - admin (* permission) → all of the above
 */
class VolunteerGroupAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $perms, string $roleName = 'TEST'): User
    {
        // Use unique role names per call so multiple roles can coexist in
        // a single test (e.g. when we need both an admin and a viewer).
        $unique = $roleName . '_' . uniqid();

        $role = Role::create([
            'name'         => $unique,
            'display_name' => $unique,
            'description'  => '',
        ]);

        foreach ($perms as $p) {
            RolePermission::create(['role_id' => $role->id, 'permission' => $p]);
        }

        return User::create([
            'name'              => $unique . ' user',
            'email'             => strtolower($unique) . '@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    // ─── Unauthenticated ─────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);

        $this->get(route('volunteer-groups.index'))->assertRedirect('/login');
        $this->get(route('volunteer-groups.show', $group))->assertRedirect('/login');
        $this->get(route('volunteer-groups.create'))->assertRedirect('/login');
        $this->post(route('volunteer-groups.store'), ['name' => 'X'])->assertRedirect('/login');
    }

    // ─── Authed, no perms → 403 ──────────────────────────────────────────────

    public function test_authed_user_without_permissions_is_forbidden(): void
    {
        $user = $this->userWithPermissions([]);
        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);

        $this->actingAs($user)->get(route('volunteer-groups.index'))->assertForbidden();
        $this->actingAs($user)->get(route('volunteer-groups.show', $group))->assertForbidden();
        $this->actingAs($user)->get(route('volunteer-groups.create'))->assertForbidden();
        $this->actingAs($user)->post(route('volunteer-groups.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->get(route('volunteer-groups.edit', $group))->assertForbidden();
        $this->actingAs($user)->put(route('volunteer-groups.update', $group), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete(route('volunteer-groups.destroy', $group))->assertForbidden();
        $this->actingAs($user)->get(route('volunteer-groups.members.edit', $group))->assertForbidden();
        $this->actingAs($user)->post(route('volunteer-groups.members.update', $group), [])->assertForbidden();
    }

    // ─── volunteers.view → read-only ─────────────────────────────────────────

    public function test_view_permission_can_list_and_show_only(): void
    {
        $user = $this->userWithPermissions(['volunteers.view']);
        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);

        $this->actingAs($user)->get(route('volunteer-groups.index'))->assertOk();
        $this->actingAs($user)->get(route('volunteer-groups.show', $group))->assertOk();

        $this->actingAs($user)->get(route('volunteer-groups.create'))->assertForbidden();
        $this->actingAs($user)->post(route('volunteer-groups.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->put(route('volunteer-groups.update', $group), ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete(route('volunteer-groups.destroy', $group))->assertForbidden();
        $this->actingAs($user)->post(route('volunteer-groups.members.update', $group), [])->assertForbidden();
    }

    // ─── volunteers.create → can create ──────────────────────────────────────

    public function test_create_permission_can_post_store(): void
    {
        $user = $this->userWithPermissions(['volunteers.view', 'volunteers.create']);

        $this->actingAs($user)->get(route('volunteer-groups.create'))->assertOk();
        $this->actingAs($user)
             ->post(route('volunteer-groups.store'), ['name' => 'New Group'])
             ->assertRedirect();

        $this->assertDatabaseHas('volunteer_groups', ['name' => 'New Group']);
    }

    // ─── volunteers.edit → can update + manage members ───────────────────────

    public function test_edit_permission_can_update_and_manage_members(): void
    {
        $user  = $this->userWithPermissions(['volunteers.view', 'volunteers.edit']);
        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);
        $vol   = Volunteer::create(['first_name' => 'A', 'last_name' => 'B']);

        $this->actingAs($user)->get(route('volunteer-groups.edit', $group))->assertOk();
        $this->actingAs($user)
             ->put(route('volunteer-groups.update', $group), ['name' => 'Renamed'])
             ->assertRedirect();
        $this->assertDatabaseHas('volunteer_groups', ['id' => $group->id, 'name' => 'Renamed']);

        $this->actingAs($user)->get(route('volunteer-groups.members.edit', $group))->assertOk();
        $this->actingAs($user)
             ->post(route('volunteer-groups.members.update', $group), ['volunteer_ids' => [$vol->id]])
             ->assertRedirect();
        $this->assertDatabaseHas('volunteer_group_memberships', [
            'group_id'     => $group->id,
            'volunteer_id' => $vol->id,
        ]);
    }

    // ─── volunteers.delete → can delete ──────────────────────────────────────

    public function test_delete_permission_can_destroy(): void
    {
        $user  = $this->userWithPermissions(['volunteers.view', 'volunteers.delete']);
        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);

        $this->actingAs($user)->delete(route('volunteer-groups.destroy', $group))->assertRedirect();
        $this->assertDatabaseMissing('volunteer_groups', ['id' => $group->id]);
    }

    // ─── Admin (*) → everything ──────────────────────────────────────────────

    public function test_admin_with_wildcard_permission_can_do_all_actions(): void
    {
        // Mirror the seeder: an ADMIN role has the '*' permission.
        $admin = $this->userWithPermissions(['*'], 'ADMIN');
        // Also force isAdmin() = true via the role name convention.
        $admin->role->update(['name' => 'ADMIN']);
        $admin->refresh();

        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);
        $vol   = Volunteer::create(['first_name' => 'A', 'last_name' => 'B']);

        $this->actingAs($admin)->get(route('volunteer-groups.index'))->assertOk();
        $this->actingAs($admin)->get(route('volunteer-groups.create'))->assertOk();
        $this->actingAs($admin)->post(route('volunteer-groups.store'), ['name' => 'AdminGrp'])->assertRedirect();
        $this->actingAs($admin)->get(route('volunteer-groups.edit', $group))->assertOk();
        $this->actingAs($admin)->put(route('volunteer-groups.update', $group), ['name' => 'X'])->assertRedirect();
        $this->actingAs($admin)->post(route('volunteer-groups.members.update', $group), ['volunteer_ids' => [$vol->id]])->assertRedirect();
        $this->actingAs($admin)->delete(route('volunteer-groups.destroy', $group))->assertRedirect();
    }
}
