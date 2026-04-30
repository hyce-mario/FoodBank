<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Auditable;

    // Exclude sensitive fields from audit log
    protected array $auditExclude = ['password', 'remember_token'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // ─── Permission Helpers ───────────────────────────────────────────────────

    public function hasPermission(string $permission): bool
    {
        if (! $this->role) {
            return false;
        }

        if (! $this->relationLoaded('role') || ! $this->role->relationLoaded('permissions')) {
            $this->load('role.permissions');
        }

        $permissions = $this->role->permissions->pluck('permission');

        return $permissions->contains('*') || $permissions->contains($permission);
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'ADMIN';
    }
}
