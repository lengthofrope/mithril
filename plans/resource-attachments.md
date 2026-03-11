# Messages & Attachments on Resources — Implementation Plan

## Summary

Add the ability to attach messages (comments/notes) and file attachments to any core resource: Tasks, Follow-ups, Bilas, and Notes. This creates a threaded activity feed per resource where users can add context, upload files, and track the history of a resource over time. Messages are timestamped, stored in chronological order, and displayed as a timeline on the resource detail view.

---

## Design Decisions

### Single Polymorphic Model for Messages

A `ResourceMessage` model uses a polymorphic relationship (`messageable_type`/`messageable_id`) to attach to any resource type. This avoids creating separate message tables per entity and follows the same polymorphic pattern used by `CalendarEventLink`.

### Attachments as Children of Messages

File attachments belong to a `ResourceMessage`, not directly to a resource. This keeps the relationship clean: every attachment has a context (the message it was added with). A message can have zero or more attachments. An attachment without a message body is valid (e.g., "user uploaded a file" with no comment).

### Local File Storage

Attachments are stored on the local filesystem (Laravel's `local` or `public` disk, configurable). No external storage service (S3, etc.) needed for v1. Files are stored in a user-scoped directory: `attachments/{user_id}/{resource_type}/{resource_id}/`.

### File Size & Type Limits

- **Max file size:** 10 MB per file (configurable)
- **Max files per message:** 5
- **Allowed types:** Common document and image types (PDF, DOC/DOCX, XLS/XLSX, PNG, JPG, GIF, TXT, CSV, MD). Configurable via config file.
- **No executable files** (.exe, .bat, .sh, .php, etc.)

### No Rich Text in Messages

Messages are plain text (no markdown, no HTML). This keeps the UI simple and avoids XSS concerns. Messages are displayed with `nl2br()` for line breaks and linked URLs auto-detected.

### Soft Delete on Messages

Messages are soft-deleted to preserve the timeline integrity. Deleted messages show as "[Message removed]" with the timestamp preserved. Attachments of soft-deleted messages are retained on disk for 30 days, then purged by a cleanup job.

---

## Data Model

### New Table: `resource_messages`

```
resource_messages
├── id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
├── user_id             BIGINT UNSIGNED, FK → users.id, ON DELETE CASCADE
├── messageable_type    VARCHAR(255), NOT NULL  — Morph type
├── messageable_id      BIGINT UNSIGNED, NOT NULL
├── body                TEXT, NULL  — Plain text message (NULL if attachment-only)
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
├── deleted_at          TIMESTAMP, NULL  — Soft delete
└── INDEX (messageable_type, messageable_id)
```

### New Table: `resource_attachments`

```
resource_attachments
├── id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
├── resource_message_id BIGINT UNSIGNED, FK → resource_messages.id, ON DELETE CASCADE
├── user_id             BIGINT UNSIGNED, FK → users.id, ON DELETE CASCADE
├── original_filename   VARCHAR(255), NOT NULL  — Original upload filename
├── stored_filename     VARCHAR(255), NOT NULL  — UUID-based filename on disk
├── mime_type           VARCHAR(100), NOT NULL
├── file_size           BIGINT UNSIGNED, NOT NULL  — Size in bytes
├── disk                VARCHAR(50), NOT NULL, DEFAULT 'local'  — Storage disk name
├── path                VARCHAR(500), NOT NULL  — Relative path on disk
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
```

---

## Backend Architecture

### Model: `ResourceMessage`

```php
class ResourceMessage extends Model
{
    use BelongsToUser;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['body', 'messageable_type', 'messageable_id'];

    public function messageable(): MorphTo  // → Task|FollowUp|Bila|Note
    public function attachments(): HasMany  // → ResourceAttachment
    public function user(): BelongsTo       // → User (message author)
}
```

### Model: `ResourceAttachment`

```php
class ResourceAttachment extends Model
{
    use BelongsToUser;
    use HasFactory;

    protected $fillable = [
        'resource_message_id',
        'original_filename',
        'stored_filename',
        'mime_type',
        'file_size',
        'disk',
        'path',
    ];

    public function message(): BelongsTo  // → ResourceMessage

    /**
     * Get the full URL or path for downloading this attachment.
     */
    public function getDownloadUrl(): string

    /**
     * Check if this attachment is an image (for inline preview).
     */
    public function isImage(): bool
}
```

### Trait: `HasMessages`

New reusable trait that models opt into:

```php
trait HasMessages
{
    /**
     * Get all messages for this resource, ordered chronologically.
     */
    public function messages(): MorphMany
    {
        return $this->morphMany(ResourceMessage::class, 'messageable')
            ->orderBy('created_at');
    }

    /**
     * Get the count of messages (excluding soft-deleted).
     */
    public function messagesCount(): int
    {
        return $this->messages()->count();
    }
}
```

Applied to: `Task`, `FollowUp`, `Bila`, `Note`.

### Service: `AttachmentService`

```php
class AttachmentService
{
    /**
     * Store uploaded files and create ResourceAttachment records.
     * Returns the created attachments.
     */
    public function storeAttachments(
        ResourceMessage $message,
        array $uploadedFiles,
    ): Collection

    /**
     * Generate the storage path for an attachment.
     */
    public function generatePath(int $userId, string $resourceType, int $resourceId): string

    /**
     * Delete attachment files from disk.
     */
    public function deleteFiles(Collection $attachments): void

    /**
     * Validate uploaded files against allowed types and size limits.
     */
    public function validateFiles(array $files): void
}
```

### Controller: `ResourceMessageController`

```php
class ResourceMessageController extends Controller
{
    /**
     * GET /api/{resourceType}/{resourceId}/messages
     * Returns all messages with attachments for a resource.
     */
    public function index(string $resourceType, int $resourceId): JsonResponse

    /**
     * POST /api/{resourceType}/{resourceId}/messages
     * Creates a new message (with optional attachments) on a resource.
     * Accepts multipart/form-data for file uploads.
     */
    public function store(Request $request, string $resourceType, int $resourceId): JsonResponse

    /**
     * PUT /api/messages/{resourceMessage}
     * Updates a message body (not attachments).
     */
    public function update(Request $request, ResourceMessage $resourceMessage): JsonResponse

    /**
     * DELETE /api/messages/{resourceMessage}
     * Soft-deletes a message.
     */
    public function destroy(ResourceMessage $resourceMessage): JsonResponse

    /**
     * GET /api/attachments/{resourceAttachment}/download
     * Streams the attachment file for download.
     */
    public function download(ResourceAttachment $resourceAttachment): StreamedResponse
}
```

### Cleanup Job: `PurgeDeletedAttachments`

Runs daily. Finds soft-deleted messages older than 30 days, deletes their attachment files from disk, then force-deletes the message records.

```php
class PurgeDeletedAttachments implements ShouldQueue
{
    public function handle(): void
    {
        $cutoff = now()->subDays(30);

        ResourceMessage::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->with('attachments')
            ->chunk(100, function ($messages) {
                foreach ($messages as $message) {
                    $this->attachmentService->deleteFiles($message->attachments);
                    $message->forceDelete();
                }
            });
    }
}
```

### Scheduler

```php
Schedule::job(new PurgeDeletedAttachments())->daily();
```

### Config: `config/attachments.php`

```php
return [
    'max_file_size' => env('ATTACHMENT_MAX_SIZE', 10 * 1024 * 1024),  // 10 MB
    'max_files_per_message' => 5,
    'disk' => env('ATTACHMENT_DISK', 'local'),

    'allowed_extensions' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'png', 'jpg', 'jpeg', 'gif', 'webp',
        'txt', 'csv', 'md', 'json',
        'zip', 'rar',
    ],

    'blocked_extensions' => [
        'exe', 'bat', 'sh', 'php', 'js', 'py', 'rb', 'pl',
        'com', 'cmd', 'vbs', 'ps1', 'msi',
    ],

    'purge_after_days' => 30,
];
```

### Model Map Update

Add to `AutoSaveController` model map (if needed for message body editing):
- Not needed — messages use their own controller, not AutoSave.

Add to resource type resolver (shared with CalendarActionController pattern):

```php
private function resolveModel(string $type, int $id): Model
{
    return match ($type) {
        'task' => Task::findOrFail($id),
        'follow-up' => FollowUp::findOrFail($id),
        'bila' => Bila::findOrFail($id),
        'note' => Note::findOrFail($id),
        default => abort(404, "Unknown resource type: {$type}"),
    };
}
```

---

## API Routes

```php
// routes/api.php

// Messages on resources
Route::prefix('{resourceType}/{resourceId}/messages')
    ->whereIn('resourceType', ['task', 'follow-up', 'bila', 'note'])
    ->group(function () {
        Route::get('/', [ResourceMessageController::class, 'index'])
            ->name('api.messages.index');
        Route::post('/', [ResourceMessageController::class, 'store'])
            ->name('api.messages.store');
    });

// Individual message operations
Route::prefix('messages')->group(function () {
    Route::put('{resourceMessage}', [ResourceMessageController::class, 'update'])
        ->name('api.messages.update');
    Route::delete('{resourceMessage}', [ResourceMessageController::class, 'destroy'])
        ->name('api.messages.destroy');
});

// Attachment download
Route::get('attachments/{resourceAttachment}/download', [ResourceMessageController::class, 'download'])
    ->name('api.attachments.download');
```

---

## Frontend

### Resource Detail — Message Timeline

Each resource detail view (task page, follow-up page, bila page, note page) gets a new "Activity" section at the bottom showing the message timeline:

```
Activity
├── Message 1 (oldest first)
│   ├── Author name + relative time ("You, 2 days ago")
│   ├── Message body (plain text, nl2br, auto-linked URLs)
│   ├── Attachments:
│   │   ├── 📄 report.pdf (1.2 MB) [Download]
│   │   ├── 🖼️ screenshot.png (340 KB) [Preview] [Download]
│   │   └── 📄 notes.txt (2 KB) [Download]
│   └── Actions: [Edit] [Delete] (only for own messages)
├── Message 2
│   └── ...
├── [Message removed] — Mar 10 (soft-deleted)
├── Message 3
│   └── ...
└── New message form:
    ├── [textarea: Add a message...]
    ├── [📎 Attach files] (file picker, drag & drop zone)
    ├── Pending uploads preview (with remove button)
    └── [Send] button
```

### Blade Component: `tl/message-timeline`

```blade
<x-tl.message-timeline
    :resource-type="'task'"
    :resource-id="$task->id"
    :messages="$task->messages"
/>
```

### Alpine Component: `messageTimeline`

```typescript
interface MessageTimelineData {
    resourceType: string;
    resourceId: number;
    messages: ResourceMessage[];
    newBody: string;
    pendingFiles: File[];
    loading: boolean;
    submitting: boolean;

    loadMessages(): Promise<void>;
    submitMessage(): Promise<void>;      // POST with multipart/form-data
    editMessage(id: number): void;
    updateMessage(id: number): Promise<void>;
    deleteMessage(id: number): Promise<void>;
    addFiles(files: FileList): void;
    removeFile(index: number): void;
    downloadAttachment(id: number): void;
}
```

### Resource Cards — Message Count Badge

On task cards, follow-up cards, etc., show a small message count badge if messages exist:

```
[💬 3] — indicating 3 messages/comments on this resource
```

### Drag & Drop Upload

The message form supports drag & drop file upload. Dragging files over the form area highlights it with a dashed border. Dropping adds files to the `pendingFiles` array for preview before submission.

### Image Preview

Image attachments (PNG, JPG, GIF, WebP) show an inline thumbnail in the timeline. Clicking opens the full image in a lightbox overlay (simple modal, no external library needed).

### TypeScript Types

```typescript
// resources/js/types/models.ts — add:
interface ResourceMessage {
    id: number;
    user_id: number;
    user?: { id: number; name: string };
    messageable_type: string;
    messageable_id: number;
    body: string | null;
    attachments: ResourceAttachment[];
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

interface ResourceAttachment {
    id: number;
    resource_message_id: number;
    original_filename: string;
    mime_type: string;
    file_size: number;
    is_image: boolean;
    download_url: string;
    created_at: string;
}
```

---

## Implementation Phases

### Phase 1: Data Layer (backend agent)

**Files:**
- `database/migrations/xxxx_create_resource_messages_table.php`
- `database/migrations/xxxx_create_resource_attachments_table.php`
- `app/Models/ResourceMessage.php`
- `app/Models/ResourceAttachment.php`
- `app/Models/Traits/HasMessages.php`
- `app/Models/Task.php` (update: use HasMessages)
- `app/Models/FollowUp.php` (update: use HasMessages)
- `app/Models/Bila.php` (update: use HasMessages)
- `app/Models/Note.php` (update: use HasMessages)
- `database/factories/ResourceMessageFactory.php`
- `database/factories/ResourceAttachmentFactory.php`

**Tests (TDD — write first):**
- ResourceMessage model: creation, soft delete, polymorphic relationships
- ResourceAttachment model: creation, belongs to message
- HasMessages trait: messages() returns correct morphMany
- Cascade: deleting resource does NOT cascade-delete messages (soft-delete aware)
- Cascade: force-deleting message cascades to attachments
- Morph relationship works for all 4 resource types

**Depends on:** nothing

### Phase 2: Attachment Service (backend agent)

**Files:**
- `app/Services/AttachmentService.php`
- `config/attachments.php`

**Tests (TDD — write first):**
- `storeAttachments()`: stores files on disk, creates records
- `storeAttachments()`: generates UUID-based filenames
- `storeAttachments()`: stores in correct user-scoped directory
- `validateFiles()`: rejects oversized files
- `validateFiles()`: rejects blocked extensions
- `validateFiles()`: accepts allowed extensions
- `validateFiles()`: rejects when exceeding max files per message
- `generatePath()`: returns correct path structure
- `deleteFiles()`: removes files from disk
- `isImage()`: correctly identifies image MIME types

**Depends on:** Phase 1

### Phase 3: API Endpoints (backend agent)

**Files:**
- `app/Http/Controllers/Api/ResourceMessageController.php`
- `app/Http/Requests/StoreResourceMessageRequest.php`
- `routes/api.php` (update: add message routes)

**Tests (TDD — write first):**
- `index`: returns messages with attachments for a resource
- `index`: respects BelongsToUser scope (via resource ownership)
- `store`: creates message with body only
- `store`: creates message with attachments only (no body)
- `store`: creates message with body + attachments
- `store`: validates file size and type
- `store`: rejects more than 5 files
- `update`: updates message body (own messages only)
- `update`: rejects update on other user's message
- `destroy`: soft-deletes message (own messages only)
- `download`: streams correct file
- `download`: rejects access to other user's attachments

**Depends on:** Phase 2

### Phase 4: Cleanup Job (backend agent)

**Files:**
- `app/Jobs/PurgeDeletedAttachments.php`

**Tests (TDD — write first):**
- Purges messages soft-deleted more than 30 days ago
- Does not purge messages soft-deleted less than 30 days ago
- Deletes attachment files from disk during purge
- Force-deletes message records after purge

**Depends on:** Phase 2

### Phase 5: Frontend — Message Timeline (frontend + typescript agent)

**Files:**
- `resources/views/components/tl/message-timeline.blade.php` (new)
- `resources/js/components/messageTimeline.ts` (new)
- `resources/js/app.ts` (update: register component)
- `resources/js/types/models.ts` (update: add message types)

**Behavior:**
- Chronological message list with author and timestamp
- Plain text body with nl2br and auto-linked URLs
- Attachment list with download links
- Image thumbnails with lightbox
- Edit/delete own messages
- Soft-deleted messages show "[Message removed]"

**Depends on:** Phase 3

### Phase 6: Frontend — Message Form (frontend + typescript agent)

**Files:**
- `resources/views/components/tl/message-form.blade.php` (new)
- Message timeline component (update: integrate form)

**Behavior:**
- Textarea for message body
- File picker + drag & drop zone
- Pending files preview with remove button
- Submit via multipart/form-data
- Loading state during upload

**Depends on:** Phase 5

### Phase 7: Integration — Resource Views (frontend agent)

**Files:**
- Task detail Blade (update: add message timeline)
- Follow-up detail Blade (update: add message timeline)
- Bila detail Blade (update: add message timeline)
- Note detail Blade (update: add message timeline)
- Resource card components (update: add message count badge)

**Depends on:** Phase 6

---

## Agent Ownership

| Phase | Agent | Owns |
|-------|-------|------|
| 1 | backend | Migrations, models, trait, factories |
| 2 | backend | AttachmentService, config |
| 3 | backend | Controller, request, routes |
| 4 | backend | Cleanup job |
| 5 | frontend + typescript | Timeline component, Alpine component, types |
| 6 | frontend + typescript | Message form component |
| 7 | frontend | Resource view integration, card badges |

**Shared files:**
- `routes/api.php` — Phase 3 adds routes
- `resources/js/app.ts` — Phase 5 registers Alpine component

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Message with no body and no attachments | Validation rejects: at least one of body or attachments required. |
| File upload fails mid-way (3 of 5 files succeed) | Transaction: if any file fails, roll back all. No partial messages. |
| Resource deleted while messages exist | Messages are orphaned (messageable returns null). Cleanup job can handle these in a future phase. |
| Concurrent file uploads | Each upload is atomic. UUID filenames prevent collisions. |
| Disk full | `storeAttachments()` catches storage exceptions, returns error response, cleans up partial uploads. |
| User tries to download another user's attachment | BelongsToUser scope on both ResourceMessage and ResourceAttachment. Download endpoint checks ownership chain: attachment → message → resource → user. |
| Very long message body | No hard limit in DB (TEXT column). Frontend can optionally truncate display with "Show more" link. |
| Same file uploaded twice | Allowed — each upload creates a unique stored_filename (UUID). No deduplication needed for v1. |

---

## Security

- **File validation:** Both extension AND MIME type are checked server-side. Client-side extension check is cosmetic only.
- **Stored filenames:** UUID-based, never exposing original filenames in the path. Prevents path traversal.
- **Download authorization:** Every download request verifies that the authenticated user owns the resource chain.
- **No direct URL access:** Files are served via a controller endpoint (not a public directory), ensuring authorization is always checked.
- **XSS prevention:** Message bodies are plain text, rendered with `e()` (htmlspecialchars) + `nl2br()`. No raw HTML.
- **Upload directory:** Stored outside `public/` (using `local` disk, not `public`). Files are only accessible via the authenticated download endpoint.

---

## Out of Scope (Potential Future Enhancements)

- **@mentions** — tag team members in messages, trigger notifications
- **Rich text / Markdown in messages** — allow formatting in message bodies
- **Message reactions** — emoji reactions on messages
- **File versioning** — track versions of the same file
- **External storage** — S3, Azure Blob Storage for scalability
- **Inline image paste** — paste from clipboard into message form
- **Message threading** — reply to specific messages (nested conversations)
- **Activity log integration** — auto-generated messages for status changes, assignment changes, etc.
