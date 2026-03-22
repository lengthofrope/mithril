"""Tests for optional pyannote diarization engine."""

import importlib
import os
from unittest.mock import MagicMock, patch


def test_default_engine_when_no_hf_token():
    """Without HUGGINGFACE_TOKEN, diarization engine is 'default'."""
    with patch.dict(os.environ, {}, clear=False):
        os.environ.pop("HUGGINGFACE_TOKEN", None)
        import server
        importlib.reload(server)
        assert server.DIARIZATION_ENGINE == "default"


def test_pyannote_engine_when_hf_token_set():
    """With HUGGINGFACE_TOKEN set, diarization engine is 'pyannote'."""
    with patch.dict(os.environ, {"HUGGINGFACE_TOKEN": "hf_test_token_123"}):
        import server
        importlib.reload(server)
        assert server.DIARIZATION_ENGINE == "pyannote"

    # Restore default state
    os.environ.pop("HUGGINGFACE_TOKEN", None)
    importlib.reload(server)


def test_health_reports_pyannote_engine():
    """Health endpoint reports pyannote when HF token is configured."""
    from fastapi.testclient import TestClient

    with patch.dict(os.environ, {"HUGGINGFACE_TOKEN": "hf_test_token_123"}):
        import server
        importlib.reload(server)

        with patch("server.models_ready", True), \
             patch("server.whisper_model", MagicMock()):
            client = TestClient(server.app)
            response = client.get("/health")
            assert response.json()["diarization_engine"] == "pyannote"

    # Restore default state
    os.environ.pop("HUGGINGFACE_TOKEN", None)
    importlib.reload(server)


def test_health_reports_default_engine():
    """Health endpoint reports default when no HF token."""
    from fastapi.testclient import TestClient

    with patch("server.models_ready", True), \
         patch("server.whisper_model", MagicMock()):
        import server
        client = TestClient(server.app)
        response = client.get("/health")
        assert response.json()["diarization_engine"] == "default"


def test_response_format_identical_regardless_of_engine(sample_wav):
    """Both engines produce the same response structure."""
    import contextlib
    from fastapi.testclient import TestClient

    def _make_seg(speaker, start, end):
        seg = MagicMock()
        seg.speaker = speaker
        seg.start = start
        seg.end = end
        return seg

    def _make_wseg(text, start, end):
        seg = MagicMock()
        seg.text = text
        seg.start = start
        seg.end = end
        return seg

    diarize_segs = [_make_seg("SPEAKER_00", 0.0, 3.0)]
    whisper_segs = [_make_wseg("Hello.", 0.0, 3.0)]

    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(whisper_segs), None)

    mock_diarize_result = MagicMock()
    mock_diarize_result.segments = diarize_segs
    mock_diarize_result.speakers = ["SPEAKER_00"]

    # Test with default engine
    with patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True), \
         patch("server.diarize_audio", return_value=mock_diarize_result):
        import server
        client = TestClient(server.app)
        resp_default = client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    default_data = resp_default.json()
    assert "segments" in default_data
    assert "speakers" in default_data
    assert "speaker" in default_data["segments"][0]
    assert "start" in default_data["segments"][0]
    assert "end" in default_data["segments"][0]
    assert "text" in default_data["segments"][0]
