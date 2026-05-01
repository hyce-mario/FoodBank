<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('volunteer_check_ins', function (Blueprint $table) {
            // Stored when checking out; computed as checked_out_at - checked_in_at in hours.
            // Null until checkout occurs (includes auto-checkout by artisan command).
            $table->decimal('hours_served', 5, 2)->nullable()->after('checked_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('volunteer_check_ins', function (Blueprint $table) {
            $table->dropColumn('hours_served');
        });
    }
};
