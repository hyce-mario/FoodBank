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
 * Pins the Volunteer Show page "Add to group" quick-action endpoint.
 *
 *   POST /volunteers/{volunteer}/groups   group_id=N
 *
 * Authorized via VolunteerGroupPolicy::manageMembers (same ability the
 * full member-sync UI uses, which means anyone with volunteers.edit).
 *
 * Re-attaching the same group is idempotent — the pivot's UNIQUE
 * (volunteer_id, group_id) constraint guarantees no duplicate row, and
 * syncWithoutDetaching matches that contract.
 */
class VolunteerAttachGroupTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermissions(array $perms): User
    {
        $unique = 'TEST_' . uniqid();
        $role = Role::create([
            'name' => $unique, 'display_name' => $unique, 'description' => '',
        ]);
        foreach ($perms as $p) {
            RolePermission::create(['role_id' => $role->id, 'permission' => $p]);
        }
        return User::create([
            'name' => $unique, 'email' => strtolower($unique) . '@test.local',
            'password' => bcrypt('p'), 'role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_unauthenticated_is_redirected_to_login(): void
    {
        $vol   = Volunteer::create(['first_name' => 'A', 'last_name' => 'B']);
        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);

        $this->post(route('volunteers.groups.attach', $vol), ['group_id' => $group->id])
             ->assertRedirect('/login');
    }

    public function test_authed_without_perms_is_forbidden(): void
    {
        $user  = $this->userWithPermissions(['volunteers.view']);
        $vol   = Volunteer::create(['first_name' => 'A', 'last_name' => 'B']);
        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);

        $this->actingAs($user)
             ->post(route('volunteers.groups.attach', $vol), ['group_id' => $group->id])
             ->assertForbidden();

        $this->assertDatabaseMissing('volunteer_group_memberships', [
            'volunteer_id' => $vol->id,
            'group_id'     => $group->id,
        ]);
    }

    public function test_edit_perm_can_attach_volunteer_to_group(): void
    {
        $user  = $this->userWithPermissions(['volunteers.view', 'volunteers.edit']);
        $vol   = Volunteer::create(['first_name' => 'A', 'last_name' => 'B']);
        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);

        $this->actingAs($user)
             ->post(route('volunteers.groups.attach', $vol), ['group_id' => $group->id])
             ->assertRedirect(route('volunteers.show', $vol));

        $this->assertDatabaseHas('volunteer_group_memberships', [
            'volunteer_id' => $vol->id,
            'group_id'     => $group->id,
        ]);
    }

    public function test_attaching_same_group_twice_is_idempotent(): void
    {
        $user  = $this->userWithPermissions(['volunteers.view', 'volunteers.edit']);
        $vol   = Volunteer::create(['first_name' => 'A', 'last_name' => 'B']);
        $group = VolunteerGroup::create(['name' => 'Saturday Crew']);

        $this->actingAs($user)
             ->post(route('volunteers.groups.attach', $vol), ['group_id' => $group->id])
             ->assertRedirect();

        // Second submission — should not 500, should not duplicate the row.
        $this->actingAs($user)
             ->post(route('volunteers.groups.attach', $vol), ['group_id' => $group->id])
             ->assertRedirect();

        $this->assertSame(
            1,
            \DB::table('volunteer_group_memberships')
                ->where('volunteer_id', $vol->id)
                ->where('group_id', $group->id)
                ->count(),
            'Re-attaching must not insert a second membership row',
        );
    }

    public function test_validates_group_id_required_and_exists(): void
    {
        $user = $this->userWithPermissions(['volunteers.view', 'volunteers.edit']);
        $vol  = Volunteer::create(['first_name' => 'A', 'last_name' => 'B']);

        // Missing group_id
        $this->actingAs($user)
             ->from(route('volunteers.show', $vol))
             ->post(route('volunteers.groups.attach', $vol), [])
             ->assertSessionHasErrors('group_id');

        // Non-existent group_id
        $this->actingAs($user)
             ->from(route('volunteers.show', $vol))
             ->post(route('volunteers.groups.attach', $vol), ['group_id' => 999999])
             ->assertSessionHasErrors('group_id');
    }
}
