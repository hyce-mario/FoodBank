<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use Auditable;
    protected $table = 'app_settings';

    protected $fillable = ['group', 'key', 'value', 'type'];

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Return the value cast to the correct PHP type based on the `type` column.
     */
    public function getCastedValueAttribute(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'float'   => (float) $this->value,
            'json'    => json_decode($this->value, true),
            default   => $this->value,
        };
    }
}
