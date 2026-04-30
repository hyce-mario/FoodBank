<?php

namespace App\Policies;

use App\Models\EventReview;
use App\Models\User;

class EventReviewPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool { return $user->hasPermission('reviews.view'); }
    public function view(User $user, EventReview $review): bool { return $user->hasPermission('reviews.view'); }
    public function update(User $user, EventReview $review): bool { return $user->hasPermission('reviews.moderate'); }
}
