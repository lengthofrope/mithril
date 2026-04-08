# PLAN-029: Email Full Sync and Pagination

**Created:** 2026-04-08
**Status:** Complete
**Author:** Bas de Kort
**PRDs:** none

## Problem Statement

The email page only displays the first batch of emails synced from Microsoft Graph due to a 10-page cap (max ~500 messages) in `MicrosoftGraphService::getMyMessages()`. Additionally, all synced emails are loaded into the browser at once with no pagination, which degrades performance for larger inboxes and offers no way to browse through all emails efficiently.

## Acceptance Criteria

1. Email sync fetches all pages from Microsoft Graph inbox (no arbitrary page cap)
2. The email API endpoint supports cursor-based pagination with a configurable page size
3. The email page displays 25 emails per page by default
4. Pagination controls (previous/next, page indicator) appear only when total emails exceed 25
5. Pagination works correctly with all existing source filters (all, flagged, categorized, unread)
6. Date grouping and category grouping continue to work within each page
7. Changing the source filter resets to page 1
8. Existing email functionality (linking, actions, sync button) remains unaffected
9. The dashboard flagged emails endpoint remains unaffected (no pagination)

## Technical Design

### Approach

**Sync:** Remove the `maxPages` safety cap in `getMyMessages()` and replace it with a much higher ceiling (100 pages = 5,000 messages) to prevent infinite loops while accommodating large inboxes.

**API:** Convert `EmailActionController::index()` from `->get()` to Laravel's `->paginate()`, returning a standard paginated JSON response. The frontend receives `data`, `current_page`, `last_page`, `per_page`, and `total`.

**Frontend:** Add pagination state to the `emailPage` Alpine component. Fetch a specific page via `?page=N&per_page=25`. Render pagination controls below the email list. Date/category grouping operates on the current page's emails only.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `app/Services/MicrosoftGraphService.php` | Modify | Increase `maxPages` cap from 10 to 100 |
| `app/Http/Controllers/Api/EmailActionController.php` | Modify | Replace `->get()` with `->paginate()`, return paginated response |
| `resources/js/components/email-page.ts` | Modify | Add pagination state, page navigation methods, update fetch to include page param |
| `resources/views/pages/mail.blade.php` | Modify | Add pagination controls below email list |
| `tests/Feature/Http/Controllers/Api/EmailActionControllerTest.php` | Modify | Add pagination tests |
| `tests/Unit/Services/MicrosoftGraphServiceMessagesTest.php` | Modify | Update page cap assertions |

### Data Flow

```
User clicks page 2 → emailPage.goToPage(2) → GET /api/v1/emails?page=2&per_page=25&source=all
                                                         ↓
                                              EmailActionController::index()
                                              Email::query()->paginate(25)
                                                         ↓
                                              { success, data: [...], meta: { current_page, last_page, total } }
                                                         ↓
                                              emailPage updates emails[], paginationMeta
                                                         ↓
                                              Blade re-renders email cards + pagination controls
```

### Edge Cases & Error Handling

- Empty inbox: pagination controls hidden, "No emails found" shown
- Single page of results: pagination controls hidden
- Filter change: reset to page 1
- Page beyond range: API returns empty data array; frontend shows page 1
- Sync in progress: pagination reflects current DB state; new emails appear after next fetch

## Implementation Phases

### Phase 1: Backend — Increase sync cap and add API pagination
- **Goal:** Remove the email sync limit and add paginated API response
- **Specs:**
  - [x] `MicrosoftGraphService::getMyMessages()` follows up to 100 pages of `@odata.nextLink` results
  - [x] `EmailActionController::index()` accepts `per_page` query parameter (default 25, max 100)
  - [x] `EmailActionController::index()` accepts `page` query parameter
  - [x] Response includes `meta` object with `current_page`, `last_page`, `per_page`, `total`
  - [x] Source filter (`source` param) works correctly with pagination
  - [x] `EmailActionController::dashboard()` remains unpaginated
  - [x] Sender display data and email links are still included in paginated results
- **Files:** `app/Services/MicrosoftGraphService.php`, `app/Http/Controllers/Api/EmailActionController.php`, `tests/Feature/Http/Controllers/Api/EmailActionControllerTest.php`, `tests/Unit/Services/MicrosoftGraphServiceMessagesTest.php`

### Phase 2: Frontend — Pagination UI
- **Goal:** Add pagination controls and page-aware fetching to the email page
- **Specs:**
  - [x] `emailPage` component tracks `currentPage`, `lastPage`, `total`, and `perPage` state
  - [x] `fetchEmails()` includes `page` and `per_page` query parameters
  - [x] `fetchEmails()` updates pagination metadata from API response
  - [x] Previous/Next navigation buttons rendered below the email list
  - [x] Page indicator shows "Page X of Y" between navigation buttons
  - [x] Previous button disabled on page 1; Next button disabled on last page
  - [x] `setFilter()` resets `currentPage` to 1 before fetching
  - [x] Pagination controls hidden when `lastPage <= 1`
  - [x] Date grouping and category grouping work on current page emails
  - [x] Email count badge shows total count (not just current page)
- **Files:** `resources/js/components/email-page.ts`, `resources/views/pages/mail.blade.php`

## Parallelization

**Strategy:** Sequential
**Execution method:** Subagents

Phase 2 depends on the API response format from Phase 1 (pagination meta structure). Sequential execution is appropriate.

## Review

**Review strategy:** end

Two phases, straightforward changes, low risk. A single review pass after both phases suffices.

## Out of Scope

- Infinite scroll (explicit pagination preferred for this use case)
- Email search/full-text filtering
- Customizable page size in the UI
- Caching of paginated results

## Open Questions

None.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
