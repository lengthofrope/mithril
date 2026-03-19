# whisper.cpp — Self-hosted Speech-to-Text

A standalone [whisper.cpp](https://github.com/ggml-org/whisper.cpp) server for transcribing meeting recordings locally. No data leaves your network.

## Prerequisites

- Docker and Docker Compose
- For GPU mode: NVIDIA GPU with [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/latest/install-guide.html) installed

## Quick Start

```bash
cd docker/whispercpp

# CPU mode (default)
./setup.sh

# NVIDIA GPU mode (significantly faster)
./setup.sh --gpu
```

This will:

1. Download the `ggml-large-v3-turbo` model (~1.6 GB) into a Docker volume
2. Start the whisper.cpp server on `http://localhost:8080`

The model is only downloaded once. Subsequent runs skip the download.

## Choosing a Model

Pass a model filename to `setup.sh` to use a different model:

```bash
./setup.sh ggml-base.bin
./setup.sh --gpu ggml-base.bin    # combine with GPU mode
```

| Model | Size | Speed | Quality | Use case |
|-------|------|-------|---------|----------|
| `ggml-tiny.bin` | ~75 MB | Fastest | Low | Quick testing |
| `ggml-base.bin` | ~142 MB | Fast | Fair | Development |
| `ggml-small.bin` | ~466 MB | Moderate | Good | Light production |
| `ggml-medium.bin` | ~1.5 GB | Slow | High | Production |
| `ggml-large-v3-turbo.bin` | ~1.6 GB | Moderate | Near-best | **Recommended** |
| `ggml-large-v3.bin` | ~3.1 GB | Slowest | Best | Maximum accuracy |

All models support Dutch and English transcription.

## Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `WHISPER_CPP_PORT` | `8080` | Host port to expose the server on |
| `WHISPER_MODEL_FILE` | `ggml-large-v3-turbo.bin` | Model file to load from the volume |

Set these in a `.env` file next to the `docker-compose.yml` or export them before running.

## Mithril Integration

In your Mithril `.env`, point the transcription service to this server:

```env
MEETING_TRANSCRIPTION_PROVIDER=whisper_cpp
WHISPER_CPP_BASE_URL=http://localhost:8080
```

## Managing the Service

```bash
# Start (CPU)
docker compose --profile cpu up -d

# Start (GPU)
docker compose --profile gpu up -d

# Stop
docker compose --profile cpu down
# or
docker compose --profile gpu down

# View logs
docker compose logs -f

# Restart with a different model
WHISPER_MODEL_FILE=ggml-base.bin docker compose --profile cpu up -d
```

## Verifying the Server

```bash
curl http://localhost:8080/inference \
  -F file=@recording.wav \
  -F response_format=json \
  -F language=nl
```

Expected response:

```json
{"text": "Transcribed text here..."}
```

## Storage

Models are stored in a Docker volume (`whispercpp_whisper-models`) and persist across container restarts. To remove the volume and downloaded models:

```bash
docker compose --profile cpu down -v
# or
docker compose --profile gpu down -v
```
