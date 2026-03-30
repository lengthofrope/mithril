# PRD-007: Global Search

**Created:** 2026-03-30
**Status:** Approved
**Author:** Bas de Kort
**Source:** GitHub issue #1

## Problem Statement

Users store information across multiple entity types in Mithril — tasks, notes, follow-ups, meetings, and team members — but they don't always remember which type they used when capturing something. When they need to retrieve a piece of information, they are forced to navigate to each section individually and scan manually, or rely on per-page search fields that only cover a single entity type.

The user's stated need is direct: "I'm not sure if I put something in Mithril and whether it's a task or follow-up. A general search field would help." There is currently no way to search across all entity types from a single location. A global search bar in the application header, available on every page, would eliminate this friction and make Mithril genuinely useful as a personal information hub.

## In Scope

- A persistent, visible search bar in the application header, available on every page
- Cross-entity search covering tasks, notes, follow-ups, meetings, and team members
- Search results grouped by entity type so the user immediately understands what kind of item matched
- Direct navigation from a search result to the corresponding detail or index page for that item
- A minimum character threshold before the search query is sent, to avoid noise from single-character inputs
- A clear empty state when no results are found for a given query
- Keyboard accessibility: the search bar must be reachable and operable without a mouse
- Responsive behavior: the search experience must be usable on both desktop and mobile viewport sizes

## Out of Scope

- Full-text search engines (Elasticsearch, Meilisearch, or similar); this feature uses the existing LIKE-based search infrastructure
- Expanding the set of searchable entity types beyond what the current backend search endpoint supports (tasks, notes, follow-ups, meetings, team members); agreements, teams, task groups, and task categories are excluded for now
- Search result ranking, relevance scoring, or sorting by match quality
- Saved or recent searches
- Search suggestions or autocomplete while typing
- Searching within file attachments or rich-text content beyond plain field values

## User Roles

**Authenticated user (team lead):** The only role in Mithril. All search results are scoped to the authenticated user's own data; no user can surface another user's records through search.

## Acceptance Criteria

1. A search bar is visible in the application header on every authenticated page without requiring any action to reveal it; it is not hidden behind a button or icon by default.
2. The search bar accepts free-text input and does not submit a query until the input is at least 2 characters long.
3. When a valid query is submitted, results are returned and displayed grouped by entity type (e.g. Tasks, Notes, Follow-ups, Meetings, Team Members), with each group labelled.
4. Each result displays sufficient information to identify the item (at minimum: a title or name and the entity type group heading).
5. Clicking or activating a search result navigates the user directly to the relevant page for that item.
6. When a query returns no matches across any entity type, a clear "no results" message is shown in place of result groups.
7. The search bar is keyboard-accessible: it can be focused with Tab, input is accepted, and results can be navigated and activated using the keyboard alone.
8. The search experience renders and functions correctly on mobile viewports (small screens), not only on desktop.
9. Search results are scoped exclusively to the authenticated user's own data; no result from another user's account can appear.
10. The search bar does not interfere with or replace the existing per-page search on the tasks, follow-ups, or notes index pages.
11. The search bar and its results function correctly regardless of which page the user is on; navigation to a result works from any page.

## Constraints

- The backend search infrastructure (API endpoint and searchable model trait) already exists and must be reused; no duplicate search logic should be introduced.
- Search is limited to the entity types already covered by the existing backend endpoint; expanding coverage requires a separate decision.
- The minimum query length is 2 characters, matching the current API constraint.
- The visual design of the search bar and results must be consistent with the existing Rivendell UI theme and TailAdmin design language used throughout the application.

## Dependencies

- The existing global search API endpoint (`GET /api/v1/search`) must remain stable and continue returning results grouped by entity type.
- The existing Blade partial for rendering grouped search results exists but is currently unwired; this feature's primary work is connecting it to a visible UI entry point in the header and wiring the interaction.
- No new backend endpoints are required unless the existing endpoint proves insufficient during implementation review.

## Success Metrics

- A user can locate an item by searching a keyword, regardless of which entity type it belongs to, from any page in the application.
- The time to find a specific item drops compared to the current flow of visiting each section individually.
- No regression in existing per-page search behavior on index pages.
- Zero cross-user data leakage confirmed by test coverage.

## Changelog

| Date | Author | Change |
|------|--------|--------|
| 2026-03-30 | Bas de Kort | Initial draft |
| 2026-03-30 | Bas de Kort | Moved search bar from dashboard-only to application header (all pages) |
