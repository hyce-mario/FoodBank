<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Volunteer extends Model
{
    /** Operational roles available for selection. */
    public const ROLES = [
        'Driver'      => 'Driver',
        'Loader'      => 'Loader',
        'Intake'      => 'Intake',
        'Scanner'     => 'Scanner',
        'Coordinator' => 'Coordinator',
        'Other'       => 'Other',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'role',
        'user_id',
    ];

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            VolunteerGroup::class,
            'volunteer_group_memberships',
            'volunteer_id',
            'group_id'
        )->withPivot('joined_at')->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(VolunteerCheckIn::class);
    }

    /** All events this volunteer has been assigned to (pre-assignment pivot table). */
    public function assignedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_volunteer')->withTimestamps();
    }

    // ─── First-timer logic ────────────────────────────────────────────────────

    /**
     * A volunteer is a first-timer if they have served at most ONE distinct
     * event — based on actual service history, not account creation date.
     *
     * Distinct event count is the right denominator: a volunteer with two
     * check-in rows on a single event day (e.g. checked out for lunch,
     * checked back in) has served one event, not two. Counting rows would
     * mark them as "returning" after their first lunch break.
     */
    public function isFirstTimer(): bool
    {
        return $this->checkIns()->distinct('event_id')->count('event_id') <= 1;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
              ->orWhere('last_name', 'like', "%{$term}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
