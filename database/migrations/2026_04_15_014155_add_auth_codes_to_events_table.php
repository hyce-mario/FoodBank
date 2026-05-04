<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->char('intake_auth_code',  4)->nullable()->after('notes');
            $table->char('scanner_auth_code', 4)->nullable()->after('intake_auth_code');
            $table->char('loader_auth_code',  4)->nullable()->after('scanner_auth_code');
            $table->char('exit_auth_code',    4)->nullable()->after('loader_auth_code');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['intake_auth_code', 'scanner_auth_code', 'loader_auth_code', 'exit_auth_code']);
        });
    }
};
