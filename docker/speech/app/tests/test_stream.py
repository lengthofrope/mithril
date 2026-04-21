"""Tests for SSE streaming endpoints: /transcribe/stream and /diarize/stream."""

import contextlib
import json
import threading
import time
from unittest.mock import MagicMock, patch

from fastapi.testclient import TestClient

import server


# ---------------------------------------------------------------------------
# SSE parsing helper
# ---------------------------------------------------------------------------

def _parse_sse(raw: str) -> list[dict]:
    """Parse an SSE stream body into a list of {'event': str, 'data': dict} records.

    Handles one 'event:' and one 'data:' line per record, separated by blank lines.
    """
    records: list[dict] = []
    event_name: str | None = None
    data_lines: list[str] = []

    for line in raw.splitlines():
        if line == "":
            if event_name is not None or data_lines:
                payload_raw = "\n".join(data_lines)
                try:
                    payload = json.loads(payload_raw) if payload_raw else {}
                except json.JSONDecodeError:
                    payload = {"_raw": payload_raw}
                records.append({"event": event_name or "message", "data": payload})
            event_name = None
            data_lines = []
            continue

        if line.startswith("event: "):
            event_name = line[len("event: "):]
        elif line.startswith("data: "):
            data_lines.append(line[len("data: "):])

    if event_name is not None or data_lines:
        payload_raw = "\n".join(data_lines)
        try:
            payload = json.loads(payload_raw) if payload_raw else {}
        except json.JSONDecodeError:
            payload = {"_raw": payload_raw}
        records.append({"event": event_name or "message", "data": payload})

    return records


def _make_whisper_segment(text: str, end: float, start: float = 0.0) -> MagicMock:
    """Create a minimal mock faster-whisper segment."""
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


def _make_diarize_segment(speaker: str, start: float, end: float) -> MagicMock:
    """Create a mock diarize speaker segment."""
    seg = MagicMock()
    seg.speaker = speaker
    seg.start = start
    seg.end = end
    return seg


@contextlib.contextmanager
def _streaming_transcribe_client(whisper_segments, info):
    """Test client with mocked whisper model for /transcribe/stream."""
    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(whisper_segments), info)

    with patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True):
        yield TestClient(server.app)


@contextlib.contextmanager
def _streaming_diarize_client(whisper_segments, info, diarize_segments):
    """Test client with mocked whisper + diarize for /diarize/stream."""
    mock_whisper = MagicMock()
    mock_whisper.transcribe.return_value = (iter(whisper_segments), info)

    mock_diarize_result = MagicMock()
    mock_diarize_result.segments = diarize_segments

    with patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True), \
         patch("server.diarize_audio", return_value=mock_diarize_result), \
         patch("server.DIARIZATION_ENGINE", "default"):
        yield TestClient(server.app)


# ---------------------------------------------------------------------------
# /health reports streaming capability
# ---------------------------------------------------------------------------

def test_health_reports_streaming_capability(client):
    """GET /health returns streaming=True indicating SSE support."""
    response = client.get("/health")
    data = response.json()

    assert response.status_code == 200
    assert data.get("streaming") is True, (
        f"GET /health must include streaming:true so clients can detect SSE support; "
        f"got {data}"
    )


# ---------------------------------------------------------------------------
# /transcribe/stream happy path
# ---------------------------------------------------------------------------

