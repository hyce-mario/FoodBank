<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reproduce the user-reported bug: a user whose role has settings.view but
 * NOT settings.update should NOT be able to PUT /settings/{group}, POST
 * /settings/branding/{asset}, or DELETE /settings/branding/{asset}.
 *
 * Routes are gated by permission:settings.view at the group level + an
 * additional permission:settings.update on the writes — both must pass.
 */
class SettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $sysAdminWithoutUpdate;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        // Mirror the production "SYS_ADMIN11" role — broad permissions but
        // explicitly NO settings.update. Asserts that this user is forbidden
        // from writing settings even though they can view them.
        $role = Role::create([
            'name'         => 'SYS_ADMIN_TEST',
            'display_name' => 'System Administrator (no settings.update)',
            'description'  => 'Reproduction role for the settings.update bypass bug',
        ]);

        foreach (['settings.view', 'users.view', 'roles.view'] as $perm) {
            RolePermission::create(['role_id' => $role->id, 'permission' => $perm]);
        }

        $this->sysAdminWithoutUpdate = User::create([
            'name'     => 'Ben',
            'email'    => 'ben@example.test',
            'password' => bcrypt('secret-password'),
            'role_id'  => $role->id,
        ]);
    }

    public function test_settings_view_is_allowed(): void
    {
        $this->actingAs($this->sysAdminWithoutUpdate)
            ->get(route('settings.show', 'general'))
            ->assertOk();
    }

    public function test_put_settings_group_is_forbidden_without_settings_update(): void
    {
        $before = SettingService::get('general.app_name', 'Foodbank');

        $response = $this->actingAs($this->sysAdminWithoutUpdate)
            ->put(route('settings.update', 'general'), [
                'app_name' => 'HACKED-BY-BEN',
            ]);

        $response->assertForbidden();

        $after = SettingService::get('general.app_name', 'Foodbank');
        $this->assertSame($before, $after, 'Setting must NOT have been written');
    }

    public function test_branding_upload_is_forbidden_without_settings_update(): void
    {
        $this->actingAs($this->sysAdminWithoutUpdate)
            ->post(route('settings.branding.upload', 'logo'))
            ->assertForbidden();
    }

    public function test_branding_delete_is_forbidden_without_settings_update(): void
    {
        $this->actingAs($this->sysAdminWithoutUpdate)
            ->delete(route('settings.branding.delete', 'logo'))
            ->assertForbidden();
    }
}
