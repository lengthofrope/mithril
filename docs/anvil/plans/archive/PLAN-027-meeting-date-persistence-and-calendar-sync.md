# PLAN-027: Meeting Date Persistence and Calendar Sync

**Created:** 2026-03-30
**Status:** Complete
**Author:** Bas de Kort
**PRDs:** PRD-006

## PRD References

| PRD | Title | Status |
|-----|-------|--------|
| [PRD-006](../prds/PRD-006-meeting-date-persistence-and-calendar-sync.md) | Meeting Date Persistence and Calendar Sync | Approved |

## Problem Statement

Two related issues: (1) changing a meeting's date on the show page doesn't save because `Object.assign(autoSaveField(...), datePicker())` overwrites `autoSaveField.init()` with `datePicker.init()`, so the `$watch` on `value` is never registered. (2) When an Outlook calendar event's date changes, the linked meeting's `scheduled_at` is not updated during sync.

## Acceptance Criteria

1. Meeting date change via date picker persists on page reload.
2. Date change triggers auto-save; no "Save" button needed.
3. Auto-save shows visible feedback consistent with existing pattern.
4. Sync updates linked meeting's `scheduled_at` when calendar event date changes.
5. Updated date is immediately visible after sync without manual action.
6. Non-blocking warning shown when editing date on a meeting linked to a calendar event.
7. Warning does not block saving.
8. No warning when meeting has no linked calendar event.
9. Sync-updated meetings look the same in UI as manually-set ones.

## Technical Design

### Approach

**Phase 1 (Bug fix):** Create a dedicated `autoSaveDatePicker` Alpine component that properly composes both `autoSaveField` and `datePicker` behaviors in a single component, avoiding the `Object.assign` init collision. The component initializes Flatpickr and wires the `$watch` on `value` in a single `init()` method.

**Phase 2 (Sync propagation):** After the `SyncCalendarEventsJob` upserts calendar events, iterate over `CalendarEventLink` records where `linkable_type` is `Meeting`. If the event's `start_at` changed, update the meeting's `scheduled_at`.

**Phase 3 (Warning UI):** On the meeting show page, check if the meeting has `calendarEventLinks` loaded. If so, display a subtle warning near the date picker. This is purely a Blade conditional; no new endpoint needed since `calendarEventLinks` is already eager-loaded in the show controller.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `resources/js/components/auto-save-date-picker.ts` | Create | Combined component that properly merges autoSaveField + datePicker init |
| `resources/js/app.ts` | Modify | Register the new `autoSaveDatePicker` Alpine component |
| `resources/views/pages/meetings/show.blade.php` | Modify | Use `autoSaveDatePicker` instead of `Object.assign(...)`, add sync warning |
| `app/Jobs/SyncCalendarEventsJob.php` | Modify | After upsert, propagate date changes to linked meetings |
| `tests/Unit/Jobs/SyncCalendarEventsJobTest.php` | Modify | Test date propagation to linked meetings |
| `tests/Feature/Http/Controllers/Web/MeetingPageControllerTest.php` | Modify | Test date auto-save via PATCH |

### Data Flow

**Date save (Phase 1):**
1. User changes date in Flatpickr
2. Flatpickr fires `onChange`, sets `value`
3. `$watch('value')` triggers debounced save
4. PATCH to `meetings.update` with `{ scheduled_at: 'YYYY-MM-DD' }`
5. Controller validates and persists

**Sync propagation (Phase 2):**
1. `SyncCalendarEventsJob` fetches events from Microsoft Graph
2. `updateOrCreate` upserts calendar events (existing behavior)
3. For each upserted event, query `CalendarEventLink` where `linkable_type = Meeting`
4. If event's `start_at` differs from meeting's `scheduled_at`, update meeting

### Edge Cases & Error Handling

- Meeting linked to multiple calendar events (unlikely but possible via the polymorphic pivot): use the most recently synced event's date.
- Calendar event deleted in Outlook: existing stale-record cleanup handles this; meeting date is not changed.
- User changes date right before a sync overwrites it: the sync wins (by design; PRD says one-way from Outlook). The warning prepares the user for this.
- Meeting with no linked events: no sync behavior, no warning.

## Implementation Phases

### Phase 1: Fix Date Picker Auto-Save
- **Goal:** Meeting date changes persist via auto-save
- **PRD criteria:** 1, 2, 3
- **Specs:**
  - [x] New `autoSaveDatePicker` component initializes Flatpickr and wires `$watch('value')` in a single `init()` method
  - [x] Changing the date triggers a PATCH request within 500ms
  - [x] The patched date is returned correctly by the server and persists on reload
  - [x] Auto-save status indicator shows saving/saved/error states
  - [x] Component is registered in `app.ts`
  - [x] Meeting show page uses `autoSaveDatePicker` instead of `Object.assign(...)`
- **Files:** `resources/js/components/auto-save-date-picker.ts`, `resources/js/app.ts`, `resources/views/pages/meetings/show.blade.php`, `tests/Feature/Http/Controllers/Web/MeetingPageControllerTest.php`

### Phase 2: Calendar Sync Date Propagation
- **Goal:** Synced calendar event date changes propagate to linked meetings
- **PRD criteria:** 4, 5, 9
- **Specs:**
  - [x] After upsert, the sync job queries `CalendarEventLink` for meetings linked to each updated event
  - [x] If the calendar event's `start_at` date differs from the meeting's `scheduled_at`, the meeting is updated
  - [x] Meetings not linked to any calendar event are unaffected by the sync
  - [x] The sync respects user ownership scoping (only updates meetings belonging to the authenticated user)
  - [x] A meeting linked to a deleted calendar event retains its current date
- **Files:** `app/Jobs/SyncCalendarEventsJob.php`, `tests/Unit/Jobs/SyncCalendarEventsJobTest.php`

### Phase 3: Calendar Sync Warning
- **Goal:** Users see a warning when manually editing dates on calendar-linked meetings
- **PRD criteria:** 6, 7, 8
- **Specs:**
  - [x] When the meeting has at least one `calendarEventLink`, a non-blocking warning is displayed near the date picker
  - [x] The warning text explains that the next sync may overwrite the manual change
  - [x] The warning does not prevent saving
  - [x] When the meeting has no linked calendar events, no warning is displayed
  - [x] Warning styling matches Rivendell UI theme (TailAdmin info alert pattern)
- **Files:** `resources/views/pages/meetings/show.blade.php`, `tests/Feature/Http/Controllers/Web/MeetingPageControllerTest.php`

## Parallelization

**Strategy:** Sequential — Phase 1 must be complete before Phase 2 (both touch meeting date saving). Phase 3 is independent but small enough not to warrant parallelization.

## Out of Scope

- Write-back to Outlook (creating/updating Outlook events from Mithril)
- Two-way sync
- Syncing fields other than date (title, location, attendees)
- Conflict resolution UI or date locking
- Changing sync frequency

## Open Questions

None.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
| 2026-03-30 | 3 | Remove `canCreateMeeting` gate from calendar event actions | Users need to create meetings from calendar events with external attendees (clients); backend already supports it | ADR-029 |
