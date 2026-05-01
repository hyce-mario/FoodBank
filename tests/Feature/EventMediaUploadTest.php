<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMedia;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Pins the upload contract for EventMediaController::store().
 *
 * Regression context — discovered in production: the controller was calling
 * getSize() / getClientOriginalName() / getMimeType() AFTER $file->move(),
 * which moves the temp file off disk. Once moved, the UploadedFile's
 * internal path no longer exists, so SplFileInfo::stat() throws and the
 * client sees a 500. The fix is to capture metadata up front; this test
 * exercises the full path so a future refactor can't reintroduce the bug.
 */
class EventMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;

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

        $this->event = Event::create([
            'name'   => 'Upload Test Event',
            'date'   => now()->toDateString(),
            'status' => 'current',
            'lanes'  => 1,
        ]);
    }

    /**
     * Clean up any files written into public/event-media during the test.
     * Tests use the real filesystem because the controller writes via
     * $file->move() to public_path() — there's no Storage facade abstraction
     * to fake. We're explicit about cleanup so test runs don't litter.
     */
    protected function tearDown(): void
    {
        $dir = public_path("event-media/{$this->event->id}");
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        parent::tearDown();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson(route('events.media.store', $this->event), [
            'file' => UploadedFile::fake()->create('test.jpg', 50, 'image/jpeg'),
        ])->assertStatus(401);
    }

    public function test_image_upload_succeeds_and_persists_metadata(): void
    {
        $file = UploadedFile::fake()->create('vacation.jpg', 150, 'image/jpeg'); // 150 KB

        $response = $this->actingAs($this->admin)
                         ->postJson(route('events.media.store', $this->event), [
                             'file' => $file,
                         ]);

        $response->assertCreated();
        $response->assertJsonStructure(['ok', 'media' => ['id', 'type', 'url', 'name', 'size_formatted', 'mime_type']]);

        // The DB row must carry the values captured BEFORE move(). If the
        // pre-fix bug regresses, getSize() throws and we never reach this point.
        $this->assertDatabaseHas('event_media', [
            'event_id' => $this->event->id,
            'name'     => 'vacation.jpg',
            'type'     => 'image',
            'size'     => 150 * 1024,
        ]);

        // And the file actually landed on disk.
        $media = EventMedia::where('event_id', $this->event->id)->latest('id')->first();
        $this->assertFileExists(public_path($media->path));
    }

    public function test_video_upload_is_classified_as_video(): void
    {
        $file = UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'); // 500 KB

        $response = $this->actingAs($this->admin)
                         ->postJson(route('events.media.store', $this->event), [
                             'file' => $file,
                         ]);

        $response->assertCreated();
        $this->assertDatabaseHas('event_media', [
            'event_id' => $this->event->id,
            'name'     => 'clip.mp4',
            'type'     => 'video',
        ]);
    }

    public function test_disallowed_mime_type_returns_422(): void
    {
        // text/plain is not in the default allow-list; rejected at validation.
        $file = UploadedFile::fake()->create('notes.txt', 100, 'text/plain');

        $this->actingAs($this->admin)
             ->postJson(route('events.media.store', $this->event), ['file' => $file])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['file']);
    }

    public function test_pdf_upload_succeeds_and_is_classified_as_document(): void
    {
        // PDF was added to the default allow-list and gets a dedicated
        // 'document' type (not 'image'), so the photos tab can render it
        // with a file-icon card instead of a broken thumbnail.
        $file = UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf');

        $this->actingAs($this->admin)
             ->postJson(route('events.media.store', $this->event), ['file' => $file])
             ->assertCreated();

        $this->assertDatabaseHas('event_media', [
            'event_id' => $this->event->id,
            'name'     => 'receipt.pdf',
            'type'     => 'document',
        ]);
    }

    public function test_oversize_file_returns_422_at_validation_layer(): void
    {
        // 51 MB > the 50 MB limit. Laravel's `max:51200` rule (KB) catches it
        // before PHP's post_max_size would (which is the 413 path).
        $file = UploadedFile::fake()->create('huge.jpg', 51 * 1024, 'image/jpeg');

        $this->actingAs($this->admin)
             ->postJson(route('events.media.store', $this->event), ['file' => $file])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['file']);
    }

    public function test_missing_file_returns_422(): void
    {
        $this->actingAs($this->admin)
             ->postJson(route('events.media.store', $this->event), [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['file']);
    }

    public function test_sort_order_increments_for_repeat_uploads(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($this->admin)
                 ->postJson(route('events.media.store', $this->event), [
                     'file' => UploadedFile::fake()->create("img-{$i}.jpg", 50, 'image/jpeg'),
                 ])->assertCreated();
        }

        $orders = EventMedia::where('event_id', $this->event->id)
            ->orderBy('id')
            ->pluck('sort_order')
            ->all();

        $this->assertSame([1, 2, 3], $orders);
    }

    // ─── Settings-driven limits ──────────────────────────────────────────────

    public function test_max_size_is_enforced_from_setting(): void
    {
        // Tighten the cap to 1MB via setting; uploading 2MB should fail.
        SettingService::set('general.max_upload_size_mb', 1);

        $this->actingAs($this->admin)
             ->postJson(route('events.media.store', $this->event), [
                 'file' => UploadedFile::fake()->create('big.jpg', 2 * 1024, 'image/jpeg'), // 2MB
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['file']);
    }

    public function test_format_whitelist_is_enforced_from_setting(): void
    {
        // Allow only JPEG; PNG must be rejected even though it's in the
        // controller's hard-coded fallback. Confirms the setting wins.
        SettingService::set('general.allowed_upload_formats', json_encode(['image/jpeg']));

        $this->actingAs($this->admin)
             ->postJson(route('events.media.store', $this->event), [
                 'file' => UploadedFile::fake()->create('photo.png', 50, 'image/png'),
             ])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['file']);

        // JPEG still works.
        $this->actingAs($this->admin)
             ->postJson(route('events.media.store', $this->event), [
                 'file' => UploadedFile::fake()->create('photo.jpg', 50, 'image/jpeg'),
             ])
             ->assertCreated();
    }

    public function test_empty_format_whitelist_falls_back_to_baseline(): void
    {
        // An admin who unchecks every format must NOT brick uploads — the
        // controller's fallback list takes over so the system stays usable.
        SettingService::set('general.allowed_upload_formats', json_encode([]));

        $this->actingAs($this->admin)
             ->postJson(route('events.media.store', $this->event), [
                 'file' => UploadedFile::fake()->create('photo.jpg', 50, 'image/jpeg'),
             ])
             ->assertCreated();
    }

    public function test_size_setting_is_clamped_to_safe_range(): void
    {
        // Bogus row in app_settings: zero MB. The controller floors it to 1
        // so the validator generates a sane rule (max:1024) instead of max:0.
        SettingService::set('general.max_upload_size_mb', 0);

        // 100KB should still pass — confirms we didn't lock everyone out.
        $this->actingAs($this->admin)
             ->postJson(route('events.media.store', $this->event), [
                 'file' => UploadedFile::fake()->create('tiny.jpg', 100, 'image/jpeg'),
             ])
             ->assertCreated();
    }
}
