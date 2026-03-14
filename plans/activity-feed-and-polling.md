# Activity Feed & Polling System

**Created:** 2026-03-14
**Status:** In Progress
**Author:** Bas de Kort

## Problem Statement

Resources (Tasks, Follow-ups, Notes, Bilas) lack a way to capture comments, links, file attachments, and system events. Users cannot add context to their work items beyond the structured fields. Additionally, the UI has no mechanism to refresh stale data without a full page reload — the existing `data-changed` event only works for components on the same page that triggered the mutation.

## Acceptance Criteria

1. Every resource detail page (Task, FollowUp, Note, Bila) displays a chronological activity feed
2. Users can add markdown comments to any resource
3. Users can attach links (URL + optional title) to any resource
4. Users can upload files (max 10 MB each, max 5 per activity) with drag & drop support
5. Files are stored privately (not publicly accessible) and served via signed download URLs
6. System events (status changes, priority changes, completion) appear automatically in the feed
7. Activities can be edited (body only) and deleted by the owner
8. A generic `refreshable` Alpine component enables any section to poll for updates via ETag-based partial endpoints
9. Dashboard sections (counters, calendar, flagged emails) lazy-load with skeleton placeholders and poll for background updates
10. The `refreshable` component pauses polling when the browser tab is inactive
11. Activity feed refreshes instantly after user actions (via `data-changed` event) and polls for background changes (e.g. sync-originated events)
12. Orphaned attachments are cleaned up via a scheduled artisan command

## Technical Design

### Approach

Two interlocking systems built on existing patterns:

```
┌─────────────────────────────────────────────────────────────┐
│  ACTIVITY FEED (per resource)                               │
│                                                             │
│  ┌───────────┐  ┌──────────┐  ┌────────┐  ┌─────────────┐ │
│  │ Comments   │  │ Files    │  │ Links  │  │ System      │ │
│  │ (markdown) │  │ (upload) │  │ (URL)  │  │ events      │ │
│  └─────┬──────┘  └────┬─────┘  └───┬────┘  └──────┬──────┘ │
│        └──────────────┴────────────┴───────────────┘        │
│                        ▼                                    │
│             activities (polymorphic table)                   │
│             + attachments (file storage)                     │
│                        ▼                                    │
│             Blade partial per resource                       │
│             Refreshable via polling + data-changed           │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  POLLING + DIRECT DISPATCH                                  │
│                                                             │
│  Trigger 1: User action → apiClient dispatches              │
│             `data-changed` → instant refresh                │
│                                                             │
│  Trigger 2: Polling (15-60s) → fetch with ETag              │
│             → 304 if unchanged, swap HTML if new            │
│                                                             │
│  Layer: PartialController (Blade fragments)                 │
│         Same endpoints for lazy load + polling + refresh    │
└─────────────────────────────────────────────────────────────┘
```

### Key Design Decisions

1. **Extend `data-changed`, don't replace it.** ADR-007 established `data-changed` as the central mutation event. The polling plan's `resource-updated` event is not needed — we add an optional `detail` payload to `data-changed` for topic-based filtering, which is already suggested as a follow-up in ADR-007.

2. **`BelongsToUser` on Activity, not manual scoping.** The original plan uses manual `where('user_id', auth()->id())` checks. Our codebase uses the `BelongsToUser` trait with a global scope — Activity will use this too.

3. **`string` columns, not `enum()`.** MariaDB compatibility requires `$table->string()` with PHP enum validation via `Rule::enum()`, matching all existing models.

4. **API routes for activity CRUD, web routes for partials.** Activity store/update/destroy go through `/api/v1/` (matching existing CRUD pattern). Partial HTML endpoints go through web routes (new `PartialController`).

5. **`apiClient` for JSON, raw `fetch` for file uploads.** The existing `apiClient` sets `Content-Type: application/json` which breaks `FormData`. File uploads use raw `fetch` with CSRF token from the same meta tag.

6. **No `HasSortOrder` on Activity.** Activities are always chronological — no user-reorderable sort.

