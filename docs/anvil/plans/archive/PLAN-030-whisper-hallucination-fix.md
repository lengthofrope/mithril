# PLAN-030: Whisper Transcription Hallucination Fix

**Created:** 2026-04-21
**Status:** Complete
**Author:** Bas de Kort
**PRDs:** none

## Problem Statement

The speech service's faster-whisper transcription produces runaway repetition loops (e.g. `"Ja. Ja ja ja. Ja."`) after a few minutes of audio. The cause is not the `large-v3-turbo` model itself; it is how `WhisperModel.transcribe()` is configured. Greedy decoding (`temperature=0.0` with no fallback), absence of VAD filtering, and default `condition_on_previous_text=True` together let Whisper enter a self-reinforcing loop on silence or ambiguous audio and carry it forward indefinitely.

## Acceptance Criteria

1. `_transcribe_audio` in [docker/speech/app/server.py](docker/speech/app/server.py) calls `whisper_model.transcribe(...)` with `condition_on_previous_text=False`.
2. The same call passes a temperature fallback ladder (`[0.0, 0.2, 0.4, 0.6, 0.8, 1.0]`) instead of a scalar `0.0`.
3. The same call enables `vad_filter=True` with `vad_parameters={"min_silence_duration_ms": 500}`.
4. The same call passes `compression_ratio_threshold=2.4`, `log_prob_threshold=-1.0`, and `no_speech_threshold=0.6` as explicit hallucination guards.
5. The `_diarize_and_transcribe` code path applies the same guards (except VAD, which is incompatible with `word_timestamps=True` for diarization; keep `condition_on_previous_text=False` and the temperature ladder there).
6. All existing tests in [docker/speech/app/tests/](docker/speech/app/tests/) still pass.
7. New tests assert the exact kwargs are passed to `whisper_model.transcribe()` for both code paths.

## Technical Design

### Approach

Two call sites in [server.py](docker/speech/app/server.py) invoke `whisper_model.transcribe()`:

- [`_transcribe_audio`](docker/speech/app/server.py#L206-L215) — plain transcription endpoint.
- [`_diarize_and_transcribe`](docker/speech/app/server.py#L310-L346) — diarize + transcribe endpoint; needs `word_timestamps=True` so VAD is incompatible.

Change is configuration-only; no new dependencies, no API surface change, no behavioral change for callers. Tests use the existing `MagicMock` for `whisper_model`, so we assert on `mock_whisper.transcribe.call_args.kwargs`.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `docker/speech/app/server.py` | Modify | Update both `transcribe()` call sites with hallucination-mitigation kwargs |
| `docker/speech/app/tests/test_transcribe.py` | Modify | Add assertions that the transcribe call receives the expected kwargs |
| `docker/speech/app/tests/test_diarize.py` | Modify | Add assertion for the diarize code path's kwargs |

### Data Flow

Unchanged. Audio file → `ensure_wav` → `run_in_queue(_transcribe_audio|_diarize_and_transcribe)` → response. Only decoder parameters change.

### Edge Cases & Error Handling

- **Silent audio:** VAD filter should skip non-speech regions in the transcribe path; no output is preferable to hallucinated output.
- **Long audio with pauses:** `condition_on_previous_text=False` prevents a loop that started in one window from seeding the next.
- **High-quality audio without silence:** the temperature ladder starts at 0.0 (greedy) and only escalates when compression ratio or log-probability thresholds trip, so quality on clean audio is unaffected.
- **Diarization path:** VAD filter is omitted because pyannote provides its own voice activity via speaker segmentation; combining them can drop short utterances at segment boundaries.

## Implementation Phases

### Phase 1: Apply hallucination-mitigation kwargs with TDD
- **Goal:** Both `transcribe()` call sites use the correct decoder configuration, verified by kwargs assertions.
- **Model:** sonnet
- **Specs:**
  - [x] Test: `test_transcribe_passes_condition_on_previous_text_false` asserts `mock_whisper.transcribe.call_args.kwargs["condition_on_previous_text"] is False`.
  - [x] Test: `test_transcribe_passes_temperature_fallback_ladder` asserts `kwargs["temperature"] == [0.0, 0.2, 0.4, 0.6, 0.8, 1.0]`.
  - [x] Test: `test_transcribe_passes_vad_filter` asserts `kwargs["vad_filter"] is True` and `kwargs["vad_parameters"] == {"min_silence_duration_ms": 500}`.
  - [x] Test: `test_transcribe_passes_hallucination_thresholds` asserts `compression_ratio_threshold`, `log_prob_threshold`, and `no_speech_threshold` are set to the specified values.
  - [x] Test: `test_diarize_passes_condition_on_previous_text_false` and `test_diarize_passes_temperature_fallback_ladder` for the diarize path.
  - [x] Test: `test_diarize_does_not_pass_vad_filter` asserts VAD is not enabled on the diarize path (diarization supplies its own VAD via pyannote).
  - [x] Update `_transcribe_audio` call to make the above tests pass.
  - [x] Update `_diarize_and_transcribe` call to make the above tests pass.
  - [x] All existing tests in [docker/speech/app/tests/](docker/speech/app/tests/) continue to pass.
- **Files:** `docker/speech/app/server.py`, `docker/speech/app/tests/test_transcribe.py`, `docker/speech/app/tests/test_diarize.py`

## Parallelization

**Strategy:** Sequential
**Execution method:** Subagents

Single phase; nothing to parallelize.

## Review

**Review strategy:** end

Scope is narrow (one file's two call sites plus kwarg-assertion tests). End review is sufficient; no compounding risk across phases.

## Out of Scope

- Model swap (e.g. `large-v3-turbo` → `large-v3`). Turbo is slightly more loop-prone, but the user has not requested a model change and the proposed kwargs address the root cause.
- Exposing decoder kwargs via environment variables. Not needed today; can be added later if clients need per-request tuning.
- Post-processing loop-detection (e.g. collapsing identical consecutive segments). The decoder-level guards should prevent the loop from producing the output in the first place; post-processing masks rather than fixes.
- Real recorded-audio integration test. Existing tests mock `whisper_model`; adding a real-audio smoke test is a separate effort.

## Open Questions

None.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
