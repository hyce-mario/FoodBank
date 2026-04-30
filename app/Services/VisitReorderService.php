<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Visit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VisitReorderService
{
    public const ERR_VERSION_MISMATCH = 'reorder.version_mismatch';
    public const ERR_SCOPE_MISMATCH   = 'reorder.scope_mismatch';

    /**
     * Apply a batch of (lane, queue_position) moves to visits within an event.
     *
     * Phase 1.1.c.2 — wraps the batch in a transaction with row-level locks,
     * verifies an optimistic `updated_at` token per move, and uses a NULL-stage
     * update so two-row swaps (e.g. id=A:pos2, id=B:pos1) don't transiently
     * collide on the unique (event_id, lane, queue_position) index added in
     * Phase 1.1.a.
     *
     * `updated_at` is treated as optional at the service boundary so this
     * class is reusable from contexts (tests, future jobs) that don't
     * carry a client-supplied token. The HTTP controller validates it as
     * required, so end-user flows always exercise the version check. Tokens
     * are compared at second-precision via Carbon::equalTo; if `visits.updated_at`
     * is ever migrated to fractional seconds (DATETIME(6) / TIMESTAMP(6)),
     * this comparison must be revisited.
     *
     * @param  array<int, array{id:int|string, lane:int, queue_position:int, updated_at?:?string}>  $moves
     * @throws RuntimeException with message ERR_VERSION_MISMATCH if any
     *         move's updated_at no longer matches the current row, or
     *         ERR_SCOPE_MISMATCH if any visit id doesn't belong to $event.
     */
    public function reorder(Event $event, array $moves): void
    {
        if (count($moves) === 0) {
            return;
        }

        // Normalize ids early so the caller can pass strings.
        $ids = array_values(array_unique(array_map(
            static fn ($m) => (int) $m['id'],
            $moves
        )));

        try {
            DB::transaction(function () use ($event, $moves, $ids) {
                // Lock the affected rows in a deterministic order to avoid
                // deadlocks if two reorder calls overlap.
                $locked = Visit::where('event_id', $event->id)
                    ->whereIn('id', $ids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id', 'updated_at'])
                    ->keyBy('id');

                // Scope check — every submitted id must belong to this event.
                if ($locked->count() !== count($ids)) {
                    throw new RuntimeException(self::ERR_SCOPE_MISMATCH);
                }

                // Optimistic version check — if the client supplied an
                // updated_at, it must match the row we just locked.
                foreach ($moves as $move) {
                    if (! array_key_exists('updated_at', $move) || $move['updated_at'] === null || $move['updated_at'] === '') {
                        continue;
                    }

                    $row    = $locked[(int) $move['id']];
                    $client = Carbon::parse($move['updated_at']);

                    if ($row->updated_at === null || ! $client->equalTo($row->updated_at)) {
                        throw new RuntimeException(self::ERR_VERSION_MISMATCH);
                    }
                }

                // Phase 1: release positions for all affected rows. This avoids
                // tripping the unique index on intermediate states when two rows
                // swap positions inside the same lane. queue_position became
                // nullable in Phase 1.1.c.1, so this is now safe.
                // Bulk query-builder update — bypasses Eloquent model events
                // (including Auditable). Intentional: Visit::$auditOnly is
                // ['visit_status'], so lane/queue_position changes are not audited.
                Visit::where('event_id', $event->id)
                    ->whereIn('id', $ids)
                    ->update(['queue_position' => null]);

                // Phase 2: apply the final lane + position for each move.
                foreach ($moves as $move) {
                    Visit::where('id', (int) $move['id'])
                        ->where('event_id', $event->id)
                        ->update([
                            'lane'           => (int) $move['lane'],
                            'queue_position' => (int) $move['queue_position'],
                        ]);
                }
            });
        } catch (QueryException $e) {
            // A unique-index violation here means a concurrent writer
            // (e.g. a fresh check-in) claimed a position the reorder is
            // trying to apply. lockForUpdate blocks updates to existing
            // rows but doesn't block fresh inserts into the same lane.
            // Surface as a version mismatch so the client refetches and
            // recomputes positions against the new state, instead of
            // bubbling a 500.
            // SQLSTATE 23000 = integrity constraint violation; the only
            // such constraint touched by this transaction's UPDATEs is the
            // (event_id, lane, queue_position) unique index from 1.1.a.
            if (($e->errorInfo[0] ?? null) === '23000') {
                throw new RuntimeException(self::ERR_VERSION_MISMATCH, 0, $e);
            }
            throw $e;
        }
    }
}
