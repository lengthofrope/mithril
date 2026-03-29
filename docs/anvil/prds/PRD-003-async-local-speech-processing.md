# PRD-003: Async Local Speech Processing with Callback

**Created:** 2026-03-26
**Status:** Draft
**Author:** Bas de Kort
**Source:** direct input; follow-up to PRD-002 (deduplication fix)

## Problem Statement

When a user in local speech service mode starts transcription, the browser must remain on the meeting page for the entire duration of processing (up to 30+ minutes for long recordings with diarization). If the user navigates away, closes the tab, or the browser loses the connection, the speech service still completes the work but the result is never delivered to Mithril because the browser was the sole intermediary. This forces users to keep their browser idle on a single page for extended periods, which is a poor experience for a tool designed to help team leads manage their day.

PRD-002 addressed the duplicate processing bug but explicitly deferred this tab-dependency problem. The blocking modal introduced in PRD-002 is a stopgap that communicates the constraint but does not remove it.

## In Scope

- Allowing users to navigate away from the meeting page during local speech processing without losing the result
- Asynchronous job acceptance by the speech service (immediate response, process in background, deliver result via callback)
- A callback endpoint on the Mithril backend that receives completed transcription/diarization results from the speech service
- Authentication on the callback endpoint to prevent unauthorized result submission
- Support for both locally-hosted and remotely-hosted speech service instances
- Frontend changes to fire-and-forget the speech service request and switch to polling for completion

## Out of Scope

- Server-to-server audio transfer (audio is still sent from the browser to the speech service)
- Real-time streaming transcription
- Job cancellation (stopping an in-progress speech service job)
- Job progress reporting from the speech service (percentage, current step)
- Multi-GPU or distributed processing
- Changes to server-mode (queue-based) transcription flow

## User Roles

| Role | Goals | Key Interactions |
|------|-------|------------------|
| Team lead (local mode) | Start transcription, continue working, find result when returning to the meeting page | Visits meeting, triggers processing, navigates away, returns later to see completed transcription |
| Self-hosting administrator | Deploy speech service that can call back to the Mithril instance | Configures callback URL and authentication in speech service environment |

## Acceptance Criteria

1. **Fire-and-forget submission** ; when a local mode user triggers processing, the browser submits audio to the speech service and receives an immediate acknowledgment without waiting for the result
2. **Tab-independent completion** ; after submission, the user can navigate away, close the tab, or refresh the page; when the speech service finishes, the result is delivered to the Mithril backend regardless of browser state
3. **Callback delivery** ; the speech service POSTs the completed transcription/diarization result to a callback URL on the Mithril backend, authenticated with a per-job token
4. **Callback authentication** ; the callback endpoint rejects requests without a valid token; tokens are single-use and scoped to a specific meeting's transcription
5. **Status visibility** ; when the user returns to the meeting page while processing is in progress, the UI shows a processing indicator and polls for completion (same as PRD-002 server-mode behavior, no blocking modal needed)
6. **Result display** ; when processing completes (whether the user is on the page or not), the transcription is saved and displayed normally upon next page visit or via polling
7. **Failure handling** ; if the speech service fails to process the audio, it POSTs an error to the callback URL; the transcription status transitions to `failed` with an error message
8. **Remote compatibility** ; the callback mechanism works for speech service instances running on a different host than the Mithril backend, provided the callback URL is reachable from the speech service
9. **Backwards compatibility** ; the speech service continues to support synchronous processing (no callback parameter) for existing integrations and server-mode usage

## Constraints

- The callback URL must be reachable from the speech service's network context (this is a deployment requirement, not an application one)
- Callback tokens must not be reusable or guessable; they are single-use secrets
- The speech service's existing FIFO queue behavior must be preserved; async acceptance does not mean parallel processing
- The existing `/transcribe` and `/diarize` response formats must remain unchanged when no callback is provided (backwards compatibility)
- No new external dependencies on the speech service (no Redis, no database, no message broker)

## Dependencies

| Dependency | Impact | Status |
|------------|--------|--------|
| mithril-speech container | Must be updated to support async callback mode | Pending |
| Network reachability | Callback URL must be accessible from speech service | Deployment concern |
| PRD-002 implementation | The `/start-local` endpoint and `processing` status flow are prerequisites | Resolved |

## Success Metrics

| Metric | Target | How to Measure |
|--------|--------|----------------|
| Tab-independent completion rate | 100% of submitted jobs result in saved transcription regardless of browser state | MeetingTranscription records transition from processing to completed without browser involvement |
| Blocking modal removal | Modal from PRD-002 is no longer shown during async processing | Visual verification; modal code removed or bypassed |
| Callback delivery latency | < 5 seconds between speech service completion and result storage | ProcessingTimingLog comparison |

## Changelog

| Date | Change | Reason |
|------|--------|--------|
| 2026-03-26 | Initial draft | Follow-up to PRD-002; remove tab-dependency for local speech processing |
