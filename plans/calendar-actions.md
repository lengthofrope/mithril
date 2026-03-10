# Calendar Actions — Create Resources from Calendar Events

## Summary

Enable users to create Bilas, Tasks, Follow-ups, and Notes directly from synced calendar events. Calendar events display a context menu with "Create..." actions that pre-fill resource fields from event data (date, subject, attendees). Created resources are linked back to the calendar event via a polymorphic pivot table, allowing multiple resources per event. The calendar UI shows visual indicators for events that have linked resources, and clicking navigates to the linked resource.

---

## Design Decisions

### Link Strategy: Polymorphic Pivot Table

A `calendar_event_links` pivot table connects one calendar event to many resources of different types. This is preferred over a single polymorphic FK on `calendar_events` because one meeting can legitimately produce a bila, a follow-up, AND a note.

### Attendee Storage

A JSON `attendees` column on `calendar_events` stores an array of `{email, name}` objects from Microsoft Graph. This avoids a separate table for what is essentially denormalized cache data that gets overwritten every sync cycle.

### Attendee Matching Rules

| Scenario | Behavior |
|----------|----------|
| Exactly 1 team member found among attendees (excluding the logged-in user) | Auto-assign `team_member_id` |
| 0 team members found | Leave `team_member_id` null — user picks manually |
| 2+ team members found | Leave `team_member_id` null — user picks manually |

Matching is done by comparing attendee emails against both `TeamMember.microsoft_email` and `TeamMember.email` (case-insensitive).

### Past Events

Fully supported. A meeting at 10:00 can produce a task at 16:00. No time-based restrictions.

### `next_bila_date` Update

When a Bila is created from a calendar event, the existing `BilaScheduled` event is dispatched, which triggers `ScheduleNextBila` to recalculate `next_bila_date`. No new logic needed.

---

## Data Model

### New Table: `calendar_event_links`

```
calendar_event_links
├── id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
├── calendar_event_id   BIGINT UNSIGNED, FK → calendar_events.id, ON DELETE CASCADE
├── linkable_type       VARCHAR(255), NOT NULL  — Morph type (App\Models\Bila, etc.)
├── linkable_id         BIGINT UNSIGNED, NOT NULL
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
└── INDEX (linkable_type, linkable_id)
└── UNIQUE (calendar_event_id, linkable_type, linkable_id)
```

### Modified Table: `calendar_events`

```
calendar_events (add column)
├── attendees           JSON, NULL  — [{email: string, name: string}, ...]
```

---

## Pre-fill Mapping

When creating a resource from a calendar event, fields are pre-filled as follows:

| Target Resource | Pre-filled Fields |
|----------------|-------------------|
| **Bila** | `scheduled_date` ← event `start_at` (date only), `team_member_id` ← auto-matched attendee |
| **Task** | `title` ← event `subject`, `deadline` ← event `start_at` (date only), `team_member_id` ← auto-matched attendee |
| **Follow-up** | `description` ← event `subject`, `follow_up_date` ← event `start_at` (date only), `team_member_id` ← auto-matched attendee |
| **Note** | `title` ← event `subject`, `team_member_id` ← auto-matched attendee |

All pre-filled values are **suggestions** — the user can modify them before or after creation.

---

## Backend Architecture

### Service: `CalendarActionService`

Central service that handles matching and linking logic. Keeps controllers thin.

```php
class CalendarActionService
{
    /**
     * Resolve which team member (if any) should be auto-assigned from the
     * calendar event's attendees. Returns null if 0 or 2+ members match.
     */
    public function resolveTeamMember(CalendarEvent $event): ?TeamMember

    /**
     * Create a link between a calendar event and a resource.
     */
    public function linkResource(CalendarEvent $event, Model $resource): CalendarEventLink

    /**
     * Get all linked resources for a calendar event, eager-loaded by type.
     */
    public function getLinkedResources(CalendarEvent $event): Collection

    /**
     * Build the pre-fill data array for a given resource type.
     */
    public function buildPrefillData(CalendarEvent $event, string $resourceType): array
}
```

### Controller: `CalendarActionController`

API controller with endpoints for creating resources from calendar events.

```php
class CalendarActionController extends Controller
{
    /**
     * GET /api/calendar-events/{calendarEvent}/prefill/{type}
     * Returns pre-fill data for creating a resource of the given type.
     * Response: {team_member_id, team_member_name, fields: {...}}
     */
    public function prefill(CalendarEvent $calendarEvent, string $type): JsonResponse

    /**
     * POST /api/calendar-events/{calendarEvent}/create/{type}
     * Creates the resource, links it to the event, returns the resource.
     * For bilas: dispatches BilaScheduled event.
     */
    public function create(Request $request, CalendarEvent $calendarEvent, string $type): JsonResponse

    /**
     * DELETE /api/calendar-events/{calendarEvent}/links/{calendarEventLink}
     * Removes a link (does NOT delete the resource itself).
     */
    public function unlink(CalendarEvent $calendarEvent, CalendarEventLink $calendarEventLink): JsonResponse
}
```

### Model: `CalendarEventLink`

