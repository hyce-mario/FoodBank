<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCategory extends Model
{
    /** Phase 7.4.a — NFP functional classification (IRS-990 expense buckets). */
    public const FUNCTION_PROGRAM            = 'program';
    public const FUNCTION_MANAGEMENT_GENERAL = 'management_general';
    public const FUNCTION_FUNDRAISING        = 'fundraising';

    public const FUNCTIONS = [
        self::FUNCTION_PROGRAM,
        self::FUNCTION_MANAGEMENT_GENERAL,
        self::FUNCTION_FUNDRAISING,
    ];

    public const FUNCTION_LABELS = [
        self::FUNCTION_PROGRAM            => 'Program',
        self::FUNCTION_MANAGEMENT_GENERAL => 'Management & General',
        self::FUNCTION_FUNDRAISING        => 'Fundraising',
    ];

    protected $fillable = ['name', 'type', 'description', 'is_active', 'function_classification'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class, 'category_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', 'expense');
    }

    public function scopeProgram(Builder $query): Builder
    {
        return $query->where('function_classification', self::FUNCTION_PROGRAM);
    }

    public function scopeManagementGeneral(Builder $query): Builder
    {
        return $query->where('function_classification', self::FUNCTION_MANAGEMENT_GENERAL);
    }

    public function scopeFundraising(Builder $query): Builder
    {
        return $query->where('function_classification', self::FUNCTION_FUNDRAISING);
    }

    public function functionLabel(): string
    {
        return self::FUNCTION_LABELS[$this->function_classification] ?? 'Program';
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function typeBadgeClasses(): string
    {
        return $this->type === 'income'
            ? 'bg-green-100 text-green-700'
            : 'bg-red-100 text-red-700';
    }

    public function typeLabel(): string
    {
        return ucfirst($this->type);
    }
}
