"""
FastAPI server for unified speech processing (transcription and diarization).

Single service replacing separate whisper.cpp and pyannote containers.
Processes requests through a FIFO queue to prevent GPU/memory contention.
"""

import asyncio
import json
import logging
import os
import subprocess
import tempfile
import threading
import time
from collections.abc import AsyncGenerator, Callable
from contextlib import asynccontextmanager

import ctranslate2
from dataclasses import dataclass
from diarize import diarize as diarize_audio
from fastapi import FastAPI, File, Form, HTTPException, Request, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse, StreamingResponse
from starlette.types import ASGIApp, Receive, Scope, Send
from faster_whisper import WhisperModel

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

WHISPER_MODEL_SIZE = os.environ.get("WHISPER_MODEL", "large-v3-turbo")
MODEL_CACHE_DIR = os.environ.get("MODEL_CACHE_DIR", "/models")
HUGGINGFACE_TOKEN = os.environ.get("HUGGINGFACE_TOKEN", "")
SPEECH_AUTH_TOKEN = os.environ.get("SPEECH_AUTH_TOKEN", "")
SPEECH_CORS_ORIGINS = os.environ.get("SPEECH_CORS_ORIGINS", "*")
def _detect_device() -> str:
    """Detect CUDA GPU via CTranslate2 (used by faster-whisper)."""
    try:
        ctranslate2.get_supported_compute_types("cuda")
        return "cuda"
    except RuntimeError:
        return "cpu"


DEVICE = _detect_device()

whisper_model: WhisperModel | None = None
pyannote_pipeline = None
models_ready: bool = False
queue_depth: int = 0
DIARIZATION_ENGINE = "pyannote" if HUGGINGFACE_TOKEN else "default"


def _load_pyannote():
    """Load pyannote speaker diarization pipeline."""
    global pyannote_pipeline
    import torch
    from pyannote.audio import Pipeline

    logger.info("Loading pyannote speaker-diarization on %s...", DEVICE)
    pyannote_pipeline = Pipeline.from_pretrained(
        "pyannote/speaker-diarization-3.1",
        use_auth_token=HUGGINGFACE_TOKEN,
        cache_dir=MODEL_CACHE_DIR,
    )
    if DEVICE == "cuda":
        pyannote_pipeline = pyannote_pipeline.to(torch.device("cuda"))
    logger.info("pyannote model loaded.")


def load_models() -> None:
    """Load faster-whisper model (and optionally pyannote) into memory."""
    global whisper_model, models_ready

    compute_type = "float16" if DEVICE == "cuda" else "int8"
    logger.info(
        "Loading faster-whisper model '%s' on %s (%s)...",
        WHISPER_MODEL_SIZE,
        DEVICE,
        compute_type,
    )
    whisper_model = WhisperModel(
        WHISPER_MODEL_SIZE,
        device=DEVICE,
        compute_type=compute_type,
        download_root=MODEL_CACHE_DIR,
    )
    logger.info("faster-whisper model loaded.")

    if DIARIZATION_ENGINE == "pyannote":
        _load_pyannote()

    models_ready = True


@asynccontextmanager
async def lifespan(application: FastAPI):
    """Load models on startup, release on shutdown."""
    load_models()
    yield


app = FastAPI(title="Mithril Speech Service", lifespan=lifespan)

PROTECTED_PREFIXES = ("/transcribe", "/diarize")


@app.middleware("http")
async def token_auth_middleware(request: Request, call_next):
    """Validate X-Speech-Token header on protected endpoints."""
    if SPEECH_AUTH_TOKEN and any(
        request.url.path.startswith(prefix) for prefix in PROTECTED_PREFIXES
    ):
        token = request.headers.get("X-Speech-Token", "")
        if token != SPEECH_AUTH_TOKEN:
            return JSONResponse(
                status_code=401,
                content={"detail": "Invalid or missing authentication token."},
            )
    return await call_next(request)


class PrivateNetworkAccessMiddleware:
    """Add Private Network Access header for browser requests to loopback.

    Pure ASGI middleware that wraps CORSMiddleware to inject the header
    into preflight responses.
    """

    def __init__(self, app: ASGIApp) -> None:
        """Store the wrapped ASGI application."""
        self.app = app

    async def __call__(self, scope: Scope, receive: Receive, send: Send) -> None:
        """Intercept HTTP responses to add PNA header when requested."""
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        request_headers = dict(scope.get("headers", []))
        needs_pna = request_headers.get(b"access-control-request-private-network") == b"true"

        if not needs_pna:
            await self.app(scope, receive, send)
            return

        async def send_with_pna(message) -> None:
            """Inject Access-Control-Allow-Private-Network into response headers."""
            if message["type"] == "http.response.start":
                headers = list(message.get("headers", []))
                headers.append((b"access-control-allow-private-network", b"true"))
                message["headers"] = headers
            await send(message)

        await self.app(scope, receive, send_with_pna)


