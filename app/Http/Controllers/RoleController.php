<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Services\RolePermissionService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RoleController extends Controller
{
    public function __construct(protected RolePermissionService $service) {}

    // ─── index ────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $query = Role::withCount(['permissions', 'users'])
            ->orderBy('name');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $roles = $query->get();

        return view('roles.index', compact('roles', 'search'));
    }

    // ─── create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        $groups = RolePermissionService::permissionGroups();
        return view('roles.create', compact('groups'));
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = $this->service->create($request->validated());

        return redirect()
            ->route('roles.show', $role)
            ->with('success', "Role \"{$role->display_name}\" created successfully.");
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function show(Role $role): View
    {
        $role->load(['permissions', 'users']);
        $groups = RolePermissionService::permissionGroups();

        // Build permission set for quick lookup in the view
        $rolePermissions = $role->permissions->pluck('permission')->toArray();

        return view('roles.show', compact('role', 'groups', 'rolePermissions'));
    }

    // ─── edit ─────────────────────────────────────────────────────────────────

    public function edit(Role $role): View
    {
        $role->load('permissions');
        $groups          = RolePermissionService::permissionGroups();
        $rolePermissions = $role->permissions->pluck('permission')->toArray();

        return view('roles.edit', compact('role', 'groups', 'rolePermissions'));
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        // Protect system roles when the setting is enabled
        if ((bool) SettingService::get('security.protect_system_roles', true) && $role->is_system) {
            return redirect()
                ->route('roles.show', $role)
                ->with('error', "System role \"{$role->display_name}\" is protected and cannot be modified.");
        }

        $this->service->update($role, $request->validated());

        return redirect()
            ->route('roles.show', $role)
            ->with('success', "Role \"{$role->display_name}\" updated successfully.");
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function destroy(Role $role): RedirectResponse
    {
        // Tier 2 — RolePolicy::delete gates on roles.delete. Prior to this fix
        // any authenticated user could DELETE /roles/{id}.
        $this->authorize('delete', $role);

        // Protect system roles from deletion
        if ((bool) SettingService::get('security.protect_system_roles', true) && $role->is_system) {
            return redirect()
                ->route('roles.index')
                ->with('error', "System role \"{$role->display_name}\" cannot be deleted.");
        }

        // Role deletion protection — block if users are assigned
        if ((bool) SettingService::get('security.role_deletion_protection', true)) {
            $userCount = $role->users()->count();
            if ($userCount > 0) {
                return redirect()
                    ->route('roles.index')
                    ->with('error', "Cannot delete \"{$role->display_name}\" — {$userCount} user(s) are assigned to it. Reassign them first.");
            }
        }

        try {
            $this->service->delete($role);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('roles.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('roles.index')
            ->with('success', "Role deleted successfully.");
    }
}
