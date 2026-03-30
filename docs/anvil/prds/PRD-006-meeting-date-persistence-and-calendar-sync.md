# PRD-006: Meeting Date Persistence and Calendar Sync

**Created:** 2026-03-30
**Status:** Approved
**Author:** Bas de Kort
**Source:** GitHub issue #4

## Problem Statement

Users experience two related but distinct problems around meeting dates in Mithril.

**Date saving bug:** When a user changes the scheduled date of a meeting on the meeting detail page, the new date does not persist. On page reload the original date reappears, making it impossible to reliably schedule meetings through the interface. The date picker is present and interactive, but its value is silently discarded before it reaches the database.

**Calendar sync gap:** Mithril can link meetings to Outlook calendar events imported from Microsoft Graph. When a calendar event's date is updated in Outlook, the next sync brings in the new event data — but the linked meeting's scheduled date is not updated to match. As a result, a user's Mithril meeting list can show stale dates even when the authoritative source (Outlook) has moved.

Both problems undermine user trust in the meeting schedule displayed in Mithril and create the risk of missed or incorrectly timed meetings.

## In Scope

- Fixing the meeting date picker so that a changed date is reliably saved and survives a page reload.
- When a linked Outlook calendar event's date changes during a sync, the associated meeting's scheduled date is updated to match the new event date.
- When a user manually changes a meeting's date on the meeting detail page and that meeting is linked to an Outlook calendar event, the user receives an informational warning that their manual change may be overwritten on the next sync cycle.
- The warning described above is visible and understandable without being destructive; it does not block saving the manual change.

## Out of Scope

- Writing back to Outlook: creating, updating, or deleting Outlook calendar events from Mithril.
- Two-way sync; this is strictly a one-way propagation from Outlook to Mithril.
- Changing or expanding the OAuth scopes used for Microsoft Graph access.
- A conflict-resolution UI or the ability to "lock" a meeting date against sync overwrites.
- Syncing any meeting data fields other than the scheduled date (e.g. title, location, attendees).
- Creating or deleting meetings in Mithril based on calendar event changes.
- Any changes to the sync frequency or scheduling mechanism.

## User Roles

| Role | Goals | Key Interactions |
|------|-------|------------------|
| Authenticated User | Set accurate scheduled dates for meetings; trust that the date shown is current | Meeting detail page: change date via date picker; receive feedback when a linked calendar event could affect the date |
| Authenticated User (Outlook connected) | Keep Mithril meeting dates in sync with their Outlook calendar without manual effort | Passive; the sync runs automatically and updates the meeting date |

## Acceptance Criteria

1. When a user changes the meeting date via the date picker on the meeting detail page and the page is reloaded, the new date is displayed; the change has been persisted.
2. The date picker change triggers the auto-save mechanism; no explicit "Save" button is required.
3. The auto-save provides visible feedback (success or error) after the date change is submitted, consistent with the application's existing auto-save feedback pattern.
4. When a sync cycle runs and a linked Outlook calendar event's `start` date differs from the meeting's current `scheduled_at` value, the meeting's scheduled date is updated to the event's new date.
5. After a sync that updates a meeting's scheduled date via criterion 4, the updated date is immediately visible on the meeting detail page and meeting list without requiring a manual action.
6. When a user manually edits the scheduled date of a meeting that is linked to an Outlook calendar event, a non-blocking informational warning is displayed explaining that the date may be overwritten on the next sync.
7. The warning described in criterion 6 does not prevent the manual change from being saved; the user retains the ability to set the date.
8. If a meeting has no linked calendar event, no sync-related warning or behavior is introduced.
9. A meeting whose date has been updated by the sync is indistinguishable in the UI from a meeting whose date was set manually, except for any standard "last synced" indicators already present in the application.

## Constraints

- The Microsoft Graph integration remains read-only; no new write scopes may be requested or used.
- The auto-save behavior for meeting dates must conform to the application-wide 500ms debounce pattern and use the existing API response format.
- Any sync-triggered date update must respect the same user-ownership scoping that applies to all meeting data; a sync may only update meetings belonging to the authenticated user whose Outlook account is connected.
- UI feedback and warnings must conform to the Rivendell UI theme and TailAdmin design language.

## Dependencies

| Dependency | Impact | Status |
|------------|--------|--------|
| Meeting date picker and auto-save interaction | The bug fix is a prerequisite for all date-related acceptance criteria | Exists; needs investigation and fix |
| Microsoft Graph calendar sync job | Must be extended to propagate date changes to linked meetings | Exists; read-only |
| `CalendarEventLink` pivot | The relationship between calendar events and meetings must be traversed to apply date updates | Exists |
| Meeting update endpoint | Must correctly accept and persist the scheduled date submitted by the date picker | Exists; suspected defect |

## Success Metrics

| Metric | Target | How to Measure |
|--------|--------|----------------|
| Date persistence after manual change | 100% of valid date changes entered via the date picker are stored and returned on reload | Acceptance tests covering the save and reload cycle |
| Sync propagation accuracy | A meeting linked to a calendar event whose date has changed reflects the new date within one sync cycle | Test: create a linked meeting, simulate a changed event date, run the sync, assert the meeting date |
| No regression on unlinked meetings | Meetings without a linked calendar event are not affected by the sync propagation logic | Existing meeting tests continue to pass |
| Warning visibility | The sync-overwrite warning is present in the DOM when and only when a meeting has at least one linked calendar event | Acceptance test asserting presence and absence of the warning based on link state |

## Changelog

| Date | Change | Reason |
|------|--------|--------|
| 2026-03-30 | Initial draft | — |
