<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VolunteerGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function volunteers(): BelongsToMany
    {
        return $this->belongsToMany(
            Volunteer::class,
            'volunteer_group_memberships',
            'group_id',
            'volunteer_id'
        )->withPivot('joined_at')->withTimestamps();
    }

    // Placeholder for future phase — event assignments
    // public function events(): BelongsToMany
    // {
    //     return $this->belongsToMany(Event::class, 'event_volunteer_groups')
    //                 ->withTimestamps();
    // }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('name', 'like', "%{$term}%")
                     ->orWhere('description', 'like', "%{$term}%");
    }
}
