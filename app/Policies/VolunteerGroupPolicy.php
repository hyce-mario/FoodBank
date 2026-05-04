<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VolunteerGroup;

/**
 * Authorizes volunteer-group actions.
 *
 * Reuses the existing `volunteers.*` permission set rather than introducing
 * a parallel `volunteer-groups.*` key — groups are a sub-concept of
 * volunteers, and the VOL_MANAGER role already carries the right perms.
 * Admins fall through via the before() wildcard, matching the convention
 * established by VolunteerPolicy / EventPolicy / HouseholdPolicy.
 */
class VolunteerGroupPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool      { return $user->hasPermission('volunteers.view'); }
    public function view(User $user, VolunteerGroup $group): bool { return $user->hasPermission('volunteers.view'); }
    public function create(User $user): bool       { return $user->hasPermission('volunteers.create'); }
    public function update(User $user, VolunteerGroup $group): bool { return $user->hasPermission('volunteers.edit'); }
    public function delete(User $user, VolunteerGroup $group): bool { return $user->hasPermission('volunteers.delete'); }

    /**
     * Syncing the membership list is an update on the group, not a separate
     * permission — anyone who can edit the group can manage who's in it.
     */
    public function manageMembers(User $user, VolunteerGroup $group): bool
    {
        return $user->hasPermission('volunteers.edit');
    }
}
