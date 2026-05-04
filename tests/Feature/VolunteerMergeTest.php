<?php

namespace Tests\Feature;

use App\Exceptions\VolunteerMergeConflictException;
use App\Models\Event;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerCheckIn;
use App\Models\VolunteerGroup;
use App\Services\VolunteerMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 5.8 — pins the volunteer-merge contract.
 *
 * The service moves everything attached to the duplicate over to the
 * keeper atomically, then deletes the duplicate row. Pivot UNIQUE
 * collisions (overlapping group memberships, event assignments) are
 * deduped before the move; check-ins re-route via UPDATE; the
 * "both have open check-ins for the same event" case throws
 * VolunteerMergeConflictException to force admin to close one first.
 */
class VolunteerMergeTest extends TestCase
{
    use RefreshDatabase;

    private VolunteerMergeService $service;
    private User $admin;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $admin->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $admin->id,
            'email_verified_at' => now(),
        ]);

        $viewer = Role::create(['name' => 'VIEWER', 'display_name' => 'Viewer', 'description' => '']);
        RolePermission::create(['role_id' => $viewer->id, 'permission' => 'volunteers.view']);
        $this->viewer = User::create([
            'name'              => 'Viewer',
            'email'             => 'viewer@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $viewer->id,
            'email_verified_at' => now(),
        ]);

        $this->service = app(VolunteerMergeService::class);
    }

    private function makeVolunteer(string $first, string $phone): Volunteer
    {
        return Volunteer::create([
            'first_name' => $first,
            'last_name'  => 'X',
            'phone'      => $phone,
        ]);
    }

    private function makeEvent(string $name = 'Test Event'): Event
    {
        return Event::create([
            'name'   => $name,
            'date'   => now()->subDay()->toDateString(),
            'status' => 'past',
            'lanes'  => 1,
        ]);
    }

    // ─── Service-level: atomic transfer ─────────────────────────────────────

    public function test_check_ins_are_re_pointed_at_the_keeper(): void
    {
        $keeper = $this->makeVolunteer('Keep', '5551111');
        $dupe   = $this->makeVolunteer('Dupe', '5552222');
        $event  = $this->makeEvent();

        // Both have closed history at the same event.
        VolunteerCheckIn::create([
            'event_id' => $event->id, 'volunteer_id' => $keeper->id,
            'role' => 'Other', 'source' => 'walk_in', 'is_first_timer' => false,
            'checked_in_at' => now()->subHours(3), 'checked_out_at' => now()->subHours(1), 'hours_served' => 2.0,
        ]);
        VolunteerCheckIn::create([
            'event_id' => $event->id, 'volunteer_id' => $dupe->id,
            'role' => 'Other', 'source' => 'walk_in', 'is_first_timer' => true,
            'checked_in_at' => now()->subHours(2), 'checked_out_at' => now()->subHour(), 'hours_served' => 1.0,
        ]);

        $result = $this->service->merge($keeper, $dupe);

        $this->assertSame(1, $result['check_ins_transferred']);
        $this->assertSame(2, VolunteerCheckIn::where('volunteer_id', $keeper->id)->count());
        $this->assertSame(0, VolunteerCheckIn::where('volunteer_id', $dupe->id)->count());
        $this->assertNull(Volunteer::find($dupe->id));
    }

    public function test_overlapping_group_memberships_are_deduped(): void
    {
        $keeper = $this->makeVolunteer('Keep', '5551111');
        $dupe   = $this->makeVolunteer('Dupe', '5552222');
        $shared = VolunteerGroup::create(['name' => 'Shared']);
        $onlyDupe = VolunteerGroup::create(['name' => 'Only Dupe']);

        $keeper->groups()->attach($shared, ['joined_at' => now()->subYear()]);
        $dupe->groups()->attach($shared, ['joined_at' => now()->subMonth()]);
        $dupe->groups()->attach($onlyDupe, ['joined_at' => now()]);

        $result = $this->service->merge($keeper, $dupe);

        // Only "Only Dupe" was new for the keeper.
        $this->assertSame(1, $result['groups_transferred']);
        // Disambiguate `id` — joined query against pivot has its own `id`.
        $keeperGroups = $keeper->fresh()->groups()->pluck('volunteer_groups.id')->all();
        $this->assertEqualsCanonicalizing([$shared->id, $onlyDupe->id], $keeperGroups);
    }

    public function test_overlapping_event_assignments_are_deduped(): void
    {
        $keeper = $this->makeVolunteer('Keep', '5551111');
        $dupe   = $this->makeVolunteer('Dupe', '5552222');
        $shared = $this->makeEvent('Shared Event');
        $only   = $this->makeEvent('Only Dupe');

        $keeper->assignedEvents()->attach($shared);
        $dupe->assignedEvents()->attach($shared);
        $dupe->assignedEvents()->attach($only);

        $result = $this->service->merge($keeper, $dupe);

        $this->assertSame(1, $result['events_transferred']);
        $keeperEvents = $keeper->fresh()->assignedEvents()->pluck('events.id')->all();
        $this->assertEqualsCanonicalizing([$shared->id, $only->id], $keeperEvents);
    }

    public function test_self_merge_is_refused_at_the_service_layer(): void
    {
        $vol = $this->makeVolunteer('Same', '5551111');
        $this->expectException(\InvalidArgumentException::class);
        $this->service->merge($vol, $vol);
    }

    public function test_open_check_in_conflict_throws_and_rolls_back(): void
    {
        $keeper = $this->makeVolunteer('Keep', '5551111');
        $dupe   = $this->makeVolunteer('Dupe', '5552222');
        $event  = $this->makeEvent();

        // Both currently OPEN at the same event.
        VolunteerCheckIn::create([
            'event_id' => $event->id, 'volunteer_id' => $keeper->id,
            'role' => 'Other', 'source' => 'walk_in', 'is_first_timer' => false,
            'checked_in_at' => now()->subHour(),
        ]);
        VolunteerCheckIn::create([
            'event_id' => $event->id, 'volunteer_id' => $dupe->id,
            'role' => 'Other', 'source' => 'walk_in', 'is_first_timer' => true,
            'checked_in_at' => now()->subMinutes(30),
        ]);

        // Side-state we'd hate to lose if a partial merge slipped through.
        $shared = VolunteerGroup::create(['name' => 'Side State']);
        $dupe->groups()->attach($shared, ['joined_at' => now()]);

        try {
            $this->service->merge($keeper, $dupe);
            $this->fail('Expected VolunteerMergeConflictException');
        } catch (VolunteerMergeConflictException $e) {
            $this->assertSame([$event->id], $e->conflictingEventIds);
        }

        // Rollback verification — duplicate still owns its own state.
        $this->assertNotNull(Volunteer::find($dupe->id));
        $this->assertSame(1, $dupe->fresh()->groups()->count(), 'Group must NOT have been transferred on rollback');
        $this->assertSame(1, VolunteerCheckIn::where('volunteer_id', $dupe->id)->count());
    }

    public function test_open_check_in_on_different_events_does_not_block_merge(): void
    {
        $keeper = $this->makeVolunteer('Keep', '5551111');
        $dupe   = $this->makeVolunteer('Dupe', '5552222');
        $eventA = $this->makeEvent('Event A');
        $eventB = $this->makeEvent('Event B');

        VolunteerCheckIn::create([
            'event_id' => $eventA->id, 'volunteer_id' => $keeper->id,
            'role' => 'Other', 'source' => 'walk_in', 'is_first_timer' => false,
            'checked_in_at' => now()->subHour(),
        ]);
        VolunteerCheckIn::create([
            'event_id' => $eventB->id, 'volunteer_id' => $dupe->id,
            'role' => 'Other', 'source' => 'walk_in', 'is_first_timer' => true,
            'checked_in_at' => now()->subMinutes(30),
        ]);

        $result = $this->service->merge($keeper, $dupe);

        // Both check-ins now belong to keeper, on different events — no conflict.
        $this->assertSame(2, VolunteerCheckIn::where('volunteer_id', $keeper->id)->count());
        // Only the duplicate's row was *transferred*; the keeper's own row
        // didn't move. Service returns the count of UPDATEd rows.
        $this->assertSame(1, $result['check_ins_transferred']);
    }

    // ─── HTTP layer ─────────────────────────────────────────────────────────

    public function test_unauthenticated_merge_redirects_to_login(): void
    {
        $keeper = $this->makeVolunteer('K', '1');
        $dupe   = $this->makeVolunteer('D', '2');

        $this->post(route('volunteers.merge', $dupe), ['keeper_id' => $keeper->id])
             ->assertRedirect('/login');
    }

    public function test_viewer_cannot_merge(): void
    {
        $keeper = $this->makeVolunteer('K', '1');
        $dupe   = $this->makeVolunteer('D', '2');

        $this->actingAs($this->viewer)
             ->post(route('volunteers.merge', $dupe), ['keeper_id' => $keeper->id])
             ->assertForbidden();

        $this->assertNotNull(Volunteer::find($dupe->id));
    }

    public function test_admin_can_merge_via_http(): void
    {
        $keeper = $this->makeVolunteer('K', '1');
        $dupe   = $this->makeVolunteer('D', '2');

        $this->actingAs($this->admin)
             ->post(route('volunteers.merge', $dupe), ['keeper_id' => $keeper->id])
             ->assertRedirect(route('volunteers.show', $keeper))
             ->assertSessionHas('success');

        $this->assertNull(Volunteer::find($dupe->id));
    }

    public function test_self_merge_is_refused_at_the_validator(): void
    {
        $vol = $this->makeVolunteer('Self', '1');

        $this->actingAs($this->admin)
             ->from(route('volunteers.show', $vol))
             ->post(route('volunteers.merge', $vol), ['keeper_id' => $vol->id])
             ->assertSessionHasErrors('keeper_id');

        $this->assertNotNull(Volunteer::find($vol->id));
    }

    public function test_conflict_renders_friendly_error_via_http(): void
    {
        $keeper = $this->makeVolunteer('K', '1');
        $dupe   = $this->makeVolunteer('D', '2');
        $event  = $this->makeEvent();

        VolunteerCheckIn::create([
            'event_id' => $event->id, 'volunteer_id' => $keeper->id,
            'role' => 'Other', 'source' => 'walk_in', 'is_first_timer' => false,
            'checked_in_at' => now()->subHour(),
        ]);
        VolunteerCheckIn::create([
            'event_id' => $event->id, 'volunteer_id' => $dupe->id,
            'role' => 'Other', 'source' => 'walk_in', 'is_first_timer' => true,
            'checked_in_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($this->admin)
             ->from(route('volunteers.show', $dupe))
             ->post(route('volunteers.merge', $dupe), ['keeper_id' => $keeper->id])
             ->assertRedirect(route('volunteers.show', $dupe))
             ->assertSessionHas('error');

        $this->assertNotNull(Volunteer::find($dupe->id));
    }
}
