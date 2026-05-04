<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('review_text');
            $table->string('reviewer_name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'is_visible']);
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reviews');
    }
};
