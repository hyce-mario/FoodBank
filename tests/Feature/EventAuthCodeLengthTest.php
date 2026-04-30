<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Event;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the invariant that Event::generateAuthCode() always produces a
 * 4-character zero-padded numeric code, independent of any value present
 * in the app_settings table.
 *
 * Background: previously the length was driven by a configurable
 * `public_access.auth_code_length` setting with no upper bound. Bumping
 * that setting past 4 silently broke event creation because the schema
 * fixes auth-code columns at char(4). The setting was removed and the
 * length pinned to Event::AUTH_CODE_LENGTH; this test guards the pin so
 * a future maintainer who tries to re-introduce configurability gets a
 * clear failure mode.
 */
class EventAuthCodeLengthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();
    }

    public function test_generate_auth_code_returns_four_character_numeric(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = Event::generateAuthCode();

            $this->assertSame(4, strlen($code), "code must be exactly 4 chars: '{$code}'");
            $this->assertTrue(ctype_digit($code), "code must be numeric digits only: '{$code}'");
        }
    }

    /**
     * Even if a stale `public_access.auth_code_length` row exists in the
     * app_settings table (e.g. surviving from an old install or a manual
     * INSERT), the auth-code generator must ignore it. The constant on
     * the Event model is the only source of truth.
     */
    public function test_generate_auth_code_ignores_stale_setting_row(): void
    {
        AppSetting::create([
            'group' => 'public_access',
            'key'   => 'public_access.auth_code_length',
            'value' => '8',
            'type'  => 'integer',
        ]);
        SettingService::flush();

        $code = Event::generateAuthCode();

        $this->assertSame(4, strlen($code));
    }

    public function test_event_creation_populates_4_char_codes_via_boot(): void
    {
        $event = Event::create([
            'name'  => 'Auth Code Length Test',
            'date'  => '2026-05-01',
            'lanes' => 1,
        ]);

        foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
            $code = $event->{"{$role}_auth_code"};
            $this->assertSame(4, strlen($code), "{$role}_auth_code must be 4 chars: '{$code}'");
            $this->assertTrue(ctype_digit($code));
        }
    }

}
