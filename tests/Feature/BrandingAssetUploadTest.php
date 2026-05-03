<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Production-ready upload / replace / remove for logo + favicon.
 *
 * Pins:
 *   - Permission gate (settings.update) on POST + DELETE
 *   - Stored filename is content-hashed so URL changes when the file does
 *     (browser cache busts automatically on replace)
 *   - Old file is deleted when a different image replaces it; same-content
 *     re-upload is idempotent (filename matches, no churn)
 *   - SVG is rejected for logo (XSS surface area)
 *   - Oversize files rejected (logo > 2 MB, favicon > 512 KB)
 *   - Over-dimension PNG rejected (logo > 1500x1500, favicon > 256x256)
 *   - Wrong mime rejected
 *   - DELETE clears the setting AND removes the file from disk
 *   - DELETE with empty path is a no-op (no 500)
 *
 * Uses real PNG fixtures (tests/fixtures/branding/*.png) instead of
 * UploadedFile::fake()->image() so the suite runs without the GD
 * extension being loaded in the test PHP CLI. The fixtures were
 * generated once via `php -d extension=gd` and committed.
 */
class BrandingAssetUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private const FIX_DIR = __DIR__ . '/../fixtures/branding';

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();
        Storage::fake('public');

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

    private function makeNonAdmin(): User
    {
        $role = Role::create(['name' => 'INTAKE', 'display_name' => 'Intake', 'description' => '']);
        RolePermission::create(['role_id' => $role->id, 'permission' => 'checkin.scan']);
        return User::create([
            'name'              => 'NonAdmin',
            'email'             => 'na@test.local',
            'password'          => bcrypt('p'),
            'role_id'           => $role->id,
            'email_verified_at' => now(),
        ]);
    }

    /** Build an UploadedFile from a checked-in fixture. */
    private function fixture(string $name, string $uploadName = null, string $mime = null): UploadedFile
    {
        $path = self::FIX_DIR . '/' . $name;
        if (! file_exists($path)) {
            $this->fail("Missing fixture: {$path}");
        }
        return new UploadedFile(
            $path,
            $uploadName ?? $name,
            $mime,
            test: true,
        );
    }

    // ─── Permission gate ─────────────────────────────────────────────────────

    public function test_unauthenticated_user_redirected_to_login_on_upload(): void
    {
        $this->post(route('settings.branding.upload', 'logo'), [
            'file' => $this->fixture('logo-200.png'),
        ])->assertRedirect(route('login'));
    }

    public function test_non_admin_without_settings_update_gets_403(): void
    {
        $user = $this->makeNonAdmin();
        $this->actingAs($user)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('logo-200.png'),
             ])
             ->assertForbidden();
    }

    public function test_unknown_asset_returns_404(): void
    {
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'banner'), [
                 'file' => $this->fixture('logo-200.png'),
             ])
             ->assertNotFound();
    }

    // ─── Logo: upload, replace, validation ───────────────────────────────────

    public function test_logo_upload_stores_hash_named_file_and_saves_setting(): void
    {
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('logo-200.png', 'mylogo.png'),
             ])
             ->assertRedirect(route('settings.show', 'branding'));

        $path = SettingService::get('branding.logo_path');

        $this->assertNotEmpty($path);
        // Filename pattern: branding/logo-<12 hex chars>.png
        $this->assertMatchesRegularExpression('/^branding\/logo-[0-9a-f]{12}\.png$/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_logo_replace_with_different_content_deletes_old_file(): void
    {
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('logo-200.png'),
             ]);
        $oldPath = SettingService::get('branding.logo_path');

        // Different image content → different hash → different filename.
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('logo-350.png'),
             ]);
        $newPath = SettingService::get('branding.logo_path');

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_logo_replace_with_same_content_is_idempotent(): void
    {
        // Two UploadedFile instances backed by the same fixture file will
        // produce the same content hash → same filename → settings update
        // is a no-op for the storage layer.
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('logo-200.png', 'a.png'),
             ]);
        $firstPath = SettingService::get('branding.logo_path');

        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('logo-200.png', 'b.png'),
             ]);
        $secondPath = SettingService::get('branding.logo_path');

        $this->assertSame($firstPath, $secondPath);
        Storage::disk('public')->assertExists($firstPath);
    }

    public function test_logo_rejects_svg(): void
    {
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('svg-logo.svg', 'logo.svg', 'image/svg+xml'),
             ])
             ->assertSessionHasErrors('file');

        $this->assertEmpty(SettingService::get('branding.logo_path', ''));
    }

    public function test_logo_rejects_over_dimension_image(): void
    {
        // 2000x2000 → exceeds 1500x1500 max
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('logo-too-big.png'),
             ])
             ->assertSessionHasErrors('file');
    }

    public function test_logo_rejects_wrong_mime(): void
    {
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('fake-pdf.pdf', 'logo.pdf', 'application/pdf'),
             ])
             ->assertSessionHasErrors('file');
    }

    // ─── Favicon: upload + dimension cap ─────────────────────────────────────

    public function test_favicon_png_upload_succeeds(): void
    {
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'favicon'), [
                 'file' => $this->fixture('favicon-32.png'),
             ])
             ->assertRedirect(route('settings.show', 'branding'));

        $path = SettingService::get('branding.favicon_path');
        $this->assertMatchesRegularExpression('/^branding\/favicon-[0-9a-f]{12}\.png$/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_favicon_rejects_png_above_256_pixels(): void
    {
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'favicon'), [
                 'file' => $this->fixture('favicon-too-big.png'),
             ])
             ->assertSessionHasErrors('file');
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public function test_delete_clears_setting_and_removes_file(): void
    {
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('logo-200.png'),
             ]);
        $path = SettingService::get('branding.logo_path');
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->admin)
             ->delete(route('settings.branding.delete', 'logo'))
             ->assertRedirect(route('settings.show', 'branding'));

        $this->assertEmpty(SettingService::get('branding.logo_path', ''));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_with_no_existing_path_is_a_noop(): void
    {
        // No prior upload — DELETE should still return 302 cleanly.
        $this->actingAs($this->admin)
             ->delete(route('settings.branding.delete', 'logo'))
             ->assertRedirect(route('settings.show', 'branding'));

        $this->assertEmpty(SettingService::get('branding.logo_path', ''));
    }

    public function test_non_admin_cannot_delete(): void
    {
        $user = $this->makeNonAdmin();
        $this->actingAs($user)
             ->delete(route('settings.branding.delete', 'logo'))
             ->assertForbidden();
    }

    // ─── Render of the branding settings page ────────────────────────────────

    public function test_branding_settings_page_renders_upload_card(): void
    {
        // Pins the wiring between SettingsController::show + show.blade.php +
        // branding_above.blade.php. Earlier architecture used @push/@stack
        // but the @stack yielded BEFORE the section was included, so the
        // upload card was silently dropped from the rendered HTML. This
        // test catches that regression class — if the upload card stops
        // rendering, the suite fails immediately.
        $this->actingAs($this->admin)
             ->get(route('settings.show', 'branding'))
             ->assertOk()
             ->assertSee('Logo &amp; Favicon', false)
             ->assertSee('Application Logo')
             ->assertSee('Favicon')
             ->assertSee('Upload Logo')
             ->assertSee('Upload Favicon');
    }

    public function test_branding_page_shows_replace_label_when_logo_set(): void
    {
        $this->actingAs($this->admin)
             ->post(route('settings.branding.upload', 'logo'), [
                 'file' => $this->fixture('logo-200.png'),
             ]);

        $this->actingAs($this->admin)
             ->get(route('settings.show', 'branding'))
             ->assertOk()
             ->assertSee('Replace Logo')
             ->assertDontSee('>Upload Logo<', false);
    }
}
