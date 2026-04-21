"""Tests for the /diarize endpoint."""

import contextlib
from unittest.mock import MagicMock, patch

from fastapi.testclient import TestClient

import server


# ---------------------------------------------------------------------------
# _diarize_and_transcribe progress callback contract
# ---------------------------------------------------------------------------

def _make_diarize_segment_direct(speaker: str, start: float, end: float):
    """Create a mock diarize segment for direct function-level tests."""
    seg = MagicMock()
    seg.speaker = speaker
    seg.start = start
    seg.end = end
    return seg


def _make_whisper_segment_direct(text: str, start: float, end: float):
    """Create a mock faster-whisper segment for direct function-level tests."""
    seg = MagicMock()
    seg.text = text
    seg.start = start
    seg.end = end
    return seg


def _make_info(duration: float) -> MagicMock:
    """Create a minimal mock TranscriptionInfo with a known duration."""
    info = MagicMock()
    info.duration = duration
    return info


def test_diarize_and_transcribe_stage_order_is_diarizing_transcribing_merging():
    """_diarize_and_transcribe emits stages in order: diarizing, transcribing, merging."""
    diarize_segs = [_make_diarize_segment_direct("SPEAKER_00", 0.0, 3.0)]
    whisper_segs = [_make_whisper_segment_direct("Test.", 0.0, 3.0)]

    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(whisper_segs), _make_info(duration=10.0))

    mock_diarize_result = MagicMock()
    mock_diarize_result.segments = diarize_segs

    stages = []

    with patch("server.whisper_model", mock_whisper), \
         patch("server.diarize_audio", return_value=mock_diarize_result), \
         patch("server.DIARIZATION_ENGINE", "default"):
        server._diarize_and_transcribe("fake.wav", "en", on_stage=stages.append)

    expected_order = ["diarizing", "transcribing", "merging"]
    # Filter to only the three expected stages to allow for future additions
    filtered = [s for s in stages if s in expected_order]
    assert filtered == expected_order, (
        f"Stages must be emitted in order diarizing -> transcribing -> merging; "
        f"got {filtered}"
    )


def test_diarize_and_transcribe_on_progress_called_during_transcribing():
    """_diarize_and_transcribe forwards on_progress to the Whisper transcription step."""
    diarize_segs = [_make_diarize_segment_direct("SPEAKER_00", 0.0, 5.0)]

    whisper_segs = [
        _make_whisper_segment_direct("Part one.", 2.0, 2.0),
        _make_whisper_segment_direct("Part two.", 5.0, 5.0),
    ]

    mock_info = MagicMock()
    mock_info.duration = 5.0

    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(whisper_segs), mock_info)

    mock_diarize_result = MagicMock()
    mock_diarize_result.segments = diarize_segs

    progress_values = []

    with patch("server.whisper_model", mock_whisper), \
         patch("server.diarize_audio", return_value=mock_diarize_result), \
         patch("server.DIARIZATION_ENGINE", "default"):
        server._diarize_and_transcribe(
            "fake.wav", "en", on_progress=progress_values.append
        )

    assert len(progress_values) > 0, (
        f"on_progress must be called at least once during the transcribing stage; "
        f"got {len(progress_values)} calls"
    )


def test_diarize_and_transcribe_without_callbacks_does_not_raise():
    """_diarize_and_transcribe works without on_stage or on_progress (default no-ops)."""
    diarize_segs = [_make_diarize_segment_direct("SPEAKER_00", 0.0, 3.0)]
    whisper_segs = [_make_whisper_segment_direct("Test.", 0.0, 3.0)]

    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(whisper_segs), None)

    mock_diarize_result = MagicMock()
    mock_diarize_result.segments = diarize_segs

    with patch("server.whisper_model", mock_whisper), \
         patch("server.diarize_audio", return_value=mock_diarize_result), \
         patch("server.DIARIZATION_ENGINE", "default"):
        result = server._diarize_and_transcribe("fake.wav", "en")

    assert "segments" in result, (
        "_diarize_and_transcribe must return result dict even when callbacks are omitted"
    )


