<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerCheckIn extends Model
{
    protected $fillable = [
        'event_id',
        'volunteer_id',
        'role',
        'source',
        'is_first_timer',
        'checked_in_at',
        'checked_out_at',
        'hours_served',
        'notes',
    ];

    protected $casts = [
        'is_first_timer'  => 'boolean',
        'checked_in_at'   => 'datetime',
        'checked_out_at'  => 'datetime',
        'hours_served'    => 'decimal:2',
    ];

    // ─── Source labels ─────────────────────────────────────────────────────────

    public const SOURCES = [
        'pre_assigned'  => 'Pre-Assigned',
        'walk_in'       => 'Walk-In',
        'new_volunteer' => 'New Volunteer',
    ];

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst($this->source);
    }

    public function sourceBadgeClasses(): string
    {
        return match ($this->source) {
            'pre_assigned'  => 'bg-blue-100 text-blue-700',
            'walk_in'       => 'bg-amber-100 text-amber-700',
            'new_volunteer' => 'bg-purple-100 text-purple-700',
            default         => 'bg-gray-100 text-gray-600',
        };
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }
}
