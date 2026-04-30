<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Attach to any Eloquent model to write an AuditLog row on create/update/delete.
 *
 * Sensitive fields can be excluded per-model:
 *   protected array $auditExclude = ['password', 'remember_token'];
 *
 * To limit auditing to specific columns only (e.g. Visit status overrides):
 *   protected array $auditOnly = ['visit_status'];
 *
 * Refs: AUDIT_REPORT.md Part 13 §4.2.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        // ── Create ────────────────────────────────────────────────────────────
        static::created(function ($model) {
            $after = static::auditFilter($model, $model->getAttributes());
            if (! empty($after)) {
                AuditLog::writeEntry('created', $model, null, $after);
            }
        });

        // ── Update ────────────────────────────────────────────────────────────
        // 'updating' fires before the save, so getOriginal() still holds the
        // pre-change values and getDirty() has what's about to be written.
        // Trade-off: if the surrounding DB::transaction rolls back AFTER this
        // audit row is written, the audit row survives (orphaned). This is
        // the standard accept-a-ghost trade-off for pre-save auditing; the
        // alternative (post-save 'updated') would stale-read getOriginal().
        static::updating(function ($model) {
            $dirty  = $model->getDirty();
            $before = array_intersect_key($model->getOriginal(), $dirty);

            $before = static::auditFilter($model, $before);
            $after  = static::auditFilter($model, $dirty);

            if (! empty($after)) {
                AuditLog::writeEntry('updated', $model, $before, $after);
            }
        });

        // ── Delete ────────────────────────────────────────────────────────────
        static::deleted(function ($model) {
            $before = static::auditFilter($model, $model->getAttributes());
            AuditLog::writeEntry('deleted', $model, $before, null);
        });
    }

    // ─── Filter helper ────────────────────────────────────────────────────────

    private static function auditFilter($model, array $attributes): array
    {
        $exclude = $model->auditExclude ?? [];
        $only    = $model->auditOnly    ?? [];

        // Always strip internal Laravel timestamps and auto-audit fields
        $alwaysExclude = ['updated_at', 'created_at', 'remember_token'];

        $exclude = array_merge($alwaysExclude, $exclude);

        $filtered = array_diff_key($attributes, array_flip($exclude));

        if (! empty($only)) {
            $filtered = array_intersect_key($filtered, array_flip($only));
        }

        return $filtered;
    }
}
