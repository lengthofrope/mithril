# PRD: Unified Speech Processing Service

**Created:** 2026-03-22
**Status:** Approved
**Source:** User request — consolidate transcription and diarization infrastructure

## Problem Statement

The current speech processing setup requires two separate Docker containers (whisper.cpp for transcription, pyannote-audio for diarization), each downloading large models independently (~8GB+ combined). GPU support is unreliable across CUDA and Vulkan profiles, and managing two containers with separate configurations, health checks, and failure modes adds unnecessary operational complexity. A single, self-contained service would halve the infrastructure footprint and simplify deployment on both development machines and production hosting.

## Scope

### In Scope

- Single Docker container exposing separate `/transcribe` and `/diarize` HTTP endpoints
- Transcription via faster-whisper (replaces both whisper.cpp server and OpenAI Whisper cloud API as a self-hosted option)
- Default diarization engine that works without HuggingFace gated model access (e.g. faster-whisper + VAD-based speaker clustering)
- Optional pyannote-powered diarization for users who provide a HuggingFace token
- CPU and CUDA GPU support in a single image, auto-detected at runtime
- `/health` endpoint reporting readiness, device type, loaded models, and queue depth
- Built-in FIFO request queue — requests are processed one at a time to prevent GPU/memory contention; additional requests wait in order
- Model download and caching on first startup via a persistent volume
- Deployment compatibility with VPS, bare-metal, and managed Docker platforms (Railway, Fly.io, Coolify)
- New Laravel service implementation (`UnifiedSpeechService`) consuming this service
- Updated configuration and provider bindings to support the new provider alongside existing ones
- Docker Compose file and setup script for local development
- Documentation for deployment and configuration

### Out of Scope

- Vulkan GPU support (dropped due to unreliability)
- OpenAI Whisper cloud API integration (existing `WhisperTranscriptionService` remains as a separate provider)
- Changes to the AI extraction pipeline or frontend UI
- Multi-GPU or distributed processing
- Speaker identification (mapping speaker labels to known names/attendees)
- Real-time / streaming transcription

## Acceptance Criteria

1. A single `docker compose up` command starts one container that serves both `/transcribe` and `/diarize` endpoints
2. `POST /transcribe` accepts an audio file and language parameter, returns transcribed text in JSON format
3. `POST /diarize` accepts an audio file and language parameter, returns speaker-labeled segments in the same JSON format the current pyannote service uses (`{ segments: [...], speakers: [...] }`)
4. The service starts and transcribes successfully on a CPU-only host (no GPU required)
5. The service automatically detects and uses a CUDA GPU when available, without config changes
6. When no HuggingFace token is provided, diarization uses a built-in engine that requires no gated model access
7. When a HuggingFace token is provided, diarization uses pyannote for higher quality results
8. `GET /health` returns service status, device type (cpu/cuda), loaded models, diarization engine in use, and current queue depth
9. Requests are processed in FIFO order — only one transcription or diarization job runs at a time; concurrent requests queue and wait their turn
10. Models are downloaded on first startup and cached in a Docker volume; subsequent starts use the cache
11. Total model disk footprint for the default configuration (transcription + default diarization) is under 4GB
12. The existing Laravel `TranscriptionServiceInterface` and `DiarizationServiceInterface` contracts are satisfied by the new service implementations without changes to the interfaces
13. The provider can be selected via `config/meetings.php` environment variables, coexisting with existing providers (whisper_cpp, whisper cloud)
14. The old whisper.cpp and pyannote Docker directories can be removed after migration
15. Whisper model size is configurable via `WHISPER_MODEL` environment variable (e.g. `tiny` for development, `large-v3-turbo` for production)

## Constraints

- **Response format compatibility:** The `/diarize` endpoint must return the exact same JSON structure as the current pyannote service so `DiarizationResult::fromResponse()` works without changes
- **Response format compatibility:** The `/transcribe` endpoint must return JSON with a `text` field, matching the whisper.cpp server format
- **No gated models by default:** The default diarization engine must not require HuggingFace license acceptance or token
- **Single image:** One Dockerfile, one image — no separate CPU/CUDA builds. Runtime detection only.
- **Stateless:** The service must not persist any data beyond the model cache volume. All audio files are transient.
- **No artificial timeouts:** Processing time depends on audio length and hardware. The service should not impose internal time limits.
- **FIFO processing:** Only one job at a time to prevent GPU/memory contention. The queue is in-memory (no external broker).

## Dependencies

| Dependency | Type | Status | Impact |
|-----------|------|--------|--------|
| faster-whisper Python package | External library | Stable | Core transcription engine |
| pyannote-audio Python package | Optional library | Stable | High-quality diarization option |
| NVIDIA Container Toolkit | Optional runtime | Stable | Required for GPU acceleration |
| HuggingFace Hub | Optional service | Stable | Required only for pyannote model download |

## User Roles & Interactions

### Self-hosting administrator

- **Goal:** Deploy speech processing with minimal configuration on their infrastructure
- **Key interactions:** Runs Docker Compose, optionally provides HF token for pyannote, monitors via `/health` endpoint

### Application (Mithril Laravel backend)

- **Goal:** Transcribe and diarize meeting recordings via HTTP API
- **Key interactions:** Posts audio files to `/transcribe` and `/diarize`, receives JSON results, handles errors and timeouts

## Success Metrics

- Infrastructure reduced from 2 containers + 2 volumes to 1 container + 1 volume
- Default model disk footprint reduced from ~8GB to under 4GB
- Setup time for a new developer: under 5 minutes (one `docker compose up` + model download)
- Transcription quality parity with current whisper.cpp setup (same model, same output)
- GPU detection works reliably without manual profile selection

## Open Questions

- [ ] Which non-gated diarization approach to use as default? Research narrowed to: (1) **`diarize` by FoxNoseTech** — ONNX-only, ~10.8% DER, Apache 2.0, no PyTorch needed, but very new (Mar 2026); (2) **DIY with SpeechBrain ECAPA-TDNN + Silero-VAD + spectral clustering** — proven libs, ~18-25% DER, more engineering effort. NeMo disqualified (CC-BY-NC license, bloated). Needs prototype spike to validate `diarize` quality on real meeting recordings. — Bob — blocks diarization implementation

### Resolved

- **Configurable model sizes:** Yes — support via `WHISPER_MODEL` env var (e.g. `tiny` for dev, `large-v3-turbo` for production)
- **Base Docker image:** `nvidia/cuda:12.x-runtime` — works with and without GPU, no separate builds needed
