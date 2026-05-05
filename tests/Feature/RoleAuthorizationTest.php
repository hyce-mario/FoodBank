<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tier 2 — Roles module authorization. The CRITICAL pre-fix state:
 *   - StoreRoleRequest::authorize and UpdateRoleRequest::authorize both
 *     hard-coded `return true`
 *   - RoleController::destroy had no auth check
 *   - The /roles route group sat behind `auth` only
 *
 * Combined effect: any authenticated user could POST /roles with
 * permissions=['*'] to mint an admin-equivalent role, then PUT /users/{self}
 * to assign it. UserController::update line 97 keeps role assignment
 * ADMIN-only as the second line of defense, but THIS is the first line —
 * preventing the malicious role from being created in the first place.
 */
class RoleAuthorizationTest extends TestCase
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

    // ─── Headline: prevent privilege escalation via role creation ────────────

    public function test_non_admin_cannot_create_a_wildcard_role(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $response = $this->actingAs($intake)->post('/roles', [
            'name'         => 'EVIL',
            'display_name' => 'Evil',
            'description'  => 'pre-Tier-2 escalation path',
            'permissions'  => ['*'],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'EVIL']);
    }

    public function test_non_admin_cannot_promote_existing_role_to_wildcard(): void
    {
        $existing = $this->makeRole('EXISTING', ['households.view']);
        $intake   = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $response = $this->actingAs($intake)->put("/roles/{$existing->id}", [
            'display_name' => 'Existing',
            'description'  => '',
            'permissions'  => ['*'],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('role_permissions', [
            'role_id'    => $existing->id,
            'permission' => '*',
        ]);
    }

    public function test_non_admin_cannot_delete_role(): void
    {
        $existing = $this->makeRole('EXISTING', ['households.view']);
        $intake   = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $response = $this->actingAs($intake)->delete("/roles/{$existing->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('roles', ['id' => $existing->id]);
    }

    // ─── Baseline route middleware: roles.view ────────────────────────────────

    public function test_user_without_roles_view_blocked_at_index(): void
    {
        $intake = $this->makeUser($this->makeRole('INTAKE', ['households.view']), 'intake@test.local');

        $this->actingAs($intake)->get('/roles')->assertForbidden();
    }

    public function test_unauthenticated_role_request_redirects_to_login(): void
    {
        $this->get('/roles')->assertRedirect(route('login'));
    }

    // ─── Granted-perm grantees succeed ────────────────────────────────────────

    public function test_admin_wildcard_can_create_role(): void
    {
        $admin = $this->makeUser($this->makeRole('ADMIN', ['*']), 'admin@test.local');

        $response = $this->actingAs($admin)->post('/roles', [
            'name'         => 'NEW',
            'display_name' => 'New',
            'description'  => '',
            'permissions'  => ['households.view'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', ['name' => 'NEW']);
    }

    public function test_role_admin_with_create_perm_can_create_role(): void
    {
        $manager = $this->makeUser(
            $this->makeRole('ROLE_MANAGER', ['roles.view', 'roles.create']),
            'rolemgr@test.local'
        );

        $response = $this->actingAs($manager)->post('/roles', [
            'name'         => 'ANALYST',
            'display_name' => 'Analyst',
            'description'  => '',
            'permissions'  => ['reports.view'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', ['name' => 'ANALYST']);
    }

    public function test_role_admin_with_edit_perm_can_update_role(): void
    {
        $existing = $this->makeRole('ANALYST', ['reports.view']);
        $manager  = $this->makeUser(
            $this->makeRole('ROLE_EDITOR', ['roles.view', 'roles.edit']),
            'roleed@test.local'
        );

        $response = $this->actingAs($manager)->put("/roles/{$existing->id}", [
            'display_name' => 'Renamed Analyst',
            'description'  => '',
            'permissions'  => ['reports.view', 'reports.export'],
        ]);

        $response->assertRedirect();
        $existing->refresh();
        $this->assertSame('Renamed Analyst', $existing->display_name);
    }

    public function test_role_admin_with_delete_perm_can_destroy_role(): void
    {
        $existing = $this->makeRole('DOOMED', ['households.view']);
        $manager  = $this->makeUser(
            $this->makeRole('ROLE_PRUNER', ['roles.view', 'roles.delete']),
            'roleprune@test.local'
        );

        $response = $this->actingAs($manager)->delete("/roles/{$existing->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $existing->id]);
    }

    public function test_user_with_only_view_cannot_create_or_edit(): void
    {
        $existing = $this->makeRole('TARGET', ['households.view']);
        $viewer   = $this->makeUser(
            $this->makeRole('ROLE_VIEWER', ['roles.view']),
            'roleview@test.local'
        );

        $this->actingAs($viewer)->get('/roles')->assertOk();
        $this->actingAs($viewer)->post('/roles', [
            'name'         => 'X',
            'display_name' => 'X',
            'description'  => '',
            'permissions'  => [],
        ])->assertForbidden();
        $this->actingAs($viewer)->put("/roles/{$existing->id}", [
            'display_name' => 'Renamed',
            'description'  => '',
            'permissions'  => [],
        ])->assertForbidden();
        $this->actingAs($viewer)->delete("/roles/{$existing->id}")->assertForbidden();
    }
}
