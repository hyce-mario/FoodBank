<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_ruleset_components', function (Blueprint $table) {
            $table->id();

            $table->foreignId('allocation_ruleset_id')
                  ->constrained('allocation_rulesets')
                  ->cascadeOnDelete();

            $table->foreignId('inventory_item_id')
                  ->constrained('inventory_items')
                  ->restrictOnDelete();

            // How many units of this item constitute one bag.
            $table->unsignedSmallInteger('qty_per_bag');

            $table->timestamps();

            // An item should only appear once per ruleset.
            // Explicit name to stay under MySQL's 64-char identifier limit.
            $table->unique(['allocation_ruleset_id', 'inventory_item_id'], 'arc_ruleset_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_ruleset_components');
    }
};