7. **Activity does not use `AbstractResourceController`.** The polymorphic parent resolution (task/follow-up/note/bila) requires custom routing that doesn't fit the abstract CRUD pattern. A dedicated `ActivityController` in `Api/` namespace follows the `AutoSaveController` model-map pattern instead.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `database/migrations/xxxx_create_activities_table.php` | Create | Activities + attachments tables |
| `app/Enums/ActivityType.php` | Create | String-backed enum: comment, attachment, link, system |
| `app/Models/Activity.php` | Create | Polymorphic model with BelongsToUser |
| `app/Models/Attachment.php` | Create | File metadata model, auto-deletes physical files |
| `app/Models/Traits/HasActivityFeed.php` | Create | Trait for Task, FollowUp, Note, Bila |
| `app/Models/Task.php` | Modify | Add `HasActivityFeed` trait |
| `app/Models/FollowUp.php` | Modify | Add `HasActivityFeed` trait |
| `app/Models/Note.php` | Modify | Add `HasActivityFeed` trait |
| `app/Models/Bila.php` | Modify | Add `HasActivityFeed` trait |
| `app/Http/Controllers/Api/ActivityController.php` | Create | Generic CRUD with model-map pattern |
| `app/Http/Controllers/Api/AttachmentController.php` | Create | Signed download endpoint |
| `app/Http/Controllers/Web/PartialController.php` | Create | ETag-based HTML fragment endpoints |
| `app/Http/Requests/ActivityRequest.php` | Create | Validation for comment/link/file activities |
| `app/Observers/ActivityObserver.php` | Create | Dispatches system events on tracked model changes |
| `routes/api.php` | Modify | Add activity CRUD routes |
| `routes/web.php` | Modify | Add partial endpoints + attachment download |
| `app/Console/Commands/CleanOrphanedAttachments.php` | Create | Weekly cleanup command |
| `resources/js/components/refreshable.ts` | Create | Generic polling + lazy-load Alpine component |
| `resources/js/components/activity-input.ts` | Create | Comment/link/file input Alpine component |
| `resources/js/types/models.ts` | Modify | Add Activity, Attachment, ActivityType types |
| `resources/js/app.ts` | Modify | Register new Alpine components |
| `resources/js/utils/api-client.ts` | Modify | Add topic detail to `data-changed` event |
| `resources/views/components/tl/activity-feed.blade.php` | Create | Reusable feed component |
| `resources/views/components/tl/activity-item.blade.php` | Create | Per-type activity rendering |
| `resources/views/partials/activity-feed.blade.php` | Create | Pollable partial for feed content |
| `resources/views/partials/dashboard/counters.blade.php` | Create | Extracted from dashboard for lazy load |
| `resources/views/partials/skeletons/*.blade.php` | Create | Skeleton loading placeholders |
| `resources/views/pages/tasks/show.blade.php` | Modify | Add activity feed side panel |
| `resources/views/pages/follow-ups/show.blade.php` | Modify | Add activity feed side panel |
| `resources/views/pages/notes/show.blade.php` | Modify | Add activity feed side panel |
| `resources/views/pages/bilas/show.blade.php` | Modify | Add activity feed side panel |
| `resources/views/pages/dashboard.blade.php` | Modify | Wrap sections in refreshable components |
| `config/attachments.php` | Create | Attachment config: max storage per user (reads `ATTACHMENT_MAX_STORAGE_MB` from `.env`) |
| `.env.example` | Modify | Add `ATTACHMENT_MAX_STORAGE_MB=1024` |
| `app/Http/Controllers/Web/DashboardController.php` | Modify | Extract section data to reusable methods |
| `resources/views/pages/tasks/index.blade.php` | Modify | Wrap list in refreshable component |
| `resources/views/pages/follow-ups/index.blade.php` | Modify | Wrap timeline in refreshable component |

### Data Model

