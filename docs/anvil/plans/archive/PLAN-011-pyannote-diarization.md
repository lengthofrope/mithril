# Plan: Add pyannote-audio Diarization Service

## Context

Mithril's meeting transcription pipeline currently produces plain text via whisper.cpp — no speaker attribution. Adding pyannote-audio for speaker diarization will label *who said what*, improving both the transcription display and downstream AI extraction (GPT can attribute action items to specific attendees).

pyannote-audio is a Python library with gated HuggingFace models. There's no official Docker image, so we need a custom one with a FastAPI HTTP wrapper.

## Architecture Decision

**The pyannote service handles both diarization AND timestamped transcription internally** (via `faster-whisper`), returning merged speaker-labeled segments. This avoids:
- Changing whisper.cpp's response format (currently returns plain text)
- Complex cross-service timestamp alignment in PHP
- HTTP round-trips between containers

**Diarization is a separate post-transcription step**, not a replacement:
1. `TranscribeMeetingJob` → whisper.cpp → plain text (fast, immediate feedback)
2. `DiarizeMeetingJob` → pyannote service → speaker-labeled segments (slower, enriches result)

If diarization fails, the plain-text transcription remains valid.

---

## Phase 1: Docker Service (`docker/pyannote/`)

### Files to create

**`docker/pyannote/Dockerfile`**
- Base: `python:3.11-slim` (cpu profile) / `nvidia/cuda:12.1-runtime-ubuntu22.04` (cuda profile)
- Install: `pyannote.audio`, `faster-whisper`, `fastapi`, `uvicorn`, `python-multipart`
- Copy `server.py` into image
- Expose port 8081
- Entrypoint: `uvicorn server:app --host 0.0.0.0 --port 8081`

**`docker/pyannote/server.py`** — FastAPI app
- `GET /health` — readiness check
- `POST /diarize` — accepts multipart `file` + `language` param
  - Runs pyannote `Pipeline.from_pretrained("pyannote/speaker-diarization-3.1")` → speaker segments
  - Runs `faster-whisper` with word timestamps → text segments
  - Merges by timestamp overlap: each whisper segment gets the speaker with maximum overlap
  - Returns JSON:
    ```json
    {
      "segments": [
        {"speaker": "SPEAKER_00", "start": 0.0, "end": 3.5, "text": "..."},
        ...
      ],
      "speakers": ["SPEAKER_00", "SPEAKER_01"]
    }
    ```
- Models cached in `/models` volume (loaded once at startup)

**`docker/pyannote/docker-compose.yml`** — mirrors whisper.cpp pattern
- YAML anchor `x-common` for shared config
- Two profiles: `cpu` and `cuda`
- Port: `${PYANNOTE_PORT:-8081}:8081`
- Volume: `pyannote-models:/models` (persistent, external)
- Env: `HUGGINGFACE_TOKEN` (required, gated model), `WHISPER_MODEL_SIZE` (default: `large-v3-turbo`)

**`docker/pyannote/setup.sh`** — mirrors whisper.cpp setup
- Auto-detects NVIDIA GPU → cuda or cpu profile
- Validates `HUGGINGFACE_TOKEN` is set (with instructions for obtaining one)
- Creates Docker volume
- Builds image, starts service
- Prints health check + test curl

**`docker/pyannote/.env.example`**, **`docker/pyannote/.env`**

