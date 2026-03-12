## ADR-016: Remove email dismiss functionality

**Date:** 2026-03-12
**Phase:** Email Integration
**Tags:** backend, frontend, email, ux, microsoft
**Status:** Accepted

### Context

The email integration originally included a "dismiss" feature allowing users to hide individual emails from the Mithril inbox without affecting the source mailbox. This was implemented via an `is_dismissed` boolean column on the `emails` table, with dismiss/undismiss API endpoints, a dismiss button on each email card, and Alpine.js event handling.

In practice, the dismiss feature adds complexity without clear value. Mithril should reflect the user's actual inbox state — what's in their Microsoft mailbox is what they see. Allowing local dismissals creates divergence between Mithril and the source of truth (Outlook), leading to confusion about whether an email was handled or just hidden.

### Decision

Remove the dismiss UI and API surface entirely. Mithril's email list is now a direct mirror of the user's inbox — no local filtering or hiding.

**Removed:**
- Dismiss button from the email card template (`_email-card.blade.php`)
- `dismissEmail()` method and `dismiss-email` event listener from the Alpine `emailPage` component
- `dismiss()` and `undismiss()` controller methods from `EmailActionController`
- Dismiss/undismiss API routes (`POST emails/{email}/dismiss`, `POST emails/{email}/undismiss`)
- `is_dismissed` filters from all email queries (index, dashboard, sync cleanup)
- `is_dismissed` field from the TypeScript `Email` interface
- Related tests (controller dismiss/undismiss tests, sync service dismissed-email preservation test)

**Retained:**
- The `is_dismissed` database column remains in place (harmless default `false`). Removing it would require a migration for no functional benefit.
- The `is_dismissed` attribute on the Eloquent model remains in `$fillable` and `$casts` for backward compatibility with the column.

### Deviation from plan

The original email integration plan included dismiss as a way to manage inbox noise. This decision removes that capability in favor of treating the Microsoft inbox as the single source of truth.

### Consequences

- Users who want to remove an email from Mithril must handle it in Outlook (archive, delete, or move it out of the inbox).
- The sync service now deletes all local emails not present in the latest Graph API response, regardless of any prior local state.
- The `is_dismissed` column can be dropped in a future migration if desired, but has no operational impact remaining as `false`.
- Dashboard flagged emails widget and mail page both show all matching emails unconditionally.

### Follow-ups / open questions

- Consider a migration to drop the `is_dismissed` column and remove it from the model's `$fillable`/`$casts` arrays during a future cleanup pass.
