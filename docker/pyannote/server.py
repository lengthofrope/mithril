"""
FastAPI server for speaker diarization using pyannote-audio and faster-whisper.

Accepts audio files and returns speaker-labeled transcription segments by:
1. Running pyannote speaker diarization to identify who spoke when
2. Running faster-whisper to get timestamped transcription
3. Merging the two by timestamp overlap
"""

import logging
import os
import subprocess
import tempfile
from contextlib import asynccontextmanager
from dataclasses import dataclass

import torch
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from faster_whisper import WhisperModel
from pyannote.audio import Pipeline

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

HUGGINGFACE_TOKEN = os.environ.get("HUGGINGFACE_TOKEN", "")
WHISPER_MODEL_SIZE = os.environ.get("WHISPER_MODEL_SIZE", "large-v3-turbo")
MODEL_CACHE_DIR = os.environ.get("MODEL_CACHE_DIR", "/models")
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"

diarization_pipeline: Pipeline | None = None
whisper_model: WhisperModel | None = None


@dataclass
class DiarizedSegment:
    """A single segment of diarized transcription."""

    speaker: str
    start: float
    end: float
    text: str


def load_models() -> None:
    """Load pyannote and faster-whisper models into memory."""
    global diarization_pipeline, whisper_model

    logger.info("Loading pyannote speaker-diarization-community-1 on %s...", DEVICE)
    diarization_pipeline = Pipeline.from_pretrained(
        "pyannote/speaker-diarization-community-1",
        token=HUGGINGFACE_TOKEN,
        cache_dir=MODEL_CACHE_DIR,
    )
    if DEVICE == "cuda":
        diarization_pipeline = diarization_pipeline.to(torch.device("cuda"))
    logger.info("pyannote model loaded.")

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


@asynccontextmanager
async def lifespan(application: FastAPI):
    """Load models on startup, release on shutdown."""
    load_models()
    yield


app = FastAPI(title="Mithril Diarization Service", lifespan=lifespan)


def get_speaker_segments(audio_path: str) -> list[tuple[str, float, float]]:
    """Run pyannote diarization and return (speaker, start, end) tuples."""
    from pyannote.audio.core.io import Audio

    audio = Audio(mono="downmix")
    waveform, sample_rate = audio(audio_path)
    duration = waveform.shape[1] / sample_rate

    output = diarization_pipeline(
        {"waveform": waveform, "sample_rate": sample_rate, "uri": audio_path},
    )

    segments = []
    for turn, speaker in output.exclusive_speaker_diarization:
        segments.append((speaker, turn.start, turn.end))
    return segments


def get_whisper_segments(
    audio_path: str, language: str
) -> list[tuple[str, float, float]]:
    """Run faster-whisper and return (text, start, end) tuples."""
    segments_iter, _ = whisper_model.transcribe(
        audio_path,
        language=language,
        beam_size=5,
        word_timestamps=False,
        temperature=0.0,
    )
    return [(seg.text.strip(), seg.start, seg.end) for seg in segments_iter]


def find_overlap(start1: float, end1: float, start2: float, end2: float) -> float:
    """Calculate the overlap duration between two time intervals."""
    overlap_start = max(start1, start2)
    overlap_end = min(end1, end2)
    return max(0.0, overlap_end - overlap_start)


def merge_segments(
    speaker_segments: list[tuple[str, float, float]],
    whisper_segments: list[tuple[str, float, float]],
) -> list[DiarizedSegment]:
    """Merge whisper text segments with pyannote speaker labels by timestamp overlap."""
    results: list[DiarizedSegment] = []

    for text, w_start, w_end in whisper_segments:
        if not text:
            continue

        best_speaker = "UNKNOWN"
        best_overlap = 0.0

        for speaker, s_start, s_end in speaker_segments:
            overlap = find_overlap(w_start, w_end, s_start, s_end)
            if overlap > best_overlap:
                best_overlap = overlap
                best_speaker = speaker

        results.append(
            DiarizedSegment(
                speaker=best_speaker,
                start=round(w_start, 2),
                end=round(w_end, 2),
                text=text,
            )
        )

    return results


def collapse_consecutive_speakers(
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


@app.get("/health")
async def health():
    """Readiness check."""
    return {
        "status": "ok",
        "device": DEVICE,
        "models_loaded": diarization_pipeline is not None
        and whisper_model is not None,
    }


def ensure_wav(audio_path: str) -> str:
    """Convert audio to WAV (16-bit PCM, mono) if not already .wav."""
    if audio_path.lower().endswith(".wav"):
        return audio_path

    wav_path = audio_path + ".wav"
    logger.info("Converting %s to WAV...", os.path.basename(audio_path))

    result = subprocess.run(
        ["ffmpeg", "-y", "-i", audio_path, "-ar", "16000", "-ac", "1", "-c:a", "pcm_s16le", wav_path],
        capture_output=True,
        text=True,
    )

    if result.returncode != 0:
        raise RuntimeError(f"ffmpeg conversion failed: {result.stderr}")

    logger.info("Conversion complete: %s", os.path.basename(wav_path))
    return wav_path


@app.post("/diarize")
async def diarize(
    file: UploadFile = File(...),
    language: str = Form("en"),
):
    """Diarize and transcribe an audio file, returning speaker-labeled segments."""
    if diarization_pipeline is None or whisper_model is None:
        raise HTTPException(status_code=503, detail="Models not loaded yet.")

    suffix = os.path.splitext(file.filename or "audio.wav")[1] or ".wav"

    with tempfile.NamedTemporaryFile(suffix=suffix, delete=True) as tmp:
        content = await file.read()
        tmp.write(content)
        tmp.flush()

        logger.info(
            "Processing %s (%.1f MB, language=%s)",
            file.filename,
            len(content) / 1024 / 1024,
            language,
        )

        audio_path = ensure_wav(tmp.name)

        try:
            speaker_segments = get_speaker_segments(audio_path)
            logger.info("Diarization complete: %d speaker segments", len(speaker_segments))

            whisper_segments = get_whisper_segments(audio_path, language)
            logger.info("Transcription complete: %d text segments", len(whisper_segments))
        finally:
            if audio_path != tmp.name:
                os.unlink(audio_path)

    merged = merge_segments(speaker_segments, whisper_segments)
    collapsed = collapse_consecutive_speakers(merged)

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
