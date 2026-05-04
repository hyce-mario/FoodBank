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
        Schema::create('event_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');            // storage path e.g. event-media/12/abc.jpg
            $table->string('name');            // original filename
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size')->default(0); // bytes
            $table->enum('type', ['image', 'video'])->default('image');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_media');
    }
};