```
activities
├── id (PK)
├── user_id (FK → users, cascadeOnDelete)
├── activityable_type (string, morph)
├── activityable_id (unsigned bigint, morph)
├── type (string → ActivityType enum)
├── body (text, nullable — markdown for comments, description for links)
├── metadata (text, nullable — JSON-encoded, for link URL/title, system changes)
├── created_at
└── updated_at

Index: (activityable_type, activityable_id, created_at)
Index: (user_id, type)

attachments
├── id (PK)
├── user_id (FK → users, cascadeOnDelete)
├── activity_id (FK → activities, cascadeOnDelete)
├── filename (string — original name)
├── path (string — storage path)
├── disk (string, default 'local')
├── mime_type (string)
├── size (unsigned bigint — bytes)
├── created_at
└── updated_at
```

**Why `metadata` as text (JSON-encoded) instead of a JSON column?** MariaDB's JSON support is less mature than MySQL's. We store as text, cast to array in the model. No JSON path queries needed — we always read the full metadata blob.

### Edge Cases & Error Handling

1. **File upload exceeds 10 MB** — Laravel validation rejects before storage; client-side check as well
2. **File upload with 6+ files** — Validation rejects; client limits to 5 in UI
3. **Deleting a resource with activities** — Activities cascade-delete; attachment files cleaned up via model `deleting` event
4. **Orphaned attachments** (edge case: activity deleted between file write and DB commit) — Weekly cleanup command
5. **Signed URL expired** — 403 response, user re-clicks download to get fresh URL
6. **Polling on inactive tab** — Polling pauses via `visibilitychange` listener, resumes + immediate refresh on tab focus
7. **Rapid successive mutations** — `data-changed` listeners debounce (existing 1000ms pattern)
8. **ETag match (304)** — No DOM swap, no bandwidth wasted
9. **Activity feed on a resource the user doesn't own** — `BelongsToUser` global scope returns 404 automatically
10. **Concurrent file upload + comment** — Each is a separate activity; both appear in feed after refresh

## Implementation Phases

### Phase 1: Database & Models

**Goal:** Activity and Attachment models with trait, fully tested.

**Specs:**
- [x] Migration creates `activities` table with morph columns, string type, text body, text metadata
- [x] Migration creates `attachments` table with FK to activities
- [x] Migration is compatible with both MariaDB and SQLite (no enum columns, no JSON columns)
- [x] `ActivityType` enum exists with values: `comment`, `attachment`, `link`, `system`
- [x] `Activity` model uses `BelongsToUser` trait, casts metadata to array, has morph relation
- [x] `Activity` model has scopes: `ofType()`, `chronological()`, `latestFirst()`
- [x] `Activity` model has helper methods: `isComment()`, `isLink()`, `isAttachment()`, `isSystem()`, `getUrl()`, `getLinkTitle()`
- [x] `Attachment` model has helpers: `isImage()`, `isPdf()`, `humanSize()`, `downloadUrl()`
- [x] `Attachment` model deletes physical file on model deletion (via `deleting` event)
- [x] `HasActivityFeed` trait provides `activities()` morph relation
- [x] `HasActivityFeed` trait provides `addComment()`, `addLink()`, `logSystemEvent()`, `getActivityFeed()` methods
- [x] Task, FollowUp, Note, Bila models use `HasActivityFeed` trait
- [x] Factory exists for Activity and Attachment models

**Files:** `database/migrations/`, `app/Enums/ActivityType.php`, `app/Models/Activity.php`, `app/Models/Attachment.php`, `app/Models/Traits/HasActivityFeed.php`, `app/Models/Task.php`, `app/Models/FollowUp.php`, `app/Models/Note.php`, `app/Models/Bila.php`, `database/factories/`

### Phase 2: API Controllers & Routes

**Goal:** Full CRUD for activities via API, file upload and signed download working.

