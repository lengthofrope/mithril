"""Shared fixtures for speech service tests."""

import io
import sys
import wave
from unittest.mock import MagicMock

import pytest

# Mock heavy dependencies before any test imports server.py
_mock_torch = MagicMock()
_mock_torch.cuda.is_available.return_value = False
sys.modules["torch"] = _mock_torch

_mock_faster_whisper = MagicMock()
sys.modules["faster_whisper"] = _mock_faster_whisper


@pytest.fixture(autouse=True)
def _reset_server_state():
    """Reset server module state between tests."""
    import server
    yield
    # Reset mutable globals after each test
    server.queue_depth = 0


@pytest.fixture
def client():
    """Create a test client with mocked models."""
    from unittest.mock import patch

    from fastapi.testclient import TestClient

    mock_whisper = MagicMock()
    mock_segment = MagicMock()
    mock_segment.text = "Hello world"
    mock_whisper.transcribe.return_value = (iter([mock_segment]), None)

    with patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True):
        from server import app
        yield TestClient(app)


@pytest.fixture
def unready_client():
    """Create a test client where models are not loaded."""
    from unittest.mock import patch

    from fastapi.testclient import TestClient

    with patch("server.whisper_model", None), \
         patch("server.models_ready", False):
        from server import app
        yield TestClient(app)


@pytest.fixture
def sample_wav() -> bytes:
    """Generate a minimal valid WAV file."""
    buf = io.BytesIO()
    with wave.open(buf, "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(16000)
        w.writeframes(b"\x00\x00" * 16000)
    return buf.getvalue()
