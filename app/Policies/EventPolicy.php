<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool { return $user->hasPermission('events.view'); }
    public function view(User $user, Event $event): bool { return $user->hasPermission('events.view'); }
    public function create(User $user): bool  { return $user->hasPermission('events.create'); }
    public function update(User $user, Event $event): bool { return $user->hasPermission('events.edit'); }
    public function delete(User $user, Event $event): bool { return $user->hasPermission('events.delete'); }
}
