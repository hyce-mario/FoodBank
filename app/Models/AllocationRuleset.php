<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllocationRuleset extends Model
{
    protected $fillable = [
        'name',
        'allocation_type',
        'description',
        'is_active',
        'max_household_size',
        'rules',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'max_household_size' => 'integer',
        'rules'              => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The inventory items that make up one bag for this ruleset.
     * Phase 2.1.b — Option A schema (new table, not embedded JSON).
     */
    public function components(): HasMany
    {
        return $this->hasMany(AllocationRulesetComponent::class, 'allocation_ruleset_id');
    }

    // ─── Business Logic ───────────────────────────────────────────────────────

    /**
     * Calculate the number of bags allocated for a given household size.
     * Returns 0 if no rule matches.
     */
    public function getBagsFor(int $size): int
    {
        foreach ($this->rules as $rule) {
            $min = (int) ($rule['min'] ?? 1);
            $max = isset($rule['max']) && $rule['max'] !== null
                ? (int) $rule['max']
                : PHP_INT_MAX;

            if ($size >= $min && $size <= $max) {
                return (int) ($rule['bags'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Return human-readable range label for a rule.
     * $unit: 'person' (default) or 'family'
     */
    public static function ruleLabel(array $rule, string $unit = 'person'): string
    {
        $min      = (int) ($rule['min'] ?? 1);
        $max      = isset($rule['max']) && $rule['max'] !== null ? (int) $rule['max'] : null;
        $singular = $unit;
        $plural   = $unit === 'person' ? 'people' : 'families';

        if ($max === null) {
            return "{$min}+ {$plural}";
        }

        if ($min === $max) {
            return "{$min} " . ($min === 1 ? $singular : $plural);
        }

        return "{$min}–{$max} {$plural}";
    }
}