**Specs:**
- [ ] `ActivityController` uses model-map pattern (like `AutoSaveController`) to resolve parent resource
- [ ] `POST /api/v1/{type}/{id}/activities` creates comment, link, or attachment activity based on request content
- [ ] `PATCH /api/v1/{type}/{id}/activities/{activity}` updates body only
- [ ] `DELETE /api/v1/{type}/{id}/activities/{activity}` deletes activity + cascades attachments
- [ ] Controller verifies activity belongs to the resolved parent resource
- [ ] `ActivityRequest` validates: body (max 10000), url (valid URL, max 2048), files (max 5, max 10MB each)
- [ ] `AttachmentController` serves file downloads via signed URL with 30-minute expiry
- [ ] Attachment download verifies ownership through activity → parent → user chain
- [ ] Files stored in `storage/app/private/attachments/{Y}/{m}/` on local disk
- [ ] Upload endpoint checks user's total attachment storage against `ATTACHMENT_MAX_STORAGE_MB` (default 1024, configurable in `.env`); rejects with 422 if quota exceeded
- [ ] All endpoints return standard `ApiResponse` format
- [ ] Routes registered with `whereIn` constraint for type parameter

**Files:** `app/Http/Controllers/Api/ActivityController.php`, `app/Http/Controllers/Api/AttachmentController.php`, `app/Http/Requests/ActivityRequest.php`, `config/attachments.php`, `.env.example`, `routes/api.php`, `routes/web.php`

### Phase 3: System Events

**Goal:** Status/priority/completion changes automatically log to the activity feed.

**Specs:**
- [ ] Observer listens to `updated` event on models with `HasActivityFeed`
- [ ] Tracks changes to: `status`, `priority`, `completed_at`, `snoozed_until`, `is_done`
- [ ] Creates system-type activity with human-readable description (e.g. "Status changed: open → done")
- [ ] System activities have metadata with `action` and `changes` keys
- [ ] System activities use the authenticated user (or null for background jobs)
- [ ] Does not create activity when no tracked fields changed

**Files:** `app/Observers/ActivityObserver.php`, `app/Providers/AppServiceProvider.php`

### Phase 4: Refreshable Component & PartialController

**Goal:** Generic polling infrastructure that any page section can use.

**Specs:**
- [ ] `refreshable` Alpine component accepts: `url`, `topics` (optional string array), `lazy` (boolean), `pollInterval` (ms, default 15000)
- [ ] On init: optionally fetches content (lazy mode), starts polling timer
- [ ] Listens to `data-changed` window event; if topics provided, only refreshes when event detail matches
- [ ] Fetches URL with `Accept: text/html` and `If-None-Match` ETag header
- [ ] On 304: no DOM update. On 200: swaps `[data-refresh-target]` innerHTML
- [ ] Pauses polling when `document.hidden` is true; resumes + immediate refresh on visibility
- [ ] Debounces rapid `data-changed` triggers (300ms)
- [ ] `PartialController` has private `withETag()` helper that renders view, computes ETag from HTML hash, returns 304 on match
- [ ] `PartialController::activityFeed()` returns rendered activity feed partial for a given resource
- [ ] `apiClient` `data-changed` event gains optional `detail.topic` field (backward compatible)
- [ ] Existing `liveCounter` and `analyticsChart` listeners continue to work unchanged

- [ ] Tasks list page (`/tasks`) wrapped in `refreshable` with topic `tasks`, poll 30s
- [ ] Follow-ups timeline page (`/follow-ups`) wrapped in `refreshable` with topic `follow_ups`, poll 30s

**Files:** `resources/js/components/refreshable.ts`, `resources/js/app.ts`, `resources/js/utils/api-client.ts`, `app/Http/Controllers/Web/PartialController.php`, `routes/web.php`, `resources/views/pages/tasks/index.blade.php`, `resources/views/pages/follow-ups/index.blade.php`

### Phase 5: Activity Feed UI

**Goal:** Activity feed component rendered on all four detail pages.

**Specs:**
- [ ] `<x-tl.activity-feed>` Blade component accepts `$parent`, `$parentType`, `$activities`
- [ ] Input area has three tabs: Comment (textarea + markdown hint), Link (URL + title inputs), File (drop zone)
- [ ] `activityInput` Alpine component handles submit for all three types
- [ ] File upload uses raw `fetch` with `FormData` (not `apiClient`) to support multipart
- [ ] Drop zone supports drag & drop with visual feedback
- [ ] File list shows selected files with remove button, enforces max 5 limit client-side
- [ ] After successful submit, dispatches `data-changed` to trigger feed refresh
- [ ] Feed items render differently per type: markdown for comments, clickable card for links, file cards with download for attachments, subtle italic for system events
- [ ] Each item shows user name, relative timestamp, and type indicator icon
- [ ] Non-system items show a delete button on hover (only for own items)
- [ ] Image attachments show inline preview thumbnail
- [ ] Detail pages gain a 2-column layout: main content (2/3) + activity feed sidebar (1/3)
- [ ] Activity feed is wrapped in `refreshable` component with 30s polling

