# ADR-001: AUDIT_REPORT.md Part 13 is the remediation spec

- **Date:** 2026-04-29
- **Status:** accepted
- **Phase:** All phases
- **Author:** Claude (session 0)

## Context

A 12-part operational audit was completed and saved to `AUDIT_REPORT.md` at the project root. Part 13 was added the next day as a phased remediation plan with explicit method + procedure + acceptance criteria for each sub-task. Future sessions, possibly handled by different agents, need a single authoritative source of "what we are building and why."

Without a pinned spec, sub-task scope drifts: a fresh agent re-derives the plan, makes different micro-choices, or skips acceptance tests. With the audit decomposed across 7 phases and dozens of sub-tasks, that drift is fatal to the user's stated goal of thoroughness.

## Decision

`AUDIT_REPORT.md` Part 13 is the **canonical spec** for all remediation work. Every commit message references the relevant Part/Phase. `docs/remediation/LOG.md` tracks status and commit SHAs. `docs/remediation/HANDOFF.md` carries session state. Decisions that diverge from the spec are recorded as ADRs and logged in `LOG.md`'s "Deviations" section.

The audit report itself is treated as **read-mostly**: edits are allowed only to fix factual errors discovered during implementation, with the change noted in the corresponding ADR.

## Alternatives considered

- **Free-form per-session plan.** Rejected — every new session would re-derive context and probably skip steps.
- **GitHub issues per sub-task.** Better, but adds an external dependency the user hasn't asked for and splits state between repo and issue tracker.
- **A single living "TASKS.md" instead of LOG + HANDOFF + ADR.** Rejected — single file gets too long, and conflates audit-style status (LOG) with session-style state (HANDOFF) with architectural reasoning (ADR). Three roles, three files.

## Consequences

- **Positive:** any agent (or human) opening the repo cold can read AUDIT_REPORT.md → LOG.md → HANDOFF.md and resume in minutes.
- **Positive:** commit history becomes navigable by `git log --grep="Phase N"`.
- **Negative / accepted trade-offs:** AUDIT_REPORT.md is large (~25KB). New readers may not skim Part 13 properly without prompting.
- **Follow-ups:** every commit message should include a `Refs: AUDIT_REPORT.md Part 13 §X.Y` footer.

## Implementation notes

- Spec file: [AUDIT_REPORT.md](../../../AUDIT_REPORT.md)
- Log: [docs/remediation/LOG.md](../LOG.md)
- Session state: [docs/remediation/HANDOFF.md](../HANDOFF.md)
- Memory: project context saved to `~/.claude/projects/c--xampp-htdocs-Foodbank/memory/project_foodbank_remediation.md`
