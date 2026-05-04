<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPreRegistration extends Model
{
    protected $fillable = [
        'event_id',
        'attendee_number',
        'first_name',
        'last_name',
        'email',
        'city',
        'state',
        'zipcode',
        'household_size',
        'children_count',
        'adults_count',
        'seniors_count',
        'household_id',
        'potential_household_id',
        'match_status',
    ];

    protected $casts = [
        'household_size' => 'integer',
        'children_count' => 'integer',
        'adults_count'   => 'integer',
        'seniors_count'  => 'integer',
    ];

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function potentialHousehold(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'potential_household_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public static function generateAttendeeNumber(): string
    {
        do {
            $number = str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
        } while (self::where('attendee_number', $number)->exists());

        return $number;
    }
}
