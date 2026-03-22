"""Tests for device detection."""

import importlib
import sys
from unittest.mock import MagicMock


def test_cuda_detected_when_available():
    """Service reports cuda device when GPU is available."""
    mock_ct2 = MagicMock()
    mock_ct2.get_supported_compute_types.return_value = {"float16", "int8"}
    sys.modules["ctranslate2"] = mock_ct2

    import server
    importlib.reload(server)
    assert server.DEVICE == "cuda"

    # Restore CPU mock for other tests
    mock_ct2.get_supported_compute_types.side_effect = RuntimeError("no CUDA")
    importlib.reload(server)


def test_cpu_fallback_when_no_gpu():
    """Service reports cpu device when no GPU is available."""
    import server
    assert server.DEVICE == "cpu"
