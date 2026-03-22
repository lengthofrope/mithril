# Mithril Speech Service

Unified speech processing service for Mithril. Provides transcription (via faster-whisper) and speaker diarization in a single Docker container with automatic CPU/GPU detection.

## Quick Start

```bash
cp .env.example .env
docker compose up --build
```

The service will be available at `http://localhost:8090`. On first startup, models are downloaded and cached in a Docker volume (~1.6 GB for `large-v3-turbo`).

## GPU Support

The service auto-detects CUDA GPUs at runtime. To enable GPU passthrough to the container, you need the [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/install-guide.html) installed, and one change in `.env`:

```env
COMPOSE_FILE=docker-compose.yml:docker-compose.gpu.yml
```

Then `docker compose up` passes the GPU to the container automatically.

If Docker reports `could not select device driver "nvidia"`, generate the CDI spec and restart Docker:

```bash
sudo nvidia-ctk cdi generate --output=/etc/cdi/nvidia.yaml
sudo systemctl restart docker
```

## Endpoints

### `GET /health`

Returns service status.

```json
{
  "ready": true,
  "device": "cuda",
  "models": {
    "whisper": "large-v3-turbo"
  },
  "queue_depth": 0,
  "diarization_engine": "default"
}
```

- `ready` is `false` while models are loading (first startup or after restart)
- `device` reports `cpu` or `cuda` depending on available hardware
- `queue_depth` shows how many requests are waiting
- `diarization_engine` reports `default` or `pyannote`

### `POST /transcribe`

Transcribes an audio file to text.

**Request:** multipart form data
- `file` — audio file (WAV, MP3, M4A, OGG, FLAC, etc.)
- `language` — BCP-47 language code (default: `en`)

**Response:**

```json
{
  "text": "The transcribed text content."
}
```

### `POST /diarize`

Diarizes and transcribes an audio file, returning speaker-labeled segments.

**Request:** multipart form data
- `file` — audio file (WAV, MP3, M4A, OGG, FLAC, etc.)
- `language` — BCP-47 language code (default: `en`)

**Response:**

```json
{
  "segments": [
    {
      "speaker": "SPEAKER_00",
      "start": 0.0,
      "end": 5.23,
      "text": "Hello, how are you?"
    },
    {
      "speaker": "SPEAKER_01",
      "start": 5.24,
      "end": 8.15,
      "text": "I'm fine, thanks."
    }
  ],
  "speakers": ["SPEAKER_00", "SPEAKER_01"]
}
```

Non-WAV files are automatically converted via ffmpeg for both endpoints.

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `SPEECH_PORT` | `8090` | Host port to expose the service on |
| `WHISPER_MODEL` | `large-v3-turbo` | Whisper model size (see below) |
| `HUGGINGFACE_TOKEN` | *(empty)* | When set, uses pyannote for higher-quality diarization |

### Diarization Engines

| Engine | Token Required | Quality | Description |
|--------|---------------|---------|-------------|
| `default` | No | Good (~10.8% DER) | Uses `diarize` library (ONNX, Apache 2.0) |
| `pyannote` | Yes (HuggingFace) | Better | Uses pyannote-audio (requires gated model access) |

The engine is selected automatically based on whether `HUGGINGFACE_TOKEN` is set.

### Whisper Model Sizes

| Model | Disk Size | Speed | Quality | Use Case |
|-------|-----------|-------|---------|----------|
| `tiny` | ~75 MB | Fastest | Low | Development/testing |
| `base` | ~142 MB | Fast | Fair | Quick prototyping |
| `small` | ~466 MB | Moderate | Good | Light production |
| `medium` | ~1.5 GB | Slow | Very good | General production |
| `large-v3-turbo` | ~1.6 GB | Moderate | Excellent | **Recommended** |
| `large-v3` | ~3.1 GB | Slowest | Best | Maximum quality |

## Request Queue

All requests (both transcription and diarization) are processed through a shared FIFO queue — one at a time. This prevents GPU/memory contention. The current queue depth is reported via `/health`.

## Connecting to Mithril

In Mithril's root `.env`:

```env
MEETING_TRANSCRIPTION_PROVIDER=unified
MEETING_DIARIZATION_PROVIDER=unified
UNIFIED_SPEECH_BASE_URL=http://localhost:8090
```

## Development

Python source code and tests are in the `app/` directory.

### Running Tests

```bash
cd app
pip install -r requirements-test.txt
python -m pytest tests/ -v
```

Tests mock heavy dependencies so they run without a GPU or model downloads.

## File Structure

```
docker/speech/
  .env.example              # Configuration template
  docker-compose.yml        # Base compose (CPU)
  docker-compose.gpu.yml    # GPU override (merge via COMPOSE_FILE)
  Dockerfile                # Single image for CPU and GPU
  README.md                 # This file
  INSTALLATION.md           # Detailed installation guide
  app/
    server.py               # FastAPI application
    requirements.txt        # Python dependencies
    requirements-test.txt   # Test dependencies
    tests/                  # pytest test suite
```
