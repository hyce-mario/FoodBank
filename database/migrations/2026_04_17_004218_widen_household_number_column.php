<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropUnique('households_household_number_unique');
            // Original column was varchar(5); setting generates up to 10-digit numbers.
            // Widen to varchar(20) to accommodate any configured length.
            $table->string('household_number', 20)->change();
            $table->unique('household_number');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropUnique('households_household_number_unique');
            $table->string('household_number', 5)->change();
            $table->unique('household_number');
        });
    }
};
