# PLAN-028: Global Search

**Created:** 2026-03-30
**Status:** Approved
**Author:** Bas de Kort
**PRDs:** PRD-007

## PRD References

| PRD | Title | Status |
|-----|-------|--------|
| [PRD-007](../prds/PRD-007-global-dashboard-search.md) | Global Search | Approved |

## Problem Statement

Users can't search across entity types from a single place. A backend search API (`GET /api/v1/search`) and a Blade results partial (`search-results.blade.php`) already exist but are not wired to any UI. The header needs a search bar that calls this API and displays results in a dropdown/overlay.

## Acceptance Criteria

1. Search bar visible in the application header on every authenticated page.
2. No query sent until input is at least 2 characters.
3. Results grouped by entity type (Tasks, Notes, Follow-ups, Meetings, Team Members).
4. Each result shows enough info to identify the item (title/name + group heading).
5. Clicking a result navigates to the item's detail page.
6. "No results" message when query matches nothing.
7. Keyboard accessible (Tab to focus, navigate results, Enter to activate).
8. Works on mobile viewports.
9. Results scoped to authenticated user's data (already enforced by `BelongsToUser`).
10. Per-page search on index pages unaffected.
11. Works from any page; navigation to result works cross-page.

## Technical Design

### Approach

Add a search bar to the app header between the sidebar toggle and the right-side actions. Create a new `globalSearch` Alpine component that:
1. Accepts input, debounces 300ms, calls `GET /api/v1/search?q=...` via `apiClient`
2. Renders results in a dropdown overlay below the search bar
3. Groups results by type with clickable links to detail pages
4. Handles keyboard navigation (arrow keys through results, Escape to close)
5. Closes on click-outside or Escape

The existing `search-results.blade.php` partial renders full card components with server-side HTML. For the header dropdown, we need a lighter JSON-driven approach since we're calling the API endpoint (which returns JSON, not HTML). The Alpine component will render result items client-side from the JSON response.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `resources/js/components/global-search.ts` | Create | Alpine component for search bar behavior, API calls, keyboard nav |
| `resources/js/app.ts` | Modify | Register `globalSearch` component |
| `resources/views/layouts/app-header.blade.php` | Modify | Add search bar between sidebar toggle and right-side actions |
| `resources/views/components/tl/global-search.blade.php` | Create | Blade component for the search bar + results dropdown markup |
| `tests/Feature/Http/Controllers/Api/SearchControllerTest.php` | Create | Test the search API endpoint responses |

### Data Flow

1. User types in search bar (header)
2. After 300ms debounce and >= 2 chars, `globalSearch` calls `GET /api/v1/search?q=term`
3. API returns `{ success: true, data: { tasks: [...], notes: [...], follow_ups: [...], meetings: [...], team_members: [...] } }`
4. Alpine component renders grouped results in dropdown
5. User clicks/activates a result -> `window.location` navigates to the item's URL

### URL Generation

The Alpine component needs to generate URLs for each result type. Strategy: use route patterns with ID placeholders, passed as data attributes from Blade:
- Tasks: `/tasks/{id}`
- Notes: `/notes/{id}`
- Follow-ups: `/follow-ups` (index page; follow-ups don't have a dedicated show page)
- Meetings: `/meetings/{id}`
- Team members: `/teams/{team_id}/members/{id}`

### Edge Cases & Error Handling

- Empty search input or < 2 chars: clear results, show nothing.
- API error: show brief error message in dropdown.
- No results: show "No results found" message.
- Rapid typing: debounce prevents excessive API calls; latest response wins.
- Click outside dropdown: close it.
- Mobile: dropdown should be full-width below header, not absolutely positioned off-screen.

## Implementation Phases

### Phase 1: Search Component and Header Integration
- **Goal:** Working search bar in the header with API-driven results
- **PRD criteria:** 1, 2, 3, 4, 5, 6, 9, 10, 11
- **Specs:**
  - [ ] `globalSearch` Alpine component calls `GET /api/v1/search?q=...` with 300ms debounce
  - [ ] No API call fires when input is < 2 characters
  - [ ] Results are displayed in a dropdown grouped by entity type with headings
  - [ ] Each result shows the item title/name and is clickable to navigate to its detail page
  - [ ] "No results" message appears when the query matches nothing
  - [ ] Dropdown closes on click-outside and on Escape key
  - [ ] Search bar is visible in the header on all authenticated pages
  - [ ] Existing per-page search on tasks, follow-ups, and notes index pages still works
  - [ ] API error displays a brief error message in the dropdown
- **Files:** `resources/js/components/global-search.ts`, `resources/js/app.ts`, `resources/views/layouts/app-header.blade.php`, `resources/views/components/tl/global-search.blade.php`, `tests/Feature/Http/Controllers/Api/SearchControllerTest.php`

### Phase 2: Keyboard Navigation and Mobile
- **Goal:** Full keyboard accessibility and mobile-responsive layout
- **PRD criteria:** 7, 8
- **Specs:**
  - [ ] Search bar is focusable via Tab
  - [ ] Arrow keys navigate through results in the dropdown
  - [ ] Enter key on a focused result navigates to its detail page
  - [ ] Escape key closes the dropdown and returns focus to the search input
  - [ ] On mobile viewports, the dropdown renders full-width below the header without horizontal overflow
  - [ ] Search bar collapses to an icon on small viewports and expands on tap/focus
- **Files:** `resources/js/components/global-search.ts`, `resources/views/components/tl/global-search.blade.php`

## Parallelization

**Strategy:** Sequential — Phase 2 builds directly on Phase 1's component.

## Out of Scope

- Full-text search engine (Elasticsearch, Meilisearch)
- Additional entity types beyond the current 5
- Search result ranking or relevance scoring
- Saved/recent searches
- Autocomplete/suggestions
- Keyboard shortcut to focus search (e.g., Cmd+K); could be a future enhancement

## Open Questions

- Should the follow-up results link to the follow-ups index page with a filter, or just to the index page? Follow-ups don't have a dedicated show page; linking to the index with a highlight or scroll-to would be ideal but adds complexity. For now, link to the index page.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
