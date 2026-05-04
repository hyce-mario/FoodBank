<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_pre_registrations', function (Blueprint $table) {
            $table->unsignedSmallInteger('children_count')->default(0)->after('household_size');
            $table->unsignedSmallInteger('adults_count')->default(0)->after('children_count');
            $table->unsignedSmallInteger('seniors_count')->default(0)->after('adults_count');
        });

        // Back-fill: treat all existing members as adults
        DB::statement('UPDATE event_pre_registrations SET adults_count = household_size');

        Schema::table('event_pre_registrations', function (Blueprint $table) {
            $table->dropColumn(['number_of_families', 'family_breakdown']);
        });
    }

    public function down(): void
    {
        Schema::table('event_pre_registrations', function (Blueprint $table) {
            $table->dropColumn(['children_count', 'adults_count', 'seniors_count']);
            $table->unsignedSmallInteger('number_of_families')->default(1)->after('household_size');
            $table->json('family_breakdown')->nullable()->after('number_of_families');
        });
    }
};
