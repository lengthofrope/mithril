# Tasks from E-mails — Implementation Plan

## Summary

Surface flagged, categorized, or unread Microsoft Outlook emails as a task source on the dashboard. Users can view a curated email inbox within Mithril and convert any email into a task (or follow-up/note) with a single click, pre-filling the task title from the email subject and linking back to the original email. This extends the existing Microsoft Graph integration with the `Mail.Read` permission scope.

---

## Design Decisions

### Read-Only Email Access

Mithril only **reads** emails — it never sends, moves, archives, or modifies flags. The only write-back is marking an email as "read" (optional, user preference). This keeps the permission scope minimal and avoids accidental side-effects.

### Cached Email Model (Same Pattern as Calendar Events)

Emails are synced to a local `emails` cache table, following the same pattern as `calendar_events`. This avoids slow API calls on page load and enables filtering/searching locally. The sync job runs on a schedule (configurable, default every 15 minutes).

### Three Email Sources

Users configure which emails to surface:

| Source | Description | Graph API Filter |
|--------|-------------|------------------|
| **Flagged** | Emails the user has flagged in Outlook | `flag/flagStatus eq 'flagged'` |
| **Categorized** | Emails with a specific Outlook category (e.g., "Mithril") | `categories/any(c:c eq 'Mithril')` |
| **Unread** | Unread emails in the inbox | `isRead eq false` |

Users toggle which sources are active in Settings. At least one must be active for the email panel to appear on the dashboard.

### Email → Task Conversion (Same Pattern as Calendar Actions)

Converting an email to a task follows the same architecture as "Create from Calendar Event" — pre-fill fields, create via API, link back via a polymorphic pivot. The `EmailLink` model mirrors `CalendarEventLink`.

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
        // 4. Upsert into emails table (on microsoft_message_id)
        // 5. Remove cached emails that no longer match the filter (except dismissed ones with links)
        // 6. Update synced_at
    }
}
```

### Controller: `EmailActionController`

```php
class EmailActionController extends Controller
{
    /**
     * GET /api/emails
     * Returns the user's cached emails, filterable by source.
     */
    public function index(Request $request): JsonResponse

    /**
     * GET /api/emails/{email}/prefill/{type}
     * Returns pre-fill data for creating a resource from an email.
     */
    public function prefill(Email $email, string $type): JsonResponse

