<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->date('manufacturing_date')->nullable()->after('description');
            $table->date('expiry_date')->nullable()->after('manufacturing_date');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['manufacturing_date', 'expiry_date']);
        });
    }
};
