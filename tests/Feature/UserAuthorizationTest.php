<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 0.1 — privilege-escalation regression suite.
 *
 * Refs: AUDIT_REPORT.md Part 10, Part 13 §0.1.
 *
 * Before this phase: StoreUserRequest::authorize() and UpdateUserRequest::authorize()
 * both returned `true`, which meant any authenticated user could create users or
 * promote anyone (including themselves) to ADMIN. These tests pin the new
 * admin-only behavior in place.
 */
class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private Role $intakeRole;
    private User $admin;
    private User $intakeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name'         => 'ADMIN',
            'display_name' => 'Administrator',
            'description'  => 'Full system access',
        ]);
        RolePermission::create(['role_id' => $this->adminRole->id, 'permission' => '*']);

        $this->intakeRole = Role::create([
            'name'         => 'INTAKE',
            'display_name' => 'Intake Staff',
            'description'  => 'Register and manage households',
        ]);
        RolePermission::create(['role_id' => $this->intakeRole->id, 'permission' => 'households.view']);

        $this->admin = User::create([
            'name'              => 'Admin User',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $this->adminRole->id,
            'email_verified_at' => now(),
        ]);

        $this->intakeUser = User::create([
            'name'              => 'Intake User',
            'email'             => 'intake@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $this->intakeRole->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin)->post('/users', [
            'name'                  => 'New User',
            'email'                 => 'new@test.local',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role_id'               => $this->intakeRole->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'new@test.local']);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $response = $this->actingAs($this->intakeUser)->post('/users', [
            'name'                  => 'Sneaky New Admin',
            'email'                 => 'sneaky@test.local',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role_id'               => $this->adminRole->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@test.local']);
    }

    public function test_admin_can_update_another_users_role(): void
    {
        $target = User::create([
            'name'              => 'Target',
            'email'             => 'target@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $this->intakeRole->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->put("/users/{$target->id}", [
            'name'    => 'Target Renamed',
            'email'   => 'target@test.local',
            'role_id' => $this->adminRole->id,
        ]);

        $response->assertRedirect();
        $target->refresh();
        $this->assertEquals($this->adminRole->id, $target->role_id);
        $this->assertEquals('Target Renamed', $target->name);
    }

    public function test_non_admin_cannot_update_any_user(): void
    {
        $originalName = $this->admin->name;
        $originalRole = $this->admin->role_id;

        $response = $this->actingAs($this->intakeUser)->put("/users/{$this->admin->id}", [
            'name'    => 'Hacked Admin Name',
            'email'   => 'admin@test.local',
            'role_id' => $this->intakeRole->id,
        ]);

        $response->assertForbidden();
        $this->admin->refresh();
        $this->assertEquals($originalRole, $this->admin->role_id);
        $this->assertEquals($originalName, $this->admin->name);
    }

    /**
     * The headline regression: a non-admin posting their own user id with a
     * promotion to ADMIN must be rejected. This is the exact path that was
     * exploitable before Phase 0.1.
     */
    public function test_non_admin_cannot_promote_self_to_admin(): void
    {
        $response = $this->actingAs($this->intakeUser)->put("/users/{$this->intakeUser->id}", [
            'name'    => 'Intake User',
            'email'   => 'intake@test.local',
            'role_id' => $this->adminRole->id,
        ]);

        $response->assertForbidden();
        $this->intakeUser->refresh();
        $this->assertEquals($this->intakeRole->id, $this->intakeUser->role_id);
        $this->assertFalse($this->intakeUser->isAdmin());
    }

    public function test_unauthenticated_user_cannot_access_user_routes(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->post('/users', [])->assertRedirect('/login');
        $this->put("/users/{$this->admin->id}", [])->assertRedirect('/login');
        $this->delete("/users/{$this->admin->id}")->assertRedirect('/login');
    }

    public function test_admin_can_delete_another_user(): void
    {
        $target = User::create([
            'name'              => 'Target',
            'email'             => 'target@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $this->intakeRole->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->delete("/users/{$target->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    /**
     * Without admin enforcement on destroy(), a non-admin could DELETE the
     * only admin, causing permanent loss of administrative access. This pins
     * the additional guard added during Phase 0.1 review.
     */
    public function test_non_admin_cannot_delete_any_user(): void
    {
        $response = $this->actingAs($this->intakeUser)->delete("/users/{$this->admin->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    // ─── Tier 3b regressions ──────────────────────────────────────────────────
    // A dedicated user-manager role with users.{view,create,edit,delete} should
    // be able to manage accounts — but role assignment remains ADMIN-only as
    // defense in depth (UserController::update line 97).

    private function makeUserManager(): User
    {
        $role = Role::create(['name' => 'USER_MANAGER', 'display_name' => 'User Manager', 'description' => '']);
        foreach (['users.view', 'users.create', 'users.edit', 'users.delete'] as $perm) {
            RolePermission::create(['role_id' => $role->id, 'permission' => $perm]);
        }
        return User::create([
            'name'              => 'Manager',
            'email'             => 'manager@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_user_manager_can_create_user_without_admin(): void
    {
        $manager = $this->makeUserManager();

        $response = $this->actingAs($manager)->post('/users', [
            'name'                  => 'Created By Manager',
            'email'                 => 'cbm@test.local',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role_id'               => $this->intakeRole->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'cbm@test.local']);
    }

    public function test_user_manager_can_edit_name_and_email_but_not_role(): void
    {
        $manager = $this->makeUserManager();
        $target  = User::create([
            'name'              => 'Original Name',
            'email'             => 'target@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $this->intakeRole->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($manager)->put("/users/{$target->id}", [
            'name'    => 'Renamed By Manager',
            'email'   => 'renamed@test.local',
            'role_id' => $this->adminRole->id,
        ]);

        $response->assertRedirect();
        $target->refresh();
        $this->assertSame('Renamed By Manager', $target->name);
        $this->assertSame('renamed@test.local', $target->email);
        $this->assertSame($this->intakeRole->id, $target->role_id,
            'role_id MUST stay unchanged — non-admin user-manager cannot reassign roles');
    }

    public function test_user_manager_can_delete_user_without_admin(): void
    {
        $manager = $this->makeUserManager();
        $target  = User::create([
            'name'              => 'Doomed',
            'email'             => 'doomed@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $this->intakeRole->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($manager)->delete("/users/{$target->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_user_manager_without_view_perm_blocked_at_route_middleware(): void
    {
        // Tier 3b — baseline middleware on /users is permission:users.view.
        // A user with only users.create (no users.view) cannot reach the routes.
        $role = Role::create(['name' => 'CREATE_ONLY', 'display_name' => 'Create Only', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => 'users.create']);
        $halfManager = User::create([
            'name'              => 'Half Manager',
            'email'             => 'half@test.local',
            'password'          => bcrypt('password'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($halfManager)
             ->get('/users')
             ->assertForbidden();
    }
}
