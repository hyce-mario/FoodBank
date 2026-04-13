<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'ADMIN')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@foodbank.local'],
            [
                'name'              => 'John Solomon',
                'password'          => Hash::make('password'),
                'role_id'           => $adminRole->id,
                'email_verified_at' => now(),
            ]
        );
    }
}
