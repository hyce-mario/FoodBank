# ADR-003: checkin_overrides remains a specialized domain table

- **Date:** 2026-04-30
- **Status:** accepted
- **Phase:** Phase 4.2
- **Author:** Claude (session 4)

## Context

Phase 1.3.c created a `checkin_overrides` table to capture supervisor re-check-in overrides with rich structured columns: `household_ids` JSON, `prior_visit_ids` JSON, `reason` TEXT, `representative_household_id`. Phase 4 introduces a general-purpose `audit_logs` table for capturing state changes across many models. The question: should `checkin_overrides` be absorbed into `audit_logs`?

## Decision

**Keep `checkin_overrides` as its own specialized domain table.** Do not migrate rows into `audit_logs`.

## Alternatives considered

- **Absorb into `audit_logs`**: Rows from `checkin_overrides` would map to `action='checkin_override'`, `target_type='Visit'`, and the structured data (`household_ids`, `prior_visit_ids`, `reason`, `representative_household_id`) would be flattened into `before_json`/`after_json`. Rejected because:
  - The domain-specific columns are optimized for queries like "which households were re-served at event X?". Flattening into opaque JSON loses type-safety and queryability.
  - A data migration has risk and complexity; existing data has been accumulating since 1.3.c.
  - The `audit_logs` generic before/after schema is designed for state-change diffs, not for capturing domain workflow events with their own rich context.

## Consequences

- **Positive:** `checkin_overrides` retains its queryable, domain-specific schema. No data migration risk.
- **Positive:** `audit_logs` stays focused on generic model state changes.
- **Negative / accepted trade-off:** Two separate tables for "audit-type" data. The admin UI will surface them in separate sections (or the audit-log viewer can include a link to the check-in overrides page).
- **Follow-ups:** Phase 4.2.c admin `/audit-logs` page should include a navigation link to a future `/checkin-overrides` admin viewer (Phase 4 or 5 scope).

## Implementation notes

No code changes required for this decision. The `checkin_overrides` table already exists and `audit_logs` is a new table created in Phase 4.2.a.
