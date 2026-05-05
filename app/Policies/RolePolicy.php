<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /** ADMIN wildcard — allow everything without hitting the DB again. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool              { return $user->hasPermission('roles.view'); }
    public function view(User $user, Role $role): bool     { return $user->hasPermission('roles.view'); }
    public function create(User $user): bool               { return $user->hasPermission('roles.create'); }
    public function update(User $user, Role $role): bool   { return $user->hasPermission('roles.edit'); }
    public function delete(User $user, Role $role): bool   { return $user->hasPermission('roles.delete'); }
}
