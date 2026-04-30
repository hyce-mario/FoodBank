<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Widen plaintext columns from char(4) to char(6) to hold the new
            // 6-character alphanumeric codes during the grace period.
            $table->char('intake_auth_code',  6)->nullable()->change();
            $table->char('scanner_auth_code', 6)->nullable()->change();
            $table->char('loader_auth_code',  6)->nullable()->change();
            $table->char('exit_auth_code',    6)->nullable()->change();

            // New hashed columns (nullable — populated by the backfill below
            // and by boot/regenerateAuthCodes going forward).
            $table->string('intake_auth_code_hash')->nullable()->after('intake_auth_code');
            $table->string('scanner_auth_code_hash')->nullable()->after('scanner_auth_code');
            $table->string('loader_auth_code_hash')->nullable()->after('loader_auth_code');
            $table->string('exit_auth_code_hash')->nullable()->after('exit_auth_code');
        });

        // Backfill: generate new 6-char codes + hashes for all upcoming/current
        // events. Past events are ignored — their codes are no longer needed.
        // Skip-on-empty so fresh installs (no events rows) run without error.
        $events = DB::table('events')
            ->whereIn('status', ['upcoming', 'current'])
            ->get(['id']);

        foreach ($events as $event) {
            $codes = [];
            foreach (['intake', 'scanner', 'loader', 'exit'] as $role) {
                $code = strtoupper(Str::random(6));
                $codes["{$role}_auth_code"]      = $code;
                $codes["{$role}_auth_code_hash"] = password_hash($code, PASSWORD_BCRYPT);
            }
            DB::table('events')->where('id', $event->id)->update($codes);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'intake_auth_code_hash',
                'scanner_auth_code_hash',
                'loader_auth_code_hash',
                'exit_auth_code_hash',
            ]);
            $table->char('intake_auth_code',  4)->nullable()->change();
            $table->char('scanner_auth_code', 4)->nullable()->change();
            $table->char('loader_auth_code',  4)->nullable()->change();
            $table->char('exit_auth_code',    4)->nullable()->change();
        });
    }
};
