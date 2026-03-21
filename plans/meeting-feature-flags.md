# Meeting Feature Flags + Tab Freshness

**Created:** 2026-03-21
**Status:** In Progress
**Author:** Bas de Kort + Claude

## Problem Statement

Recording, transcription, diarization, and AI extraction require external services (whisper.cpp, pyannote, AI provider) that may not be available in production. The meetings tool must function without these services. Users should be able to toggle features via `.env` and still paste transcriptions manually as reference material.

Additionally, tab content goes stale — switching between tabs shows outdated data (e.g. transcription completed while on another tab, or AI extractions tab not detecting a new transcription) until the page is manually refreshed.

## Acceptance Criteria

1. `MEETING_RECORDING_ENABLED=false` disables all recording functionality (upload, browser recording, playback)
2. `MEETING_TRANSCRIPTION_ENABLED=false` disables auto-transcription (whisper/pyannote) but manual paste remains available
3. `AI_ENABLED=false` disables AI extraction globally (not just for meetings)
4. When recording is disabled, the Recording tab is hidden
5. When transcription is disabled, the Transcription tab still shows but only with manual input — no auto-transcribe/retry/retranscribe buttons
6. When AI is disabled, the AI Extractions tab is hidden
7. Manual transcription paste is always available (even when all features are off) via the Transcription tab
8. Recording upload API returns 403 when `MEETING_RECORDING_ENABLED=false`
9. Transcription job dispatch is blocked when `MEETING_TRANSCRIPTION_ENABLED=false`
10. Extraction job dispatch is blocked when `AI_ENABLED=false`
11. Existing data (recordings, transcriptions, extractions) remains visible when features are later disabled
12. All feature flags default to `true` (backwards compatible — no behavior change without explicit config)
13. Switching to the Transcription tab refreshes transcription status and content from the API
14. Switching to the AI Extractions tab refreshes extraction data and detects newly available transcriptions
15. Team member meeting history shows title, summary, transcription excerpt, and extraction counts — not just the `notes` field
16. "No notes recorded" only appears when a meeting has no notes, no summary, and no transcription

## Technical Design

### Approach

**Feature flags:** Three env-backed flags, two in `config/meetings.php` and one in `config/ai.php`:

```php
// config/meetings.php
'recording' => [
    'enabled' => env('MEETING_RECORDING_ENABLED', true),
    // ...existing keys...
],
'transcription' => [
    'enabled' => env('MEETING_TRANSCRIPTION_ENABLED', true),
    // ...existing keys...
],

// config/ai.php (already exists)
'enabled' => env('AI_ENABLED', true),
```

The flags gate three layers:
1. **UI** — Blade conditionals hide tabs, buttons, and forms
2. **API** — Controllers/jobs check config before proceeding
3. **Job dispatch** — Jobs abort early with a log message when their feature is disabled

**Tab freshness:** The root cause is that tab content is initialized from server-rendered `@js()` values and only polls while in an active processing state. When a user switches away during processing and comes back after completion, the data is stale.

Fix: Add a `refreshData()` method to the Transcription and AI Extractions Alpine components. Hook into the parent tab switcher via Alpine's `$dispatch`/`$watch` or `x-on` to call `refreshData()` when a tab becomes active. This fetches the latest state from the existing API endpoints.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `config/meetings.php` | Modify | Add `recording.enabled` and `transcription.enabled` flags |
| `config/ai.php` | Modify | Add `enabled` flag |
| `.env.example` | Modify | Add `MEETING_RECORDING_ENABLED`, `MEETING_TRANSCRIPTION_ENABLED`, `AI_ENABLED` vars |
| `resources/views/pages/meetings/show.blade.php` | Modify | Conditional tab rendering, conditional buttons/forms, tab-switch refresh logic |
| `app/Http/Controllers/Api/MeetingRecordingController.php` | Modify | Guard `store()` with recording.enabled check |
| `app/Jobs/TranscribeMeetingJob.php` | Modify | Early return when transcription.enabled is false |
| `app/Jobs/DiarizeMeetingJob.php` | Modify | Early return when transcription.enabled is false |
| `app/Jobs/ExtractMeetingInsightsJob.php` | Modify | Early return when ai.enabled is false |
| `app/Http/Controllers/Api/MeetingExtractionController.php` | Modify | Guard `reExtract()` when AI disabled |
| `app/Http/Controllers/Web/MeetingPageController.php` | Modify | Pass feature flags to the view |

### Edge Cases & Error Handling

