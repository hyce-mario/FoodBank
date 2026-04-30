<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Event;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the invariant that Event::generateAuthCode() always produces a
 * 4-digit zero-padded numeric code and that plaintext columns are populated
 * on event creation.
 *
 * The drive-by fix in Phase 1.3 removed the configurable auth_code_length
 * setting; length is pinned to Event::AUTH_CODE_LENGTH = 4.
 *
 * Refs: AUDIT_REPORT.md Part 13 §3.2 (reverted).
 */
class EventAuthCodeLengthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();
    }

    public function test_generate_auth_code_returns_four_digit_numeric(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = Event::generateAuthCode();

            $this->assertSame(4, strlen($code), "code must be exactly 4 chars: '{$code}'");
            $this->assertMatchesRegularExpression('/^\d{4}$/', $code, "code must be numeric: '{$code}'");
        }
    }

    /**
     * Even if a stale `public_access.auth_code_length` row exists in the
     * app_settings table, the auth-code generator ignores it. The constant
     * on the Event model is the only source of truth.
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

    /**
     * Event creation via boot populates the plaintext auth code columns for
     * all four roles. Codes are stored as-is and readable by admins at any time.
     */
    public function test_event_creation_populates_plaintext_columns_via_boot(): void
    {
        $event = Event::create([
            'name'  => 'Auth Code Length Test',
            'date'  => '2026-05-01',
            'lanes' => 1,
        ]);

        foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
            $code = $event->{"{$role}_auth_code"};
            $this->assertNotNull($code, "{$role}_auth_code must be populated");
            $this->assertMatchesRegularExpression('/^\d{4}$/', $code, "{$role}_auth_code must be a 4-digit number: '{$code}'");
        }
    }
}
