# Speech Service Token Authentication

**Created:** 2026-03-22
**Status:** Complete
**Author:** Bas de Kort + Claude

## Problem Statement

The speech service Docker container (`docker/speech/`) exposes its endpoints (`/health`, `/transcribe`, `/diarize`) without authentication, making them accessible to anyone who can reach the network. A simple token-based auth mechanism is needed to restrict access.

## Acceptance Criteria

1. The speech service FastAPI app requires an `X-Speech-Token` header on `/transcribe` and `/diarize` endpoints
2. When `SPEECH_AUTH_TOKEN` is empty or unset in Docker, token auth is disabled (backwards compatible)
3. Requests without the header or with the wrong value return 401 Unauthorized
4. `/health` remains accessible without authentication (monitoring/liveness probes)
5. Laravel sends the configured token with every HTTP request to the speech service
6. CORS middleware added to the FastAPI service (preparation for future browser-based access)
7. All existing tests remain passing; new behavior is covered by tests

## Technical Design

### Approach

Two-sided change: FastAPI gets a middleware that validates the `X-Speech-Token` header against a `SPEECH_AUTH_TOKEN` env var. Laravel's speech service classes get an optional `$authToken` constructor parameter that is sent as header when non-empty.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `docker/speech/app/server.py` | Modify | Add token auth middleware + CORS middleware |
| `docker/speech/.env.example` | Modify | Add `SPEECH_AUTH_TOKEN`, `SPEECH_CORS_ORIGINS` |
| `.env.example` | Modify | Add `SPEECH_AUTH_TOKEN` |
| `config/meetings.php` | Modify | Add `speech_auth_token` config key |
| `app/Services/Transcription/UnifiedSpeechTranscriptionService.php` | Modify | Accept + send auth token header |
| `app/Services/Diarization/UnifiedSpeechDiarizationService.php` | Modify | Accept + send auth token header |
| `app/Providers/AppServiceProvider.php` | Modify | Pass token to service constructors |

### Edge Cases & Error Handling

- `SPEECH_AUTH_TOKEN` empty in Docker → auth disabled (open access, backwards compatible)
- `SPEECH_AUTH_TOKEN` set in Docker but not in Laravel `.env` → requests get 401 (clear error in job failure log)
- Token mismatch → 401 with descriptive error message
- CORS origins default to `*` for development; configurable via `SPEECH_CORS_ORIGINS` for production

## Implementation Phases

### Phase 1: FastAPI Token Auth + CORS
- **Goal:** Secure the speech service endpoints with optional token authentication
- **Specs:**
  - [x] FastAPI middleware checks `X-Speech-Token` header against `SPEECH_AUTH_TOKEN` env var on `/transcribe` and `/diarize`
  - [x] Requests without the header or with wrong value return 401 Unauthorized with JSON error body
  - [x] When `SPEECH_AUTH_TOKEN` is empty or unset, no auth check is performed
  - [x] `/health` endpoint remains accessible without authentication
  - [x] CORS middleware added via FastAPI `CORSMiddleware`, origins configurable via `SPEECH_CORS_ORIGINS` env var (default `*`)
  - [x] `.env.example` updated with `SPEECH_AUTH_TOKEN` and `SPEECH_CORS_ORIGINS`
- **Files:** `docker/speech/app/server.py`, `docker/speech/.env.example`

### Phase 2: Laravel Token Integration
- **Goal:** Laravel sends the auth token with all speech service HTTP requests
- **Specs:**
  - [x] `config/meetings.php` adds `'speech_auth_token' => env('SPEECH_AUTH_TOKEN', '')` under a new `'speech'` key
  - [x] `UnifiedSpeechTranscriptionService` constructor accepts optional `string $authToken = ''` parameter
  - [x] When `$authToken` is non-empty, it is sent as `X-Speech-Token` header on the HTTP request
  - [x] `UnifiedSpeechDiarizationService` gets the same `$authToken` parameter and header logic
  - [x] `AppServiceProvider` passes `config('meetings.speech.auth_token')` to both service constructors
  - [x] `.env.example` updated with `SPEECH_AUTH_TOKEN` variable
  - [x] Existing tests remain green (token defaults to empty string, no behavioral change when empty)
- **Files:** `config/meetings.php`, `.env.example`, `UnifiedSpeechTranscriptionService.php`, `UnifiedSpeechDiarizationService.php`, `AppServiceProvider.php`

## Out of Scope

- Per-user tokens (covered by the separate per-user speech service plan)
- Token rotation or expiration
- Rate limiting
- Admin UI for token management

## Open Questions

None.
