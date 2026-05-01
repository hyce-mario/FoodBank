<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6.10 — pin the per-permission diff audit contract.
 * RolePermissionService::syncPermissions writes a single audit_logs entry
 * per save with action='permissions_changed' and the before/after lists.
 */
class RolePermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_with_permissions_writes_audit_entry(): void
    {
        $role = app(RolePermissionService::class)->create([
            'name'         => 'TESTROLE',
            'display_name' => 'Test Role',
            'description'  => '',
            'permissions'  => ['households.view', 'events.view'],
        ]);

        $entry = AuditLog::where('action', 'permissions_changed')
            ->where('target_id', $role->id)
            ->first();

        $this->assertNotNull($entry, 'permissions_changed audit entry must be written');
        $this->assertSame([], $entry->before_json['permissions']);
        $this->assertEqualsCanonicalizing(
            ['households.view', 'events.view'],
            $entry->after_json['permissions']
        );
    }

    public function test_update_with_permission_diff_writes_granted_and_revoked(): void
    {
        $role = app(RolePermissionService::class)->create([
            'name'         => 'TESTROLE2',
            'display_name' => 'Test Role 2',
            'description'  => '',
            'permissions'  => ['households.view', 'events.view'],
        ]);

        $initialAuditCount = AuditLog::where('action', 'permissions_changed')->count();

        app(RolePermissionService::class)->update($role, [
            'display_name' => 'Test Role 2',
            'description'  => '',
            'permissions'  => ['households.view', 'volunteers.view'], // dropped events.view, added volunteers.view
        ]);

        $updateAudit = AuditLog::where('action', 'permissions_changed')
            ->where('target_id', $role->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($updateAudit);
        $this->assertEqualsCanonicalizing(
            ['households.view', 'events.view'],
            $updateAudit->before_json['permissions']
        );
        $this->assertEqualsCanonicalizing(
            ['households.view', 'volunteers.view'],
            $updateAudit->after_json['permissions']
        );
        $this->assertSame($initialAuditCount + 1, AuditLog::where('action', 'permissions_changed')->count());
    }

    public function test_no_diff_writes_no_audit_entry(): void
    {
        $role = app(RolePermissionService::class)->create([
            'name'         => 'TESTROLE3',
            'display_name' => 'Test Role 3',
            'description'  => '',
            'permissions'  => ['households.view'],
        ]);

        $countBefore = AuditLog::where('action', 'permissions_changed')->count();

        app(RolePermissionService::class)->update($role, [
            'display_name' => 'Test Role 3',
            'description'  => '',
            'permissions'  => ['households.view'], // unchanged
        ]);

        $this->assertSame(
            $countBefore,
            AuditLog::where('action', 'permissions_changed')->count(),
            'No permission diff = no permissions_changed audit entry'
        );
    }
}
