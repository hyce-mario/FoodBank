<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('household_number', 5)->unique();   // 5-digit human-readable ID
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->tinyInteger('household_size')->unsigned()->default(1);
            $table->text('notes')->nullable();
            $table->string('qr_token', 64)->unique()->nullable(); // token encoded in QR
            $table->timestamps();

            $table->index(['first_name', 'last_name']);
            $table->index('zip');
            $table->index('household_size');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
