<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-shot cleanup: remove the orphan `public_access.auth_code_length`
 * row from app_settings. The setting was previously configurable but had
 * no upper bound; bumping it past 4 silently broke event creation because
 * the events table fixes auth-code columns at char(4). Length is now
 * hard-coded as Event::AUTH_CODE_LENGTH; the definition was removed from
 * SettingService::definitions(). After this migration runs there will be
 * no row, no definition, and no way to reintroduce the bug via the UI.
 *
 * Idempotent: harmless to run twice (DELETE on a missing row is a no-op).
 *
 * down() does NOT recreate the row — once the definition is gone, the
 * row would be orphan again. If a future maintainer wants to reintroduce
 * the configurable setting, they should also widen the schema column,
 * add a min/max bound to the definition, and write their own forward
 * migration to seed a sensible default.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')
            ->where('key', 'public_access.auth_code_length')
            ->delete();
    }

    public function down(): void
    {
        // Intentional no-op. See class docblock for why we don't recreate.
    }
};