def test_transcribe_stream_emits_progress_then_result(sample_wav):
    """POST /transcribe/stream emits progress events per segment, then one result event."""
    whisper_segs = [
        _make_whisper_segment("Hello", 2.0),
        _make_whisper_segment("world", 5.0),
        _make_whisper_segment("again", 10.0),
    ]
    info = _make_info(duration=10.0)

    with _streaming_transcribe_client(whisper_segs, info) as client:
        response = client.post(
            "/transcribe/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    assert response.status_code == 200, (
        f"POST /transcribe/stream must return 200 on success; got {response.status_code}"
    )
    assert response.headers["content-type"].startswith("text/event-stream"), (
        f"response content-type must be text/event-stream; "
        f"got {response.headers.get('content-type')}"
    )

    records = _parse_sse(response.text)
    event_types = [r["event"] for r in records]

    progress_events = [r for r in records if r["event"] == "progress"]
    result_events = [r for r in records if r["event"] == "result"]

    assert len(progress_events) >= 3, (
        f"must emit at least one progress event per Whisper segment (3); "
        f"got {len(progress_events)} progress events in sequence {event_types}"
    )
    assert len(result_events) == 1, (
        f"must emit exactly one result event; got {len(result_events)} in sequence {event_types}"
    )
    assert event_types[-1] == "result", (
        f"result event must be last; got sequence {event_types}"
    )
    assert "text" in result_events[0]["data"], (
        f"result event must contain text field; got {result_events[0]['data']}"
    )
    assert result_events[0]["data"]["text"] == "Hello world again", (
        f"result text must be concatenated segment text; got {result_events[0]['data']['text']!r}"
    )


