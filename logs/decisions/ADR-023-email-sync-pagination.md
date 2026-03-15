## ADR-023: Email sync paginates through all inbox pages

**Date:** 2026-03-15
**Phase:** Email Integration
**Tags:** backend, microsoft, email, sync, graph-api, pagination
**Status:** Accepted

### Context

`MicrosoftGraphService::getMyMessages()` used a single Graph API call with `$top=50`, returning only the first page of results. Users with more than 50 emails in their inbox would silently lose visibility of older messages. The Microsoft Graph API provides `@odata.nextLink` for pagination, which was not being followed.

ADR-015 already noted this as a follow-up: "Consider increasing the `$top` parameter or implementing pagination if users have very active inboxes where 50 messages isn't enough."

### Decision

`getMyMessages()` now follows `@odata.nextLink` to retrieve all pages of inbox messages. A safety cap of **10 pages** (up to 500 messages at `$top=50`) prevents runaway loops on extremely large inboxes.

Implementation detail: `$response->json()['@odata.nextLink']` is used instead of `$response->json('@odata.nextLink')` because Laravel's `data_get()` helper does not correctly resolve keys containing `@`.

### Consequences

- All inbox messages are now synced, not just the first 50.
- The `EmailSyncService::syncEmails()` cleanup query removes messages no longer in the inbox, so stale messages are still cleaned up correctly.
- Maximum of 11 HTTP requests per sync (1 initial + 10 pagination) — acceptable for a background job.
- The `$top` parameter remains as page size, not a total cap.
