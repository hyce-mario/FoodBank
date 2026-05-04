<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->string('vehicle_make', 100)->nullable()->after('zip');
            $table->string('vehicle_color', 50)->nullable()->after('vehicle_make');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['vehicle_make', 'vehicle_color']);
        });
    }
};
