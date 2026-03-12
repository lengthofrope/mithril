## ADR-015: Email sync fetches all inbox emails with per-message source tagging

**Date:** 2026-03-12
**Phase:** Email Integration (tasks-from-emails plan)
**Tags:** backend, microsoft, email, sync, graph-api
**Status:** Accepted

### Context

The original email sync design used user-configurable source preferences (flagged, categorized, unread) as **OData filters** in the Microsoft Graph API request. Only emails matching the enabled filters were fetched and stored locally.

Two problems surfaced during real-world testing:

1. **Graph API limitation with `$orderby`:** Combining complex OData filters (e.g. `flag/flagStatus eq 'flagged'`) with `$orderby` causes a 400 error: "The restriction or sort order is too complex for this operation."
2. **No OData filter for "has any category":** The Graph API does not support filtering for emails with any non-empty category. The `categories/any()` lambda only supports matching a specific category name, which defeated the goal of showing all categorized emails grouped by their Outlook categories.
3. **User expectation mismatch:** The user expected all inbox emails to be visible in Mithril, with sources serving as grouping/filtering in the UI — not as criteria for what gets synced.

### Decision

**Email sync always fetches all inbox emails.** The `getMyMessages()` Graph API call targets `me/mailFolders/Inbox/messages` without any `$filter` or `$orderby` parameter. Results are sorted client-side by `received_at`.

**Sources are display-only tags**, determined per-message after fetch based on actual message properties:
- `flagged`: `is_flagged === true`
- `categorized`: `categories` array is non-empty
- `unread`: `is_read === false`

Source tags are stored in the `sources` JSON column on the `emails` table for client-side filtering. The user settings `email_source_flagged`, `email_source_categorized`, and `email_source_unread` remain as UI preferences controlling which filter tabs are highlighted, but they no longer affect what gets fetched.

The `email_source_category_name` setting was removed — categorization now covers all Outlook categories, not a single named one. The mail page groups emails by their actual category names when the "categorized" filter is active.

### Deviation from plan

The `tasks-from-emails.md` plan specified source preferences as fetch filters:
> "When categorized is enabled, filter Graph API for emails matching the configured category name."

The implementation deviates by fetching all inbox emails unconditionally and using source preferences purely for display. This simplifies the sync logic and avoids multiple Graph API workarounds.

### Consequences

- **EmailSyncService** simplified: `buildFilter()` removed, `syncEmails()` makes a single unfiltered Graph API call, `determineSourcesForMessage()` tags each message individually.
- **MicrosoftGraphService::getMyMessages()** filter parameter is now optional; endpoint changed from `me/messages` to `me/mailFolders/Inbox/messages`.
- **More emails stored locally:** Up to 50 inbox emails per sync (the `$top` limit), regardless of flags/categories. Previously only matching emails were stored.
- **Settings UI simplified:** Removed category name input field. Categorized toggle description updated.
- **Mail page UI:** When "categorized" filter is active, emails are grouped by their Outlook category names with alphabetical ordering and received_at sorting within each group.
- **Migration not needed:** No schema changes; the `email_source_category_name` column remains in the database but is unused.

### Follow-ups / open questions

- Consider increasing the `$top` parameter or implementing pagination if users have very active inboxes where 50 messages isn't enough.
- The `email_source_category_name` column could be removed in a future migration cleanup.
