<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'ADMIN')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@foodbank.local'],
            [
                'name'              => 'John Solomon',
                'password'          => Hash::make($this->resolvePassword()),
                'role_id'           => $adminRole->id,
                'email_verified_at' => now(),
            ]
        );
    }

    /**
     * In production / staging, require ADMIN_SEED_PASSWORD via env so a
     * known credential ('password') can never reach prod. In local /
     * testing the literal 'password' is allowed for dev convenience —
     * but env-set value still wins if present.
     */
    private function resolvePassword(): string
    {
        $env = app()->environment();
        $fromEnv = env('ADMIN_SEED_PASSWORD');

        if (in_array($env, ['production', 'staging'], true)) {
            if (! is_string($fromEnv) || $fromEnv === '') {
                throw new RuntimeException(
                    "ADMIN_SEED_PASSWORD env var is required when running AdminUserSeeder in {$env}. "
                    . "Set it (e.g. ADMIN_SEED_PASSWORD=\$(openssl rand -base64 24)) and re-run."
                );
            }
            return $fromEnv;
        }

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : 'password';
    }
}
