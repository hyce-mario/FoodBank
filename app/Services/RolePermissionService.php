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
     *
     * Tier 1 audit (2026-05-05) — catalog now reflects every module the
     * application actually ships, plus the permissions that policies +
     * middleware already check. Adding entries here makes them grantable
     * via the role editor; the wildcard '*' continues to grant
     * everything regardless. Removing an entry only stops it from
     * appearing in the editor — existing role assignments keep their
     * exact permission strings in the DB unchanged.
     *
     * What changed in this revision:
     *   • Added `reviews.{view,moderate}` — already enforced by
     *     EventReviewPolicy; previously only the '*' wildcard could grant.
     *   • Added `finance.{view,create,edit,delete}` — the entire finance
     *     module ships unguarded; catalog now exposes the perm strings
     *     so a Tier 2 wiring pass can attach them to routes/policies
     *     without re-editing this file.
     *   • Added `finance_reports.{view,export}` — separate from the
     *     operational `reports` module so a finance-only role can be
     *     constructed without granting access to /reports/*.
     *   • Added `audit_logs.view` — replaces the AuditLogPolicy hard-coded
     *     isAdmin() check with a grantable permission so a compliance
     *     role can read audits without full admin.
     *   • Added `users.{view,create,edit,delete}` — replaces the StoreUserRequest /
     *     UpdateUserRequest hard-coded isAdmin() checks.
     *   • Added `purchase_orders.{view,create,edit,receive,cancel}` — the
     *     PO module has finer-grained operations than CRUD (receive +
     *     cancel are workflow transitions worth gating separately).
     *   • Removed `distributions.{view,create}` — group was never
     *     referenced by any policy, controller, middleware, or @can
     *     directive. Phase 2's distribution-posting flow runs through
     *     the loader's event-day auth code, not these permissions.
     */
    public static function permissionGroups(): array
    {
        return [
            'households'      => ['view', 'create', 'edit', 'delete'],
            'events'          => ['view', 'create', 'edit', 'delete'],
            'volunteers'      => ['view', 'create', 'edit', 'delete'],
            'checkin'         => ['view', 'scan'],
            'inventory'       => ['view', 'edit'],
            'purchase_orders' => ['view', 'create', 'edit', 'receive', 'cancel'],
            'finance'         => ['view', 'create', 'edit', 'delete'],
            'reports'         => ['view', 'export'],
            'finance_reports' => ['view', 'export'],
            'reviews'         => ['view', 'moderate'],
            'audit_logs'      => ['view'],
            'users'           => ['view', 'create', 'edit', 'delete'],
            'roles'           => ['view', 'create', 'edit', 'delete'],
            'settings'        => ['view', 'update'],
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
