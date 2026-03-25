# Installation Guide

## Prerequisites

- Docker Engine 24+ with Docker Compose v2
- (Optional) NVIDIA GPU with CUDA support + [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/install-guide.html)

## CPU-Only Installation

1. Navigate to the speech service directory:

   ```bash
   cd docker/speech
   ```

2. Copy the environment template:

   ```bash
   cp .env.example .env
   ```

3. (Optional) Edit `.env` to choose a smaller model for development:

   ```env
   WHISPER_MODEL=tiny
   ```

4. Build and start the service:

   ```bash
   docker compose up --build -d
   ```

5. Wait for the model to download (check readiness):

   ```bash
   curl http://localhost:8090/health
   ```

   The `ready` field will be `true` once the model is loaded.

6. Test transcription:

   ```bash
   curl -X POST http://localhost:8090/transcribe \
     -F "file=@/path/to/audio.wav" \
     -F "language=en"
   ```

## GPU Installation

Follow the CPU steps above, then:

1. Install the [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/install-guide.html)

2. Add the GPU compose override to your `.env`:

   ```env
   COMPOSE_FILE=docker-compose.yml:docker-compose.gpu.yml
   ```

3. Restart the service:

   ```bash
   docker compose down
   docker compose up -d
   ```

4. Verify GPU is detected:

   ```bash
   curl http://localhost:8090/health
   ```

   The `device` field should report `cuda`.

## Enabling Pyannote Diarization (Optional)

For higher-quality speaker diarization, provide a HuggingFace token:

1. Get a token at https://huggingface.co/settings/tokens
2. Accept the model license at https://huggingface.co/pyannote/speaker-diarization-3.1
3. Add to `.env`:

   ```env
   HUGGINGFACE_TOKEN=hf_your_token_here
   ```

4. Restart: `docker compose restart`

Without a token, the default diarization engine is used (no account needed).

## Connecting to Mithril

In Mithril's root `.env` file, set:

```env
MEETING_TRANSCRIPTION_PROVIDER=unified
MEETING_DIARIZATION_PROVIDER=unified
UNIFIED_SPEECH_BASE_URL=http://localhost:8090
```

## Updating the Model

To switch models without rebuilding the image:

1. Update `WHISPER_MODEL` in `.env`
2. Restart the container: `docker compose restart`
3. The new model downloads on startup (first request waits until ready)

The old model remains in the volume cache; it is not automatically deleted.

## Troubleshooting

### Service shows `ready: false` for a long time

The model is still downloading. Check container logs:

```bash
docker compose logs -f speech
```

### Out of memory errors

Large models on CPU can require significant RAM. Try a smaller model:

```env
WHISPER_MODEL=small
```

### GPU not detected (device shows "cpu" on a GPU host)

1. Verify the NVIDIA Container Toolkit is installed: `nvidia-ctk --version`
2. Generate the CDI spec: `sudo nvidia-ctk cdi generate --output=/etc/cdi/nvidia.yaml`
3. Restart Docker: `sudo systemctl restart docker`
4. Verify the GPU compose override is active: check `COMPOSE_FILE` in `.env`
5. Verify Docker can see the GPU: `docker run --rm --gpus all nvidia/cuda:12.6.3-base-ubuntu24.04 nvidia-smi`

### Port conflict

If port 8090 is in use, change `SPEECH_PORT` in `.env`:

```env
SPEECH_PORT=8091
```
