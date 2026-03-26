# Fix Local Speech Processing Deduplication

**Created:** 2026-03-26
**Status:** In Progress
**Author:** Bas de Kort
**PRDs:** PRD-002

## PRD References

| PRD | Title | Status |
|-----|-------|--------|
| [PRD-002](prds/PRD-002-local-speech-processing-deduplication.md) | Local Speech Processing Deduplication | Approved |

## Problem Statement

Local mode speech processing re-triggers on every page visit because no server-side state is set before the long-running speech service call begins. See PRD-002 for full problem description.

## Acceptance Criteria

Derived from PRD-002:

1. Before audio is sent to the local speech service, a `MeetingTranscription` record exists with `status: processing`
2. When `init()` sees `status: processing` in local mode, it shows processing UI and polls instead of re-triggering
3. The "start processing" endpoint is idempotent; calling it multiple times does not create duplicates or reset an in-progress transcription
4. Exactly one processing request is sent to the speech service per meeting/visit cycle
5. When the speech service completes, the result is saved and the status transitions to `completed`

## Technical Design

### Approach

Add a new endpoint `POST /api/v1/meetings/{meeting}/transcription/start-local` on `ClientTranscriptionController` that upserts a `MeetingTranscription` with `status: processing`. The frontend calls this endpoint at the start of `processLocally()` before downloading audio or contacting the speech service. The `init()` guard already skips local processing when `status !== null && status !== 'pending'`, so once the server returns `processing`, subsequent page visits fall through to the existing polling path.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `app/Http/Controllers/Api/ClientTranscriptionController.php` | Modify | Add `startProcessing()` method |
| `routes/api.php` | Modify | Add route for new endpoint |
| `resources/js/components/transcription-viewer.ts` | Modify | Call start endpoint at beginning of `processLocally()` |
| `tests/Feature/Http/Controllers/Api/ClientTranscriptionControllerTest.php` | Create/Modify | Tests for new endpoint |

### Data Flow

```
Page visit → init() → status is null/pending?
  ├─ YES → processLocally()
  │         ├─ POST /start-local → creates MeetingTranscription(status: processing)
  │         ├─ Update local state to 'processing'
  │         ├─ Download audio from server
  │         ├─ POST audio to speech service (long-running)
  │         ├─ POST result to /client-result → status: completed
  │         └─ refreshData()
  └─ NO (status: processing/completed/failed) → poll or show result
```

### Edge Cases & Error Handling

- **Race condition**: Two tabs open simultaneously, both see `status: null`. The `startProcessing` endpoint must be idempotent; if a record with `processing` status already exists, return success without resetting it.
- **Failed processing**: If `processLocally()` fails after marking `processing`, the status stays `processing`. The existing retry button should still work (it resets status and re-triggers).
- **Non-local-mode user**: The endpoint must reject requests from users not in local mode (same auth as `storeResult`).

## Implementation Phases

### Phase 1: Backend; Start Processing Endpoint
- **Goal:** New API endpoint that creates/updates a `MeetingTranscription` with `status: processing`
- **PRD criteria:** #2 (server-side processing state), #5 (idempotent start)
- **Specs:**
  - [x] `POST /api/v1/meetings/{meeting}/transcription/start-local` creates a `MeetingTranscription` with `status: processing` when none exists
  - [x] Endpoint returns success without resetting when a transcription with `status: processing` already exists (idempotent)
  - [x] Endpoint updates status to `processing` when transcription exists with `status: pending` or `status: failed`
  - [x] Endpoint rejects requests from users not in local speech service mode (403)
  - [x] Endpoint returns 422 when meeting has no recordings
  - [x] Endpoint sets `processing_started_at` timestamp for elapsed timer display
- **Files:** `app/Http/Controllers/Api/ClientTranscriptionController.php`, `routes/api.php`, `tests/Feature/Http/Controllers/Api/ClientTranscriptionControllerTest.php`

### Phase 2: Frontend; Call Start Endpoint Before Processing
- **Goal:** Frontend marks processing on the server before contacting the speech service
- **PRD criteria:** #1 (single processing request), #3 (processing indicator on revisit), #4 (result delivery)
- **Specs:**
  - [x] `processLocally()` calls `POST /start-local` before downloading audio
  - [x] If start endpoint fails, `processLocally()` aborts with error message
  - [x] After successful start, local state is updated to `status: processing` immediately
  - [x] `init()` does not re-trigger processing when `status === 'processing'` in local mode (existing behavior; verified by integration)
  - [x] When local processing is active, a blocking modal overlay is shown preventing navigation away from the meeting page; the modal displays the processing status, elapsed time, and a clear message that the user must stay on the page
  - [x] The modal is dismissed automatically when processing completes (success) or fails (error shown in modal)
- **Files:** `resources/js/components/transcription-viewer.ts`, `resources/views/pages/meetings/show.blade.php`

## Parallelization

**Strategy:** Sequential

Phase 2 depends on Phase 1 (frontend needs the endpoint). Both phases are small enough for a single session.

## Out of Scope

- Stale processing recovery (e.g., auto-resetting `processing` status after a timeout)
- Speech service queue deduplication
- Browser Service Worker-based processing persistence

## Open Questions

- **Server-side callback (future):** Currently, local processing requires the browser tab to stay open. A more robust solution would have the speech service POST results directly to the Mithril backend via a callback URL, making processing tab-independent. This requires changes to both the mithril-speech container and the Laravel app. Planned as a separate feature after this fix.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
| 2026-03-26 | Phase 2 | Added blocking modal specs | User requested modal overlay during local processing to prevent navigation and lost results | — |
