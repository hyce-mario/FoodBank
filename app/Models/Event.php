<?php

namespace App\Models;

use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Event extends Model
{
    protected $fillable = [
        'name',
        'date',
        'status',
        'location',
        'lanes',
        'ruleset_id',
        'volunteer_group_id',
        'notes',
        'intake_auth_code',
        'scanner_auth_code',
        'loader_auth_code',
        'exit_auth_code',
    ];

    protected $casts = [
        'date'      => 'date',
        'lanes'     => 'integer',
        'ruleset_id'=> 'integer',
    ];

    // ─── Boot: generate auth codes on create ──────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (! SettingService::get('public_access.auto_generate_codes', true)) {
                return;
            }
            $event->intake_auth_code  ??= self::generateAuthCode();
            $event->scanner_auth_code ??= self::generateAuthCode();
            $event->loader_auth_code  ??= self::generateAuthCode();
            $event->exit_auth_code    ??= self::generateAuthCode();
        });
    }

    /**
     * Hard-coded length of every auth code, matching the schema's char(4)
     * column width. Previously this was a configurable setting
     * (`public_access.auth_code_length`), but the setting had no upper
     * bound and bumping it past 4 silently broke event creation with
     * "Data too long for column" — the schema width was not lockstep with
     * the setting. Removing the configurability + pinning to a constant
     * makes the bug impossible to reintroduce.
     */
    public const AUTH_CODE_LENGTH = 4;

    public static function generateAuthCode(): string
    {
        $max = (int) (10 ** self::AUTH_CODE_LENGTH) - 1;
        return str_pad(
            (string) random_int(0, $max),
            self::AUTH_CODE_LENGTH,
            '0',
            STR_PAD_LEFT,
        );
    }

    /** Regenerate all four auth codes and save. */
    public function regenerateAuthCodes(): void
    {
        $this->update([
            'intake_auth_code'  => self::generateAuthCode(),
            'scanner_auth_code' => self::generateAuthCode(),
            'loader_auth_code'  => self::generateAuthCode(),
            'exit_auth_code'    => self::generateAuthCode(),
        ]);
    }

    /** Check if a code is valid for a given role on this event. */
    public function authCodeFor(string $role): ?string
    {
        return match ($role) {
            'intake'  => $this->intake_auth_code,
            'scanner' => $this->scanner_auth_code,
            'loader'  => $this->loader_auth_code,
            'exit'    => $this->exit_auth_code,
            default   => null,
        };
    }

    /** Auth codes are valid while the event is current. */
    public function authCodesActive(): bool
    {
        return $this->status === 'current';
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    /**
     * Derive the correct status string from a date.
     */
    public static function deriveStatus(Carbon $date): string
    {
        if ($date->isToday()) {
            return 'current';
        }

        if ($date->isFuture()) {
            return 'upcoming';
        }

        return 'past';
    }

    /** True once an event has been completed / date has passed — no edits allowed. */
    public function isLocked(): bool
    {
        return $this->status === 'past';
    }

    public function isUpcoming(): bool
    {
        return $this->status === 'upcoming';
    }

    public function isCurrent(): bool
    {
        return $this->status === 'current';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'upcoming' => 'Upcoming',
            'current'  => 'Today',
            'past'     => 'Past',
            default    => ucfirst($this->status),
        };
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->status) {
            'upcoming' => 'bg-blue-100 text-blue-700',
            'current'  => 'bg-green-100 text-green-700',
            'past'     => 'bg-gray-100 text-gray-500',
            default    => 'bg-gray-100 text-gray-500',
        };
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function volunteerGroup()
    {
        return $this->belongsTo(\App\Models\VolunteerGroup::class);
    }

    public function ruleset()
    {
        return $this->belongsTo(\App\Models\AllocationRuleset::class, 'ruleset_id');
    }

    public function assignedVolunteers()
    {
        return $this->belongsToMany(\App\Models\Volunteer::class, 'event_volunteer')
                    ->withTimestamps();
    }

    public function preRegistrations()
    {
        return $this->hasMany(\App\Models\EventPreRegistration::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(EventMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(EventReview::class)->latest();
    }

    public function inventoryAllocations(): HasMany
    {
        return $this->hasMany(EventInventoryAllocation::class);
    }

    public function volunteerCheckIns(): HasMany
    {
        return $this->hasMany(VolunteerCheckIn::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%' . $term . '%';
        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
              ->orWhere('location', 'like', $like);
        });
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'upcoming');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('status', 'current');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('status', 'past');
    }
}