> **HuggingFace token setup** (user doesn't have one yet):
> The setup script and README will include instructions to:
> 1. Create a HuggingFace account
> 2. Accept the pyannote/speaker-diarization-3.1 license at the model page
> 3. Generate an access token at hf.co/settings/tokens
> 4. Set it as `HUGGINGFACE_TOKEN` in `.env`

## Phase 2: Laravel Service Layer

### New files

**`app/Services/Diarization/DiarizationServiceInterface.php`**
```php
public function diarize(string $audioPath, string $language): DiarizationResult;
```

**`app/Services/Diarization/DiarizationResult.php`** — value object
- `segments`: `array<DiarizedSegment>` (speaker, start, end, text)
- `speakers`: `array<string>` unique speaker IDs
- `toJson(): string` — serialize for `diarized_content` column
- `toFormattedText(): string` — `"[Speaker 1]\ntext\n\n[Speaker 2]\ntext\n..."` for `content` column
- `static fromJson(string $json): self` — deserialize

**`app/Services/Diarization/DiarizedSegment.php`** — value object for one segment

**`app/Services/Diarization/PyAnnoteDiarizationService.php`**
- POSTs audio to `/diarize` endpoint (multipart)
- Timeout: 1800s (diarization is ~0.1x real-time on GPU)
- Parses JSON into `DiarizationResult`
- Pattern follows `WhisperCppTranscriptionService` exactly

### Config additions — `config/meetings.php`
```php
'diarization' => [
    'enabled' => env('MEETING_DIARIZATION_ENABLED', false),
    'pyannote' => [
        'base_url' => env('PYANNOTE_BASE_URL', 'http://localhost:8081'),
    ],
],
```

### Service binding — `app/Providers/AppServiceProvider.php`
Conditional bind `DiarizationServiceInterface` → `PyAnnoteDiarizationService`

## Phase 3: Migration + Model

**Migration: `add_diarization_fields_to_meeting_transcriptions`**
- `diarized_content` longText nullable — structured JSON segments
- `diarization_status` string nullable — pending/processing/completed/failed
- `diarization_error` text nullable

**`app/Enums/DiarizationStatus.php`** — string-backed, mirrors `TranscriptionStatus`

**`app/Models/MeetingTranscription.php`** — add new fields to `$fillable`

## Phase 4: Job + Pipeline

**`app/Jobs/DiarizeMeetingJob.php`**
- Queued, 2 retries, backoff [60, 300]
- Calls `DiarizationServiceInterface::diarize()`
- On success:
  - Stores structured JSON in `diarized_content`
  - Overwrites `content` with `result->toFormattedText()` (extraction pipeline benefits immediately)
  - Sets `diarization_status = completed`
- On failure: sets status + error, `content` keeps plain text from whisper.cpp

**`app/Http/Controllers/Api/MeetingRecordingController.php`** — modify `store()`
- When diarization enabled: `Bus::chain([TranscribeMeetingJob, DiarizeMeetingJob])->dispatch()`
- When disabled: current behavior (just `TranscribeMeetingJob`)

**`app/Http/Controllers/Api/MeetingTranscriptionController.php`** — extend
- `show()` includes `diarized_content`, `diarization_status` in response
- New `diarize()` method — manually trigger diarization
- New `retryDiarization()` method

**`routes/api.php`** — new endpoints
- `POST meetings/{meeting}/transcription/diarize`
- `POST meetings/{meeting}/transcription/retry-diarization`

## Phase 5: View Updates — DEFERRED

UI display of speaker-labeled transcription deferred to a follow-up conversation.
For now, `content` column will contain formatted text like `[Speaker 1]\ntext\n\n[Speaker 2]\ntext\n...`
which renders acceptably as plain text in the existing view.

## Phase 6: Tests

- `tests/Unit/Jobs/DiarizeMeetingJobTest.php` — mirror TranscribeMeetingJob test structure
- `tests/Unit/Services/Diarization/PyAnnoteDiarizationServiceTest.php` — mock HTTP
- `tests/Unit/Services/Diarization/DiarizationResultTest.php` — value object serialization
- Feature tests for new API endpoints

## Key Files to Modify

| File | Change |
|------|--------|
| `config/meetings.php` | Add `diarization` config block |
| `app/Providers/AppServiceProvider.php` | Bind DiarizationServiceInterface |
| `app/Models/MeetingTranscription.php` | Add diarization fields to $fillable |
| `app/Http/Controllers/Api/MeetingRecordingController.php` | Chain DiarizeMeetingJob when enabled |
| `app/Http/Controllers/Api/MeetingTranscriptionController.php` | Add diarize/retry endpoints |
| `routes/api.php` | Add diarization routes |
| `resources/views/pages/meetings/show.blade.php` | Deferred — future follow-up |

## Verification

1. `./docker/pyannote/setup.sh` starts the service, `curl localhost:8081/health` returns 200
2. `curl -F file=@test.wav -F language=en localhost:8081/diarize` returns speaker-labeled segments
3. `php artisan test` — all existing + new tests pass
4. `npx tsc --noEmit && npm run build` — clean
5. Upload a recording in the UI → transcription appears → diarization processes → speaker labels appear