def test_transcribe_stream_progress_is_monotonically_non_decreasing(sample_wav):
    """Progress values emitted via SSE never decrease, even with out-of-order segment timestamps."""
    whisper_segs = [
        _make_whisper_segment("A", 3.0),
        _make_whisper_segment("B", 2.0),  # out of order
        _make_whisper_segment("C", 5.0),
    ]
    info = _make_info(duration=5.0)

    with _streaming_transcribe_client(whisper_segs, info) as client:
        response = client.post(
            "/transcribe/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    records = _parse_sse(response.text)
    progress_values = [r["data"]["progress"] for r in records if r["event"] == "progress"]

    for i in range(1, len(progress_values)):
        assert progress_values[i] >= progress_values[i - 1], (
            f"progress must be monotonically non-decreasing; "
            f"value at index {i} ({progress_values[i]}) < value at {i - 1} ({progress_values[i - 1]})"
        )


def test_transcribe_stream_empty_audio_emits_progress_1_before_result(sample_wav):
    """When no segments are produced, the stream still emits progress=1.0 before the result."""
    info = _make_info(duration=5.0)

    with _streaming_transcribe_client([], info) as client:
        response = client.post(
            "/transcribe/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    records = _parse_sse(response.text)
    progress_events = [r for r in records if r["event"] == "progress"]
    result_events = [r for r in records if r["event"] == "result"]

    assert len(result_events) == 1, "must still emit a result event for empty audio"
    assert len(progress_events) >= 1, (
        f"empty-audio edge case must emit at least one progress event at 1.0 before result; "
        f"got {len(progress_events)} progress events"
    )
    assert progress_events[-1]["data"]["progress"] == 1.0, (
        f"final progress for empty audio must be 1.0; "
        f"got {progress_events[-1]['data']['progress']}"
    )


def test_transcribe_stream_progress_event_includes_elapsed_s(sample_wav):
    """Progress events include an elapsed_s field measured server-side."""
    whisper_segs = [_make_whisper_segment("Hi", 5.0)]
    info = _make_info(duration=5.0)

    with _streaming_transcribe_client(whisper_segs, info) as client:
        response = client.post(
            "/transcribe/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    records = _parse_sse(response.text)
    progress_events = [r for r in records if r["event"] == "progress"]

    assert len(progress_events) >= 1
    first = progress_events[0]["data"]
    assert "elapsed_s" in first, (
        f"progress events must include an elapsed_s field; got {first}"
    )
    assert isinstance(first["elapsed_s"], (int, float))
    assert first["elapsed_s"] >= 0


# ---------------------------------------------------------------------------
# /diarize/stream happy path
# ---------------------------------------------------------------------------

def test_diarize_stream_emits_stage_sequence_then_result(sample_wav):
    """POST /diarize/stream emits stage events converting -> diarizing -> transcribing -> merging, then result."""
    # Use a non-wav suffix to trigger the 'converting' stage.
    whisper_segs = [_make_whisper_segment("Hello there.", 3.0, start=0.0)]
    info = _make_info(duration=3.0)
    diarize_segs = [_make_diarize_segment("SPEAKER_00", 0.0, 3.0)]

    # Stub ffmpeg conversion so ensure_wav does not actually run ffmpeg.
    mock_proc = MagicMock()
    mock_proc.returncode = 0

    with _streaming_diarize_client(whisper_segs, info, diarize_segs) as client, \
         patch("server.subprocess.run", return_value=mock_proc):
        response = client.post(
            "/diarize/stream",
            files={"file": ("test.mp3", sample_wav, "audio/mpeg")},
            data={"language": "en"},
        )

    assert response.status_code == 200
    records = _parse_sse(response.text)
    stages = [r["data"].get("stage") for r in records if r["event"] == "stage"]

    expected = ["converting", "diarizing", "transcribing", "merging"]
    # Filter to only these stages to tolerate extras
    filtered = [s for s in stages if s in expected]
    assert filtered == expected, (
        f"stage events must appear in order {expected}; got {filtered} (full stage list: {stages})"
    )

    result_events = [r for r in records if r["event"] == "result"]
    assert len(result_events) == 1, "must emit exactly one result event"
    assert set(result_events[0]["data"].keys()) == {"segments", "speakers"}, (
        f"result payload must match blocking /diarize shape (segments, speakers); "
        f"got keys {set(result_events[0]['data'].keys())}"
    )


def test_diarize_stream_emits_progress_during_transcribing(sample_wav):
    """/diarize/stream emits numeric progress events during the transcribing stage."""
    whisper_segs = [
        _make_whisper_segment("One.", 2.0, start=0.0),
        _make_whisper_segment("Two.", 5.0, start=2.0),
    ]
    info = _make_info(duration=5.0)
    diarize_segs = [_make_diarize_segment("SPEAKER_00", 0.0, 5.0)]

    with _streaming_diarize_client(whisper_segs, info, diarize_segs) as client:
        response = client.post(
            "/diarize/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    records = _parse_sse(response.text)
    progress_events = [r for r in records if r["event"] == "progress"]

    assert len(progress_events) >= 2, (
        f"must emit a progress event per Whisper segment during diarize transcribing stage; "
        f"got {len(progress_events)}"
    )
    for ev in progress_events:
        assert ev["data"].get("stage") == "transcribing", (
            f"progress events during diarize should carry stage=transcribing; got {ev['data']}"
        )


# ---------------------------------------------------------------------------
# Error handling
# ---------------------------------------------------------------------------

def test_transcribe_stream_emits_error_event_on_worker_exception(sample_wav):
    """When the worker raises mid-stream, an error event is emitted and stream closes cleanly."""
    mock_whisper = MagicMock()
    mock_whisper.transcribe.side_effect = RuntimeError("whisper blew up")

    with patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True):
        client = TestClient(server.app)
        response = client.post(
            "/transcribe/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    assert response.status_code == 200, (
        "SSE convention: errors are in-band; HTTP status remains 200 once the stream has opened"
    )
    records = _parse_sse(response.text)
    error_events = [r for r in records if r["event"] == "error"]
    result_events = [r for r in records if r["event"] == "result"]

    assert len(error_events) >= 1, (
        f"worker exception must produce an error SSE event; got events {[r['event'] for r in records]}"
    )
    assert "detail" in error_events[0]["data"], (
        f"error event must include a detail field; got {error_events[0]['data']}"
    )
    assert len(result_events) == 0, (
        "must not emit a result event after a worker failure"
    )
    # Semaphore must be released so subsequent requests are not blocked.
    assert server._processing_semaphore.acquire(blocking=False), (
        "FIFO semaphore must be released after worker exception"
    )
    server._processing_semaphore.release()


# ---------------------------------------------------------------------------
# Pre-stream auth + readiness
# ---------------------------------------------------------------------------

def test_transcribe_stream_requires_auth_token_when_configured(sample_wav):
    """POST /transcribe/stream returns 401 before opening the stream when token is missing."""
    with patch("server.SPEECH_AUTH_TOKEN", "secret"), \
         patch("server.whisper_model", MagicMock()), \
         patch("server.models_ready", True):
        client = TestClient(server.app)
        response = client.post(
            "/transcribe/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    assert response.status_code == 401, (
        f"/transcribe/stream must return 401 (not open an SSE stream) when token missing; "
        f"got {response.status_code}"
    )
    assert "event:" not in response.text, (
        "401 must not include any SSE events; it is a normal HTTP error before the stream opens"
    )


def test_diarize_stream_requires_auth_token_when_configured(sample_wav):
    """POST /diarize/stream returns 401 before opening the stream when token is missing."""
    with patch("server.SPEECH_AUTH_TOKEN", "secret"), \
         patch("server.whisper_model", MagicMock()), \
         patch("server.models_ready", True):
        client = TestClient(server.app)
        response = client.post(
            "/diarize/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    assert response.status_code == 401


def test_transcribe_stream_returns_503_when_models_not_ready(unready_client, sample_wav):
    """POST /transcribe/stream returns 503 before opening the stream when models are not loaded."""
    response = unready_client.post(
        "/transcribe/stream",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    assert response.status_code == 503, (
        f"/transcribe/stream must return 503 (not open a stream) when models not ready; "
        f"got {response.status_code}"
    )


def test_diarize_stream_returns_503_when_models_not_ready(unready_client, sample_wav):
    """POST /diarize/stream returns 503 before opening the stream when models are not loaded."""
    response = unready_client.post(
        "/diarize/stream",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    assert response.status_code == 503


# ---------------------------------------------------------------------------
# Client disconnect cleanup
# ---------------------------------------------------------------------------

def test_transcribe_stream_releases_semaphore_on_client_disconnect(sample_wav):
    """If the client disconnects mid-stream, the FIFO semaphore is released so later requests proceed."""
    worker_started = threading.Event()
    worker_may_finish = threading.Event()
    worker_observed_cancel = threading.Event()

    def slow_transcribe(*args, **kwargs):
        """Mock transcribe that blocks until the test releases it, simulating an in-flight run."""
        worker_started.set()
        # Block up to 5 seconds waiting to be released.
        if not worker_may_finish.wait(timeout=5.0):
            worker_observed_cancel.set()
        return (iter([]), _make_info(duration=1.0))

    mock_whisper = MagicMock()
    mock_whisper.transcribe.side_effect = slow_transcribe

    with patch("server.whisper_model", mock_whisper), \
         patch("server.models_ready", True):
        client = TestClient(server.app)
        with client.stream(
            "POST",
            "/transcribe/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        ) as response:
            assert response.status_code == 200
            # Wait until the worker thread has started processing.
            assert worker_started.wait(timeout=3.0), "worker should have started"
            # Simulate client disconnect by closing the stream early.

        # Release the worker so it can exit cleanly (even after disconnect).
        worker_may_finish.set()

        # Give the server a moment to release the semaphore.
        deadline = time.time() + 3.0
        released = False
        while time.time() < deadline:
            if server._processing_semaphore.acquire(blocking=False):
                released = True
                server._processing_semaphore.release()
                break
            time.sleep(0.05)

        assert released, (
            "FIFO semaphore must be released after client disconnect so subsequent requests can proceed"
        )

    # Observable-behavior upgrade: after the disconnect, a second real streaming request must
    # succeed end-to-end (open stream, emit result) proving the server can serve new work.
    fast_segs = [_make_whisper_segment("ok", 1.0)]
    fast_info = _make_info(duration=1.0)

    with _streaming_transcribe_client(fast_segs, fast_info) as next_client:
        next_response = next_client.post(
            "/transcribe/stream",
            files={"file": ("test.wav", sample_wav, "audio/wav")},
            data={"language": "en"},
        )

    assert next_response.status_code == 200, (
        f"subsequent /transcribe/stream request after disconnect must return 200; "
        f"got {next_response.status_code}"
    )
    next_records = _parse_sse(next_response.text)
    next_result_events = [r for r in next_records if r["event"] == "result"]
    assert len(next_result_events) == 1, (
        f"subsequent request must complete with exactly one result event; "
        f"got events {[r['event'] for r in next_records]}"
    )