- Feature disabled after data exists: existing recordings/transcriptions/extractions remain visible (read-only)
- Recording disabled but transcription enabled: manual transcription still works, no auto-transcribe trigger
- Transcription disabled but AI enabled: manual transcription can still be used as input for AI extraction
- All three disabled: only Prep & Notes tab + manual transcription (via Transcription tab)
- Queue job already dispatched before feature disabled: job checks config at execution time and aborts
- Tab refresh fails (network error): silently keep existing data, don't clear content

## Implementation Phases

### Phase 1: Config flags + job guards
- **Goal:** Add feature flags and guard jobs/controllers
- **Specs:**
  - [x] `config('meetings.recording.enabled')` defaults to `true`
  - [x] `config('meetings.transcription.enabled')` defaults to `true`
  - [x] `config('ai.enabled')` defaults to `true`
  - [x] `.env.example` documents all three flags
  - [x] `MeetingRecordingController::store()` returns 403 when recording disabled
  - [x] `TranscribeMeetingJob` aborts early (no exception) when transcription disabled
  - [x] `DiarizeMeetingJob` aborts early when transcription disabled
  - [x] `ExtractMeetingInsightsJob` aborts early when AI disabled
  - [x] `MeetingExtractionController::reExtract()` returns 403 when AI disabled
  - [x] `MeetingRecordingController::store()` does not dispatch transcription job when transcription disabled
- **Files:** `config/meetings.php`, `config/ai.php`, `.env.example`, `MeetingRecordingController.php`, `TranscribeMeetingJob.php`, `DiarizeMeetingJob.php`, `ExtractMeetingInsightsJob.php`, `MeetingExtractionController.php`, tests

### Phase 2: UI conditionals
- **Goal:** Hide/show tabs and controls based on feature flags
- **Specs:**
  - [x] Recording tab is hidden when `recording.enabled` is `false`
  - [x] AI Extractions tab is hidden when `ai.enabled` is `false`
  - [x] Transcription tab is always visible
  - [x] When `transcription.enabled` is `false`: auto-transcribe, retry, retranscribe buttons are hidden
  - [x] When `transcription.enabled` is `false`: manual input is shown by default (not behind a toggle)
  - [x] When `recording.enabled` is `false` and existing recordings exist: recordings remain visible (read-only, no delete/upload)
  - [x] When `ai.enabled` is `false` and existing extractions exist: extractions remain visible (read-only, no re-extract)
  - [x] `MeetingPageController::show()` passes feature flags to the view
  - [x] Default tab falls back gracefully (if recording tab hidden and URL has `?tab=recording`, redirect to `prep`)
- **Files:** `MeetingPageController.php`, `resources/views/pages/meetings/show.blade.php`

### Phase 3: Tab-switch data refresh
- **Goal:** Ensure tab content is always fresh when switching between tabs
- **Specs:**
  - [ ] Switching to the Transcription tab fetches latest status/content from `GET /api/v1/meetings/{id}/transcription`
  - [ ] Transcription tab updates status, content, diarization state, and error messages from the API response
  - [ ] If transcription was processing and is now complete, polling stops and content displays immediately
  - [ ] Switching to the AI Extractions tab fetches latest extractions from `GET /api/v1/meetings/{id}/extractions`
  - [ ] AI Extractions tab detects a newly available transcription (removes "no transcription" message, shows extraction UI)
  - [ ] Tab refresh is debounced — rapid tab switching does not fire multiple requests
  - [ ] Network errors during tab refresh are silently ignored (keep existing data)
- **Files:** `resources/views/pages/meetings/show.blade.php`

### Phase 4: Meeting history on team member page
- **Goal:** Show meaningful meeting content in member's meeting history instead of just `notes`
- **Specs:**
  - [ ] Meeting history cards show the meeting title
  - [ ] Meeting history cards show the AI summary when available (`$meeting->summary`)
  - [ ] Meeting history cards show transcription excerpt (first ~200 chars) when no summary or notes exist
  - [ ] Meeting history cards show accepted extraction count (tasks, follow-ups) as a compact indicator
  - [ ] "No notes recorded" only shows when there is genuinely no content (no notes, no summary, no transcription)
  - [ ] `TeamPageController` eager-loads `transcription` and `extractions` on `memberMeetings`
  - [ ] Meeting history respects feature flags (don't show AI summary section when `ai.enabled` is false)
- **Files:** `app/Http/Controllers/Web/TeamPageController.php`, `resources/views/pages/teams/member.blade.php`

## Out of Scope

- Per-user or per-meeting feature toggles (this is system-wide via `.env`)
- Admin UI for toggling features
- Graceful degradation of in-progress jobs when feature is disabled mid-execution
- Notification to user when features are disabled
- Real-time push updates (WebSocket/SSE) — tab-switch fetch is sufficient
