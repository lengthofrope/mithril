## ADR-011: Calendar event resource linking via polymorphic pivot table

**Date:** 2026-03-10
**Phase:** Calendar Actions
**Tags:** backend, frontend, calendar, microsoft, polymorphic, pivot
**Status:** Accepted

### Context

Mithril syncs calendar events from Microsoft 365 and displays them on the dashboard. Users want to create actionable resources (Bilas, Tasks, Follow-ups, Notes) directly from calendar appointments. A bila with a team member scheduled in Outlook should translate into a Mithril bila entry with preparation items, without manual data entry.

Two linking strategies were considered:

1. **Polymorphic FK on `calendar_events`** (`linkable_type` + `linkable_id`) — simple, one link per event
2. **Polymorphic pivot table `calendar_event_links`** — flexible, many links per event

Option 1 was rejected because a single meeting can legitimately produce multiple resources (e.g., a bila AND a follow-up AND a note from the same 1-on-1).

For attendee storage, a JSON column was chosen over a separate `calendar_event_attendees` table because attendees are denormalized cache data that gets overwritten every sync cycle. No relational queries are needed against individual attendees.

### Decision

- **Pivot table** `calendar_event_links` with `calendar_event_id`, `linkable_type`, `linkable_id` (unique together) enables many-to-many polymorphic links between calendar events and any resource.
- **Attendees** stored as JSON on `calendar_events` (`[{email, name}, ...]`), populated by extending the Graph API `$select` to include `attendees`.
- **Auto-assignment rule:** if exactly 1 team member matches the attendee list (excluding the logged-in user), the resource is auto-assigned. Otherwise, left null for manual selection. Matching is case-insensitive against both `TeamMember.microsoft_email` and `TeamMember.email`.
- **`BilaScheduled` event** dispatched when creating a Bila from a calendar event, reusing the existing `ScheduleNextBila` listener to update `next_bila_date`. No new event infrastructure needed.
- **Cascade delete** on the pivot when calendar events are removed by sync (meeting cancelled). Linked resources are never auto-deleted — they represent real user-created data.
- **Past events** are fully supported. No time-based restriction on creating resources.

### Consequences

- **New migration:** `calendar_event_links` table + `attendees` JSON column on `calendar_events`
- **New model:** `CalendarEventLink` (no `BelongsToUser` — scoped through `CalendarEvent`)
- **New service:** `CalendarActionService` for attendee matching, pre-fill logic, and link management
- **New controller:** `CalendarActionController` with `prefill`, `create`, and `unlink` endpoints
- **Model updates:** `CalendarEvent`, `Bila`, `Task`, `FollowUp`, `Note` gain relationship methods
- **Sync update:** `MicrosoftGraphService::getMyCalendarEvents()` and `SyncCalendarEventsJob` extended to include attendees
- **Frontend:** Calendar event cards gain an action dropdown and linked-resource indicators
- **No breaking changes** to existing calendar display, resource creation, or sync behavior

### Follow-ups / open questions

- Should creating a resource auto-redirect to the resource detail page, or stay on the dashboard with a toast notification? To be decided during frontend implementation.
- Future enhancement: auto-create bilas from recurring 1-on-1 calendar patterns (out of scope for now).
- Future enhancement: two-way sync — creating a bila creates a calendar event in Outlook (out of scope).
