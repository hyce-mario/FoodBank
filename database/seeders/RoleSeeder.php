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
                    // Tier 1 audit: dropped 'distributions.{view,create}' —
                    // never referenced by any policy / controller / middleware.
                    // The loader's actual workflow runs through the event-day
                    // auth-code flow (no admin-permission gate); inventory.*
                    // covers the admin-side stock screens.
                    'inventory.view', 'inventory.edit',
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
            // Tier 2 demo roles — exercise the new finance / inventory /
            // purchase_orders / finance_reports gates so the perms have a
            // visible non-admin grantee for QA. ADMIN keeps everything via '*'.
            [
                'name'         => 'FINANCE',
                'display_name' => 'Finance Officer',
                'description'  => 'Manage finance transactions, categories, and reports',
                'permissions'  => [
                    'finance.view', 'finance.create', 'finance.edit', 'finance.delete',
                    'finance_reports.view', 'finance_reports.export',
                ],
            ],
            [
                'name'         => 'WAREHOUSE',
                'display_name' => 'Warehouse Operator',
                'description'  => 'Manage inventory items, stock movements, and purchase orders',
                'permissions'  => [
                    'inventory.view', 'inventory.edit',
                    'purchase_orders.view', 'purchase_orders.create',
                    'purchase_orders.receive', 'purchase_orders.cancel',
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
