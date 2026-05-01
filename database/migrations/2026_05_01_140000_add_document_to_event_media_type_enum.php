<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds 'document' to the event_media.type enum so PDFs (and other non-AV
 * media) can be classified explicitly. Without this, application/pdf
 * uploads would fall through to type='image' and render as broken
 * thumbnails in the photos gallery.
 *
 * Portability:
 *   - MySQL: native ALTER MODIFY COLUMN ENUM(...).
 *   - SQLite: $table->enum() compiles to a CHECK constraint, which can't
 *     be altered in place. We re-create the column via the Laravel-
 *     idiomatic add-copy-drop-rename dance so the constraint widens
 *     correctly without doctrine/dbal.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE event_media
                MODIFY COLUMN type ENUM('image','video','document') NOT NULL DEFAULT 'image'
            ");
            return;
        }

        if ($driver === 'sqlite') {
            // SQLite path: rebuild the column with the wider enum.
            Schema::table('event_media', function (Blueprint $table) {
                $table->enum('type_new', ['image', 'video', 'document'])
                      ->default('image')
                      ->after('type');
            });
            DB::statement('UPDATE event_media SET type_new = type');
            Schema::table('event_media', function (Blueprint $table) {
                $table->dropColumn('type');
            });
            Schema::table('event_media', function (Blueprint $table) {
                $table->renameColumn('type_new', 'type');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        // Normalize any 'document' rows back to 'image' first so the narrower
        // enum doesn't reject them.
        DB::table('event_media')
            ->where('type', 'document')
            ->update(['type' => 'image']);

        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE event_media
                MODIFY COLUMN type ENUM('image','video') NOT NULL DEFAULT 'image'
            ");
            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('event_media', function (Blueprint $table) {
                $table->enum('type_new', ['image', 'video'])
                      ->default('image')
                      ->after('type');
            });
            DB::statement('UPDATE event_media SET type_new = type');
            Schema::table('event_media', function (Blueprint $table) {
                $table->dropColumn('type');
            });
            Schema::table('event_media', function (Blueprint $table) {
                $table->renameColumn('type_new', 'type');
            });
        }
    }
};
