<?php

namespace App\Models;

use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        'intake_auth_code_hash',
        'scanner_auth_code_hash',
        'loader_auth_code_hash',
        'exit_auth_code_hash',
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
            // If the controller pre-populated the hash columns (e.g. EventController
            // generates codes before create() so it can flash the plaintexts), the
            // observer skips those roles. Otherwise it generates blind hashes. The
            // blind-hash path is a fallback — the controller should always pre-generate
            // when it needs to show the codes to the admin.
            foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
                if (! $event->{"{$role}_auth_code_hash"}) {
                    $event->{"{$role}_auth_code_hash"} = Hash::make(self::generateAuthCode());
                }
            }
        });
    }

    /**
     * Length of every auth code. Updated from 4 (numeric) to 6 (alphanumeric)
     * in Phase 3.2 — 36⁶ ≈ 2B possibilities vs 10,000 previously, making
     * brute-force infeasible even without rate limiting alone.
     */
    public const AUTH_CODE_LENGTH = 6;

    /**
     * Generate a random 6-character uppercase alphanumeric auth code.
     * Returns the plaintext — callers are responsible for hashing before storage.
     */
    public static function generateAuthCode(): string
    {
        return Str::upper(Str::random(self::AUTH_CODE_LENGTH));
    }

    /**
     * Regenerate all four auth codes, store both plaintext (grace period) and
     * hashes, and return the plaintext codes for one-time display.
     *
     * @return array{intake: string, scanner: string, loader: string, exit: string}
     */
    /**
     * Regenerate all four auth codes, store the hashes, and return the plaintext
     * codes for one-time display (callers should flash them to the session).
     *
     * @return array{intake: string, scanner: string, loader: string, exit: string}
     */
    public function regenerateAuthCodes(): array
    {
        $plaintexts = [];
        $updates    = [];

        foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
            $code = self::generateAuthCode();
            $plaintexts[$role]                 = $code;
            $updates["{$role}_auth_code_hash"] = Hash::make($code);
        }

        $this->update($updates);

        return $plaintexts;
    }

    /** Return the bcrypt hash for the given role's auth code (Phase 3.2). */
    public function authCodeHashFor(string $role): ?string
    {
        return match ($role) {
            'intake'  => $this->intake_auth_code_hash,
            'scanner' => $this->scanner_auth_code_hash,
            'loader'  => $this->loader_auth_code_hash,
            'exit'    => $this->exit_auth_code_hash,
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
