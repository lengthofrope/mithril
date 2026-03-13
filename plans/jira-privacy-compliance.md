# Jira Privacy Compliance — Remove Personal Data Storage

**Created:** 2026-03-13
**Status:** Complete
**Author:** Bas de Kort

## Problem Statement

The current Jira integration caches personal data from Jira users (assignee/reporter names and email addresses) in the `jira_issues` table. To make the app available for other users via the Atlassian Marketplace, it must comply with the [Atlassian User Privacy Developer Guide](https://developer.atlassian.com/cloud/jira/platform/user-privacy-developer-guide/).

Full compliance with personal data storage requires implementing a 7-day polling cycle against the report-accounts API, handling `closed`/`updated` account statuses, tracking data retrieval age, and more. The recommended alternative — and the simplest path — is to **stop storing personal data entirely** and fetch it on demand via the Jira API.

## Acceptance Criteria

1. The `jira_issues` table no longer contains `assignee_name`, `assignee_email`, `reporter_name`, or `reporter_email` columns
2. `assignee_account_id` and `reporter_account_id` are stored instead (account IDs are not personal data per Atlassian's definition)
3. Assignee and reporter display names are fetched from the Jira API at render time
4. API responses are cached in-memory (application cache, ~1 hour TTL) to avoid excessive API calls
5. The browse page, dashboard widget, and issue cards display names identically to before
6. `JiraActionService::resolveTeamMember()` works with account IDs — either via a new `jira_account_id` field on `team_members` or by fetching the email on demand
7. The `web_url` field is constructed correctly (fix: use the API's `self` link or the site URL from accessible-resources, not cloud ID as subdomain)
8. All existing Jira tests are updated and pass
9. No personal data reporting API implementation is needed (because no personal data is stored)

## Technical Design

### Approach

Replace stored personal data (names, emails) with Atlassian account IDs. Introduce a `JiraUserService` that resolves account IDs to display names via the Jira REST API, with application-level caching to prevent N+1 API calls on list pages.

```
jira_issues table
  - assignee_account_id (was: assignee_name + assignee_email)
  - reporter_account_id (was: reporter_name + reporter_email)
        ↓
JiraUserService::resolveDisplayNames(accountIds[])
        ↓
Jira REST API: GET /rest/api/3/user/bulk?accountId=...
        ↓
Application cache (1 hour TTL, keyed per cloud_id:account_id)
        ↓
Blade views render display names
```

### Jira Bulk User API

The [bulk user endpoint](https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-users/#api-rest-api-3-user-bulk-get) allows fetching up to 200 users per request:

```
GET /rest/api/3/user/bulk?accountId={id1}&accountId={id2}&...&maxResults=200
```

This avoids N+1 requests. One call per page load resolves all visible assignees/reporters.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `database/migrations/..._remove_personal_data_from_jira_issues.php` | Create | Drop 4 columns, add 2 account ID columns |
| `app/Services/JiraUserService.php` | Create | Bulk-resolve account IDs → display names with caching |
| `app/Services/JiraSyncService.php` | Modify | Store account IDs instead of names/emails |
| `app/Services/JiraCloudService.php` | Modify | Add `fetchUsersBulk()` method; fix `web_url` construction |
| `app/Services/JiraActionService.php` | Modify | Resolve team member by account ID or on-demand email fetch |
| `app/Models/JiraIssue.php` | Modify | Update fillable, remove old properties, add new ones |
| `database/factories/JiraIssueFactory.php` | Modify | Replace name/email with account IDs |
| `resources/views/pages/jira/_issue-card.blade.php` | Modify | Display resolved names from service instead of model columns |
| `app/Http/Controllers/Web/JiraPageController.php` | Modify | Resolve user names before passing to view |
| `app/Http/Controllers/Api/JiraIssueController.php` | Modify | Include resolved names in API responses |
| `resources/views/components/tl/jira-widget.blade.php` | Modify | Display resolved names |
| `tests/Feature/Services/JiraSyncServiceTest.php` | Modify | Update assertions for account IDs |
| `tests/Feature/Services/JiraActionServiceTest.php` | Modify | Update team member resolution tests |
| `tests/Feature/Http/Controllers/**/*Jira*Test.php` | Modify | Update response assertions |

### Data Model Changes

**`jira_issues` table — columns removed:**
```
assignee_name              VARCHAR(255) NULL   -- REMOVED
assignee_email             VARCHAR(255) NULL   -- REMOVED
reporter_name              VARCHAR(255) NULL   -- REMOVED
reporter_email             VARCHAR(255) NULL   -- REMOVED
```

**`jira_issues` table — columns added:**
```
assignee_account_id        VARCHAR(128) NULL   -- Atlassian account ID
reporter_account_id        VARCHAR(128) NULL   -- Atlassian account ID
```

**`web_url` construction fix:**
Currently: `https://{cloudId}.atlassian.net/browse/{key}` — cloudId is a UUID, not a subdomain.
Fix: Store the site URL from the accessible-resources response (which returns `url` field), or extract it during OAuth and store on the user model.

### Caching Strategy

- **Cache key:** `jira_user:{cloudId}:{accountId}`
- **TTL:** 1 hour (balances freshness with API budget)
- **Warm-up:** `JiraUserService::resolveDisplayNames()` accepts an array of account IDs, fetches all missing from cache in one bulk API call, caches individually
- **Invalidation:** Cache entries expire naturally via TTL — no manual invalidation needed since we no longer store the data persistently
- **Fallback:** If the API call fails (rate limit, network), display "Unknown user" or the account ID — never block page rendering

### Team Member Resolution

Current approach: match `assignee_email` against `TeamMember.email` and `TeamMember.microsoft_email`.

New approach (two options, decide during implementation):

**Option A — Store `jira_account_id` on `team_members` table:**
- Add nullable `jira_account_id` column to `team_members`
- Auto-populate during "Create from Jira issue" action (store the assignee's account ID on the resolved team member)
- Match directly on account ID — no email lookup needed
- Pros: fast, deterministic, no extra API calls
- Cons: requires migration, manual linking if auto-match fails

**Option B — Fetch email on demand from Jira API:**
- When resolving, call `/rest/api/3/user?accountId={id}` to get email
- Then match against `TeamMember.email` / `TeamMember.microsoft_email`
- Pros: no schema change on team_members
- Cons: extra API call per resolution, email may not be available (privacy settings)

**Recommendation:** Option A. It's more reliable (Atlassian users can hide their email), faster (no API call), and the migration is trivial.

## Implementation Phases

### Phase 1: JiraUserService & API Changes
- **Goal:** Account ID resolution works with caching
- **Specs:**
  - [x] `JiraCloudService::fetchUsersBulk()` calls the bulk user endpoint
  - [x] `JiraUserService` resolves account IDs to display names with cache
  - [x] `JiraUserService` handles missing/closed accounts gracefully (returns fallback string)
  - [x] `JiraUserService` accepts array input for batch resolution
  - [x] Cache key follows `jira_user:{cloudId}:{accountId}` pattern with 1-hour TTL
  - [x] Rate limit (429) on user fetch doesn't break page rendering
- **Files:** `JiraCloudService`, `JiraUserService`

### Phase 2: Migration & Sync Changes
- **Goal:** Personal data columns replaced with account IDs
- **Specs:**
  - [x] Migration removes `assignee_name`, `assignee_email`, `reporter_name`, `reporter_email`
  - [x] Migration adds `assignee_account_id`, `reporter_account_id` (VARCHAR 128, nullable)
  - [x] `JiraSyncService::normalizeIssue()` stores account IDs from `assignee.accountId` and `reporter.accountId`
  - [x] `JiraIssue` model updated: fillable, properties, no more name/email casts
  - [x] `JiraIssueFactory` updated for account IDs
  - [x] `web_url` construction fixed (store site URL on user during OAuth, or extract from accessible-resources)
- **Files:** Migration, `JiraSyncService`, `JiraIssue`, `JiraIssueFactory`, `JiraCloudService`

### Phase 3: Team Member Resolution
- **Goal:** Team member matching works without email
- **Specs:**
  - [x] Migration adds `jira_account_id` to `team_members` table (nullable)
  - [x] `JiraActionService::resolveTeamMember()` matches on `jira_account_id`
  - [x] Creating a resource from a Jira issue auto-populates `jira_account_id` on the matched team member
  - [x] Fallback: if no account ID match, attempt email lookup via API (best-effort)
  - [x] Settings or team member edit allows manually linking a Jira account ID
- **Files:** Migration, `JiraActionService`, `TeamMember` model, team member views

### Phase 4: View & Controller Updates
- **Goal:** UI displays resolved names identically to before
- **Specs:**
  - [x] `JiraPageController` collects all unique account IDs from issues, resolves via `JiraUserService`, passes name map to view
  - [x] `_issue-card.blade.php` displays assignee/reporter from the resolved name map
  - [x] `JiraIssueController` (API) includes resolved names in JSON responses
  - [x] Dashboard widget resolves names for displayed issues
  - [x] "Unknown user" fallback renders cleanly when resolution fails
  - [x] Page load performance is acceptable (single bulk API call, not N+1)
- **Files:** `JiraPageController`, `JiraIssueController`, `_issue-card.blade.php`, `jira-widget.blade.php`

### Phase 5: Test Updates
- **Goal:** All tests pass with the new structure
- **Specs:**
  - [x] `JiraSyncServiceTest` asserts account IDs stored (not names/emails)
  - [x] `JiraActionServiceTest` tests team member resolution by account ID
  - [x] `JiraCloudServiceTest` tests `fetchUsersBulk()` method
  - [x] New `JiraUserServiceTest` covers caching, bulk resolution, fallback behavior
  - [x] Controller tests verify resolved names in responses
  - [x] All existing Jira tests updated and passing
  - [x] All non-Jira tests still pass (no regressions)
- **Files:** All test files in `tests/Feature/**/*Jira*`

## Parallelization

**Strategy:** sequential

## Out of Scope

- **Personal data reporting API** — not needed since we won't store personal data
- **GDPR erasure webhooks** — not needed for the same reason
- **Jira Marketplace listing process** — separate concern (security review, descriptor, etc.)
- **Multiple Jira site support** — existing limitation, unchanged
- **Offline name resolution** — names require API access; offline mode shows account IDs or cached values

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Bulk user API rate-limited | Names don't display | Aggressive caching (1h TTL), graceful fallback to "Unknown user" |
| User has hidden profile | No display name available | API still returns `displayName` for authenticated apps with `read:jira-user` scope |
| Cache cold start on deploy | First page load slower | Pre-warm cache during sync job (fetch user details alongside issues) |
| Team member auto-match fails | Manual linking required | Provide UI in team member settings to link Jira account |

## Resolved Questions

1. **Why not implement the reporting API instead?** The reporting cycle adds significant ongoing complexity (polling, erasure, interruption handling, cycle-period tracking) for data we don't strictly need to persist. The "don't store" path is simpler and fully compliant.
2. **Account ID max length?** Atlassian docs say 1-128 characters, alphanumeric with `-` and `:`. VARCHAR(128) is sufficient.
3. **Cache backend?** Use Laravel's default cache driver (Redis in production, array in tests). No new infrastructure needed.
