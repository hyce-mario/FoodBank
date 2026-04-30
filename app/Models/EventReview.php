<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReview extends Model
{
    protected $fillable = [
        'event_id',
        'rating',
        'review_text',
        'reviewer_name',
        'email',
        // is_visible intentionally omitted — public form must not control this.
        // Set it via direct property assignment in controllers.
    ];

    protected $casts = [
        'rating'     => 'integer',
        'is_visible' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
