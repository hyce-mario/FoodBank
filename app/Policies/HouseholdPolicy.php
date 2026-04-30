<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    /** ADMIN wildcard — allow everything without hitting the DB again. */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool  { return $user->hasPermission('households.view'); }
    public function view(User $user, Household $household): bool { return $user->hasPermission('households.view'); }
    public function create(User $user): bool   { return $user->hasPermission('households.create'); }
    public function update(User $user, Household $household): bool { return $user->hasPermission('households.edit'); }
    public function delete(User $user, Household $household): bool { return $user->hasPermission('households.delete'); }
}
