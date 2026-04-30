<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Volunteer;

class VolunteerPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool { return $user->hasPermission('volunteers.view'); }
    public function view(User $user, Volunteer $volunteer): bool { return $user->hasPermission('volunteers.view'); }
    public function create(User $user): bool  { return $user->hasPermission('volunteers.create'); }
    public function update(User $user, Volunteer $volunteer): bool { return $user->hasPermission('volunteers.edit'); }
    public function delete(User $user, Volunteer $volunteer): bool { return $user->hasPermission('volunteers.delete'); }
}
