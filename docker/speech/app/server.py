"""
FastAPI server for unified speech processing (transcription and diarization).

Single service replacing separate whisper.cpp and pyannote containers.
Processes requests through a FIFO queue to prevent GPU/memory contention.
"""

import logging
import os
import subprocess
import tempfile
import threading
from contextlib import asynccontextmanager

import ctranslate2
from dataclasses import dataclass
from diarize import diarize as diarize_audio
from fastapi import FastAPI, File, Form, HTTPException, Request, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
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

PROTECTED_PATHS = {"/transcribe", "/diarize"}


@app.middleware("http")
async def token_auth_middleware(request: Request, call_next):
    """Validate X-Speech-Token header on protected endpoints."""
    if SPEECH_AUTH_TOKEN and request.url.path in PROTECTED_PATHS:
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


def ensure_wav(audio_path: str) -> str:
    """Convert audio to WAV (16-bit PCM, mono) if not already .wav."""
    if audio_path.lower().endswith(".wav"):
        return audio_path

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


def _transcribe_audio(audio_path: str, language: str) -> str:
    """Run faster-whisper transcription and return full text."""
    segments_iter, _ = whisper_model.transcribe(
        audio_path,
        language=language,
        beam_size=5,
        word_timestamps=False,
        temperature=0.0,
    )
    return " ".join(seg.text.strip() for seg in segments_iter if seg.text.strip())


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


def _diarize_and_transcribe(audio_path: str, language: str) -> dict:
    """Run diarization and transcription, then merge results."""
    if DIARIZATION_ENGINE == "pyannote":
        speaker_segments = _get_pyannote_speaker_segments(audio_path)
        logger.info("Pyannote diarization complete: %d speaker segments", len(speaker_segments))
    else:
        diarize_result = diarize_audio(audio_path)
        speaker_segments = diarize_result.segments
        logger.info("Default diarization complete: %d speaker segments", len(speaker_segments))

    whisper_segments, _ = whisper_model.transcribe(
        audio_path,
        language=language,
        beam_size=5,
        word_timestamps=True,
        temperature=0.0,
    )
    whisper_segments = list(whisper_segments)
    logger.info("Transcription complete: %d text segments", len(whisper_segments))

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
            import asyncio
            loop = asyncio.get_event_loop()
            text = await loop.run_in_executor(
                None, run_in_queue, _transcribe_audio, audio_path, language,
            )
        finally:
            if audio_path != tmp.name:
                os.unlink(audio_path)

    return {"text": text}


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
            import asyncio
            loop = asyncio.get_event_loop()
            result = await loop.run_in_executor(
                None, run_in_queue, _diarize_and_transcribe, audio_path, language,
            )
        finally:
            if audio_path != tmp.name:
                os.unlink(audio_path)

    return result
