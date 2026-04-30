<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Drop the plaintext columns now that verification runs against the
            // hash columns (Phase 3.2.c) and no caller reads plaintext for auth.
            // Codes are shown to admins once via a session flash on creation or
            // regeneration — there is no longer a need to store them at rest.
            $table->dropColumn([
                'intake_auth_code',
                'scanner_auth_code',
                'loader_auth_code',
                'exit_auth_code',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Restore columns as nullable — no data is available after a drop.
            // Run inventory:reconcile and regenerate codes after rolling back.
            $table->char('intake_auth_code',  6)->nullable()->after('name');
            $table->char('scanner_auth_code', 6)->nullable()->after('intake_auth_code');
            $table->char('loader_auth_code',  6)->nullable()->after('scanner_auth_code');
            $table->char('exit_auth_code',    6)->nullable()->after('loader_auth_code');
        });
    }
};
