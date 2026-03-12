# Tasks from E-mails — Implementation Plan

## Summary

Add a dedicated **Mail page** to Mithril that surfaces flagged, categorized, or unread Microsoft Outlook emails as actionable items. Users can view a curated email inbox, convert any email into a task, follow-up, note, or bila with a single click, pre-filling fields from the email and linking back to the original. A **dashboard widget** shows flagged emails with deadlines for quick triage. This extends the existing Microsoft Graph integration with the `Mail.Read` permission scope.

---

## Design Decisions

### Dedicated Mail Page

Mail gets its own full page at `/mail`, placed in the sidebar directly below Calendar (both require Microsoft connection). The page provides the complete email management experience — filtering, searching, bulk actions, and resource creation. This mirrors how Tasks, Follow-ups, and Notes each have dedicated pages rather than living only on the dashboard.

### Dashboard Widget (Same Pattern as Calendar/Analytics Widgets)

The dashboard shows a **compact email widget** following the same pattern as existing dashboard sections (calendar upcoming, today's tasks, etc.). The widget displays only **flagged emails that have a deadline** (Outlook flag due date). Each email in the widget has action buttons to create:

- **Task** — always available
- **Follow-up** — always available
- **Note** — always available
- **Bila** — **greyed out / disabled** unless the sender's email matches a team member in one of the user's teams (resolved via `TeamMember.email` or `TeamMember.microsoft_email`)

### Sidebar Menu Order

The new menu order groups items logically with dash separators:

```
Dashboard
---
Calendar          (Microsoft connection required)
Mail              (Microsoft connection required)
---
Tasks
Follow-ups
Notes
Bila's
---
Teams
Weekly Review
---
Analytics
```

When no Microsoft connection is active, Calendar and Mail are hidden and the two separators around them collapse into one — no double separator is shown.

### Read-Only Email Access

Mithril only **reads** emails — it never sends, moves, archives, or modifies flags. The only write-back is marking an email as "read" (optional, user preference). This keeps the permission scope minimal and avoids accidental side-effects.

### Cached Email Model (Same Pattern as Calendar Events)

Emails are synced to a local `emails` cache table, following the same pattern as `calendar_events`. This avoids slow API calls on page load and enables filtering/searching locally. The sync job runs on a schedule (configurable, default every 5 minutes).

### Three Email Sources

Users configure which emails to surface:

| Source | Description | Graph API Filter |
|--------|-------------|------------------|
| **Flagged** | Emails the user has flagged in Outlook | `flag/flagStatus eq 'flagged'` |
| **Categorized** | Emails with a specific Outlook category (e.g., "Mithril") | `categories/any(c:c eq 'Mithril')` |
| **Unread** | Unread emails in the inbox | `isRead eq false` |

Users toggle which sources are active in Settings. At least one must be active for the mail page to show content.

### Email → Resource Conversion (Same Pattern as Calendar Actions)

Converting an email to a task/follow-up/note/bila follows the same architecture as "Create from Calendar Event" — pre-fill fields, create via API, link back via a polymorphic pivot. The `EmailLink` model mirrors `CalendarEventLink`.

### No Full Email Client

Mithril is NOT an email client. It shows a curated list of actionable emails. The email body is stored as plain text (stripped HTML) for preview purposes. Full email viewing/replying happens in Outlook.

---

## Data Model

### New Table: `emails`

```
emails
├── id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
├── user_id             BIGINT UNSIGNED, FK → users.id, ON DELETE CASCADE
├── microsoft_message_id VARCHAR(255), NOT NULL  — Graph API message ID
├── subject             VARCHAR(500), NOT NULL
├── sender_name         VARCHAR(255), NULL
├── sender_email        VARCHAR(255), NULL
├── received_at         TIMESTAMP, NOT NULL
├── body_preview        TEXT, NULL  — Plain text preview (max 500 chars)
├── is_read             BOOLEAN, NOT NULL, DEFAULT FALSE
├── is_flagged          BOOLEAN, NOT NULL, DEFAULT FALSE
├── flag_due_date       DATE, NULL  — Outlook flag due date (for deadline widget)
├── categories          JSON, NULL  — Array of Outlook category strings
├── importance          VARCHAR(20), NOT NULL, DEFAULT 'normal'  — 'low' | 'normal' | 'high'
├── has_attachments     BOOLEAN, NOT NULL, DEFAULT FALSE
├── web_link            VARCHAR(1000), NULL  — Outlook web link to open the email
├── source              VARCHAR(20), NOT NULL  — 'flagged' | 'categorized' | 'unread'
├── is_dismissed        BOOLEAN, NOT NULL, DEFAULT FALSE  — User dismissed from Mithril (not Outlook)
├── synced_at           TIMESTAMP, NOT NULL
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
└── UNIQUE (user_id, microsoft_message_id)
```

**Note:** `flag_due_date` is extracted from the Graph API `flag.dueDateTime` field. This is critical for the dashboard widget which shows only flagged emails **with a deadline**.

### New Table: `email_links`

```
email_links
├── id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
├── email_id            BIGINT UNSIGNED, FK → emails.id, ON DELETE CASCADE
├── linkable_type       VARCHAR(255), NOT NULL  — Morph type (App\Models\Task, etc.)
├── linkable_id         BIGINT UNSIGNED, NOT NULL
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
└── INDEX (linkable_type, linkable_id)
└── UNIQUE (email_id, linkable_type, linkable_id)
```

### Modified Table: `users`

New columns for email preferences:

```
users (existing, add columns)
├── email_source_flagged       BOOLEAN, NOT NULL, DEFAULT TRUE
├── email_source_categorized   BOOLEAN, NOT NULL, DEFAULT FALSE
├── email_source_category_name VARCHAR(100), NULL, DEFAULT 'Mithril'
├── email_source_unread        BOOLEAN, NOT NULL, DEFAULT FALSE
```

### Enum: `EmailSource`

```php
enum EmailSource: string
{
    case Flagged = 'flagged';
    case Categorized = 'categorized';
    case Unread = 'unread';
}
```

### Enum: `EmailImportance`

```php
enum EmailImportance: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
}
```

---

## Backend Architecture

### Service: `EmailSyncService`

```php
class EmailSyncService
{
    /**
     * Build the OData filter string based on user preferences.
     * Combines active sources with OR logic.
     */
    public function buildFilter(User $user): string

    /**
     * Sync emails from Microsoft Graph for the given user.
     * Upserts into the emails table, removes emails that no longer match filters.
     */
    public function syncEmails(User $user): void

    /**
     * Normalize a Graph API message response into an array suitable for upsert.
     * Includes flag_due_date extraction from flag.dueDateTime.
     */
    public function normalizeMessage(array $graphMessage, EmailSource $source): array
}
```

### Service: `EmailActionService`

Following the `CalendarActionService` pattern:

```php
class EmailActionService
{
    /**
     * Build pre-fill data for creating a resource from an email.
     * For 'bila' type, only succeeds if sender resolves to a team member.
     */
    public function buildPrefillData(Email $email, string $resourceType): array

    /**
     * Create a link between an email and a resource.
     */
    public function linkResource(Email $email, Model $resource): EmailLink

    /**
     * Resolve team member from sender email (matches against TeamMember.email and microsoft_email).
     */
    public function resolveTeamMember(Email $email): ?TeamMember

    /**
     * Check if the sender of an email is a member of any of the user's teams.
     * Used to determine if the Bila action should be enabled.
     */
    public function senderIsTeamMember(Email $email): bool
}
```

### Job: `SyncEmailsJob`

```php
class SyncEmailsJob implements ShouldQueue
{
    public function __construct(private readonly User $user) {}

    public function handle(EmailSyncService $service, MicrosoftGraphService $graph): void
    {
        // 1. Check if user has any email source enabled
        // 2. Build OData filter from user preferences
        // 3. Fetch messages from Graph API (GET /me/messages?$filter=...&$top=50&$orderby=receivedDateTime desc)
        //    Include $select=flag to get flag.dueDateTime
        // 4. Upsert into emails table (on microsoft_message_id)
        // 5. Remove cached emails that no longer match the filter (except dismissed ones with links)
        // 6. Update synced_at
    }
}
```

### Controller: `EmailPageController` (Web — Mail Page)

```php
class EmailPageController extends Controller
{
    /**
     * GET /mail
     * Renders the full mail page with all cached emails.
     * Filters by source tab, supports search.
     */
    public function index(Request $request): View
}
```

### Controller: `EmailActionController` (API — Actions)

```php
class EmailActionController extends Controller
{
    /**
     * GET /api/emails
     * Returns the user's cached emails, filterable by source.
     */
    public function index(Request $request): JsonResponse

    /**
     * GET /api/emails/dashboard
     * Returns flagged emails with a deadline for the dashboard widget.
     * Ordered by flag_due_date ASC (most urgent first).
     */
    public function dashboard(Request $request): JsonResponse

    /**
     * GET /api/emails/{email}/prefill/{type}
     * Returns pre-fill data for creating a resource from an email.
     * For 'bila': returns 422 if sender is not a team member.
     */
    public function prefill(Email $email, string $type): JsonResponse

    /**
     * POST /api/emails/{email}/create/{type}
     * Creates the resource, links it to the email, returns the resource.
     * Types: task, follow-up, note, bila
     */
    public function create(Request $request, Email $email, string $type): JsonResponse

    /**
     * POST /api/emails/{email}/dismiss
     * Marks an email as dismissed in Mithril (hides from the panel).
     */
    public function dismiss(Email $email): JsonResponse

    /**
     * DELETE /api/emails/{email}/links/{emailLink}
     * Removes a link (does NOT delete the resource itself).
     */
    public function unlink(Email $email, EmailLink $emailLink): JsonResponse
}
```

### Pre-fill Mapping

| Target Resource | Pre-filled Fields |
|----------------|-------------------|
| **Task** | `title` ← email `subject`, `team_member_id` ← resolved from sender, `priority` ← mapped from `importance` (high → High, normal → Normal, low → Low) |
| **Follow-up** | `description` ← email `subject`, `team_member_id` ← resolved from sender, `follow_up_date` ← `flag_due_date` or today + 3 days |
| **Note** | `title` ← email `subject`, `content` ← email `body_preview`, `team_member_id` ← resolved from sender |
| **Bila** | `team_member_id` ← resolved from sender (required), `notes` ← email `subject` as agenda item. Only available when sender resolves to a team member. |

### MicrosoftGraphService Extension

```php
// Add to MicrosoftGraphService:

/**
 * Fetch filtered messages from the user's inbox.
 * Includes flag.dueDateTime in $select for deadline extraction.
 */
public function getMyMessages(
    User $user,
    string $filter,
    int $top = 50,
): Collection
```

### Scheduler

```php
Schedule::command('microsoft:sync-emails')->everyFiveMinutes();
```

### Microsoft Permissions Update

Add `Mail.Read` to the required scopes in `config/microsoft.php`:

```php
'scopes' => [
    'User.Read',
    'Calendars.Read',
    'Mail.Read',       // NEW
    'offline_access',
],
```

**Important:** Existing users who connected before this scope was added will need to re-authorize to grant `Mail.Read`. Handle this gracefully — if the token lacks `Mail.Read`, skip email sync and show a "Re-authorize to enable email integration" prompt.

---

## Routes

### Web Routes

```php
// routes/web.php
Route::get('/mail', [EmailPageController::class, 'index'])
    ->name('mail.index')
    ->middleware('auth');
```

### API Routes

```php
// routes/api.php
Route::prefix('emails')->group(function () {
    Route::get('/', [EmailActionController::class, 'index'])
        ->name('api.emails.index');

    Route::get('dashboard', [EmailActionController::class, 'dashboard'])
        ->name('api.emails.dashboard');

    Route::prefix('{email}')->group(function () {
        Route::get('prefill/{type}', [EmailActionController::class, 'prefill'])
            ->name('api.emails.prefill')
            ->whereIn('type', ['task', 'follow-up', 'note', 'bila']);

        Route::post('create/{type}', [EmailActionController::class, 'create'])
            ->name('api.emails.create')
            ->whereIn('type', ['task', 'follow-up', 'note', 'bila']);

        Route::post('dismiss', [EmailActionController::class, 'dismiss'])
            ->name('api.emails.dismiss');

        Route::delete('links/{emailLink}', [EmailActionController::class, 'unlink'])
            ->name('api.emails.unlink');
    });
});
```

---

## Sidebar Menu Update

`MenuHelper::getMainNavItems()` must be updated to produce the new menu order:

```
Dashboard
--- separator ---
Calendar          ← conditional: Microsoft connection
Mail              ← conditional: Microsoft connection (NEW)
--- separator ---
Tasks
Follow-ups
Notes
Bila's
--- separator ---
Teams
Weekly Review
--- separator ---
Analytics
```

The separator support (dashes) requires either a new `'separator' => true` item type in the menu array, or grouping into multiple menu groups in `getMenuGroups()`. When no Microsoft connection is active, Calendar and Mail are hidden and the two separators around them must collapse into one — never render consecutive separators.

---

## Frontend

### Mail Page (`/mail`)

Full-page view at `resources/views/pages/mail/index.blade.php`:

```
Mail Page
├── Header: "Mail" + sync status ("Last synced: 2 min ago") + manual refresh button
├── Source tabs: [All] [Flagged] [Categorized] [Unread] (based on active sources)
├── Search bar: filter by subject/sender (client-side on cached data)
├── Email list (full height, scrollable)
│   ├── Email item (card style, elvish-card):
│   │   ├── Left: Importance indicator (color-coded)
│   │   ├── Center:
│   │   │   ├── Sender name + email
│   │   │   ├── Subject line (bold if unread)
│   │   │   ├── Body preview (truncated, 2 lines)
│   │   │   ├── Flag due date badge (if flagged with deadline)
│   │   │   └── Linked resource badges: [T] Task [F] Follow-up [N] Note [B] Bila
│   │   ├── Right: Action buttons
│   │   │   ├── [+] Create → dropdown:
│   │   │   │   ├── Task
│   │   │   │   ├── Follow-up
│   │   │   │   ├── Note
│   │   │   │   └── Bila (greyed out if sender not in teams)
│   │   │   ├── Dismiss (X)
│   │   │   └── Open in Outlook (→ web_link)
│   │   └── Meta: received time (relative), 📎 attachment indicator
│   └── ...
├── Empty state: "No actionable emails" or "Connect Office 365 to see emails"
└── Dismissed toggle: "Show dismissed" (hidden by default)
```

### Dashboard Widget: Flagged Emails with Deadlines

Compact widget on the dashboard, following the same card-style pattern as the calendar upcoming and today's tasks sections:

```
<x-tl.email-deadline-widget :emails="$deadlineEmails" />

Dashboard Email Widget
├── Header: "Mail Deadlines" (with mail icon)
├── Email list (compact, max 5 items, ordered by due date):
│   ├── Email item (compact row):
│   │   ├── Due date badge (color: overdue=red, today=amber, upcoming=default)
│   │   ├── Subject (truncated)
│   │   ├── Sender name
│   │   ├── Action buttons: [Task] [Follow-up] [Note] [Bila*]
│   │   │   └── *Bila greyed out unless sender matches a team member
│   │   └── Open in Outlook link
│   └── ...
├── "View all" link → /mail
└── Empty state: "No flagged emails with deadlines"
```

**Data source:** Only flagged emails (`is_flagged = true`) that have a `flag_due_date` set. Ordered by `flag_due_date ASC` (most urgent first). The dashboard controller passes these separately from the full email list.

### Settings: Email Sources

On the settings page, under the Microsoft section:

```
Email Integration
├── Sources:
│   ├── [toggle] Flagged emails
│   ├── [toggle] Categorized emails
│   │   └── Category name: [input: "Mithril"] (auto-save)
│   └── [toggle] Unread emails (inbox only)
└── Note: "Emails sync every 5 minutes. Only subjects and previews are cached."
```

### Alpine Component: `emailPage`

```typescript
interface EmailPageData {
    emails: Email[];
    activeSource: EmailSource | 'all';
    searchQuery: string;
    showDismissed: boolean;
    loading: boolean;

    get filteredEmails(): Email[];
    filterBySource(source: string): void;
    createResource(emailId: number, type: string): Promise<void>;
    dismiss(emailId: number): Promise<void>;
    undismiss(emailId: number): Promise<void>;
    unlinkResource(emailId: number, linkId: number): Promise<void>;
    refresh(): Promise<void>;
}
```

### Alpine Component: `emailDeadlineWidget`

```typescript
interface EmailDeadlineWidgetData {
    emails: EmailWithDeadline[];
    loading: boolean;

    createResource(emailId: number, type: string): Promise<void>;
    refresh(): Promise<void>;
    canCreateBila(email: EmailWithDeadline): boolean;
}

interface EmailWithDeadline extends Email {
    flag_due_date: string;
    sender_is_team_member: boolean;  // Resolved server-side
}
```

### TypeScript Types

```typescript
// resources/js/types/models.ts — add:
interface Email {
    id: number;
    microsoft_message_id: string;
    subject: string;
    sender_name: string | null;
    sender_email: string | null;
    received_at: string;
    body_preview: string | null;
    is_read: boolean;
    is_flagged: boolean;
    flag_due_date: string | null;
    categories: string[] | null;
    importance: EmailImportance;
    has_attachments: boolean;
    web_link: string | null;
    source: EmailSource;
    is_dismissed: boolean;
    sender_is_team_member?: boolean;  // Included in dashboard endpoint
    links?: EmailLink[];
    synced_at: string;
}

interface EmailLink {
    id: number;
    email_id: number;
    linkable_type: string;
    linkable_id: number;
    linkable?: Task | FollowUp | Note | Bila;
    created_at: string;
}

type EmailSource = 'flagged' | 'categorized' | 'unread';
type EmailImportance = 'low' | 'normal' | 'high';
```

---

## Implementation Phases

### Phase 1: Data Layer (backend agent)

**Files:**
- `database/migrations/xxxx_create_emails_table.php`
- `database/migrations/xxxx_create_email_links_table.php`
- `database/migrations/xxxx_add_email_preferences_to_users_table.php`
- `app/Models/Email.php`
- `app/Models/EmailLink.php`
- `app/Enums/EmailSource.php`
- `app/Enums/EmailImportance.php`
- `database/factories/EmailFactory.php`
- `database/factories/EmailLinkFactory.php`

**Tests (TDD — write first):**
- Email model: creation, BelongsToUser scope, relationships
- Email model: `flag_due_date` field stored and cast correctly
- EmailLink model: polymorphic relationships to Task, FollowUp, Note, Bila
- Unique constraint on `(user_id, microsoft_message_id)` prevents duplicates
- Unique constraint on `(email_id, linkable_type, linkable_id)` prevents duplicate links
- Cascade delete: deleting email removes its links
- User email preference fields default correctly
- Scope for dashboard: flagged emails with `flag_due_date` set

**Depends on:** nothing

### Phase 2: Graph API Extension (backend agent)

**Files:**
- `app/Services/MicrosoftGraphService.php` (update: add `getMyMessages()`)
- `config/microsoft.php` (update: add `Mail.Read` scope)

**Tests (TDD — write first):**
- `getMyMessages()` returns normalized collection
- OData filter construction for each source type
- Combined filter with multiple active sources
- Error handling for missing `Mail.Read` scope
- `flag.dueDateTime` extracted to `flag_due_date`

**Depends on:** Phase 1

### Phase 3: Email Sync (backend agent)

**Files:**
- `app/Services/EmailSyncService.php`
- `app/Jobs/SyncEmailsJob.php`
- `app/Console/Commands/SyncEmailsCommand.php`

**Tests (TDD — write first):**
- `buildFilter()`: flagged only → correct OData
- `buildFilter()`: categorized with custom name → correct OData
- `buildFilter()`: unread only → correct OData
- `buildFilter()`: multiple sources combined → OR logic
- `buildFilter()`: no sources enabled → returns empty (skip sync)
- `normalizeMessage()`: maps Graph fields correctly
- `normalizeMessage()`: extracts `flag_due_date` from `flag.dueDateTime`
- `syncEmails()`: upserts new emails, updates existing, removes stale
- `syncEmails()`: does not remove dismissed emails that have links
- Job dispatches correctly from scheduler command

**Depends on:** Phase 2

### Phase 4: Email Action Service (backend agent)

**Files:**
- `app/Services/EmailActionService.php`

**Tests (TDD — write first):**
- `resolveTeamMember()`: matches sender_email against TeamMember.email (case-insensitive)
- `resolveTeamMember()`: matches against TeamMember.microsoft_email
- `resolveTeamMember()`: returns null for no match
- `senderIsTeamMember()`: returns true when sender matches a team member
- `senderIsTeamMember()`: returns false for unknown senders
- `buildPrefillData()`: correct pre-fill for task, follow-up, note
- `buildPrefillData()`: correct pre-fill for bila (requires team member)
- `buildPrefillData()`: bila returns error when sender is not a team member
- `buildPrefillData()`: follow-up uses `flag_due_date` when available
- `linkResource()`: creates link, prevents duplicates

**Depends on:** Phase 1

### Phase 5: API Endpoints (backend agent)

**Files:**
- `app/Http/Controllers/Api/EmailActionController.php`
- `routes/api.php` (update: add email routes)

**Tests (TDD — write first):**
- `index`: returns user's emails, filtered by source
- `index`: respects BelongsToUser scope
- `dashboard`: returns only flagged emails with `flag_due_date` set
- `dashboard`: includes `sender_is_team_member` boolean per email
- `dashboard`: orders by `flag_due_date` ASC
- `prefill`: returns correct pre-fill data per type
- `prefill`: bila type returns 422 for non-team-member sender
- `create`: creates resource + link, returns standardized response
- `create`: bila type fails for non-team-member sender
- `dismiss`: sets `is_dismissed = true`
- `unlink`: removes link without deleting resource
- Unauthorized access returns 403

**Depends on:** Phase 4

### Phase 6: Mail Page + Menu Update (frontend agent)

**Files:**
- `app/Http/Controllers/Web/EmailPageController.php` (new)
- `resources/views/pages/mail/index.blade.php` (new)
- `routes/web.php` (update: add `/mail` route)
- `app/Helpers/MenuHelper.php` (update: new menu order + Mail item + separators)

**Tests (TDD — write first):**
- Mail page returns 200 for authenticated user with Microsoft connection
- Mail page redirects/403 for user without Microsoft connection
- MenuHelper produces correct menu order with separators
- Mail menu item only visible with Microsoft connection

**Depends on:** Phase 1

### Phase 7: Frontend — Settings (frontend agent)

**Files:**
- Settings Blade template (update: add email source toggles)
- Auto-save for email preferences on User model

**Depends on:** Phase 1

### Phase 8: Frontend — Mail Page UI (frontend + typescript agent)

**Files:**
- `resources/js/components/email-page.ts` (new)
- `resources/js/app.ts` (update: register component)
- `resources/js/types/models.ts` (update: add Email types)
- Mail page Blade template (wire up Alpine component)

**Depends on:** Phase 5, Phase 6

### Phase 9: Frontend — Dashboard Widget (frontend + typescript agent)

**Files:**
- `resources/views/components/tl/email-deadline-widget.blade.php` (new)
- `resources/js/components/email-deadline-widget.ts` (new)
- `resources/js/app.ts` (update: register component)
- Dashboard Blade (update: include email deadline widget)
- Dashboard controller (update: pass deadline emails to view)

**Depends on:** Phase 5, Phase 8

### Phase 10: Data Pruning Extension (backend agent)

**Files:**
- `app/Services/DataPruningService.php` (update: add email + calendar pruning)
- `app/DataTransferObjects/PruneResult.php` (update: add `emailsDeleted`, `calendarEventsDeleted` counters)

**Tests (TDD — write first):**
- Dismissed emails without links older than retention are pruned
- Dismissed emails with links are NOT pruned
- Stale emails (`synced_at` > 30 days) without links are pruned
- Stale emails with links are NOT pruned
- Created resources (tasks, follow-ups, notes, bilas) survive when their source email is pruned
- Orphaned EmailLinks (linked resource deleted) are cleaned up
- Old calendar events (past retention) without links are pruned
- Old calendar events with links are NOT pruned
- Created resources survive when their source calendar event is pruned
- PruneResult includes new counters
- Settings page shows email/calendar prune counts in dry-run

**Depends on:** Phase 1

---

## Agent Ownership

| Phase | Agent | Owns |
|-------|-------|------|
| 1 | backend | Migrations, models, enums, factories |
| 2 | backend | Graph service, config |
| 3 | backend | Sync service, job, command |
| 4 | backend | EmailActionService |
| 5 | backend | Controller, routes (API) |
| 6 | frontend | Mail page controller, Blade, web routes, MenuHelper |
| 7 | frontend | Settings UI |
| 8 | frontend + typescript | Mail page Alpine component, types |
| 9 | frontend + typescript | Dashboard email widget component |
| 10 | backend | DataPruningService extension, PruneResult update |

**Shared files:**
- `routes/api.php` — Phase 5 adds routes
- `routes/web.php` — Phase 6 adds mail route
- `resources/js/app.ts` — Phase 8 + 9 register Alpine components

---

## Data Pruning

Extend the existing `DataPruningService` to handle email and calendar cleanup. The guiding principle: **created resources (tasks, follow-ups, notes, bilas) always survive** — only the cached source records and orphaned links are pruned.

### New Pruning Targets

| Target | Condition | Rationale |
|--------|-----------|-----------|
| **Dismissed emails without links** | `is_dismissed = true` AND no `email_links` AND `updated_at` older than retention period | No longer actionable and user explicitly dismissed them. |
| **Stale emails no longer in inbox** | Email no longer returned by Graph API sync (already removed by sync job), but as a safety net: any `Email` record where `synced_at` is older than 30 days AND has no links | Covers edge cases where sync misses cleanup (e.g., source toggle changes between syncs). |
| **Orphaned EmailLinks** | `EmailLink` where the linked resource no longer exists (same pattern as existing `CalendarEventLink` orphan cleanup) | Linked task/follow-up was pruned or manually deleted. |
| **Old calendar events** | `CalendarEvent` where `start_at` is older than retention period AND has no `CalendarEventLink` records | Currently these accumulate forever. Past events with no linked resources are just cache bloat. |
| **Orphaned CalendarEventLinks** | Already handled — no change needed. | Existing behavior in `DataPruningService`. |

### Important: Resources Always Survive

When an `Email` or `CalendarEvent` is pruned, any tasks, follow-ups, notes, or bilas that were created from it remain untouched. The `email_links` / `calendar_event_links` pivot records are deleted (cascade or orphan cleanup), but the resources themselves are independent entities that live on their own lifecycle.

### Updated `DataPruningService`

```php
class DataPruningService
{
    public function pruneForUser(User $user): PruneResult
    {
        // Existing: done tasks + done follow-ups beyond retention
        // Existing: orphaned CalendarEventLinks

        // NEW: dismissed emails without links beyond retention
        // NEW: stale emails (synced_at > 30 days ago) without links
        // NEW: orphaned EmailLinks (linked resource deleted)
        // NEW: old calendar events (start_at beyond retention) without links
    }
}
```

The `PruneResult` DTO should be extended with additional counters (`emailsDeleted`, `calendarEventsDeleted`).

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| User has no active email sources | Mail page shows "Enable email sources in Settings". Dashboard widget hidden. Sync job skips this user. |
| Microsoft token lacks `Mail.Read` scope | Sync skips emails, shows "Re-authorize to enable email integration" prompt on mail page and dashboard widget area. |
| Email deleted/unflagged in Outlook between syncs | Next sync removes it from cache. Links and created resources survive — only the cached `Email` record is deleted. Frontend shows "Email removed" on orphaned links. |
| Same email matched by multiple sources (flagged AND unread) | First match wins. `source` field stores the primary source. Unique constraint on `microsoft_message_id` prevents duplicates. |
| High volume inbox (100+ unread) | Sync fetches max 50 most recent. Configurable via `config/microsoft.php`. |
| Email dismissed but then flagged again in Outlook | Re-sync does not un-dismiss. User must manually un-dismiss in Mithril (or the dismiss resets on next flag change — discuss during implementation). |
| Non-ASCII subjects | Graph API returns UTF-8. Laravel handles this natively. |
| Flagged email without due date | Shown on mail page but NOT in dashboard widget (widget requires `flag_due_date`). |
| Bila action for non-team-member sender | Button greyed out with tooltip "Sender is not a team member". API returns 422 if called directly. |
| Email sender matches multiple team members | First match wins (same email should not belong to multiple members). |

---

## Security & Privacy

- Only email **metadata** is cached (subject, sender, preview). Full email bodies are NOT stored.
- `body_preview` is limited to 500 characters of plain text — no HTML stored.
- `Mail.Read` is a delegated permission — only accesses the authenticated user's mailbox.
- No email content is ever exposed to other users (BelongsToUser scope).
- `web_link` opens the email in Outlook Web — Mithril never renders full email content.

---

## Out of Scope (Potential Future Enhancements)

- **Email-to-task rules** — auto-create tasks from emails matching patterns (e.g., subject contains "ACTION:")
- **Reply from Mithril** — send a reply from within the dashboard
- **Email folders/labels** — browse specific Outlook folders
- **Shared mailbox support** — access team shared mailboxes
- **Full email body view** — render HTML email content within Mithril
- **Mark as read/unflag in Outlook** — write back to Graph API when task is created
