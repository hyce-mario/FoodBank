<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Event;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the invariant that Event::generateAuthCode() always produces a
 * 6-character uppercase alphanumeric code and that both plaintext and hash
 * are populated on event creation.
 *
 * Phase 3.2 update: code format changed from 4-digit numeric to 6-char
 * uppercase alphanumeric (36⁶ ≈ 2B possibilities). The drive-by fix in
 * Phase 1.3 removed the configurable auth_code_length setting; length is
 * still pinned to Event::AUTH_CODE_LENGTH = 6.
 *
 * Refs: AUDIT_REPORT.md Part 13 §3.2.
 */
class EventAuthCodeLengthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flush();
    }

    public function test_generate_auth_code_returns_six_character_alphanumeric(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = Event::generateAuthCode();

            $this->assertSame(6, strlen($code), "code must be exactly 6 chars: '{$code}'");
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $code, "code must be uppercase alphanumeric: '{$code}'");
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

        $this->assertSame(6, strlen($code));
    }

    /**
     * Event creation via boot populates the hash columns for all four roles.
     * Phase 3.2.d dropped plaintext columns — the boot observer now generates
     * blind hashes (no plaintext stored). We verify the hash format is correct.
     * The controller-path (EventController::store) pre-generates codes so it
     * can flash plaintexts; that path is covered by the store() code path.
     */
    public function test_event_creation_populates_hash_columns_via_boot(): void
    {
        $event = Event::create([
            'name'  => 'Auth Code Length Test',
            'date'  => '2026-05-01',
            'lanes' => 1,
        ]);

        foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
            $hash = $event->{"{$role}_auth_code_hash"};
            $this->assertNotNull($hash, "{$role}_auth_code_hash must be populated");
            // Bcrypt hashes start with $2y$ — verify the hash looks like bcrypt.
            $this->assertStringStartsWith('$2y$', $hash, "{$role}_auth_code_hash must be a bcrypt hash");
        }
    }
}
