<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->enum('visit_status', ['checked_in', 'queued', 'loading', 'loaded', 'exited'])
                  ->default('checked_in')
                  ->after('queue_position');
            $table->timestamp('queued_at')->nullable()->after('end_time');
            $table->timestamp('loading_completed_at')->nullable()->after('queued_at');
            $table->timestamp('exited_at')->nullable()->after('loading_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['visit_status', 'queued_at', 'loading_completed_at', 'exited_at']);
        });
    }
};
