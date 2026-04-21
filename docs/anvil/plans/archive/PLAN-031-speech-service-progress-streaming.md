# PLAN-031: Speech Service Progress Streaming via SSE

**Created:** 2026-04-21
**Status:** Complete
**Author:** Bas de Kort
**PRDs:** none

## Problem Statement

The Mithril speech service (`docker/speech/app/server.py`) exposes `/transcribe` and `/diarize` as blocking POST endpoints; clients receive a response only when processing completes, which can take minutes for long recordings. The transcription-viewer UI compensates with an *estimated* progress bar driven by elapsed time vs. an educated guess, which drifts and feels unresponsive. The speech service internally does have real progress signal (faster-whisper yields segments with timestamps, and total audio `duration` is available on the `TranscriptionInfo` object), but this information is discarded today. We want real, server-driven progress updates pushed to the browser.

## Acceptance Criteria

1. The speech service exposes SSE-streaming variants of transcription and diarization that emit stage + numeric progress events while processing, and terminate with a final `result` event carrying the same payload shape as today's JSON responses.
2. During Whisper transcription, progress is reported as a value between `0.0` and `1.0` derived from `segment.end / audio_duration`, emitted at least once per processed segment.
3. During diarization, the service emits stage events for `converting`, `diarizing`, `transcribing`, and `merging`; the `transcribing` stage additionally emits numeric progress like criterion 2. Pyannote phase reports stage-only (no numeric progress).
4. The existing blocking `POST /transcribe` and `POST /diarize` endpoints continue to work unchanged for backwards compatibility with existing clients.
5. SSE endpoints honor the same `X-Speech-Token` authentication, CORS, and Private Network Access behavior as the blocking endpoints.
6. The TypeScript client helper (`resources/js/services/local-speech-service.ts`) exposes `transcribeStream` and `diarizeStream` functions that return an async iterator of progress events plus a final result, wrapping `EventSource`/`fetch`-with-stream.
7. The `transcription-viewer` Alpine component uses streaming when the speech service reports a version capable of it (via `/health`), and falls back to the current blocking flow otherwise. The progress bar reflects real server-reported progress instead of the estimate.
8. Errors mid-stream surface as an `error` SSE event and are handled as terminal, mapped to `SpeechServiceError` in the client with the same UX as today's failures.
9. All new Python code is covered by pytest tests (streaming sequence, progress monotonicity, error path, auth, backwards-compat of blocking endpoints) and all new TypeScript code by Pest-equivalent unit coverage where applicable. Full `php artisan test --parallel`, `npx tsc --noEmit`, and `npm run build` remain green.

## Technical Design

### Approach

Add two new FastAPI endpoints (`POST /transcribe/stream` and `POST /diarize/stream`) returning `text/event-stream`. They run the same queued work as the blocking endpoints but wrap the core routine in a generator that yields SSE events. The existing blocking endpoints remain intact and share the per-request helpers.

Event format (one JSON payload per SSE `data:` line):

```
event: progress
data: {"stage": "transcribing", "progress": 0.42, "elapsed_s": 12.1}

event: stage
data: {"stage": "diarizing"}

event: result
data: { ...same shape as current JSON response... }

event: error
data: {"detail": "message"}
```