cors_origins = [o.strip() for o in SPEECH_CORS_ORIGINS.split(",") if o.strip()]

app.add_middleware(
    CORSMiddleware,
    allow_origins=cors_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
app.add_middleware(PrivateNetworkAccessMiddleware)


def ensure_wav(
    audio_path: str,
    on_stage: Callable[[str], None] | None = None,
) -> str:
    """Convert audio to WAV (16-bit PCM, mono) if not already .wav.

    Calls on_stage('converting') before conversion when the input is not WAV.
    """
    if audio_path.lower().endswith(".wav"):
        return audio_path

    if on_stage is not None:
        on_stage("converting")

    wav_path = audio_path + ".wav"
    logger.info("Converting %s to WAV...", os.path.basename(audio_path))

    result = subprocess.run(
        [
            "ffmpeg", "-y", "-i", audio_path,
            "-ar", "16000", "-ac", "1", "-c:a", "pcm_s16le",
            wav_path,
        ],
        capture_output=True,
        text=True,
    )

    if result.returncode != 0:
        raise RuntimeError(f"ffmpeg conversion failed: {result.stderr}")

    logger.info("Conversion complete: %s", os.path.basename(wav_path))
    return wav_path


_processing_semaphore = threading.Semaphore(1)


def run_in_queue(func, *args):
    """Execute a function through the FIFO queue (one at a time)."""
    global queue_depth
    queue_depth += 1
    try:
        _processing_semaphore.acquire()
        try:
            return func(*args)
        finally:
            _processing_semaphore.release()
    finally:
        queue_depth -= 1


def _emit_segment_progress(
    seg,
    info,
    prev_progress: float,
    on_progress: Callable[[float], None] | None,
) -> float:
    """Clamp segment progress monotonically to [prev, 1.0] and emit. Returns new prev_progress."""
    if on_progress is None or info.duration <= 0:
        return prev_progress
    raw = seg.end / info.duration
    clamped = min(1.0, max(prev_progress, raw))
    on_progress(clamped)
    return clamped


def _transcribe_audio(
    audio_path: str,
    language: str,
    on_progress: Callable[[float], None] | None = None,
) -> str:
    """Run faster-whisper transcription and return full text.

    Calls on_progress(value) after each segment, where value is the ratio
    of segment.end to audio duration, clamped to [0.0, 1.0] and guaranteed
    to be monotonically non-decreasing.
    """
    segments_iter, info = whisper_model.transcribe(
        audio_path,
        language=language,
        beam_size=5,
        word_timestamps=False,
        condition_on_previous_text=False,
        temperature=[0.0, 0.2, 0.4, 0.6, 0.8, 1.0],
        vad_filter=True,
        vad_parameters={"min_silence_duration_ms": 500},
        compression_ratio_threshold=2.4,
        log_prob_threshold=-1.0,
        no_speech_threshold=0.6,
    )

    texts = []
    prev_progress = 0.0

    for seg in segments_iter:
        text = seg.text.strip()
        if text:
            texts.append(text)

        prev_progress = _emit_segment_progress(seg, info, prev_progress, on_progress)

    return " ".join(texts)


@dataclass
class DiarizedSegment:
    """A single segment of diarized transcription."""

    speaker: str
    start: float
    end: float
    text: str


def _find_overlap(start1: float, end1: float, start2: float, end2: float) -> float:
    """Calculate the overlap duration between two time intervals."""
    return max(0.0, min(end1, end2) - max(start1, start2))


def _merge_segments(
    speaker_segments: list,
    whisper_segments: list,
) -> list[DiarizedSegment]:
    """Merge whisper text segments with speaker labels by timestamp overlap."""
    results: list[DiarizedSegment] = []

    for w_seg in whisper_segments:
        text = w_seg.text.strip()
        if not text:
            continue

        best_speaker = "UNKNOWN"
        best_overlap = 0.0

        for s_seg in speaker_segments:
            overlap = _find_overlap(w_seg.start, w_seg.end, s_seg.start, s_seg.end)
            if overlap > best_overlap:
                best_overlap = overlap
                best_speaker = s_seg.speaker

        results.append(DiarizedSegment(
            speaker=best_speaker,
            start=round(w_seg.start, 2),
            end=round(w_seg.end, 2),
            text=text,
        ))

    return results


def _collapse_consecutive_speakers(
    segments: list[DiarizedSegment],
) -> list[DiarizedSegment]:
    """Merge consecutive segments from the same speaker into single segments."""
    if not segments:
        return []

    collapsed: list[DiarizedSegment] = [segments[0]]

    for segment in segments[1:]:
        if segment.speaker == collapsed[-1].speaker:
            collapsed[-1] = DiarizedSegment(
                speaker=collapsed[-1].speaker,
                start=collapsed[-1].start,
                end=segment.end,
                text=collapsed[-1].text + " " + segment.text,
            )
        else:
            collapsed.append(segment)

    return collapsed


def _get_pyannote_speaker_segments(audio_path: str) -> list:
    """Run pyannote diarization and return segment-like objects."""
    from pyannote.audio.core.io import Audio

    audio = Audio(mono="downmix")
    waveform, sample_rate = audio(audio_path)

    output = pyannote_pipeline(
        {"waveform": waveform, "sample_rate": sample_rate, "uri": audio_path},
    )

    @dataclass
    class SpeakerSegment:
        speaker: str
        start: float
        end: float

    return [
        SpeakerSegment(speaker=speaker, start=turn.start, end=turn.end)
        for turn, speaker in output.exclusive_speaker_diarization
    ]


def _diarize_and_transcribe(
    audio_path: str,
    language: str,
    on_stage: Callable[[str], None] | None = None,
    on_progress: Callable[[float], None] | None = None,
) -> dict:
    """Run diarization and transcription, then merge results.

    Emits stages 'diarizing', 'transcribing', and 'merging' via on_stage.
    Emits numeric progress per Whisper segment via on_progress.
    """
    if on_stage is not None:
        on_stage("diarizing")

    if DIARIZATION_ENGINE == "pyannote":
        speaker_segments = _get_pyannote_speaker_segments(audio_path)
        logger.info("Pyannote diarization complete: %d speaker segments", len(speaker_segments))
    else:
        diarize_result = diarize_audio(audio_path)
        speaker_segments = diarize_result.segments
        logger.info("Default diarization complete: %d speaker segments", len(speaker_segments))

    if on_stage is not None:
        on_stage("transcribing")

    whisper_segments_iter, info = whisper_model.transcribe(
        audio_path,
        language=language,
        beam_size=5,
        word_timestamps=True,
        condition_on_previous_text=False,
        temperature=[0.0, 0.2, 0.4, 0.6, 0.8, 1.0],
        compression_ratio_threshold=2.4,
        log_prob_threshold=-1.0,
        no_speech_threshold=0.6,
    )

    prev_progress = 0.0
    whisper_segments = []

    for seg in whisper_segments_iter:
        whisper_segments.append(seg)

        prev_progress = _emit_segment_progress(seg, info, prev_progress, on_progress)

    logger.info("Transcription complete: %d text segments", len(whisper_segments))

    if on_stage is not None:
        on_stage("merging")

    merged = _merge_segments(speaker_segments, whisper_segments)
    collapsed = _collapse_consecutive_speakers(merged)

    speakers = sorted(set(seg.speaker for seg in collapsed))

    return {
        "segments": [
            {
                "speaker": seg.speaker,
                "start": seg.start,
                "end": seg.end,
                "text": seg.text,
            }
            for seg in collapsed
        ],
        "speakers": speakers,
    }


@app.get("/health")
async def health():
    """Readiness and status check."""
    return {
        "ready": models_ready,
        "device": DEVICE,
        "models": {
            "whisper": WHISPER_MODEL_SIZE if models_ready else None,
        },
        "queue_depth": queue_depth,
        "diarization_engine": DIARIZATION_ENGINE,
        "streaming": True,
    }


@app.post("/transcribe")
async def transcribe(
    file: UploadFile = File(...),
    language: str = Form("en"),
):
    """Transcribe an audio file, returning plain text."""
    if not models_ready or whisper_model is None:
        raise HTTPException(status_code=503, detail="Models not loaded yet.")

    suffix = os.path.splitext(file.filename or "audio.wav")[1] or ".wav"

    with tempfile.NamedTemporaryFile(suffix=suffix, delete=True) as tmp:
        content = await file.read()
        tmp.write(content)
        tmp.flush()

        logger.info(
            "Transcribing %s (%.1f MB, language=%s)",
            file.filename,
            len(content) / 1024 / 1024,
            language,
        )

        audio_path = ensure_wav(tmp.name)

        try:
            loop = asyncio.get_running_loop()
            text = await loop.run_in_executor(
                None, run_in_queue, _transcribe_audio, audio_path, language,
            )
        finally:
            if audio_path != tmp.name:
                os.unlink(audio_path)

    return {"text": text}


def _format_sse(event: str, data: dict) -> bytes:
    """Format a single SSE record as bytes with an 'event:' line, 'data:' line, and blank line terminator."""
    return f"event: {event}\ndata: {json.dumps(data)}\n\n".encode("utf-8")


async def _stream_core(
    request: Request,
    worker: Callable[[Callable[[str], None], Callable[[float], None]], dict],
    start_time: float,
) -> AsyncGenerator[bytes, None]:
    """Drive a worker through the FIFO queue, yielding SSE events for stages, progress, result, errors.

    The worker is invoked in a background thread, wrapped by run_in_queue so that all blocking work
    (including ffmpeg conversion performed inside the worker) is serialized by the FIFO semaphore.
    The worker receives two sync callbacks (on_stage, on_progress) that bridge to the async
    generator via an asyncio.Queue. The worker's return value is emitted as the final 'result'
    event; any exception is emitted as an 'error' event. Client disconnect is polled between queue
    drains and terminates the generator; run_in_queue releases the FIFO semaphore when the worker
    naturally completes.
    """
    loop = asyncio.get_running_loop()
    queue: asyncio.Queue = asyncio.Queue()

    def push(event_type: str, payload: dict) -> None:
        """Thread-safe push of an SSE event onto the async queue from the worker thread."""
        loop.call_soon_threadsafe(queue.put_nowait, (event_type, payload))

    def on_stage(stage: str) -> None:
        """Forward a stage label from the worker thread to the SSE stream."""
        push("stage", {"stage": stage})

    def on_progress(progress: float) -> None:
        """Forward a progress ratio from the worker thread to the SSE stream."""
        push("progress", {
            "stage": "transcribing",
            "progress": progress,
            "elapsed_s": round(time.monotonic() - start_time, 3),
        })

    def thread_target() -> None:
        """Run the worker inside run_in_queue; emit done/error sentinel when complete."""
        try:
            result = run_in_queue(worker, on_stage, on_progress)
            push("__done__", {"result": result})
        except Exception as exc:  # noqa: BLE001 - forwarded as SSE error
            logger.exception("Streaming worker failed")
            push("__error__", {"detail": str(exc)})

    worker_thread = threading.Thread(target=thread_target, daemon=True)
    worker_thread.start()

    while True:
        if await request.is_disconnected():
            return

        try:
            event_type, payload = await asyncio.wait_for(queue.get(), timeout=0.5)
        except asyncio.TimeoutError:
            continue

        if event_type == "__done__":
            yield _format_sse("result", payload["result"])
            return
        if event_type == "__error__":
            yield _format_sse("error", payload)
            return

        yield _format_sse(event_type, payload)


@app.post("/transcribe/stream")
async def transcribe_stream(
    request: Request,
    file: UploadFile = File(...),
    language: str = Form("en"),
):
    """Transcribe an audio file and stream progress + result as SSE events."""
    if not models_ready or whisper_model is None:
        raise HTTPException(status_code=503, detail="Models not loaded yet.")

    content = await file.read()
    suffix = os.path.splitext(file.filename or "audio.wav")[1] or ".wav"

    tmp = tempfile.NamedTemporaryFile(suffix=suffix, delete=False)
    tmp.write(content)
    tmp.flush()
    tmp.close()
    tmp_path = tmp.name

    logger.info(
        "Streaming-transcribing %s (%.1f MB, language=%s)",
        file.filename,
        len(content) / 1024 / 1024,
        language,
    )

    start_time = time.monotonic()

    audio_path_holder: dict[str, str | None] = {"path": None}

    def worker(on_stage_cb, on_progress_cb):
        """Run ensure_wav + _transcribe_audio inside the FIFO-serialized worker thread.

        Emits the 'converting' stage (when applicable) via on_stage_cb, progress per Whisper
        segment via on_progress_cb, and guarantees progress reaches 1.0 for empty audio.
        """
        audio_path = ensure_wav(tmp_path, on_stage=on_stage_cb)
        audio_path_holder["path"] = audio_path

        last_progress = 0.0

        def progress_wrapper(value: float) -> None:
            """Record the most recent progress value and forward it to the SSE stream."""
            nonlocal last_progress
            last_progress = value
            on_progress_cb(value)

        text = _transcribe_audio(audio_path, language, on_progress=progress_wrapper)

        # Edge case: empty audio (no segments) - ensure progress reaches 1.0 before result.
        if last_progress < 1.0:
            on_progress_cb(1.0)

        return {"text": text}

    async def generator():
        """Yield SSE events for the transcribe flow, then clean up the temp file."""
        try:
            async for chunk in _stream_core(request, worker, start_time):
                yield chunk
        except Exception as exc:  # noqa: BLE001 - pre-stream setup failure, emit as SSE error
            logger.exception("transcribe_stream setup failed")
            yield _format_sse("error", {"detail": str(exc)})
        finally:
            audio_path = audio_path_holder["path"]
            if audio_path and audio_path != tmp_path:
                try:
                    os.unlink(audio_path)
                except OSError:
                    pass
            try:
                os.unlink(tmp_path)
            except OSError:
                pass

    return StreamingResponse(generator(), media_type="text/event-stream")


@app.post("/diarize")
async def diarize(
    file: UploadFile = File(...),
    language: str = Form("en"),
):
    """Diarize and transcribe an audio file, returning speaker-labeled segments."""
    if not models_ready or whisper_model is None:
        raise HTTPException(status_code=503, detail="Models not loaded yet.")

    suffix = os.path.splitext(file.filename or "audio.wav")[1] or ".wav"

    with tempfile.NamedTemporaryFile(suffix=suffix, delete=True) as tmp:
        content = await file.read()
        tmp.write(content)
        tmp.flush()

        logger.info(
            "Diarizing %s (%.1f MB, language=%s)",
            file.filename,
            len(content) / 1024 / 1024,
            language,
        )

        audio_path = ensure_wav(tmp.name)

        try:
            loop = asyncio.get_running_loop()
            result = await loop.run_in_executor(
                None, run_in_queue, _diarize_and_transcribe, audio_path, language,
            )
        finally:
            if audio_path != tmp.name:
                os.unlink(audio_path)

    return result


@app.post("/diarize/stream")
async def diarize_stream(
    request: Request,
    file: UploadFile = File(...),
    language: str = Form("en"),
):
    """Diarize and transcribe an audio file, streaming stage + progress + result events as SSE."""
    if not models_ready or whisper_model is None:
        raise HTTPException(status_code=503, detail="Models not loaded yet.")

    content = await file.read()
    suffix = os.path.splitext(file.filename or "audio.wav")[1] or ".wav"

    tmp = tempfile.NamedTemporaryFile(suffix=suffix, delete=False)
    tmp.write(content)
    tmp.flush()
    tmp.close()
    tmp_path = tmp.name

    logger.info(
        "Streaming-diarizing %s (%.1f MB, language=%s)",
        file.filename,
        len(content) / 1024 / 1024,
        language,
    )

    start_time = time.monotonic()

    audio_path_holder: dict[str, str | None] = {"path": None}

    def worker(on_stage_cb, on_progress_cb):
        """Run ensure_wav + _diarize_and_transcribe inside the FIFO-serialized worker thread.

        Emits 'converting' (when applicable), 'diarizing', 'transcribing', and 'merging' stages
        via on_stage_cb, and per-segment Whisper progress via on_progress_cb.
        """
        audio_path = ensure_wav(tmp_path, on_stage=on_stage_cb)
        audio_path_holder["path"] = audio_path
        return _diarize_and_transcribe(
            audio_path, language, on_stage=on_stage_cb, on_progress=on_progress_cb,
        )

    async def generator():
        """Yield SSE events for the diarize flow including the 'converting' stage, then clean up."""
        try:
            async for chunk in _stream_core(request, worker, start_time):
                yield chunk
        except Exception as exc:  # noqa: BLE001 - pre-stream setup failure, emit as SSE error
            logger.exception("diarize_stream setup failed")
            yield _format_sse("error", {"detail": str(exc)})
        finally:
            audio_path = audio_path_holder["path"]
            if audio_path and audio_path != tmp_path:
                try:
                    os.unlink(audio_path)
                except OSError:
                    pass
            try:
                os.unlink(tmp_path)
            except OSError:
                pass

    return StreamingResponse(generator(), media_type="text/event-stream")
