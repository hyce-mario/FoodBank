<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('movement_type', 30);
            // Signed integer: positive = stock increase, negative = stock decrease.
            // e.g. stock_in = +50, stock_out = -10, adjustment can be ±N
            $table->integer('quantity');
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — movements are immutable records
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
