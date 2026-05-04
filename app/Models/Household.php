<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Household extends Model
{
    use Auditable;
    protected $fillable = [
        'household_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'city',
        'state',
        'zip',
        'vehicle_make',
        'vehicle_color',
        'household_size',
        'children_count',
        'adults_count',
        'seniors_count',
        'representative_household_id',
        'notes',
        'qr_token',
    ];

    protected $casts = [
        'household_size'              => 'integer',
        'children_count'              => 'integer',
        'adults_count'                => 'integer',
        'seniors_count'               => 'integer',
        'events_attended_count'       => 'integer',
        'representative_household_id' => 'integer',
    ];

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getLocationAttribute(): string
    {
        return collect([$this->city, $this->state])->filter()->implode(', ');
    }

    /** "Silver Toyota" — returns null when neither field is set. */
    public function getVehicleLabelAttribute(): ?string
    {
        $parts = array_filter([$this->vehicle_color, $this->vehicle_make]);
        return count($parts) ? implode(' ', $parts) : null;
    }

    /** True when this household is being represented by another household. */
    public function getIsRepresentedAttribute(): bool
    {
        return $this->representative_household_id !== null;
    }

    /** True when this household represents at least one other household. */
    public function getIsRepresentativeAttribute(): bool
    {
        return $this->representedHouseholds()->exists();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, string $term): Builder
    {
        // Driver-aware concat: MySQL uses CONCAT(), SQLite uses ||. The
        // sqlite branch keeps the in-memory test DB happy without changing
        // production semantics.
        $concat = DB::connection()->getDriverName() === 'sqlite'
            ? "first_name || ' ' || last_name"
            : "CONCAT(first_name, ' ', last_name)";

        return $query->where(function (Builder $q) use ($term, $concat) {
            $q->where('first_name', 'like', "%{$term}%")
              ->orWhere('last_name', 'like', "%{$term}%")
              ->orWhereRaw("{$concat} LIKE ?", ["%{$term}%"])
              ->orWhere('household_number', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('zip', 'like', "%{$term}%");
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function visits(): BelongsToMany
    {
        return $this->belongsToMany(Visit::class, 'visit_households')
            ->withTimestamps()
            // Phase 1.2.a snapshot columns — see Visit::households() for context.
            ->withPivot([
                'household_size',
                'children_count',
                'adults_count',
                'seniors_count',
                'vehicle_make',
                'vehicle_color',
            ]);
    }

    /**
     * Build the pivot payload that snapshots this household's demographics
     * and vehicle info onto `visit_households` at attach time. Phase 1.2.b.
     *
     * Single source of truth for the snapshot field set — both
     * EventCheckInService and any seeders that bypass the service must
     * call this so adding a snapshot column in a future phase requires
     * touching only one place.
     */
    public function toVisitPivotSnapshot(): array
    {
        return [
            'household_size' => $this->household_size,
            'children_count' => $this->children_count,
            'adults_count'   => $this->adults_count,
            'seniors_count'  => $this->seniors_count,
            'vehicle_make'   => $this->vehicle_make,
            'vehicle_color'  => $this->vehicle_color,
        ];
    }

    /**
     * The household that is picking up on behalf of this household.
     * Null when this household visits on its own.
     */
    public function representative(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'representative_household_id');
    }

    /**
     * All households that this household picks up food for.
     */
    public function representedHouseholds(): HasMany
    {
        return $this->hasMany(Household::class, 'representative_household_id');
    }
}
