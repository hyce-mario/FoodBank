<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventMedia;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventMediaController extends Controller
{
    // Floor + ceiling for the size setting so a misconfigured row in
    // app_settings can't accidentally make uploads either impossible
    // (0 MB) or unbounded. Production PHP needs upload_max_filesize and
    // post_max_size to be at least the chosen value.
    private const MIN_SIZE_MB = 1;
    private const MAX_SIZE_MB = 500;

    // Hard-coded fallback used only when the setting row is missing or
    // empty (e.g. fresh deploy before SettingsSeeder runs).
    private const DEFAULT_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm',
        'application/pdf',
    ];

    /**
     * Map a MIME type to the value stored in event_media.type.
     * 'document' was added in migration 2026_05_01_140000.
     */
    private function classifyType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'video/') => 'video',
            $mimeType === 'application/pdf'      => 'document',
            default                              => 'image',
        };
    }

    // ─── Upload ───────────────────────────────────────────────────────────────

    public function store(Request $request, Event $event): JsonResponse
    {
        $maxMb = max(self::MIN_SIZE_MB, min(self::MAX_SIZE_MB,
            (int) SettingService::get('general.max_upload_size_mb', 50)
        ));

        $allowed = (array) SettingService::get('general.allowed_upload_formats', self::DEFAULT_MIME_TYPES);
        // Defensive: if an admin unchecks every format, fall back to the
        // baseline so uploads aren't silently bricked. The Settings UI also
        // shows a warning if every box is unticked, but belt-and-suspenders.
        if (empty($allowed)) {
            $allowed = self::DEFAULT_MIME_TYPES;
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:' . ($maxMb * 1024),
                'mimetypes:' . implode(',', $allowed),
            ],
        ]);

        $file = $request->file('file');

        // CRITICAL: capture every metadata field BEFORE move().
        // Once $file->move() relocates the temp upload, the UploadedFile
        // object's internal path points to a file that no longer exists,
        // so any subsequent getSize() / getMimeType() / getClientOriginalName()
        // call performs an stat() that fails with a RuntimeException —
        // surfaces to the client as a 500. Discovered live in production.
        $mimeType     = $file->getMimeType();
        $originalName = $file->getClientOriginalName();
        $size         = $file->getSize();
        $extension    = $file->getClientOriginalExtension();

        $type = $this->classifyType($mimeType);
        $extension = strtolower($extension ?: match ($type) {
            'video'    => 'mp4',
            'document' => 'pdf',
            default    => 'jpg',
        });

        // Store directly in public/ — no symlink required (works reliably on XAMPP/Windows)
        $filename     = Str::uuid() . '.' . $extension;
        $subPath      = "event-media/{$event->id}";              // relative inside public/
        $destDir      = public_path($subPath);
        $relativePath = "{$subPath}/{$filename}";                // stored in DB

        if (! is_dir($destDir) && ! @mkdir($destDir, 0755, true) && ! is_dir($destDir)) {
            // mkdir failed AND the dir still doesn't exist — file system or
            // permissions problem the operator needs to know about.
            return response()->json([
                'message' => "Could not create the upload directory ({$subPath}). Check filesystem permissions on public/ and retry.",
            ], 500);
        }

        $file->move($destDir, $filename);

        $nextOrder = (int) $event->media()->max('sort_order') + 1;

        $media = EventMedia::create([
            'event_id'   => $event->id,
            'disk'       => 'public_dir',
            'path'       => $relativePath,
            'name'       => $originalName,
            'mime_type'  => $mimeType,
            'size'       => $size,
            'type'       => $type,
            'sort_order' => $nextOrder,
        ]);

        return response()->json([
            'ok'    => true,
            'media' => $this->mediaData($media),
        ], 201);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function destroy(Event $event, EventMedia $media): JsonResponse
    {
        if ($media->event_id !== $event->id) {
            abort(404);
        }

        $fullPath = public_path($media->path);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
        $media->delete();

        return response()->json(['ok' => true]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function mediaData(EventMedia $media): array
    {
        return [
            'id'             => $media->id,
            'type'           => $media->type,
            'url'            => $media->url,
            'name'           => $media->name,
            'size_formatted' => $media->size_formatted,
            'mime_type'      => $media->mime_type,
        ];
    }
}
