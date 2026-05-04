<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('ruleset', 50)->nullable()->after('lanes');
            $table->foreignId('volunteer_group_id')
                  ->nullable()
                  ->constrained('volunteer_groups')
                  ->nullOnDelete()
                  ->after('ruleset');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['volunteer_group_id']);
            $table->dropColumn(['ruleset', 'volunteer_group_id']);
        });
    }
};
