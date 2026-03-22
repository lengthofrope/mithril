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

import torch
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from faster_whisper import WhisperModel

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

WHISPER_MODEL_SIZE = os.environ.get("WHISPER_MODEL", "large-v3-turbo")
MODEL_CACHE_DIR = os.environ.get("MODEL_CACHE_DIR", "/models")
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"

whisper_model: WhisperModel | None = None
models_ready: bool = False
queue_depth: int = 0


def load_models() -> None:
    """Load faster-whisper model into memory."""
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
    models_ready = True


@asynccontextmanager
async def lifespan(application: FastAPI):
    """Load models on startup, release on shutdown."""
    load_models()
    yield


app = FastAPI(title="Mithril Speech Service", lifespan=lifespan)


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
