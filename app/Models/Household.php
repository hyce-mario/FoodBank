<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $fillable = [
        'household_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'city',
        'state',
        'zip',
        'household_size',
        'notes',
        'qr_token',
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

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
              ->orWhere('last_name', 'like', "%{$term}%")
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
              ->orWhere('household_number', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    // ─── Relationships (placeholders for future phases) ───────────────────────

    // public function visits(): HasMany  { return $this->hasMany(Visit::class); }
    // public function distributions(): HasMany { ... }
}
