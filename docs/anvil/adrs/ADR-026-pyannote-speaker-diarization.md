## ADR-026: Speaker diarization via self-hosted pyannote-audio service

**Date:** 2026-03-19
**Phase:** Meetings (Diarization)
**Tags:** backend, docker, meetings, diarization, pyannote, transcription
**Status:** Accepted

### Context

Meeting transcriptions are currently plain text — no attribution of who said what. This limits the usefulness of both the transcription display and the downstream AI extraction pipeline, which relies on heuristic name-matching to assign action items to attendees.

Adding speaker diarization ("who spoke when") significantly improves both:
1. **Transcription readability** — speaker-labeled turns instead of a text wall
2. **AI extraction quality** — GPT can attribute action items to the correct attendee when the transcript includes speaker labels

Alternatives considered for the diarization approach:
1. **Extend whisper.cpp with timestamps + align in PHP** — rejected because whisper.cpp currently returns plain text, and timestamp alignment in PHP is complex
2. **Replace whisper.cpp with a combined Python service** — rejected because it would remove the fast initial transcription feedback
3. **Self-contained pyannote service with faster-whisper for timestamps** — accepted; the Python service handles diarization, timestamped transcription, and merging internally

### Decision

**Architecture:**
- A new Docker service (`docker/pyannote/`) runs a FastAPI server wrapping pyannote-audio (diarization) and faster-whisper (timestamped transcription)
- The service accepts audio via `POST /diarize`, runs both pipelines, merges by timestamp overlap, and returns speaker-labeled segments
- This duplicates the whisper model (~1.5GB) in the pyannote container, accepted for self-containment

**Pipeline:**
- Diarization is a **separate post-transcription step**, not a replacement
- `TranscribeMeetingJob` (whisper.cpp) runs first → immediate plain-text result
- `DiarizeMeetingJob` (pyannote) runs second → enriches with speaker labels
- Jobs are chained via `Bus::chain()` when diarization is enabled
- If diarization fails, the plain-text transcription remains valid

**Data model:**
- `MeetingTranscription` gains three nullable columns: `diarized_content` (longText, structured JSON), `diarization_status` (string enum), `diarization_error` (text)
- On success, `content` is overwritten with formatted speaker-labeled text (`[SPEAKER_00]\ntext`), so the extraction pipeline benefits immediately with zero changes
- `diarized_content` stores the full structured segments for future UI rendering

**Configuration:**
- Diarization is opt-in: `MEETING_DIARIZATION_ENABLED=false` by default
- pyannote requires a gated HuggingFace token

### Consequences

- **New Docker service** — separate `docker-compose.yml` with cpu/cuda profiles, setup script with model download
- **New Laravel service layer** — `DiarizationServiceInterface`, `PyAnnoteDiarizationService`, value objects (`DiarizationResult`, `DiarizedSegment`)
- **New job** — `DiarizeMeetingJob` with 2 retries, 60/300s backoff
- **Migration** — adds 3 nullable columns to `meeting_transcriptions`
- **New API endpoints** — `POST .../transcription/diarize` and `POST .../transcription/retry-diarization`
- **No breaking changes** — diarization is disabled by default, all existing behavior is preserved
- **Disk usage** — ~1.5GB additional for the faster-whisper model in the pyannote volume
- **GPU recommended** — pyannote is ~0.1x real-time on GPU; significantly slower on CPU