def test_diarize_endpoint_payload_shape_is_unchanged(sample_wav):
    """POST /diarize response shape is backwards-compatible: exactly {segments, speakers}."""
    diarize_segs = [
        _make_diarize_segment_direct("SPEAKER_00", 0.0, 3.0),
    ]
    whisper_segs = [
        _make_whisper_segment_direct("Hello.", 0.0, 3.0),
    ]

    with _diarize_client(diarize_segs, whisper_segs) as client:
        response = client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )
        data = response.json()

    assert response.status_code == 200
    assert set(data.keys()) == {"segments", "speakers"}, (
        f"POST /diarize must return exactly {{\"segments\": [...], \"speakers\": [...]}} "
        f"with no extra top-level fields; got keys {set(data.keys())}"
    )


def test_ensure_wav_emits_converting_stage_for_non_wav(tmp_path):
    """ensure_wav calls on_stage with 'converting' before running ffmpeg for non-WAV input."""
    import subprocess

    audio_file = tmp_path / "audio.mp3"
    audio_file.write_bytes(b"\x00" * 64)

    stages = []

    mock_result = MagicMock()
    mock_result.returncode = 0

    with patch("subprocess.run", return_value=mock_result):
        server.ensure_wav(str(audio_file), on_stage=stages.append)

    assert "converting" in stages, (
        f"ensure_wav must emit 'converting' stage before ffmpeg conversion; "
        f"got stages: {stages}"
    )


def test_ensure_wav_skips_stage_callback_for_wav_input(tmp_path):
    """ensure_wav does not call on_stage when input is already a .wav file."""
    wav_file = tmp_path / "audio.wav"
    wav_file.write_bytes(b"\x00" * 64)

    stages = []
    server.ensure_wav(str(wav_file), on_stage=stages.append)

    assert stages == [], (
        f"ensure_wav must not emit any stages for an already-WAV input; "
        f"got stages: {stages}"
    )


def test_ensure_wav_without_on_stage_does_not_raise(tmp_path):
    """ensure_wav works without on_stage argument (default no-op)."""
    wav_file = tmp_path / "audio.wav"
    wav_file.write_bytes(b"\x00" * 64)

    result = server.ensure_wav(str(wav_file))
    assert result == str(wav_file), (
        f"ensure_wav must return the input path unchanged for WAV files; "
        f"got {result!r}"
    )


def _make_diarize_segment(speaker: str, start: float, end: float):
    """Create a mock diarize segment."""
    seg = MagicMock()
    seg.speaker = speaker
    seg.start = start
    seg.end = end
    return seg


def _make_whisper_segment(text: str, start: float, end: float):
    """Create a mock faster-whisper segment."""
    seg = MagicMock()
    seg.text = text
    seg.start = start
    seg.end = end
    return seg


@contextlib.contextmanager
def _diarize_client(diarize_segments, whisper_segments):
    """Context manager yielding a test client with mocked diarization and transcription."""
    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(whisper_segments), None)

    mock_diarize_result = MagicMock()
    mock_diarize_result.segments = diarize_segments
    mock_diarize_result.speakers = sorted(set(s.speaker for s in diarize_segments))

    with patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True), \
         patch("server.diarize_audio", return_value=mock_diarize_result):
        from server import app
        yield TestClient(app)


def test_diarize_returns_segments_and_speakers(sample_wav):
    """POST /diarize returns segments with speaker labels and text."""
    diarize_segs = [
        _make_diarize_segment("SPEAKER_00", 0.0, 5.0),
        _make_diarize_segment("SPEAKER_01", 5.0, 10.0),
    ]
    whisper_segs = [
        _make_whisper_segment("Hello there.", 0.0, 5.0),
        _make_whisper_segment("How are you?", 5.0, 10.0),
    ]

    with _diarize_client(diarize_segs, whisper_segs) as client:
        response = client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )
        data = response.json()

    assert response.status_code == 200
    assert "segments" in data
    assert "speakers" in data
    assert len(data["segments"]) == 2
    assert data["speakers"] == ["SPEAKER_00", "SPEAKER_01"]


def test_diarize_segment_has_required_fields(sample_wav):
    """Each segment has speaker, start, end, and text fields."""
    diarize_segs = [_make_diarize_segment("SPEAKER_00", 0.0, 3.0)]
    whisper_segs = [_make_whisper_segment("Test text.", 0.0, 3.0)]

    with _diarize_client(diarize_segs, whisper_segs) as client:
        response = client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )
        seg = response.json()["segments"][0]

    assert "speaker" in seg
    assert "start" in seg
    assert "end" in seg
    assert "text" in seg
    assert isinstance(seg["start"], float)
    assert isinstance(seg["end"], float)


