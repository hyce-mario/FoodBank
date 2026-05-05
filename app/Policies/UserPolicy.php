<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** ADMIN wildcard — allow everything without hitting the DB again. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool                 { return $user->hasPermission('users.view'); }
    public function view(User $user, User $target): bool      { return $user->hasPermission('users.view'); }
    public function create(User $user): bool                  { return $user->hasPermission('users.create'); }
    public function update(User $user, User $target): bool    { return $user->hasPermission('users.edit'); }
    public function delete(User $user, User $target): bool    { return $user->hasPermission('users.delete'); }
}
