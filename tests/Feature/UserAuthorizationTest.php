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
}