    /**
     * POST /api/emails/{email}/create/{type}
     * Creates the resource, links it to the email, returns the resource.
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
| **Follow-up** | `description` ← email `subject`, `team_member_id` ← resolved from sender, `follow_up_date` ← today + 3 days |
| **Note** | `title` ← email `subject`, `content` ← email `body_preview`, `team_member_id` ← resolved from sender |

### MicrosoftGraphService Extension

```php
// Add to MicrosoftGraphService:

/**
 * Fetch filtered messages from the user's inbox.
 */
public function getMyMessages(
    User $user,
    string $filter,
    int $top = 50,
): Collection
```

### Scheduler

```php
Schedule::command('microsoft:sync-emails')->everyFifteenMinutes();
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

## API Routes

```php
// routes/api.php
Route::prefix('emails')->group(function () {
    Route::get('/', [EmailActionController::class, 'index'])
        ->name('api.emails.index');

    Route::prefix('{email}')->group(function () {
        Route::get('prefill/{type}', [EmailActionController::class, 'prefill'])
            ->name('api.emails.prefill')
            ->whereIn('type', ['task', 'follow-up', 'note']);

        Route::post('create/{type}', [EmailActionController::class, 'create'])
            ->name('api.emails.create')
            ->whereIn('type', ['task', 'follow-up', 'note']);

        Route::post('dismiss', [EmailActionController::class, 'dismiss'])
            ->name('api.emails.dismiss');

        Route::delete('links/{emailLink}', [EmailActionController::class, 'unlink'])
            ->name('api.emails.unlink');
    });
});
```

---

## Frontend

### Dashboard: Email Panel

New Blade component `resources/views/components/tl/email-panel.blade.php`:

```
<x-tl.email-panel :emails="$emails" />

├── Tab bar: [Flagged] [Categorized] [Unread] (based on active sources)
├── Email list (scrollable, max height ~300px)
│   ├── Email item:
│   │   ├── Sender name + time (relative: "2h ago")
│   │   ├── Subject line (bold if unread, normal if read)
│   │   ├── Body preview (truncated, 1 line)
│   │   ├── Importance indicator (⬆ high, none for normal, ⬇ low)
│   │   ├── 📎 attachment indicator
│   │   ├── Action button: [+] Create...
│   │   │   ├── Task
│   │   │   ├── Follow-up
│   │   │   └── Note
│   │   ├── Dismiss button (X) — hides from panel
│   │   └── Open in Outlook link (→ web_link)
│   └── Linked resource badges (if any): [T] [F] [N]
├── Empty state: "No actionable emails" or "Connect Office 365 to see emails"
└── Footer: "Last synced: X min ago" + manual refresh button
```

### Settings: Email Sources

On the settings page, under the Microsoft section:

```
Email Integration
├── Sources:
│   ├── [toggle] Flagged emails
│   ├── [toggle] Categorized emails
│   │   └── Category name: [input: "Mithril"] (auto-save)
│   └── [toggle] Unread emails (inbox only)
└── Note: "Emails sync every 15 minutes. Only subjects and previews are cached."
```

### Alpine Component: `emailPanel`

```typescript
interface EmailPanelData {
    emails: Email[];
    activeSource: EmailSource | 'all';
    loading: boolean;

    filterBySource(source: string): void;
    createResource(emailId: number, type: string): Promise<void>;
    dismiss(emailId: number): Promise<void>;
    unlinkResource(emailId: number, linkId: number): Promise<void>;
    refresh(): Promise<void>;
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
    categories: string[] | null;
    importance: EmailImportance;
    has_attachments: boolean;
    web_link: string | null;
    source: EmailSource;
    is_dismissed: boolean;
    links?: EmailLink[];
    synced_at: string;
}

interface EmailLink {
    id: number;
    email_id: number;
    linkable_type: string;
    linkable_id: number;
    linkable?: Task | FollowUp | Note;
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
- EmailLink model: polymorphic relationships to Task, FollowUp, Note
- Unique constraint on `(user_id, microsoft_message_id)` prevents duplicates
- Unique constraint on `(email_id, linkable_type, linkable_id)` prevents duplicate links
- Cascade delete: deleting email removes its links
- User email preference fields default correctly

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
- `buildPrefillData()`: correct pre-fill per resource type
- `linkResource()`: creates link, prevents duplicates

**Depends on:** Phase 1

### Phase 5: API Endpoints (backend agent)

**Files:**
- `app/Http/Controllers/Api/EmailActionController.php`
- `routes/api.php` (update: add email routes)

**Tests (TDD — write first):**
- `index`: returns user's emails, filtered by source
- `index`: respects BelongsToUser scope
- `prefill`: returns correct pre-fill data per type
- `create`: creates resource + link, returns standardized response
- `dismiss`: sets `is_dismissed = true`
- `unlink`: removes link without deleting resource
- Unauthorized access returns 403

**Depends on:** Phase 4

### Phase 6: Frontend — Settings (frontend agent)

**Files:**
- Settings Blade template (update: add email source toggles)
- Auto-save for email preferences on User model

**Depends on:** Phase 1

### Phase 7: Frontend — Email Panel (frontend + typescript agent)

**Files:**
- `resources/views/components/tl/email-panel.blade.php` (new)
- `resources/js/components/emailPanel.ts` (new)
- `resources/js/app.ts` (update: register component)
- `resources/js/types/models.ts` (update: add Email types)
- Dashboard Blade (update: include email panel)
- Dashboard controller (update: pass emails to view)

**Depends on:** Phase 5, Phase 6

---

## Agent Ownership

| Phase | Agent | Owns |
|-------|-------|------|
| 1 | backend | Migrations, models, enums, factories |
| 2 | backend | Graph service, config |
| 3 | backend | Sync service, job, command |
| 4 | backend | EmailActionService |
| 5 | backend | Controller, routes |
| 6 | frontend | Settings UI |
| 7 | frontend + typescript | Email panel component, Alpine component, types |

**Shared files:**
- `routes/api.php` — Phase 5 adds routes
- `resources/js/app.ts` — Phase 7 registers Alpine component

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| User has no active email sources | Email panel hidden from dashboard. Sync job skips this user. |
| Microsoft token lacks `Mail.Read` scope | Sync skips emails, shows "Re-authorize" prompt in email panel area. |
| Email deleted in Outlook between syncs | Next sync removes it from cache. Links remain but `Email` no longer exists — frontend shows "Email removed". |
| Same email matched by multiple sources (flagged AND unread) | First match wins. `source` field stores the primary source. Unique constraint on `microsoft_message_id` prevents duplicates. |
| High volume inbox (100+ unread) | Sync fetches max 50 most recent. Configurable via `config/microsoft.php`. |
| Email dismissed but then flagged again in Outlook | Re-sync does not un-dismiss. User must manually un-dismiss in Mithril (or the dismiss resets on next flag change — discuss during implementation). |
| Non-ASCII subjects | Graph API returns UTF-8. Laravel handles this natively. |

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
