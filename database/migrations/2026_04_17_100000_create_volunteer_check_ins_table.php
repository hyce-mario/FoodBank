<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volunteer_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained()->cascadeOnDelete();

            // Role served at this specific event (may differ from volunteer's default role)
            $table->string('role')->nullable();

            // How the volunteer was added to this event
            // pre_assigned = was in event_volunteer before check-in
            // walk_in      = existing volunteer, not pre-assigned
            // new_volunteer = created same day via public check-in page
            $table->enum('source', ['pre_assigned', 'walk_in', 'new_volunteer'])->default('walk_in');

            // True if this is the volunteer's very first event service
            $table->boolean('is_first_timer')->default(false);

            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // One check-in record per volunteer per event
            $table->unique(['event_id', 'volunteer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_check_ins');
    }
};
