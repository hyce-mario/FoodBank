<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who made the change (null = artisan command / seeder / system)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // What happened: created | updated | deleted
            $table->string('action', 20);

            // Which model class and which row
            $table->string('target_type', 150);
            $table->unsignedBigInteger('target_id');

            // The diff: null on create (no before), null on delete (no after)
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();

            // Request context for traceability
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamp('created_at')->useCurrent();
            // No updated_at — audit rows are immutable

            $table->index(['target_type', 'target_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
