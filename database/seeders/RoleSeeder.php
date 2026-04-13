<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name'         => 'ADMIN',
                'display_name' => 'Administrator',
                'description'  => 'Full system access',
                'permissions'  => ['*'],
            ],
            [
                'name'         => 'INTAKE',
                'display_name' => 'Intake Staff',
                'description'  => 'Register and manage households',
                'permissions'  => [
                    'households.view', 'households.create', 'households.edit',
                    'checkin.view', 'events.view',
                ],
            ],
            [
                'name'         => 'SCANNER',
                'display_name' => 'QR Scanner',
                'description'  => 'Scan QR codes for check-in',
                'permissions'  => [
                    'checkin.view', 'checkin.scan',
                ],
            ],
            [
                'name'         => 'LOADER',
                'display_name' => 'Loader',
                'description'  => 'Manage inventory and distributions',
                'permissions'  => [
                    'inventory.view', 'inventory.edit',
                    'distributions.view', 'distributions.create',
                ],
            ],
            [
                'name'         => 'REPORTS',
                'display_name' => 'Reports Viewer',
                'description'  => 'View and export reports',
                'permissions'  => [
                    'reports.view', 'reports.export',
                ],
            ],
            [
                'name'         => 'VOL_MANAGER',
                'display_name' => 'Volunteer Manager',
                'description'  => 'Manage volunteers and schedules',
                'permissions'  => [
                    'volunteers.view', 'volunteers.create',
                    'volunteers.edit', 'volunteers.delete',
                ],
            ],
        ];

        foreach ($roles as $data) {
            $permissions = $data['permissions'];
            unset($data['permissions']);

            $role = Role::updateOrCreate(['name' => $data['name']], $data);

            // Rebuild permissions cleanly
            $role->permissions()->delete();

            foreach ($permissions as $permission) {
                RolePermission::create([
                    'role_id'    => $role->id,
                    'permission' => $permission,
                ]);
            }
        }
    }
}
