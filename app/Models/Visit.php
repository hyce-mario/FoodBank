<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Visit extends Model
{
    protected $fillable = [
        'event_id',
        'lane',
        'queue_position',
        'visit_status',
        'start_time',
        'end_time',
        'served_bags',
        'queued_at',
        'loading_completed_at',
        'exited_at',
    ];

    protected $casts = [
        'start_time'           => 'datetime',
        'end_time'             => 'datetime',
        'queued_at'            => 'datetime',
        'loading_completed_at' => 'datetime',
        'exited_at'            => 'datetime',
        'lane'                 => 'integer',
        'queue_position'       => 'integer',
        'served_bags'          => 'integer',
    ];

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isCheckedIn(): bool  { return $this->visit_status === 'checked_in'; }
    public function isQueued(): bool     { return $this->visit_status === 'queued'; }
    public function isLoading(): bool    { return $this->visit_status === 'loading'; }
    public function isLoaded(): bool     { return $this->visit_status === 'loaded'; }
    public function isExited(): bool     { return $this->visit_status === 'exited'; }

    public function statusLabel(): string
    {
        return match ($this->visit_status) {
            'checked_in' => 'Checked In',
            'queued'     => 'Queued',
            'loading'    => 'Loading',
            'loaded'     => 'Loaded',
            'exited'     => 'Exited',
            default      => ucfirst($this->visit_status ?? 'unknown'),
        };
    }

    // ─── Legacy helpers ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->end_time === null;
    }

    public function durationMinutes(): int
    {
        return (int) now()->diffInMinutes($this->start_time);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'visit_households')
            ->withTimestamps()
            // Phase 1.2.a snapshot columns. EventCheckInService writes these
            // at attach time (Phase 1.2.b); reports read them temporally
            // (Phase 1.2.c) so editing a household after a visit does not
            // rewrite history. Without withPivot(), Eloquent silently drops
            // these on read even when the columns are populated.
            ->withPivot([
                'household_size',
                'children_count',
                'adults_count',
                'seniors_count',
                'vehicle_make',
                'vehicle_color',
            ]);
    }

    public function primaryHousehold(): ?Household
    {
        return $this->households()->first();
    }
}
