"""Tests for the /transcribe endpoint."""

from unittest.mock import MagicMock, patch

import server


# ---------------------------------------------------------------------------
# _transcribe_audio progress callback contract
# ---------------------------------------------------------------------------

def _make_segment(text: str, end: float) -> MagicMock:
    """Create a minimal mock faster-whisper segment."""
    seg = MagicMock()
    seg.text = text
    seg.end = end
    return seg


def _make_info(duration: float) -> MagicMock:
    """Create a minimal mock TranscriptionInfo with a known duration."""
    info = MagicMock()
    info.duration = duration
    return info


def test_transcribe_audio_invokes_on_progress_per_segment():
    """_transcribe_audio calls on_progress once per segment (regardless of text content)."""
    segments = [
        _make_segment("Hello", 2.0),
        _make_segment("world", 4.0),
    ]
    info = _make_info(duration=4.0)

    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(segments), info)

    recorded = []

    with patch("server.whisper_model", mock_whisper):
        server._transcribe_audio("fake.wav", "en", on_progress=recorded.append)

    assert len(recorded) == 2, (
        f"on_progress should be called once per segment (2 segments); "
        f"got {len(recorded)} calls"
    )


def test_transcribe_audio_progress_values_are_monotonically_non_decreasing():
    """_transcribe_audio progress values never go backwards, even with out-of-order segment timestamps."""
    segments = [
        _make_segment("A", 3.0),
        _make_segment("B", 2.0),  # out of order: end < previous end
        _make_segment("C", 5.0),
    ]
    info = _make_info(duration=5.0)

    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(segments), info)

    recorded = []

    with patch("server.whisper_model", mock_whisper):
        server._transcribe_audio("fake.wav", "en", on_progress=recorded.append)

    for i in range(1, len(recorded)):
        assert recorded[i] >= recorded[i - 1], (
            f"Progress must be monotonically non-decreasing; "
            f"value at index {i} ({recorded[i]}) is less than value at {i - 1} ({recorded[i - 1]})"
        )


def test_transcribe_audio_progress_values_are_clamped_to_1():
    """_transcribe_audio clamps progress to 1.0 when segment.end exceeds audio duration."""
    segments = [
        _make_segment("Overrun", 12.0),  # end > duration
    ]
    info = _make_info(duration=10.0)

    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(segments), info)

    recorded = []

    with patch("server.whisper_model", mock_whisper):
        server._transcribe_audio("fake.wav", "en", on_progress=recorded.append)

    assert recorded[-1] == 1.0, (
        f"Progress must be clamped to 1.0 when segment.end exceeds audio duration; "
        f"got {recorded[-1]}"
    )


def test_transcribe_audio_progress_last_value_equals_1_for_full_audio():
    """_transcribe_audio emits progress 1.0 on the final segment of a fully-covered recording."""
    segments = [
        _make_segment("Hello", 5.0),
        _make_segment("world", 10.0),
    ]
    info = _make_info(duration=10.0)

    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(segments), info)

    recorded = []

    with patch("server.whisper_model", mock_whisper):
        server._transcribe_audio("fake.wav", "en", on_progress=recorded.append)

    assert recorded[-1] == 1.0, (
        f"Final progress value must be 1.0 when last segment.end equals duration; "
        f"got {recorded[-1]}"
    )


def test_transcribe_audio_without_on_progress_does_not_raise():
    """_transcribe_audio works with no on_progress argument (default no-op)."""
    segments = [_make_segment("Hello", 3.0)]
    info = _make_info(duration=3.0)

    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(segments), info)

    with patch("server.whisper_model", mock_whisper):
        result = server._transcribe_audio("fake.wav", "en")

    assert result == "Hello", (
        f"_transcribe_audio must still return the transcribed text when on_progress is omitted; "
        f"got {result!r}"
    )


