# whisper.cpp — Self-hosted Speech-to-Text

> **Deprecated:** This service is superseded by the unified speech service in `docker/speech/`.
> The unified service provides both transcription and diarization in a single container.
> Set `MEETING_TRANSCRIPTION_PROVIDER=unified` to migrate. This directory will be removed in a future release.

A standalone [whisper.cpp](https://github.com/ggml-org/whisper.cpp) server for transcribing meeting recordings locally. No data leaves your network.

## Prerequisites

- Docker and Docker Compose
- For CUDA mode: NVIDIA GPU with [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/latest/install-guide.html)
- For Vulkan mode: GPU with Vulkan support and `/dev/dri` device access

## Quick Start

```bash
cd docker/whispercpp
./setup.sh
```

The setup script auto-detects the best available backend:

1. **NVIDIA GPU + `/dev/dri`** → Vulkan (most compatible)
2. **NVIDIA GPU without `/dev/dri`** → CUDA
3. **Non-NVIDIA GPU + `/dev/dri`** → Vulkan
4. **No GPU** → CPU

You can override the auto-detection:

```bash
./setup.sh --vulkan              # Force Vulkan
./setup.sh --cuda                # Force CUDA
./setup.sh --cpu                 # Force CPU
```

This will:

1. Download the `ggml-large-v3-turbo` model (~1.6 GB) into a Docker volume
2. Start the whisper.cpp server on `http://localhost:8080`

The model is only downloaded once. Subsequent runs skip the download.

## Choosing a Model

Pass a model filename to `setup.sh` to use a different model:

```bash
./setup.sh ggml-base.bin
./setup.sh --vulkan ggml-base.bin
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

## Profiles

The `docker-compose.yml` defines three profiles. The `setup.sh` script selects one automatically, but you must specify the same profile when managing the container manually.

| Profile | Image | GPU access | Requirements |
|---------|-------|------------|--------------|
| `cpu` | `main` | None | Docker only |
| `vulkan` | `main-vulkan` | `/dev/dri` device | GPU with Vulkan driver |
| `cuda` | `main-cuda` | NVIDIA runtime | NVIDIA Container Toolkit |

Vulkan is preferred over CUDA because it works without the NVIDIA Container Toolkit and avoids CUDA driver version compatibility issues.

## Daily Usage

After the initial `./setup.sh`, the model is downloaded and the profile is chosen. Day-to-day you just start and stop the container directly. Replace `<profile>` with the one you used during setup (`cpu`, `vulkan`, or `cuda`).

```bash
# Start
docker compose --profile <profile> up -d

# Stop
docker compose --profile <profile> down

# View logs
docker compose logs -f

# Restart with a different model
WHISPER_MODEL_FILE=ggml-base.bin docker compose --profile <profile> up -d
```

The container is configured with `restart: unless-stopped`, so it will start automatically after a system reboot unless you explicitly stop it.

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
docker compose --profile <profile> down -v
```
