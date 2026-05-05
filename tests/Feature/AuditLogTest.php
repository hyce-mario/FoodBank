<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\AppSetting;
use App\Models\Household;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\Visit;
use App\Services\EventCheckInService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4.2 — Audit log: Auditable trait + AuditLog model + admin page.
 *
 * Acceptance criterion: every role change, settings change, and visit-status
 * override is queryable with who/when/what.
 *
 * Refs: AUDIT_REPORT.md Part 13 §4.2.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $adminRole = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => '*']);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('p'), 'role_id' => $adminRole->id, 'email_verified_at' => now(),
        ]);
    }

    // ─── Trait coverage ───────────────────────────────────────────────────────

    /**
     * Creating a Household writes a 'created' audit row with the new attributes.
     */
    public function test_household_create_writes_audit_log(): void
    {
        $this->actingAs($this->admin);

        $initialCount = AuditLog::count();

        Household::create([
            'household_number' => 'A0001',
            'first_name'       => 'Jane',
            'last_name'        => 'Doe',
            'household_size'   => 2,
            'adults_count'     => 2,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);

        $this->assertSame($initialCount + 1, AuditLog::count());
        $log = AuditLog::latest('id')->first();
        $this->assertSame('created', $log->action);
        $this->assertSame(Household::class, $log->target_type);
        $this->assertNull($log->before_json);
        $this->assertArrayHasKey('first_name', $log->after_json);
    }

    /**
     * Updating a Household writes an 'updated' audit row with the diff only.
     */
    public function test_household_update_writes_diff(): void
    {
        $this->actingAs($this->admin);

        $household = Household::create([
            'household_number' => 'A0002',
            'first_name'       => 'John',
            'last_name'        => 'Original',
            'household_size'   => 1,
            'adults_count'     => 1,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);

        $before = AuditLog::count();
        $household->update(['last_name' => 'Updated']);

        $this->assertSame($before + 1, AuditLog::count());

        $log = AuditLog::latest('id')->first();
        $this->assertSame('updated', $log->action);
        $this->assertSame('Original', $log->before_json['last_name']);
        $this->assertSame('Updated',  $log->after_json['last_name']);
        // Fields that didn't change must NOT appear in the diff
        $this->assertArrayNotHasKey('first_name', $log->after_json);
    }

    /**
     * Deleting a Household writes a 'deleted' audit row with the old attributes.
     */
    public function test_household_delete_writes_audit_log(): void
    {
        $this->actingAs($this->admin);

        $household = Household::create([
            'household_number' => 'A0003',
            'first_name'       => 'Del',
            'last_name'        => 'Eted',
            'household_size'   => 1,
            'adults_count'     => 1,
            'children_count'   => 0,
            'seniors_count'    => 0,
        ]);

        $before = AuditLog::count();
        $household->delete();

        $this->assertSame($before + 1, AuditLog::count());
        $log = AuditLog::latest('id')->first();
        $this->assertSame('deleted', $log->action);
        $this->assertNotNull($log->before_json);
        $this->assertNull($log->after_json);
    }

    /**
     * User password must never appear in audit logs.
     */
    public function test_user_password_excluded_from_audit_log(): void
    {
        $this->actingAs($this->admin);

        $role = Role::first();
        User::create([
            'name'     => 'Test',
            'email'    => 'test2@test.local',
            'password' => bcrypt('secret123'),
            'role_id'  => $role->id,
            'email_verified_at' => now(),
        ]);

        $log = AuditLog::where('action', 'created')
                        ->where('target_type', User::class)
                        ->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->after_json ?? []);
        $this->assertArrayNotHasKey('remember_token', $log->after_json ?? []);
    }

    /**
     * Visit audit log only captures visit_status changes, not position/timestamps.
     */
    public function test_visit_audit_limited_to_status_changes(): void
    {
        $this->actingAs($this->admin);

        $event = \App\Models\Event::create([
            'name' => 'Audit Test Event', 'date' => '2026-06-01', 'lanes' => 1,
        ]);
        $household = Household::create([
            'household_number' => 'A0004', 'first_name' => 'Vis',
            'last_name' => 'It', 'household_size' => 1,
            'adults_count' => 1, 'children_count' => 0, 'seniors_count' => 0,
        ]);

        $visit = app(EventCheckInService::class)->checkIn($event, $household, 1);
        $before = AuditLog::where('target_type', Visit::class)->count();

        // Change status — should log
        $visit->update(['visit_status' => 'queued', 'queued_at' => now()]);
        $this->assertSame($before + 1, AuditLog::where('target_type', Visit::class)->count());

        $log = AuditLog::where('target_type', Visit::class)->latest('id')->first();
        $this->assertArrayHasKey('visit_status', $log->after_json ?? []);
        $this->assertArrayNotHasKey('queued_at', $log->after_json ?? []);
        $this->assertArrayNotHasKey('queue_position', $log->after_json ?? []);
    }

    // ─── Admin page ───────────────────────────────────────────────────────────

    public function test_admin_can_view_audit_log_page(): void
    {
        $this->actingAs($this->admin)
             ->get(route('audit-logs.index'))
             ->assertOk()
             ->assertSee('Audit Log');
    }

    public function test_non_admin_cannot_view_audit_log_page(): void
    {
        $viewRole = Role::create(['name' => 'REPORTS', 'display_name' => 'Reports', 'description' => '']);
        RolePermission::create(['role_id' => $viewRole->id, 'permission' => 'reports.view']);
        $viewer = User::create([
            'name' => 'Viewer', 'email' => 'viewer@test.local',
            'password' => bcrypt('p'), 'role_id' => $viewRole->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($viewer)
             ->get(route('audit-logs.index'))
             ->assertStatus(403);
    }

    /**
     * Tier 3a — a non-admin role granted audit_logs.view should be able to read
     * the audit page WITHOUT needing the full admin (*) wildcard. Demonstrates
     * the new dedicated permission works through both the route middleware and
     * the policy.
     */
    public function test_user_with_audit_logs_view_permission_can_view_page(): void
    {
        $complianceRole = Role::create(['name' => 'COMPLIANCE', 'display_name' => 'Compliance Officer', 'description' => '']);
        RolePermission::create(['role_id' => $complianceRole->id, 'permission' => 'audit_logs.view']);
        $officer = User::create([
            'name' => 'Officer', 'email' => 'officer@test.local',
            'password' => bcrypt('p'), 'role_id' => $complianceRole->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($officer)
             ->get(route('audit-logs.index'))
             ->assertOk()
             ->assertSee('Audit Log');
    }

    /**
     * Tier 3a — unauthenticated requests redirect to login (CheckPermission middleware
     * runs `auth` before the permission check; route group already enforces auth, so
     * the middleware itself short-circuits to redirect()->route('login')).
     */
    public function test_unauthenticated_audit_log_request_redirects_to_login(): void
    {
        $this->get(route('audit-logs.index'))
             ->assertRedirect(route('login'));
    }

    /**
     * Acceptance criterion: audit entries are queryable by who/when/what.
     */
    public function test_audit_log_is_queryable_by_action_and_model(): void
    {
        $this->actingAs($this->admin);

        Household::create([
            'household_number' => 'A0005', 'first_name' => 'Q',
            'last_name' => 'Test', 'household_size' => 1,
            'adults_count' => 1, 'children_count' => 0, 'seniors_count' => 0,
        ]);

        $this->actingAs($this->admin)
             ->get(route('audit-logs.index', ['action' => 'created', 'model' => 'Household']))
             ->assertOk()
             ->assertSee('Created');
    }
}
