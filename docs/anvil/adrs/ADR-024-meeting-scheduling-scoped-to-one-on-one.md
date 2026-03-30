## ADR-024: Auto-scheduling scoped to one-on-one meetings with single attendee

**Date:** 2026-03-18
**Phase:** Meetings (Phase 1)
**Tags:** backend, meetings, scheduling, events
**Status:** Accepted

### Context

The legacy `ScheduleNextBila` listener calculated the `next_bila_date` on a team member whenever a new bila was created. This worked because bilas had a 1:1 relationship with team members (direct `team_member_id` FK).

The new Meeting model supports multiple meeting types (`one_on_one`, `team`, `other`) and multiple attendees via a pivot table. Auto-scheduling the next meeting date on a team member only makes sense for recurring 1-on-1s with a single attendee — not for team meetings or multi-attendee sessions.

Alternatives considered:
1. **Auto-schedule for all meeting types** — rejected because team meetings don't follow per-member intervals
2. **Auto-schedule for all attendees** — rejected because `meeting_interval_days` is a property of the team member, implying a personal cadence (e.g., "meet with Alice every 14 days")
3. **Only schedule for one_on_one with exactly one attendee** — accepted as the correct semantic match

### Decision

The `ScheduleNextMeeting` listener only calculates `next_meeting_date` when ALL of these conditions are met:

1. The meeting type is `one_on_one`
2. The meeting has exactly one attendee
3. The attendee's `meeting_interval_days` is greater than zero

For all other meeting types, attendee counts, or zero-interval members, the listener is a no-op.

### Consequences

- **Team meetings** never trigger auto-scheduling — this is intentional and matches the domain model where team meetings are ad-hoc or have their own cadence.
- **Multi-attendee one_on_one meetings** (edge case, likely a data error) do not trigger scheduling. This prevents ambiguity about which member should get the next date.
- **Backward-compatible:** The behavior for the common case (1-on-1 with one member, positive interval) is identical to the old system.

### Follow-ups / open questions

- None. The behavior is well-defined and covered by 7 unit tests in `ScheduleNextMeetingTest`.
