<?php

namespace App\Services;

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    /**
     * All known permission strings grouped by resource.
     * Dot-notation: resource.action
     */
    public static function permissionGroups(): array
    {
        return [
            'households'  => ['view', 'create', 'edit', 'delete'],
            'checkin'     => ['view', 'scan'],
            'events'      => ['view', 'create', 'edit', 'delete'],
            'volunteers'  => ['view', 'create', 'edit', 'delete'],
            'distributions' => ['view', 'create'],
            'inventory'   => ['view', 'edit'],
            'reports'     => ['view', 'export'],
            'roles'       => ['view', 'create', 'edit', 'delete'],
            'settings'    => ['view', 'update'],
        ];
    }

    /**
     * Build flat list of all known permission strings.
     */
    public static function allPermissions(): array
    {
        $all = [];
        foreach (static::permissionGroups() as $resource => $actions) {
            foreach ($actions as $action) {
                $all[] = "{$resource}.{$action}";
            }
        }
        return $all;
    }

    /**
     * Create a new role with its permissions inside a transaction.
     */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name'         => strtoupper(trim($data['name'])),
                'display_name' => trim($data['display_name']),
                'description'  => trim($data['description'] ?? ''),
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role;
        });
    }

    /**
     * Update a role's display_name, description, and permissions.
     * The `name` (slug) is not updated after creation.
     */
    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $role->update([
                'display_name' => trim($data['display_name']),
                'description'  => trim($data['description'] ?? ''),
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role->fresh('permissions');
        });
    }

    /**
     * Delete a role. Throws RuntimeException if protected or has users.
     */
    public function delete(Role $role): void
    {
        if ($role->name === 'ADMIN') {
            throw new \RuntimeException('The ADMIN role is protected and cannot be deleted.');
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            throw new \RuntimeException(
                "Cannot delete role \"{$role->display_name}\" — {$userCount} " .
                ($userCount === 1 ? 'user is' : 'users are') . ' assigned to it.'
            );
        }

        DB::transaction(function () use ($role) {
            $role->permissions()->delete();
            $role->delete();
        });
    }

    /**
     * Replace all permissions for the role with the given list.
     * If ['*'] is passed (wildcard / full access), store only '*'.
     *
     * Phase 6.10: writes a single audit_logs entry per save capturing the
     * exact granted/revoked diff. The Role's own update audit shows the
     * display_name/description change; this entry shows the permission delta.
     */
    private function syncPermissions(Role $role, array $permissions): void
    {
        // Capture the before-state for audit
        $before = $role->permissions()->pluck('permission')->sort()->values()->toArray();

        $role->permissions()->delete();

        // Deduplicate and filter to non-empty strings
        $permissions = array_values(array_unique(array_filter($permissions, fn ($p) => is_string($p) && $p !== '')));

        foreach ($permissions as $permission) {
            RolePermission::create([
                'role_id'    => $role->id,
                'permission' => $permission,
            ]);
        }

        $after = collect($permissions)->sort()->values()->toArray();

        // Skip the audit entry if nothing actually changed
        if ($before !== $after) {
            \App\Models\AuditLog::writeEntry(
                action: 'permissions_changed',
                model:  $role,
                before: ['permissions' => $before],
                after:  ['permissions' => $after],
            );
        }
    }
}