```php
class CalendarEventLink extends Model
{
    // No BelongsToUser — scoped through CalendarEvent's BelongsToUser
    protected $fillable = ['calendar_event_id', 'linkable_type', 'linkable_id'];

    public function calendarEvent(): BelongsTo  // → CalendarEvent
    public function linkable(): MorphTo          // → Bila|Task|FollowUp|Note
}
```

### Model Updates

```php
// CalendarEvent — add:
public function links(): HasMany           // → CalendarEventLink
public function linkedBilas(): MorphToMany // convenience
// etc. for Task, FollowUp, Note

// Bila, Task, FollowUp, Note — each add:
public function calendarEventLinks(): MorphMany  // → CalendarEventLink
```

### Sync Job Update: `SyncCalendarEventsJob`

Update the `$select` parameter in `MicrosoftGraphService::getMyCalendarEvents()` to include `attendees`, and persist the attendee array in the new JSON column.

```php
// In normaliseCalendarEvent():
'attendees' => collect($event['attendees'] ?? [])
    ->map(fn (array $a) => [
        'email' => $a['emailAddress']['address'] ?? null,
        'name'  => $a['emailAddress']['name'] ?? null,
    ])
    ->filter(fn (array $a) => $a['email'] !== null)
    ->values()
    ->all(),
```

---

## API Routes

```php
// routes/api.php
Route::prefix('calendar-events/{calendarEvent}')->group(function () {
    Route::get('prefill/{type}', [CalendarActionController::class, 'prefill'])
        ->name('api.calendar-events.prefill')
        ->whereIn('type', ['bila', 'task', 'follow-up', 'note']);

    Route::post('create/{type}', [CalendarActionController::class, 'create'])
        ->name('api.calendar-events.create')
        ->whereIn('type', ['bila', 'task', 'follow-up', 'note']);

    Route::delete('links/{calendarEventLink}', [CalendarActionController::class, 'unlink'])
        ->name('api.calendar-events.unlink');
});
```

---

## Frontend

### Calendar Event Card — Context Menu

Each calendar event card in `calendar-events.blade.php` gets a small action button (three dots or `+` icon) that opens a dropdown:

```
[+] Create from event...
├── Bila          (only if event has attendees or user wants to pick manually)
├── Task
├── Follow-up
└── Note
────────────────
Linked: (shown only when links exist)
├── 🔗 Bila with John — Mar 10    → click navigates to /bilas/{id}
├── 🔗 Task: Review proposal      → click navigates to /tasks (with highlight)
```

### Alpine Component: `calendarEventActions`

```typescript
// resources/js/components/calendarEventActions.ts
interface CalendarEventActionsData {
    eventId: number;
    links: CalendarEventLink[];
    menuOpen: boolean;
    loading: boolean;

    createResource(type: string): Promise<void>;
    unlinkResource(linkId: number): Promise<void>;
}
```

**Flow for "Create Bila":**
1. User clicks "Bila" in the dropdown
2. Frontend calls `GET /api/calendar-events/{id}/prefill/bila`
3. Response includes `{team_member_id: 5, team_member_name: "John", fields: {scheduled_date: "2026-03-10"}}`
4. Frontend calls `POST /api/calendar-events/{id}/create/bila` with the pre-filled data
5. Response includes the created bila with its URL
6. UI updates: dropdown now shows linked bila, event card shows a link indicator
7. Optional: redirect to the bila detail page (user preference, discuss during implementation)

### Visual Indicators

- Events with linked resources show a small chain-link icon next to the status dot
- The icon count or type badges indicate what's linked (e.g., "B" for bila, "T" for task)
- Clicking the indicator expands the links list

### TypeScript Types

```typescript
// resources/js/types/models.ts — add:
interface CalendarEventLink {
    id: number;
    calendar_event_id: number;
    linkable_type: string;
    linkable_id: number;
    linkable?: Bila | Task | FollowUp | Note;  // eager-loaded
    created_at: string;
}

// Update CalendarEvent interface:
interface CalendarEvent {
    // ...existing fields...
    attendees: Array<{email: string; name: string}> | null;
    links?: CalendarEventLink[];
}
```

---

## Implementation Phases

### Phase 1: Data Layer (backend agent)

**Files:**
- `database/migrations/xxxx_add_attendees_to_calendar_events_table.php`
- `database/migrations/xxxx_create_calendar_event_links_table.php`
- `app/Models/CalendarEventLink.php`
- `app/Models/CalendarEvent.php` (update: add `attendees` to fillable/casts, add `links()` relationship)
- `app/Models/Bila.php` (update: add `calendarEventLinks()` morph)
- `app/Models/Task.php` (update: add `calendarEventLinks()` morph)
- `app/Models/FollowUp.php` (update: add `calendarEventLinks()` morph)
- `app/Models/Note.php` (update: add `calendarEventLinks()` morph)
- `database/factories/CalendarEventLinkFactory.php`

