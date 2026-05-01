<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the multi_select setting save path. Regression context: a stray
 * empty-string entry in the submitted array (carried in by old() after a
 * prior failed save, or by a "field is present" sentinel input) was
 * making the per-element `in:` validation rule reject `…0` and `…1` even
 * when the real selections were valid — blocking the entire form from
 * saving. The fix strips empties before validation runs.
 */
class SettingsMultiSelectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();

        $role = Role::create(['name' => 'ADMIN', 'display_name' => 'Admin', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => '*']);
        $this->admin = User::create([
            'name'              => 'Admin',
            'email'             => 'admin@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    /** Build a payload with sensible-default values for every general field
     *  so the validation rules pass on unrelated fields. */
    private function generalGroupPayload(array $overrides = []): array
    {
        $defs = SettingService::groupDefinitions('general');
        $payload = [];
        foreach ($defs as $key => $def) {
            $payload[$key] = $def['default'] ?? '';
        }
        return array_merge($payload, $overrides);
    }

    public function test_multi_select_saves_clean_selection(): void
    {
        $this->actingAs($this->admin)
             ->put(route('settings.update', 'general'), $this->generalGroupPayload([
                 'allowed_upload_formats' => ['image/jpeg', 'image/png'],
             ]))
             ->assertRedirect();

        $this->assertSame(
            ['image/jpeg', 'image/png'],
            SettingService::get('general.allowed_upload_formats')
        );
    }

    public function test_empty_string_entries_are_stripped_before_validation(): void
    {
        // Reproduces the user-reported bug: a submitted array carrying a
        // stray empty entry must NOT fail validation. Pre-fix this would
        // produce "The selected allowed_upload_formats.0 is invalid."
        $this->actingAs($this->admin)
             ->put(route('settings.update', 'general'), $this->generalGroupPayload([
                 'allowed_upload_formats' => ['', 'image/jpeg'],
             ]))
             ->assertRedirect()
             ->assertSessionHasNoErrors();

        $this->assertSame(
            ['image/jpeg'],
            SettingService::get('general.allowed_upload_formats')
        );
    }

    public function test_multiple_empty_entries_are_stripped(): void
    {
        // Pre-fix this was the .0 + .1 case from the bug report.
        $this->actingAs($this->admin)
             ->put(route('settings.update', 'general'), $this->generalGroupPayload([
                 'allowed_upload_formats' => ['', '', 'application/pdf'],
             ]))
             ->assertRedirect()
             ->assertSessionHasNoErrors();

        $this->assertSame(
            ['application/pdf'],
            SettingService::get('general.allowed_upload_formats')
        );
    }

    public function test_truly_invalid_value_still_rejected(): void
    {
        // Whitelist enforcement must still work — only empty strings get
        // the free pass, real garbage (a mime not in the option list)
        // continues to fail validation.
        $this->actingAs($this->admin)
             ->put(route('settings.update', 'general'), $this->generalGroupPayload([
                 'allowed_upload_formats' => ['evil/payload'],
             ]))
             ->assertSessionHasErrors(['allowed_upload_formats.0']);
    }

    public function test_saving_with_no_multi_select_key_clears_to_empty_array(): void
    {
        // Pre-set a value so we can prove "field absent" clears it.
        SettingService::set('general.allowed_upload_formats', json_encode(['image/jpeg']));
        SettingService::flush();

        // Build a payload that explicitly omits the multi_select key — this
        // is what happens when the user unchecks every option.
        $payload = $this->generalGroupPayload();
        unset($payload['allowed_upload_formats']);

        $this->actingAs($this->admin)
             ->put(route('settings.update', 'general'), $payload)
             ->assertRedirect()
             ->assertSessionHasNoErrors();

        $this->assertSame([], SettingService::get('general.allowed_upload_formats'));
    }

    public function test_other_fields_save_even_when_multi_select_is_present(): void
    {
        // The exact user-reported workflow: change max_upload_size_mb while
        // the multi_select is on the form. Pre-fix the form blocked saving
        // because the multi_select validation failed on empty entries.
        $this->actingAs($this->admin)
             ->put(route('settings.update', 'general'), $this->generalGroupPayload([
                 'max_upload_size_mb'     => 75,
                 'allowed_upload_formats' => ['', 'image/jpeg', ''],
             ]))
             ->assertRedirect()
             ->assertSessionHasNoErrors();

        $this->assertSame(75, SettingService::get('general.max_upload_size_mb'));
        $this->assertSame(['image/jpeg'], SettingService::get('general.allowed_upload_formats'));
    }
}
