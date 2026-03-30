## ADR-025: Fallback strategies for AI extraction resource creation

**Date:** 2026-03-18
**Phase:** Meetings (Phase 5)
**Tags:** backend, meetings, ai, extraction, data-integrity
**Status:** Accepted

### Context

When the AI extracts items from a meeting transcription, each extraction can be accepted to create a real Task, FollowUp, or Agreement record. The AI may not always provide complete data — notably:

1. **Priority may be null** — the AI couldn't determine urgency from the transcript
2. **Assignee may be null** — the AI couldn't determine who is responsible

This creates a conflict with existing database constraints:
- `tasks.priority` is NOT NULL (defaults to `'normal'` on the column, but explicit `null` in mass assignment overrides the default)
- `agreements.team_member_id` is NOT NULL with cascadeOnDelete (agreements are inherently per-member)

Alternatives considered for the Agreement case:
1. **Make `team_member_id` nullable** — rejected because agreements are conceptually always between the lead and a member; a floating agreement has no meaning
2. **Require assignee in the AI prompt** — rejected because AI extraction is best-effort
3. **Fall back to the first meeting attendee** — accepted as a reasonable default for 1-on-1 meetings (the common case)
4. **Skip creation** — rejected because it would silently discard the user's accept action

### Decision

The `MeetingExtractionController::createResource()` method applies these fallbacks:

1. **Task priority**: defaults to `Priority::Normal` when the AI provides null
2. **Agreement assignee**: falls back to the meeting's first attendee when the AI provides no assignee_id. Throws a `RuntimeException` if the meeting has no attendees (edge case — should not occur in normal use).

These fallbacks only apply during extraction acceptance, not to manual Task/FollowUp/Agreement creation through other flows.

### Consequences

- **Predictable behavior**: accepting an extraction always creates a valid record — no silent failures
- **Data accuracy trade-off**: the fallback priority/assignee may not reflect the AI's intent, but the user can override via the "Edit" flow before accepting
- **Agreement edge case**: if a team meeting with no attendees has an agreement extraction, acceptance will fail with a 500 error. This is acceptable since meetings without attendees are an atypical configuration.

### Follow-ups / open questions

- Consider whether `agreements.team_member_id` should be made nullable in a future refactor to support team-level agreements not tied to a specific member.
