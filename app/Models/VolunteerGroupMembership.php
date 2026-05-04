<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerGroupMembership extends Model
{
    protected $fillable = [
        'volunteer_id',
        'group_id',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(Volunteer::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VolunteerGroup::class, 'group_id');
    }
}