On the client, `local-speech-service.ts` gains `transcribeStream` / `diarizeStream` that use `fetch` with a `ReadableStream` (EventSource can't POST a body). A small SSE line parser yields typed events to the caller via an `AsyncGenerator`.

The `transcription-viewer` Alpine component gets a new `overallProgressPercent` path: when streaming is used, the stream's `progress` value wins; otherwise the existing estimate remains.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `docker/speech/app/server.py` | Modify | Add `/transcribe/stream` and `/diarize/stream` SSE endpoints, refactor core routines to accept an optional progress callback, expose capability flag via `/health`. |
| `docker/speech/app/tests/test_stream.py` | Create | New pytest module covering SSE happy path, monotonic progress, stage sequence, error mid-stream, auth, and blocking-endpoint regression. |
| `docker/speech/app/tests/test_transcribe.py` | Modify | Re-assert blocking endpoint still returns the current contract unchanged. |
| `docker/speech/app/tests/test_diarize.py` | Modify | Re-assert blocking endpoint still returns the current contract unchanged. |
| `resources/js/services/local-speech-service.ts` | Modify | Add `transcribeStream`, `diarizeStream`, SSE line parser, new event types. Keep existing `transcribe`/`diarize` unchanged. |
| `resources/js/types/speech-service.ts` | Modify | Add `SpeechProgressEvent`, `SpeechStageEvent`, `SpeechStreamEvent` union types; extend `SpeechServiceHealthResponse` with a `streaming: boolean` capability flag. |
| `resources/js/components/transcription-viewer.ts` | Modify | In `processLocally()`, prefer streaming when `health.streaming === true`; wire progress events to a new `streamProgress` state that drives `overallProgressPercent`. |
| `tests/js/` or nearest equivalent | Create/Modify | Unit coverage for the SSE parser and the stream wrappers (mocked Response bodies). |

### Data Flow

```
Browser (Alpine)              FastAPI /transcribe/stream        faster-whisper
     |                                  |                            |
     |--- POST audio (SSE accept) ----->|                            |
     |                                  |--- transcribe(audio) ----->|
     |                                  |<-- yield segment 1 --------|
     |<---- event: progress 0.12 -------|                            |
     |                                  |<-- yield segment 2 --------|
     |<---- event: progress 0.27 -------|                            |
     |                                  |         ...                |
     |<---- event: progress 1.00 -------|                            |
     |<---- event: result {text:...} ---|                            |
```

### Edge Cases & Error Handling

- **Audio shorter than one segment:** single progress event at 1.0 followed by `result`.
- **Client disconnects mid-stream:** detect via `await request.is_disconnected()`; abort the worker and release the FIFO semaphore cleanly.
- **ffmpeg conversion failure:** emit `error` event with the ffmpeg stderr tail, close stream with 200 (SSE convention; error is in-band).
- **Auth failure:** return HTTP 401 *before* opening the stream (same as blocking path); client sees a normal fetch rejection, not an SSE error event.
- **Models not loaded:** return HTTP 503 before opening the stream.
- **Non-monotonic segment timestamps from Whisper:** clamp reported progress to `max(prev, current)` so the bar never goes backwards.
- **Pyannote stage with no numeric progress:** emit only `stage` event; UI treats this as indeterminate (animated bar) for that stage.
- **Concurrency:** streaming endpoints use the same `_processing_semaphore` as blocking ones; the stream sends a `queued` stage event while waiting.

## Implementation Phases

### Phase 1: Server — refactor cores to accept progress callback, add blocking-endpoint regression tests

- **Goal:** Extract a progress-aware core so the existing blocking endpoints call it with a no-op callback; no behavioral change yet, safety net in place.
- **Model:** sonnet
- **Specs:**
  - [x] `_transcribe_audio` accepts an optional `on_progress: Callable[[float], None]` and invokes it per segment with `min(1.0, max(prev, seg.end / info.duration))`.
  - [x] `_diarize_and_transcribe` accepts an optional `on_stage: Callable[[str], None]` and `on_progress` callback; emits stages `diarizing`, `transcribing`, `merging` at appropriate points.
  - [x] `ensure_wav` accepts an optional `on_stage` and emits `converting` / completion.
  - [x] Existing `POST /transcribe` and `POST /diarize` continue to return the exact current payloads (byte-for-byte compatible with existing tests).
  - [x] All current tests in `test_transcribe.py`, `test_diarize.py`, `test_queue.py`, `test_auth.py`, `test_pyannote.py` still pass unmodified.
- **Files:** `docker/speech/app/server.py`, `docker/speech/app/tests/test_transcribe.py`, `docker/speech/app/tests/test_diarize.py`

### Phase 2: Server — SSE streaming endpoints + tests

- **Goal:** Ship the `/transcribe/stream` and `/diarize/stream` endpoints with full test coverage.
- **Model:** opus
- **Specs:**
  - [x] `POST /transcribe/stream` returns `text/event-stream` and emits at least one `progress` event per Whisper segment, then a single `result` event with `{"text": "..."}`.
  - [x] `POST /diarize/stream` emits stage events `converting`, `diarizing`, `transcribing`, `merging` in order, progress events during `transcribing`, and a final `result` event with the full diarized payload.
  - [x] Reported `progress` is monotonically non-decreasing within a single request.
  - [x] Authentication behaves identically to blocking endpoints (`401` before stream opens when `SPEECH_AUTH_TOKEN` set and token missing/wrong).
  - [x] Returns `503` before stream opens when models are not ready.
  - [x] When the worker raises, an `error` event is emitted and the stream closes; the FIFO semaphore is released.
  - [x] When `await request.is_disconnected()` becomes true, processing is cancelled and the semaphore is released.
  - [x] `GET /health` returns `"streaming": true` in its response.
  - [x] A new `docker/speech/app/tests/test_stream.py` covers: event sequence, monotonic progress, error mid-stream, auth, 503 when models unloaded, and client-disconnect cleanup.
- **Files:** `docker/speech/app/server.py`, `docker/speech/app/tests/test_stream.py`

### Phase 3: TypeScript client — SSE parser, stream wrappers, type updates

- **Goal:** Provide `transcribeStream` / `diarizeStream` returning a typed async iterator; no UI wiring yet.
- **Model:** sonnet
- **Specs:**
  - [x] A small SSE parser function splits a `ReadableStream<Uint8Array>` into typed `{event, data}` chunks, handling multi-line `data:` fields and blank-line event boundaries.
  - [x] `transcribeStream(audioBlob, language, url, token)` returns an `AsyncGenerator<SpeechStreamEvent>` yielding progress/stage events and terminating with a `result` event.
  - [x] `diarizeStream(...)` behaves analogously with the diarize payload on the final `result`.
  - [x] Errors (HTTP non-2xx before stream, mid-stream `error` event, network drop) map to `SpeechServiceError` with the existing `statusCode` semantics.
  - [x] `SpeechServiceHealthResponse` type gains a `streaming?: boolean` field; helper `supportsStreaming(health)` exposes the check.
  - [x] Existing `transcribe`/`diarize`/`health` signatures and behavior are unchanged.
- **Files:** `resources/js/services/local-speech-service.ts`, `resources/js/types/speech-service.ts`

### Phase 4: UI — wire progress into transcription-viewer, capability-gated with fallback

- **Goal:** The meeting transcription page shows real server-driven progress when available, with graceful fallback.
- **Model:** sonnet
- **Specs:**
  - [x] `processLocally()` calls `/health` once at the start of a local run and caches `streaming` capability.
  - [x] When `streaming === true`, it invokes `transcribeStream` / `diarizeStream`; each `progress` event updates a new `streamProgress` state (0-1) and each `stage` event updates `streamStage`.
  - [x] `overallProgressPercent` returns `Math.round(streamProgress * 100)` when `streamProgress !== null`; otherwise falls back to the existing estimate.
  - [x] `currentPhaseLabel` reflects the SSE `stage` when streaming (e.g. "Converting audio…", "Identifying speakers…", "Transcribing audio…", "Merging segments…").
  - [x] When streaming is unavailable or the health call fails, the component uses the existing blocking flow unchanged (no regressions).
  - [x] On error mid-stream, `localProcessingError` is set and the UI shows the same retry affordance as today.
  - [x] `npx tsc --noEmit` and `npm run build` pass; no new console warnings in dev.
- **Files:** `resources/js/components/transcription-viewer.ts`, `resources/js/services/local-speech-service.ts`, `resources/js/types/speech-service.ts`

## Parallelization

**Strategy:** Partial parallel
**Execution method:** Subagents

Phase 1 is a prerequisite for Phase 2 (shared refactor). Phase 3 touches only TypeScript and can run concurrently with Phase 2 since they don't share files. Phase 4 depends on Phase 3 (client helpers) and should validate against Phase 2's real server, so it runs last.

Order: Phase 1 → (Phase 2 ∥ Phase 3) → Phase 4.

## Review

**Review strategy:** per-phase

Four phases crossing Python, TypeScript, and UI layers, with convention-heavy code (error mapping, auth semantics, FIFO queue interaction) where early drift would compound. Per-phase review catches that drift before it reaches the UI wiring in Phase 4.

## Out of Scope

- Server-side transcription/diarization (the Laravel-side provider path): only the per-user *local* speech service client path changes in this plan.
- Cancelling a running transcription from the UI (stream cancel is supported, but no UI button).
- Replacing the existing blocking endpoints — they remain for backwards compatibility.
- Persisting progress across page reloads.
- Speaker-count hints or speaker-renaming during streaming.

## Open Questions

- Should stream events also include `elapsed_s` on the server side (cheap; useful for UI ETA) or keep it client-computed? Default: include on server, ignore on client if not needed.
- Should we add a `stream=true` query param to the existing `/transcribe` and `/diarize` routes instead of new `/stream` endpoints? New endpoints chosen for clarity and easier OpenAPI typing; revisit if it doubles test cost.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
