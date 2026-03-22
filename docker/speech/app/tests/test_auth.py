"""Tests for token-based authentication on speech service endpoints."""

import os
from unittest.mock import patch

from fastapi.testclient import TestClient


def _client_with_token(token: str):
    """Create a test client with SPEECH_AUTH_TOKEN set."""
    with patch.dict(os.environ, {"SPEECH_AUTH_TOKEN": token}), \
         patch("server.whisper_model", __import__("unittest.mock", fromlist=["MagicMock"]).MagicMock()), \
         patch("server.models_ready", True):
        import server
        # Re-read the env var so the middleware picks it up
        server.SPEECH_AUTH_TOKEN = token
        yield TestClient(server.app)
        server.SPEECH_AUTH_TOKEN = os.environ.get("SPEECH_AUTH_TOKEN", "")


def _client_without_token():
    """Create a test client with no SPEECH_AUTH_TOKEN."""
    env = os.environ.copy()
    env.pop("SPEECH_AUTH_TOKEN", None)
    with patch.dict(os.environ, env, clear=True), \
         patch("server.whisper_model", __import__("unittest.mock", fromlist=["MagicMock"]).MagicMock()), \
         patch("server.models_ready", True):
        import server
        server.SPEECH_AUTH_TOKEN = ""
        yield TestClient(server.app)


# --- Token required: valid token ---

def test_transcribe_with_valid_token_succeeds(sample_wav):
    """POST /transcribe with correct X-Speech-Token returns 200."""
    mock = __import__("unittest.mock", fromlist=["MagicMock"]).MagicMock()
    mock_seg = __import__("unittest.mock", fromlist=["MagicMock"]).MagicMock()
    mock_seg.text = "Hello"
    mock.transcribe.return_value = (iter([mock_seg]), None)

    with patch.dict(os.environ, {"SPEECH_AUTH_TOKEN": "secret-token-123"}), \
         patch("server.whisper_model", mock), \
         patch("server.models_ready", True):
        import server
        server.SPEECH_AUTH_TOKEN = "secret-token-123"
        client = TestClient(server.app)

        response = client.post(
            "/transcribe",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
            headers={"X-Speech-Token": "secret-token-123"},
        )

    assert response.status_code == 200


def test_diarize_with_valid_token_succeeds(sample_wav):
    """POST /diarize with correct X-Speech-Token returns 200."""
    from unittest.mock import MagicMock

    mock_whisper = MagicMock()
    mock_seg = MagicMock()
    mock_seg.text = "Hello"
    mock_seg.start = 0.0
    mock_seg.end = 3.0
    mock_whisper.transcribe.return_value = (iter([mock_seg]), None)

    mock_diarize_result = MagicMock()
    d_seg = MagicMock()
    d_seg.speaker = "SPEAKER_00"
    d_seg.start = 0.0
    d_seg.end = 3.0
    mock_diarize_result.segments = [d_seg]

    with patch.dict(os.environ, {"SPEECH_AUTH_TOKEN": "secret-token-123"}), \
         patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True), \
         patch("server.diarize_audio", return_value=mock_diarize_result):
        import server
        server.SPEECH_AUTH_TOKEN = "secret-token-123"
        client = TestClient(server.app)

        response = client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
            headers={"X-Speech-Token": "secret-token-123"},
        )

    assert response.status_code == 200


# --- Token required: missing or wrong token ---

def test_transcribe_without_token_returns_401(sample_wav):
    """POST /transcribe without X-Speech-Token returns 401 when token is required."""
    with patch.dict(os.environ, {"SPEECH_AUTH_TOKEN": "secret-token-123"}), \
         patch("server.whisper_model", __import__("unittest.mock", fromlist=["MagicMock"]).MagicMock()), \
         patch("server.models_ready", True):
        import server
        server.SPEECH_AUTH_TOKEN = "secret-token-123"
        client = TestClient(server.app)

        response = client.post(
            "/transcribe",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    assert response.status_code == 401
    assert "detail" in response.json()


def test_transcribe_with_wrong_token_returns_401(sample_wav):
    """POST /transcribe with incorrect token returns 401."""
    with patch.dict(os.environ, {"SPEECH_AUTH_TOKEN": "secret-token-123"}), \
         patch("server.whisper_model", __import__("unittest.mock", fromlist=["MagicMock"]).MagicMock()), \
         patch("server.models_ready", True):
        import server
        server.SPEECH_AUTH_TOKEN = "secret-token-123"
        client = TestClient(server.app)

        response = client.post(
            "/transcribe",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
            headers={"X-Speech-Token": "wrong-token"},
        )

    assert response.status_code == 401


def test_diarize_without_token_returns_401(sample_wav):
    """POST /diarize without X-Speech-Token returns 401 when token is required."""
    with patch.dict(os.environ, {"SPEECH_AUTH_TOKEN": "secret-token-123"}), \
         patch("server.whisper_model", __import__("unittest.mock", fromlist=["MagicMock"]).MagicMock()), \
         patch("server.models_ready", True):
        import server
        server.SPEECH_AUTH_TOKEN = "secret-token-123"
        client = TestClient(server.app)

        response = client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    assert response.status_code == 401


# --- Token not configured: endpoints remain open ---

def test_transcribe_without_configured_token_allows_access(client, sample_wav):
    """POST /transcribe works without token when SPEECH_AUTH_TOKEN is not set."""
    import server
    original = server.SPEECH_AUTH_TOKEN
    server.SPEECH_AUTH_TOKEN = ""
    try:
        response = client.post(
            "/transcribe",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )
        assert response.status_code == 200
    finally:
        server.SPEECH_AUTH_TOKEN = original


def test_diarize_without_configured_token_allows_access(sample_wav):
    """POST /diarize works without token when SPEECH_AUTH_TOKEN is not set."""
    from unittest.mock import MagicMock

    mock_whisper = MagicMock()
    mock_seg = MagicMock()
    mock_seg.text = "Hello"
    mock_seg.start = 0.0
    mock_seg.end = 3.0
    mock_whisper.transcribe.return_value = (iter([mock_seg]), None)

    mock_diarize_result = MagicMock()
    d_seg = MagicMock()
    d_seg.speaker = "SPEAKER_00"
    d_seg.start = 0.0
    d_seg.end = 3.0
    mock_diarize_result.segments = [d_seg]

    with patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True), \
         patch("server.diarize_audio", return_value=mock_diarize_result):
        import server
        server.SPEECH_AUTH_TOKEN = ""
        client = TestClient(server.app)

        response = client.post(
            "/diarize",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    assert response.status_code == 200


# --- /health always accessible ---

def test_health_accessible_without_token_when_auth_required(client):
    """GET /health returns 200 even when SPEECH_AUTH_TOKEN is set."""
    import server
    original = server.SPEECH_AUTH_TOKEN
    server.SPEECH_AUTH_TOKEN = "secret-token-123"
    try:
        response = client.get("/health")
        assert response.status_code == 200
    finally:
        server.SPEECH_AUTH_TOKEN = original


def test_health_accessible_without_token_when_auth_not_required(client):
    """GET /health returns 200 when SPEECH_AUTH_TOKEN is not set."""
    import server
    original = server.SPEECH_AUTH_TOKEN
    server.SPEECH_AUTH_TOKEN = ""
    try:
        response = client.get("/health")
        assert response.status_code == 200
    finally:
        server.SPEECH_AUTH_TOKEN = original
