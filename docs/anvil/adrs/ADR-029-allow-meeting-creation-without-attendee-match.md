# ADR-029: Allow meeting creation from calendar events without attendee match

**Date:** 2026-03-30
**Status:** Accepted
**Tags:** frontend, calendar, meetings, ux
**Phase:** PLAN-027 Phase 3 (amendment)

## Context

The `canCreateMeeting` check in the calendar event components (`calendar-upcoming.blade.php`, `calendar-events.blade.php`) requires exactly one calendar event attendee to match a team member email. When no match is found, the "Meeting" button is greyed out with "No matching team member found." This prevents creating meetings for calendar events with external participants (clients, vendors, cross-org contacts) who are not registered as team members in Mithril.

The backend (`CalendarActionController::createMeeting`) already supports creating meetings without a `team_member_id`; the constraint is purely frontend.

## Decision

Remove the `canCreateMeeting` gate from calendar event action components. The "Meeting" button is always enabled. When a matching team member is found, the attendee is still auto-attached (existing behavior). When no match is found, the meeting is created without an attendee; the user can add attendees manually afterward.

## Deviation from Plan

PLAN-027 Phase 3 originally covered only the sync warning UI. This amendment extends it to fix the greyed-out meeting creation button, which blocks manual testing of the sync propagation feature and is a usability gap for meetings with external participants.

## PRD Reference

Not directly covered by PRD-006 acceptance criteria, but required to enable practical testing of criteria 4-5 (sync propagation) and generally improves the calendar-to-meeting workflow.

## Consequences

### Positive
- Users can create meetings from any calendar event, including those with external-only attendees
- Removes a friction point in the calendar integration workflow
- Enables manual testing of PLAN-027 sync propagation

### Negative
- Meetings created from events without matching attendees will have no pre-attached attendee; user must add one manually if needed

### Code/Data Changes
- Remove `canCreateMeeting` parameter from `calendarEventActions()` Alpine component
- Remove conditional disabling logic from `calendar-event-actions.blade.php`
- Remove `$canCreateMeeting` closure from `calendar-upcoming.blade.php` and `calendar-events.blade.php`
- Simplify `calendarEventActions` TypeScript component (remove `canCreateMeeting` property)

### Migration / Operational Impact
- None; frontend-only change
