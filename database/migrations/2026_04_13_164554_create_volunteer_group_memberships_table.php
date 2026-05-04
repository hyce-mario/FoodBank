<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_group_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('volunteer_groups')->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // Prevent duplicate membership for the same volunteer/group pair
            $table->unique(['volunteer_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_group_memberships');
    }
};
