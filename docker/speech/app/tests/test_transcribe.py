"""Tests for the /transcribe endpoint."""

import server


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