def test_transcribe_endpoint_payload_shape_is_unchanged(client, sample_wav):
    """POST /transcribe response shape is byte-for-byte compatible: exactly {text: str}."""
    response = client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    data = response.json()

    assert response.status_code == 200
    assert set(data.keys()) == {"text"}, (
        f"POST /transcribe must return exactly {{\"text\": str}} with no extra fields; "
        f"got keys {set(data.keys())}"
    )
    assert isinstance(data["text"], str), (
        f"text field must be a string; got {type(data['text'])}"
    )


def test_transcribe_returns_text(client, sample_wav):
    """POST /transcribe returns JSON with text field."""
    response = client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    data = response.json()

    assert response.status_code == 200
    assert "text" in data
    assert isinstance(data["text"], str)
    assert len(data["text"]) > 0


def test_transcribe_without_file_returns_422(client):
    """POST /transcribe without a file returns 422."""
    response = client.post("/transcribe", data={"language": "en"})
    assert response.status_code == 422


def test_transcribe_default_language_is_en(client, sample_wav):
    """POST /transcribe without language param defaults to 'en'."""
    response = client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
    )
    assert response.status_code == 200


def test_transcribe_returns_503_when_models_not_loaded(unready_client, sample_wav):
    """POST /transcribe returns 503 when models are not yet loaded."""
    response = unready_client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    assert response.status_code == 503


def test_transcribe_passes_condition_on_previous_text_false(client, sample_wav):
    """POST /transcribe calls whisper with condition_on_previous_text=False to prevent repetition loops."""
    client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    kwargs = server.whisper_model.transcribe.call_args.kwargs
    assert kwargs["condition_on_previous_text"] is False, (
        f"condition_on_previous_text must be False to prevent runaway repetition loops; "
        f"got {kwargs.get('condition_on_previous_text')}"
    )


def test_transcribe_passes_temperature_fallback_ladder(client, sample_wav):
    """POST /transcribe calls whisper with a temperature fallback ladder instead of a scalar."""
    client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    kwargs = server.whisper_model.transcribe.call_args.kwargs
    assert kwargs["temperature"] == [0.0, 0.2, 0.4, 0.6, 0.8, 1.0], (
        f"temperature must be a fallback ladder [0.0, 0.2, 0.4, 0.6, 0.8, 1.0] to allow "
        f"beam-search fallback on uncertain segments; got {kwargs.get('temperature')}"
    )


def test_transcribe_passes_vad_filter(client, sample_wav):
    """POST /transcribe enables VAD filtering with min_silence_duration_ms=500."""
    client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    kwargs = server.whisper_model.transcribe.call_args.kwargs
    assert kwargs.get("vad_filter") is True, (
        f"vad_filter must be True to skip silent regions and prevent hallucinations on silence; "
        f"got {kwargs.get('vad_filter')}"
    )
    assert kwargs.get("vad_parameters") == {"min_silence_duration_ms": 500}, (
        f"vad_parameters must be {{'min_silence_duration_ms': 500}}; "
        f"got {kwargs.get('vad_parameters')}"
    )


def test_transcribe_passes_hallucination_thresholds(client, sample_wav):
    """POST /transcribe passes compression_ratio_threshold, log_prob_threshold, and no_speech_threshold."""
    client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    kwargs = server.whisper_model.transcribe.call_args.kwargs
    assert kwargs.get("compression_ratio_threshold") == 2.4, (
        f"compression_ratio_threshold must be 2.4 to discard repetitive segments; "
        f"got {kwargs.get('compression_ratio_threshold')}"
    )
    assert kwargs.get("log_prob_threshold") == -1.0, (
        f"log_prob_threshold must be -1.0 to filter low-confidence segments; "
        f"got {kwargs.get('log_prob_threshold')}"
    )
    assert kwargs.get("no_speech_threshold") == 0.6, (
        f"no_speech_threshold must be 0.6 to suppress non-speech transcription; "
        f"got {kwargs.get('no_speech_threshold')}"
    )
