<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add allocation_type to allocation_rulesets
        Schema::table('allocation_rulesets', function (Blueprint $table) {
            $table->string('allocation_type', 20)->default('household_size')->after('name');
        });

        // Replace the free-text 'ruleset' column on events with a proper FK
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('ruleset_id')->nullable()->constrained('allocation_rulesets')->nullOnDelete()->after('lanes');
        });

        // Drop the old free-text column if it exists
        if (Schema::hasColumn('events', 'ruleset')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('ruleset');
            });
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['ruleset_id']);
            $table->dropColumn('ruleset_id');
            $table->string('ruleset', 50)->nullable();
        });

        Schema::table('allocation_rulesets', function (Blueprint $table) {
            $table->dropColumn('allocation_type');
        });
    }
};