**Tests (TDD — write first):**
- `CalendarEventLink` model: creation, relationships (morph to each type), cascade delete when calendar event is deleted
- `CalendarEvent` → `links()` relationship returns correct links
- `Bila`, `Task`, `FollowUp`, `Note` → `calendarEventLinks()` returns correct links
- Unique constraint on `(calendar_event_id, linkable_type, linkable_id)` prevents duplicates
- `attendees` JSON column stores and retrieves correctly

**Depends on:** nothing

### Phase 2: Attendee Sync (backend agent)

**Files:**
- `app/Services/MicrosoftGraphService.php` (update: add `attendees` to `$select`, update `normaliseCalendarEvent()`)
- `app/Jobs/SyncCalendarEventsJob.php` (update: persist `attendees` field)

**Tests (TDD — write first):**
- `normaliseCalendarEvent()` extracts attendees from Graph response format
- `SyncCalendarEventsJob` persists attendees JSON on upsert
- Attendees with null email are filtered out
- Empty attendees array stored as `[]`, not `null`

**Depends on:** Phase 1

### Phase 3: Calendar Action Service (backend agent)

**Files:**
- `app/Services/CalendarActionService.php`

**Tests (TDD — write first):**
- `resolveTeamMember()`: 1 match → returns member; 0 matches → null; 2+ matches → null
- `resolveTeamMember()`: matches on `microsoft_email` (case-insensitive)
- `resolveTeamMember()`: matches on `email` as fallback (case-insensitive)
- `resolveTeamMember()`: excludes the logged-in user's own email from matching
- `buildPrefillData()`: correct fields per resource type
- `linkResource()`: creates link, prevents duplicates
- `getLinkedResources()`: returns grouped by type with eager-loaded resources

**Depends on:** Phase 1

### Phase 4: API Endpoints (backend agent)

**Files:**
- `app/Http/Controllers/Api/CalendarActionController.php`
- `routes/api.php` (update: add calendar action routes)

**Tests (TDD — write first):**
- `prefill` endpoint: returns correct pre-fill data per type
- `create` endpoint: creates resource + link, returns standardized response
- `create` bila: dispatches `BilaScheduled` event (triggers `next_bila_date` update)
- `create` endpoint: validates resource type (400 for invalid)
- `create` endpoint: respects `BelongsToUser` scope (403 for other user's event)
- `unlink` endpoint: removes link without deleting the resource
- `unlink` endpoint: 404 for non-existent link

**Depends on:** Phase 3

### Phase 5: Frontend UI (frontend agent)

**Files:**
- `resources/views/components/tl/calendar-events.blade.php` (update: add action menu per event)
- `resources/views/components/tl/calendar-event-actions.blade.php` (new: dropdown component)
- `resources/js/components/calendarEventActions.ts` (new: Alpine component)
- `resources/js/app.ts` (update: register component)
- `resources/js/types/models.ts` (update: add `CalendarEventLink`, update `CalendarEvent`)

**Behavior:**
- Action button visible on every calendar event card
- Dropdown opens with "Create Bila / Task / Follow-up / Note"
- On create: calls API, updates UI to show linked resource badge
- Linked resources displayed as clickable links with type icon and title
- Loading state during API call

**Depends on:** Phase 4

### Phase 6: Dashboard Integration (frontend agent)

**Files:**
- `app/Http/Controllers/Web/DashboardController.php` (update: eager-load `links.linkable` on calendar events)
- `resources/views/pages/dashboard.blade.php` (update: pass links data to calendar component)

**Tests:**
- Dashboard controller passes calendar events with pre-loaded links
- Events render with link indicators when links exist
- Events render without indicators when no links exist

**Depends on:** Phase 5

---

## Agent Ownership

| Phase | Agent | Owns |
|-------|-------|------|
| 1 | backend | Migrations, models, factories |
| 2 | backend | Graph service, sync job |
| 3 | backend | CalendarActionService |
| 4 | backend | Controller, routes |
| 5 | frontend + typescript | Blade component, Alpine component, types |
| 6 | frontend + backend | Dashboard controller update, Blade updates |

**Shared files (coordinate via messages):**
- `routes/api.php` — Phase 4 adds routes
- `resources/js/app.ts` — Phase 5 registers Alpine component
- `resources/js/types/models.ts` — Phase 5 adds types

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Calendar event deleted by sync (meeting cancelled) | `ON DELETE CASCADE` removes links. Linked resources remain (they're real data). |
| Linked resource deleted by user | Orphaned link row remains but `linkable` returns null. Frontend handles gracefully (shows "Resource deleted"). |
| Same resource linked to same event twice | Unique constraint prevents it. |
| User creates bila from event, then edits the bila's `scheduled_date` | That's fine. The link is informational, not a sync. |
| Event has no attendees (private/external event) | All "Create" options still work, just no auto-assignment. |
| Microsoft email on member changes after event was synced | Next sync updates attendees. `resolveTeamMember()` always runs live against current member data, not cached. |

---

## Out of Scope (Potential Future Enhancements)

- **Auto-create bilas** from recurring calendar events matching a pattern (e.g., "1:1 with John")
- **Two-way sync** — creating a bila creates a calendar event in Outlook
- **Bulk actions** — "Create bilas for all 1:1 meetings this week"
- **Calendar event detail page** — dedicated page showing all linked resources
