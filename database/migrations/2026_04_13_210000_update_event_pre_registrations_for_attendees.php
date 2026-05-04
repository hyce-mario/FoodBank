<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_pre_registrations', function (Blueprint $table) {
            // Auto-generated 5-digit display ID shown in the Attendees table
            $table->string('attendee_number', 10)->nullable()->unique()->after('id');

            // Family breakdown (replaces single household_size field)
            $table->unsignedSmallInteger('number_of_families')->default(1)->after('household_size');
            $table->json('family_breakdown')->nullable()->after('number_of_families');

            // Household matching
            $table->foreignId('household_id')
                  ->nullable()->constrained('households')->nullOnDelete()
                  ->after('family_breakdown');
            $table->foreignId('potential_household_id')
                  ->nullable()->constrained('households')->nullOnDelete()
                  ->after('household_id');

            // new | potential_match | matched
            $table->string('match_status', 20)->default('new')->after('potential_household_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_pre_registrations', function (Blueprint $table) {
            $table->dropForeign(['household_id']);
            $table->dropForeign(['potential_household_id']);
            $table->dropColumn([
                'attendee_number',
                'number_of_families',
                'family_breakdown',
                'household_id',
                'potential_household_id',
                'match_status',
            ]);
        });
    }
};
