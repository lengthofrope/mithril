"""Tests for the FIFO queue behavior."""

import time
import threading
from unittest.mock import MagicMock, patch

from fastapi.testclient import TestClient


def test_queue_depth_starts_at_zero(client):
    """Queue depth is 0 when no requests are being processed."""
    response = client.get("/health")
    assert response.json()["queue_depth"] == 0


def test_concurrent_requests_are_serialized(sample_wav):
    """Multiple concurrent transcribe requests are processed one at a time."""
    execution_log = []

    def slow_transcribe(*args, **kwargs):
        """Simulated slow transcription that records execution timing."""
        idx = len([e for e in execution_log if e[0] == "start"])
        execution_log.append(("start", idx, time.monotonic()))
        time.sleep(0.15)
        execution_log.append(("end", idx, time.monotonic()))
        mock_seg = MagicMock()
        mock_seg.text = f"Result {idx}"
        return iter([mock_seg]), None

    mock_whisper = MagicMock()
    mock_whisper.transcribe.side_effect = slow_transcribe

    with patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True):
        from server import app
        client = TestClient(app)

        results = []

        def make_request(idx):
            resp = client.post(
                "/transcribe",
                files={"file": (f"test_{idx}.wav", sample_wav, "audio/wav")},
                data={"language": "en"},
            )
            results.append((idx, resp.status_code))

        threads = [threading.Thread(target=make_request, args=(i,)) for i in range(3)]
        for t in threads:
            t.start()
        for t in threads:
            t.join(timeout=10)

    assert len(results) == 3
    assert all(status == 200 for _, status in results)

    # Verify serialized execution: no two tasks overlap in time
    starts = [(idx, ts) for event, idx, ts in execution_log if event == "start"]
    ends = [(idx, ts) for event, idx, ts in execution_log if event == "end"]

    for end_event, start_event in zip(ends[:-1], starts[1:]):
        assert end_event[1] <= start_event[1], \
            "Tasks should not overlap: one must finish before the next starts"
