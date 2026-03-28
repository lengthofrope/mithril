# Extraction UI Improvements

**Created:** 2026-03-21
**Status:** Complete
**Author:** Bob de Kort + Claude

## Problem Statement

The AI extraction tab on the meeting show page has three UX issues: (1) the re-extract action uses a native browser `confirm()` alert instead of a proper modal, (2) the button always says "Re-extract" even on first use, and (3) editing an extraction happens inline with limited fields — it should open in a modal with team-to-user assignment support.

## Acceptance Criteria

1. Clicking "Extract" / "Re-extract" opens a styled confirm dialog modal (not a native `confirm()` alert)
2. The button label is "Extract" when no extractions exist yet, and "Re-extract" when extractions already exist
3. Clicking "Edit" on a pending extraction opens a modal (not inline editing)
4. The edit modal contains: content (text input), team select (filters members), assignee select (filtered by team), priority select, deadline date input
5. The team-to-user filtering follows the same pattern used in task/follow-up/meeting create modals
6. "Save & accept" in the edit modal calls the existing `acceptWithEdits` API and closes the modal on success
7. Cancel or click-outside closes the edit modal without changes
8. Existing accept/reject/bulk actions remain unchanged

## Technical Design

### Approach

Three targeted changes to the extraction review component:

1. **Extract button label** — Use Alpine reactive text based on whether `extractions.length > 0`
2. **Re-extract confirm** — Replace native `confirm()` with the existing `confirm-dialog-modal` Blade component pattern (or an Alpine-driven modal inline, matching the project's modal pattern)
3. **Edit modal** — Replace the inline edit template with a modal overlay. Add `teamOptions` and `memberOptions` (already available in the view from `MeetingPageController@show`) to the Alpine component config. Add team-to-member filtering logic.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `resources/views/pages/meetings/show.blade.php` | Modify | Replace inline edit with modal, update button label, add confirm modal for re-extract |
| `resources/js/components/extraction-review.ts` | Modify | Add team/member options to config, add modal state, replace `confirm()` with modal state, add team filtering logic, dynamic button label |

### Data Flow

No new API endpoints needed. The existing `acceptWithEdits` endpoint already supports `assignee_id`. `teamOptions` and `memberOptions` are already passed to the view by `MeetingPageController@show`.

### Edge Cases & Error Handling

- Team select is optional — if no team is selected, show all members
- Assignee can be cleared (set to null)
- If extraction has an assignee from the AI, pre-select the correct team based on `member.team_id`
- Modal escape/click-outside must properly reset state (cancel editing)

## Implementation Phases

### Phase 1: Dynamic Extract/Re-extract button label
- **Goal:** Button shows "Extract" when no extractions exist, "Re-extract" otherwise
- **Specs:**
  - [x]Button text is "Extract" when `extractions.length === 0`
  - [x]Button text is "Re-extract" when `extractions.length > 0`
- **Files:** `resources/views/pages/meetings/show.blade.php`

### Phase 2: Replace native confirm() with modal for re-extract
- **Goal:** Re-extract confirmation uses a styled modal instead of native alert
- **Specs:**
  - [x]Clicking Extract/Re-extract opens a styled confirmation modal
  - [x]Modal shows title and warning message about removing pending extractions
  - [x]Confirming triggers the re-extract API call
  - [x]Cancelling or pressing Escape closes the modal without action
  - [x]Modal follows the project's existing modal pattern (backdrop blur, transitions, accessibility attributes)
- **Files:** `resources/views/pages/meetings/show.blade.php`, `resources/js/components/extraction-review.ts`

### Phase 3: Edit extraction in modal with team-to-user assignment
- **Goal:** Editing an extraction opens a modal with full editing capabilities including team/assignee selection
- **Specs:**
  - [x]Clicking "Edit" opens a modal overlay instead of inline editing
  - [x]Modal contains: content text input, team select, assignee select (filtered by team), priority select, deadline date input
  - [x]Selecting a team filters the assignee dropdown to members of that team
  - [x]Clearing the team shows all members
  - [x]If extraction has an existing assignee, the team is pre-selected based on the member's `team_id`
  - [x]"Save & accept" calls `acceptWithEdits` and closes modal on success
  - [x]Cancel / Escape / click-outside closes the modal and resets edit state
  - [x]Modal follows the project's existing modal design (rounded-2xl, border, shadow, transitions)
- **Files:** `resources/views/pages/meetings/show.blade.php`, `resources/js/components/extraction-review.ts`

## Out of Scope

- Changes to the accept/reject/bulk actions flow
- Changes to the API endpoints or backend logic
- Edit modal for already-accepted/rejected extractions (only pending)
- Creating new Blade components (changes are scoped to existing files)

## Open Questions

None — all patterns and data are already available in the codebase.
