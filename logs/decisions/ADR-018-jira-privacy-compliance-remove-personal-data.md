## ADR-018: Remove personal data from Jira integration for Atlassian Marketplace compliance

**Date:** 2026-03-13
**Phase:** Jira Privacy Compliance
**Tags:** backend, frontend, jira, privacy, atlassian, caching, api
**Status:** Accepted

### Context

The Jira integration stored assignee and reporter names and email addresses in the `jira_issues` table. To list the app on the Atlassian Marketplace, compliance with the [Atlassian User Privacy Developer Guide](https://developer.atlassian.com/cloud/jira/platform/user-privacy-developer-guide/) is required.

Full compliance with personal data storage would require implementing a 7-day polling cycle against the report-accounts API, handling `closed`/`updated` account statuses, tracking data retrieval age, and more. The simpler and fully compliant alternative is to stop storing personal data entirely.

Two approaches were considered:
1. **Implement the reporting API** — complex ongoing maintenance, polling cycles, erasure handling
2. **Stop storing personal data** — fetch display names on demand via Jira API, cache in-memory

### Decision

Remove all personal data columns (`assignee_name`, `assignee_email`, `reporter_name`, `reporter_email`) from `jira_issues` and replace them with Atlassian account IDs (`assignee_account_id`, `reporter_account_id`), which are not personal data per Atlassian's definition.

Key implementation choices:

1. **`JiraUserService`** resolves account IDs to display names via the Jira bulk user API (`/rest/api/3/user/bulk`), with application-level caching (1-hour TTL, keyed as `jira_user:{cloudId}:{accountId}`). A single bulk call per page load prevents N+1 API requests.

2. **`web_url` construction fixed** — the previous implementation used the Jira cloud ID (a UUID) as a subdomain (`https://{cloudId}.atlassian.net/browse/...`), which is incorrect. Now stores `jira_site_url` on the user model during OAuth (extracted from the accessible-resources API response) and uses it for URL construction.

3. **Team member resolution via `jira_account_id`** (Option A from the plan) — a new nullable `jira_account_id` column on `team_members` enables direct matching without email lookups. Falls back to fetching the email from the Jira API and matching against `TeamMember.email`/`microsoft_email`, auto-populating `jira_account_id` on the first successful match.

4. **`JiraTokenResponse` DTO extended** with `siteUrl` to thread the site URL through the OAuth flow from `JiraCloudService` through `JiraAuthController` to the user model.

### Consequences

- **No personal data stored** — eliminates the need for report-accounts API polling, GDPR erasure webhooks, and data retention tracking
- **API dependency for display names** — page loads require a Jira API call when the cache is cold; graceful fallback to "Unknown user" on failure
- **Pre-release migration modification** — since the project uses `RefreshDatabase` in tests and is pre-release, the original `create_jira_issues_table` migration was modified directly rather than creating a new migration
- **Team member linking** — existing team members have no `jira_account_id` set; it auto-populates on first resolution via the email fallback path, or can be set manually in the team member settings UI
- **MariaDB compatibility** — two pre-existing migrations (`calendar_event_links`, `system_notification_dismissals`) had auto-generated unique constraint names exceeding MariaDB's 64-character identifier limit; fixed with explicit short names

### Follow-ups / open questions

- Consider pre-warming the user cache during Jira sync jobs to avoid cold cache on first page load after deploy
- Multiple Jira site support remains out of scope (existing limitation)
- Atlassian Marketplace listing process (security review, app descriptor) is a separate concern
