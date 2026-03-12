# Tasks from E-mails — Implementation Plan

**Created:** 2026-03-12
**Status:** Approved
**Author:** Bas de Kort

## Summary

Add a dedicated **Mail page** to Mithril that surfaces flagged, categorized, or unread Microsoft Outlook emails as actionable items. Users can view a curated email inbox, convert any email into a task, follow-up, note, or bila with a single click, pre-filling fields from the email and linking back to the original. A **dashboard widget** shows flagged emails with deadlines for quick triage. This extends the existing Microsoft Graph integration with the `Mail.Read` permission scope.

---

## Design Decisions

### Dedicated Mail Page

Mail gets its own full page at `/mail`, placed in the sidebar directly below Calendar (both require Microsoft connection). The page provides the complete email management experience — filtering, searching, bulk actions, and resource creation. This mirrors how Tasks, Follow-ups, and Notes each have dedicated pages rather than living only on the dashboard.

### Dashboard Widget (Same Pattern as Calendar/Analytics Widgets)

The dashboard shows a **compact email widget** following the same pattern as existing dashboard sections (calendar upcoming, today's tasks, etc.). The widget displays **all flagged emails**, with those that have a deadline sorted first (most urgent at top) and visually highlighted. Flagged emails without a due date appear below. Each email in the widget has action buttons to create:

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
E-mail            (Microsoft connection required)
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

When no Microsoft connection is active, Calendar and E-mail are hidden and the two separators around them collapse into one — no double separator is shown.

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
├── sources             JSON, NOT NULL  — Array of matched sources: ['flagged', 'categorized', 'unread']
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
├── email_id            BIGINT UNSIGNED, FK → emails.id, ON DELETE SET NULL, NULL
├── email_subject       VARCHAR(500), NOT NULL  — Denormalized for display when email is pruned
├── linkable_type       VARCHAR(255), NOT NULL  — Morph type (App\Models\Task, etc.)
├── linkable_id         BIGINT UNSIGNED, NOT NULL
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
└── INDEX (linkable_type, linkable_id)
└── UNIQUE (email_id, linkable_type, linkable_id)
```

**Why `ON DELETE SET NULL`:** When an email is pruned or removed by sync, the link record survives with `email_id = NULL`. The `email_subject` field preserves provenance so the resource can still show "Created from email: [subject]". Orphaned links (where the linked *resource* is deleted) are cleaned up by the pruning service.

**Note:** This differs from `calendar_event_links`, which uses `ON DELETE CASCADE`. The SET NULL approach is an improvement — CalendarEventLinks should be migrated to SET NULL separately (out of scope for this plan).

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
    public function normalizeMessage(array $graphMessage): array
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
        // 5. Remove stale cached emails:
        //    - Email no longer returned by Graph API filter
        //    - AND email is NOT dismissed (dismissed emails are managed by the user, not sync)
        //    - Links survive via SET NULL FK regardless
        // 6. Update synced_at on all upserted records
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
     * Includes `sender_is_team_member` boolean per email (for Bila button state).
     */
    public function index(Request $request): JsonResponse

    /**
     * GET /api/emails/dashboard
     * Returns all flagged emails for the dashboard widget.
     * Ordered by flag_due_date ASC NULLS LAST (deadline emails first, then rest).
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
     * POST /api/emails/{email}/undismiss
     * Restores a dismissed email back to the active list.
     */
    public function undismiss(Email $email): JsonResponse

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
| **Bila** | `team_member_id` ← resolved from sender (required). If an upcoming Bila already exists for this team member, add a prep item with `content` ← email `subject`. If no upcoming Bila exists, create a new one with the prep item attached. Only available when sender resolves to a team member. |

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
// routes/web.php (inside the existing auth group)
Route::get('/mail', [EmailPageController::class, 'index'])->name('mail.index');
```

Same pattern as `/calendar` — no extra middleware. The controller handles the no-connection case gracefully (shows a "Connect Office 365" prompt instead of 403).

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

        Route::post('undismiss', [EmailActionController::class, 'undismiss'])
            ->name('api.emails.undismiss');

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
E-mail            ← conditional: Microsoft connection (NEW)
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

Implement separators by adding a `'separator' => true` item type in the menu items array returned by `getMainNavItems()`. The Blade sidebar partial renders a visual divider (horizontal line with a leaf in the middle, matching the `elvish-divider-leaf` pattern used elsewhere in the app). Separators are purely visual — not collapsible. `getMenuGroups()` filters out separators that end up adjacent (e.g. when no Microsoft connection hides Calendar and E-mail) — never render consecutive separators or a separator as the first/last item.

---

## Frontend

### Mail Page (`/mail`)

Full-page view at `resources/views/pages/mail/index.blade.php`:

```
E-mail Page
├── Header: "E-mail" + sync status ("Last synced: 2 min ago") + manual refresh button
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

**Note:** Graph API sync fetches max 50 emails. No client-side pagination in v1 — the 50-item cap acts as a natural limit. If this proves insufficient, pagination can be added later.

### Dashboard Widget: Flagged Emails with Deadlines

Compact widget on the dashboard, following the same card-style pattern as the calendar upcoming and today's tasks sections:

```
<x-tl.email-flagged-widget :emails="$flaggedEmails" />

Dashboard Email Widget
├── Header: "Flagged E-mail" (with mail icon)
├── Email list (compact, max 5 items, deadline emails first):
│   ├── Email item (compact row):
│   │   ├── Due date badge (if set — color: overdue=red, today=amber, upcoming=default)
│   │   ├── Subject (truncated)
│   │   ├── Sender name
│   │   ├── Action buttons: [Task] [Follow-up] [Note] [Bila*]
│   │   │   └── *Bila greyed out unless sender matches a team member
│   │   └── Open in Outlook link
│   └── ...
├── "View all" link → /mail
└── Empty state: "No flagged emails"
```

**Data source:** Flagged emails, ordered by `flag_due_date ASC NULLS LAST` (emails with deadlines first, most urgent at top, then flagged without deadline). The `DashboardController` passes these as a Blade prop (server-side), consistent with how calendar upcoming and today's tasks work. The `GET /api/emails/dashboard` endpoint exists for the Alpine component to refresh without full page reload.

### Settings: Email Sources

On the settings page, under the Microsoft section. All toggles and inputs auto-save via the existing `PATCH /settings` endpoint (same mechanism as `dashboard_upcoming_tasks` and other user preferences):

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

### Alpine Component: `emailFlaggedWidget`

```typescript
interface EmailFlaggedWidgetData {
    emails: Email[];
    loading: boolean;

    createResource(emailId: number, type: string): Promise<void>;
    refresh(): Promise<void>;
    canCreateBila(email: Email): boolean;
    hasDueDate(email: Email): boolean;
    isDueOverdue(email: Email): boolean;
    isDueToday(email: Email): boolean;
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
    sources: EmailSource[];
    is_dismissed: boolean;
    sender_is_team_member: boolean;  // Resolved server-side, included in all endpoints
    links?: EmailLink[];
    synced_at: string;
}

interface EmailLink {
    id: number;
    email_id: number | null;
    email_subject: string;
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

### Phase 0: Fix orphaned resource links (backend agent) — Bug Fix

**Problem:** When a Task, FollowUp, Note, or Bila is deleted, any `CalendarEventLink` (and future `EmailLink`) records pointing to it become orphans. The frontend then renders broken 404 links on the calendar event. Currently only `DataPruningService` cleans these up reactively — but users see stale links until pruning runs.

**Solution:** Create a `HasResourceLinks` trait that hooks into the Eloquent `deleting` event to clean up all polymorphic link records (`CalendarEventLink` and `EmailLink`) before the resource is deleted. Apply the trait to Task, FollowUp, Note, and Bila models.

**Files:**
- `app/Models/Traits/HasResourceLinks.php` (new)
- `app/Models/Task.php` (update: use HasResourceLinks)
- `app/Models/FollowUp.php` (update: use HasResourceLinks)
- `app/Models/Note.php` (update: use HasResourceLinks)
- `app/Models/Bila.php` (update: use HasResourceLinks)

**Tests (TDD — write first):**
- Deleting a Task removes its CalendarEventLinks
- Deleting a FollowUp removes its CalendarEventLinks
- Deleting a Note removes its CalendarEventLinks
- Deleting a Bila removes its CalendarEventLinks
- Deleting a resource with no links does not error
- Deleting a resource with EmailLinks removes them (after Phase 1)

**Depends on:** nothing

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
- Deleting email sets `email_id = NULL` on links (SET NULL, not cascade)
- EmailLink `email_subject` is denormalized on creation for orphan display
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
- `syncEmails()`: does not remove dismissed emails (regardless of whether they have links)
- `syncEmails()`: removes non-dismissed emails that are no longer returned by Graph API
- `syncEmails()`: links survive via SET NULL when stale emails are removed
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
- `dashboard`: returns all flagged emails
- `dashboard`: includes `sender_is_team_member` boolean per email
- `dashboard`: orders by `flag_due_date ASC NULLS LAST`
- `prefill`: returns correct pre-fill data per type
- `prefill`: bila type returns 422 for non-team-member sender
- `create`: creates resource + link, returns standardized response
- `create`: bila type fails for non-team-member sender
- `dismiss`: sets `is_dismissed = true`
- `undismiss`: sets `is_dismissed = false`
- `unlink`: removes link without deleting resource
- Unauthorized access returns 403

**Depends on:** Phase 4

### Phase 6: Mail Page + Menu Update (frontend agent)

**Files:**
- `app/Http/Controllers/Web/EmailPageController.php` (new)
- `resources/views/pages/mail/index.blade.php` (new)
- `routes/web.php` (update: add `/mail` route)
- `app/Helpers/MenuHelper.php` (update: new menu order + E-mail item + separators)

**Tests (TDD — write first):**
- Mail page returns 200 for authenticated user with Microsoft connection
- Mail page returns 200 for user without Microsoft connection (shows "Connect Office 365" prompt)
- MenuHelper produces correct menu order with separators
- MenuHelper collapses separators when no Microsoft connection (no double dashes)
- E-mail menu item only visible with Microsoft connection

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
- `resources/views/components/tl/email-flagged-widget.blade.php` (new)
- `resources/js/components/email-flagged-widget.ts` (new)
- `resources/js/app.ts` (update: register component)
- Dashboard Blade (update: include email flagged widget)
- Dashboard controller (update: pass flagged emails to view)

**Depends on:** Phase 5, Phase 8

### Phase 10: Data Pruning Extension (backend agent)

**Files:**
- `app/Services/DataPruningService.php` (update: add email + calendar pruning)
- `app/DataTransferObjects/PruneResult.php` (update: add `emailsDeleted`, `calendarEventsDeleted` counters)

**Tests (TDD — write first):**
- Dismissed emails older than retention are pruned
- Stale emails (`synced_at` > 30 days) are pruned
- Pruning email sets `email_id = NULL` on links (not deleted)
- Created resources (tasks, follow-ups, notes, bilas) survive when their source email is pruned
- EmailLink retains `email_subject` after email is pruned
- Orphaned EmailLinks (`email_id IS NULL` AND resource deleted) are cleaned up
- Old calendar events (past retention) are pruned
- Created resources survive when their source calendar event is pruned
- PruneResult includes new counters
- Settings page shows email/calendar prune counts in dry-run

**Depends on:** Phase 1

---

## Agent Ownership

| Phase | Agent | Owns |
|-------|-------|------|
| 0 | backend | HasResourceLinks trait, model updates (bug fix) |
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
| **Dismissed emails** | `is_dismissed = true` AND `updated_at` older than retention period | No longer actionable. Links survive via SET NULL FK — safe to delete the email record. |
| **Stale emails no longer in inbox** | `synced_at` older than 30 days (safety net for emails sync missed) | Covers edge cases where sync misses cleanup (e.g., source toggle changes between syncs). Links survive via SET NULL FK. |
| **Orphaned EmailLinks** | `EmailLink` where `email_id IS NULL` AND the linked resource no longer exists | Both the source email and the created resource are gone — the link serves no purpose. |
| **Old calendar events** | `CalendarEvent` where `start_at` is older than retention period | Currently these accumulate forever. Links survive via their existing SET NULL / orphan cleanup. |
| **Orphaned CalendarEventLinks** | Already handled — no change needed. | Existing behavior in `DataPruningService`. Now a safety net — Phase 0's `HasResourceLinks` trait handles the primary cleanup on resource deletion. |

### Important: Resources Always Survive

When an `Email` or `CalendarEvent` is pruned, any tasks, follow-ups, notes, or bilas created from it remain untouched. The `email_links` FK uses `ON DELETE SET NULL`, so the link record survives with `email_id = NULL` and the denormalized `email_subject` preserves provenance. Only truly orphaned links (where both the source AND the resource are gone) are cleaned up.

### Updated `DataPruningService`

```php
class DataPruningService
{
    public function pruneForUser(User $user): PruneResult
    {
        // Existing: done tasks + done follow-ups beyond retention
        // Existing: orphaned CalendarEventLinks

        // NEW: dismissed emails beyond retention (links survive via SET NULL)
        // NEW: stale emails (synced_at > 30 days ago, safety net)
        // NEW: orphaned EmailLinks (email_id IS NULL AND resource deleted)
        // NEW: old calendar events (start_at beyond retention)
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
| Same email matched by multiple sources (flagged AND unread) | `sources` JSON array stores all matched sources. Unique constraint on `microsoft_message_id` prevents duplicate records. Email appears under all matching source tabs on the mail page. |
| High volume inbox (100+ unread) | Sync fetches max 50 most recent. Configurable via `config/microsoft.php`. |
| Email dismissed but then flagged again in Outlook | Re-sync does not un-dismiss. User must manually un-dismiss in Mithril (or the dismiss resets on next flag change — discuss during implementation). |
| Non-ASCII subjects | Graph API returns UTF-8. Laravel handles this natively. |
| Flagged email without due date | Shown on both mail page and dashboard widget. On dashboard, sorted after emails with deadlines. No due date badge displayed. |
| Bila action for non-team-member sender | Button greyed out with tooltip "Sender is not a team member". API returns 422 if called directly. |
| Email sender matches multiple team members | First match wins (same email should not belong to multiple members). |
| Linked resource (task/follow-up/note/bila) deleted | `HasResourceLinks` trait cleans up all CalendarEventLinks and EmailLinks on `deleting` event. No orphaned links remain. (Bug fix — Phase 0) |

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
