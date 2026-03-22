"""Tests for device detection."""

import importlib
import sys
from unittest.mock import MagicMock


def test_cuda_detected_when_available():
    """Service reports cuda device when GPU is available."""
    mock_torch = MagicMock()
    mock_torch.cuda.is_available.return_value = True
    sys.modules["torch"] = mock_torch

    import server
    importlib.reload(server)
    assert server.DEVICE == "cuda"

    # Restore CPU mock for other tests
    mock_torch.cuda.is_available.return_value = False
    sys.modules["torch"] = mock_torch
    importlib.reload(server)


def test_cpu_fallback_when_no_gpu():
    """Service reports cpu device when no GPU is available."""
    import server
    assert server.DEVICE == "cpu"
