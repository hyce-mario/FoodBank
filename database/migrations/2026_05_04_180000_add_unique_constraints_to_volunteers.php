<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5.6.g — DB-level uniqueness on `volunteers.phone` and `.email`.
 *
 * Pre-fix, the volunteers table had no uniqueness on either column. The
 * public sign-up flow could create duplicate volunteer rows from the
 * same person re-submitting the form, and there was no DB-level guard
 * against admins manually entering a phone/email already on file.
 * 5.6.h follows up with application-layer phone-dedup on signup; this
 * migration is the foundation that backs that contract at the schema
 * level.
 *
 * MySQL 8 UNIQUE indexes treat NULL values as DISTINCT — multiple rows
 * with phone=NULL or email=NULL coexist freely. That matches the intent:
 * uniqueness only applies when a value is actually present. Empty
 * strings ARE collapsed by UNIQUE (two `''` rows would conflict), so the
 * migration first coerces any empty strings to NULL — this is a no-op
 * on the dev DB at the time of authoring (verified) but guards prod
 * deploys against the same edge case.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Coerce empty strings to NULL before adding UNIQUE. Two empty-
        // string rows would conflict; NULLs don't.
        DB::table('volunteers')->where('phone', '')->update(['phone' => null]);
        DB::table('volunteers')->where('email', '')->update(['email' => null]);

        Schema::table('volunteers', function (Blueprint $table) {
            $table->unique('phone');
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('volunteers', function (Blueprint $table) {
            $table->dropUnique('volunteers_phone_unique');
            $table->dropUnique('volunteers_email_unique');
        });
    }
};
