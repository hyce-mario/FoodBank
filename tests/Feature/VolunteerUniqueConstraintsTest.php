<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5.6.g — pins the new uniqueness contract on volunteers.phone and
 * volunteers.email.
 *
 * Contract:
 *   - phone + email are unique when present
 *   - NULL phones / NULL emails coexist freely (multiple volunteers may
 *     have neither on file)
 *   - empty strings are coerced to NULL during the migration's pre-step,
 *     so subsequent inserts can rely on the NULL semantics
 *   - StoreVolunteerRequest + UpdateVolunteerRequest validate at the
 *     application layer so users see a friendly "already exists" error
 *     instead of a 500 from a DB integrity violation
 *   - update form requests exempt the current row's value so a volunteer
 *     can save their own profile without changing phone/email
 */
class VolunteerUniqueConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    public function test_multiple_volunteers_can_have_null_phone(): void
    {
        Volunteer::create(['first_name' => 'A', 'last_name' => 'A']);
        Volunteer::create(['first_name' => 'B', 'last_name' => 'B']);

        $this->assertSame(2, Volunteer::whereNull('phone')->count(),
            'Multiple volunteers with NULL phone must coexist');
    }

    public function test_multiple_volunteers_can_have_null_email(): void
    {
        Volunteer::create(['first_name' => 'A', 'last_name' => 'A', 'phone' => '111']);
        Volunteer::create(['first_name' => 'B', 'last_name' => 'B', 'phone' => '222']);

        $this->assertSame(2, Volunteer::whereNull('email')->count());
    }

    public function test_duplicate_phone_via_form_returns_validation_error(): void
    {
        Volunteer::create(['first_name' => 'A', 'last_name' => 'A', 'phone' => '5551234']);

        $this->actingAs($this->admin)
             ->from(route('volunteers.create'))
             ->post(route('volunteers.store'), [
                 'first_name' => 'B',
                 'last_name'  => 'B',
                 'phone'      => '5551234',
             ])
             ->assertSessionHasErrors('phone');

        $this->assertSame(1, Volunteer::where('phone', '5551234')->count(),
            'Duplicate phone must not insert a row');
    }

    public function test_duplicate_email_via_form_returns_validation_error(): void
    {
        Volunteer::create(['first_name' => 'A', 'last_name' => 'A', 'email' => 'dup@x.test']);

        $this->actingAs($this->admin)
             ->from(route('volunteers.create'))
             ->post(route('volunteers.store'), [
                 'first_name' => 'B',
                 'last_name'  => 'B',
                 'email'      => 'dup@x.test',
             ])
             ->assertSessionHasErrors('email');
    }

    public function test_update_form_does_not_flag_self_as_duplicate(): void
    {
        $vol = Volunteer::create([
            'first_name' => 'A', 'last_name' => 'A',
            'phone'      => '5551234', 'email' => 'a@x.test',
        ]);

        // Saving the same phone + email + a name change should pass.
        $this->actingAs($this->admin)
             ->put(route('volunteers.update', $vol), [
                 'first_name' => 'A-renamed',
                 'last_name'  => 'A',
                 'phone'      => '5551234',
                 'email'      => 'a@x.test',
             ])
             ->assertRedirect();

        $this->assertSame('A-renamed', $vol->fresh()->first_name);
    }

    public function test_update_form_flags_other_volunteers_phone_as_duplicate(): void
    {
        Volunteer::create(['first_name' => 'A', 'last_name' => 'A', 'phone' => '5551234']);
        $other = Volunteer::create(['first_name' => 'B', 'last_name' => 'B', 'phone' => '5559999']);

        $this->actingAs($this->admin)
             ->from(route('volunteers.edit', $other))
             ->put(route('volunteers.update', $other), [
                 'first_name' => 'B',
                 'last_name'  => 'B',
                 'phone'      => '5551234',          // belongs to A
             ])
             ->assertSessionHasErrors('phone');

        $this->assertSame('5559999', $other->fresh()->phone, 'Update must not have applied');
    }
}
