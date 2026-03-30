# PRD-002: Local Speech Processing Deduplication

**Created:** 2026-03-26
**Status:** Approved
**Author:** Bas de Kort
**Source:** direct input; production observation of repeated processing

## Problem Statement

When a user in local speech service mode visits a meeting page that has recordings, the frontend immediately sends the audio to the local speech service for processing. However, this processing is not tracked server-side; the `MeetingTranscription` record is only created after the speech service returns a result (which can take 15+ minutes for long recordings). Every subsequent page visit sees `status: null` and fires off another processing request. This causes the speech service to process the same audio file repeatedly (observed: 6+ concurrent jobs for the same 15-minute recording), overloading the service and preventing any result from being saved because the browser's long-running fetch is likely interrupted before completion.

The user sees the transcription stuck at "Transcribing & identifying speakers" indefinitely, with no result ever arriving.

## In Scope

- Preventing duplicate local processing requests for the same meeting
- Server-side state tracking when local processing begins
- Ensuring the frontend correctly resumes or polls when processing is already in progress

## Out of Scope

- Changes to the mithril-speech Docker container or its queue behavior
- Server-side (queue-based) transcription job deduplication (already handled by the `processing` status check in `retry()`)
- Speech service performance optimization
- Browser tab lifecycle management (Service Worker-based processing)

## User Roles

| Role | Goals | Key Interactions |
|------|-------|------------------|
| Team lead (local mode user) | Record a meeting, leave the page, come back later to find the transcription complete | Visits meeting page, sees processing indicator, returns later to see result |

## Acceptance Criteria

1. **Single processing request** ; when a local mode user visits a meeting page with recordings and no existing transcription, exactly one processing request is sent to the speech service, regardless of how many times the page is visited or refreshed
2. **Server-side processing state** ; before audio is sent to the local speech service, a `MeetingTranscription` record exists with `status: processing` so that subsequent page loads see this state
3. **Processing indicator on revisit** ; when a user revisits a page where local processing was started (status is `processing`), the UI shows the processing indicator and polls for completion rather than re-triggering processing
4. **Result delivery** ; when the speech service completes and the browser posts the result to `/client-result`, the transcription status transitions from `processing` to `completed` and the content is displayed
5. **Idempotent start** ; if the "start processing" endpoint is called multiple times (race condition), it does not create duplicate records or reset an already-processing transcription

## Constraints

- The fix must not change the existing `/client-result` endpoint contract
- The fix must not affect server-side (queue-based) transcription flow
- No new JavaScript dependencies

## Dependencies

| Dependency | Impact | Status |
|------------|--------|--------|
| mithril-speech container | Must be running for local processing to work | Resolved (existing) |
| Existing `ClientTranscriptionController` | Result submission endpoint; must remain compatible | Resolved (existing) |

## Success Metrics

| Metric | Target | How to Measure |
|--------|--------|----------------|
| Duplicate processing requests per meeting | 0 | Docker logs show exactly 1 processing job per meeting visit cycle |
| Transcription completion rate (local mode) | 100% of started jobs result in saved transcription | MeetingTranscription records transition from processing to completed |

## Changelog

| Date | Change | Reason |
|------|--------|--------|
| 2026-03-26 | Initial draft | Production bug observed: 6+ duplicate processing jobs |