def test_diarize_collapses_consecutive_same_speaker(sample_wav):
    """Consecutive segments from the same speaker are merged."""
    diarize_segs = [
        _make_diarize_segment("SPEAKER_00", 0.0, 3.0),
        _make_diarize_segment("SPEAKER_00", 3.0, 6.0),
        _make_diarize_segment("SPEAKER_01", 6.0, 9.0),
    ]
    whisper_segs = [
        _make_whisper_segment("First part.", 0.0, 3.0),
        _make_whisper_segment("Second part.", 3.0, 6.0),
        _make_whisper_segment("Other speaker.", 6.0, 9.0),
    ]

    with _diarize_client(diarize_segs, whisper_segs) as client:
        response = client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )
        segments = response.json()["segments"]

    assert len(segments) == 2
    assert segments[0]["speaker"] == "SPEAKER_00"
    assert "First part." in segments[0]["text"]
    assert "Second part." in segments[0]["text"]
    assert segments[1]["speaker"] == "SPEAKER_01"


def test_diarize_returns_503_when_models_not_loaded(unready_client, sample_wav):
    """POST /diarize returns 503 when models are not yet loaded."""
    response = unready_client.post(
        "/diarize",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    assert response.status_code == 503


def test_diarize_without_file_returns_422(client):
    """POST /diarize without a file returns 422."""
    response = client.post("/diarize", data={"language": "en"})
    assert response.status_code == 422


def test_diarize_uses_fifo_queue(sample_wav):
    """Diarization requests go through the same FIFO queue as transcription."""
    diarize_segs = [_make_diarize_segment("SPEAKER_00", 0.0, 3.0)]
    whisper_segs = [_make_whisper_segment("Test.", 0.0, 3.0)]

    with _diarize_client(diarize_segs, whisper_segs) as client:
        response = client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    assert response.status_code == 200


def test_diarize_passes_condition_on_previous_text_false(sample_wav):
    """POST /diarize calls whisper with condition_on_previous_text=False to prevent repetition loops."""
    diarize_segs = [_make_diarize_segment("SPEAKER_00", 0.0, 3.0)]
    whisper_segs = [_make_whisper_segment("Test.", 0.0, 3.0)]

    with _diarize_client(diarize_segs, whisper_segs) as diarize_test_client:
        diarize_test_client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )
        kwargs = server.whisper_model.transcribe.call_args.kwargs

    assert kwargs["condition_on_previous_text"] is False, (
        f"condition_on_previous_text must be False to prevent runaway repetition loops; "
        f"got {kwargs.get('condition_on_previous_text')}"
    )


def test_diarize_passes_temperature_fallback_ladder(sample_wav):
    """POST /diarize calls whisper with a temperature fallback ladder instead of a scalar."""
    diarize_segs = [_make_diarize_segment("SPEAKER_00", 0.0, 3.0)]
    whisper_segs = [_make_whisper_segment("Test.", 0.0, 3.0)]

    with _diarize_client(diarize_segs, whisper_segs) as diarize_test_client:
        diarize_test_client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )
        kwargs = server.whisper_model.transcribe.call_args.kwargs

    assert kwargs["temperature"] == [0.0, 0.2, 0.4, 0.6, 0.8, 1.0], (
        f"temperature must be a fallback ladder [0.0, 0.2, 0.4, 0.6, 0.8, 1.0] to allow "
        f"beam-search fallback on uncertain segments; got {kwargs.get('temperature')}"
    )


def test_diarize_does_not_pass_vad_filter(sample_wav):
    """POST /diarize must not enable vad_filter; pyannote already provides VAD via speaker segmentation."""
    diarize_segs = [_make_diarize_segment("SPEAKER_00", 0.0, 3.0)]
    whisper_segs = [_make_whisper_segment("Test.", 0.0, 3.0)]

    with _diarize_client(diarize_segs, whisper_segs) as diarize_test_client:
        diarize_test_client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )
        kwargs = server.whisper_model.transcribe.call_args.kwargs

    assert "vad_filter" not in kwargs, (
        "vad_filter must not be present on the diarize path; pyannote supplies its own VAD "
        "via speaker segmentation and combining them can drop short utterances at segment boundaries"
    )