**Files:** `resources/views/components/tl/activity-feed.blade.php`, `resources/views/components/tl/activity-item.blade.php`, `resources/views/partials/activity-feed.blade.php`, `resources/js/components/activity-input.ts`, `resources/js/app.ts`, `resources/js/types/models.ts`, `resources/views/pages/tasks/show.blade.php`, `resources/views/pages/follow-ups/show.blade.php`, `resources/views/pages/notes/show.blade.php`, `resources/views/pages/bilas/show.blade.php`

### Phase 6: Dashboard Polling & Lazy Loading

**Goal:** Dashboard sections lazy-load with skeletons and poll for updates.

**Specs:**
- [ ] `DashboardController` extracts section data into reusable methods (counters, calendar, flagged emails)
- [ ] `PartialController` has endpoints for each dashboard section
- [ ] Skeleton Blade partials created for counters, calendar, and email sections
- [ ] Dashboard wraps each section in `refreshable` with `lazy: true`
- [ ] Counters poll every 15s, calendar every 60s, emails every 30s
- [ ] Sections show a subtle loading indicator on refresh (not on initial lazy load)
- [ ] Stale-while-revalidate: current content stays visible during refresh with translucent overlay
- [ ] Existing `liveCounter` components on dashboard still work (backward compatible)

**Files:** `app/Http/Controllers/Web/DashboardController.php`, `app/Http/Controllers/Web/PartialController.php`, `resources/views/partials/dashboard/*.blade.php`, `resources/views/partials/skeletons/*.blade.php`, `resources/views/pages/dashboard.blade.php`, `routes/web.php`

### Phase 7: Cleanup & Polish

**Goal:** Maintenance tooling and edge case handling.

**Specs:**
- [ ] `CleanOrphanedAttachments` artisan command finds and deletes attachments without an activity
- [ ] Command also deletes physical files on the configured disk
- [ ] Command is scheduled weekly in `routes/console.php` (or `Kernel`)
- [ ] Attachment model added to `AutoSaveController` model map (if body editing needed)
- [ ] Activity model factory supports all four types for seeding
- [ ] `DatabaseSeeder` creates sample activities for demo data

**Files:** `app/Console/Commands/CleanOrphanedAttachments.php`, `routes/console.php`, `database/seeders/`

## Out of Scope

- **WebSocket/SSE real-time updates** — polling is sufficient for a single-user dashboard
- **Activity feed search/filtering** — can be added later via `Searchable`/`Filterable` traits
- **Inline editing of comments** — v1 supports edit via PATCH but no inline UI; can add later
- **Activity notifications** (email/push) — no notification system exists yet
- **Activity feed on list/index pages** — only on detail (show) pages
- **Mentions (@user)** — single-user app, not needed
- **Activity pagination** — initial limit of 50 items; pagination can be added if feeds grow large
- **Thumbnail generation for images** — serve originals via signed URL; thumbnails are a future optimization

## Resolved Questions

1. **Should the `refreshable` component also be used for the tasks list page and follow-ups timeline?** Yes — include in Phase 4 scope. Wire up tasks list and follow-ups timeline to use `refreshable` for filter changes and background sync updates.
2. **Should system events track field changes beyond status/priority?** No for now. Keep it to status, priority, completion, and snooze. May expand in the future.
3. **Maximum attachment storage per user?** Yes — 1 GB default, configurable via `ATTACHMENT_MAX_STORAGE_MB` in `.env`. Upload endpoint must check current usage before accepting new files. Admin can adjust the limit without code changes.

## Parallelization

**Strategy:** Sequential

All phases have significant inter-dependencies (models → controllers → observers → UI → dashboard). Execute sequentially with the lead.
