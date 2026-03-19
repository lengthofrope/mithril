#!/usr/bin/env bash
#
# Downloads a Whisper model into the Docker volume and starts the service.
#
# Usage:
#   ./setup.sh                    # Auto-detect GPU, fallback to CPU
#   ./setup.sh --gpu              # Force NVIDIA GPU mode
#   ./setup.sh --cpu              # Force CPU mode
#   ./setup.sh ggml-base.bin      # Auto-detect with a specific model
#   ./setup.sh --gpu ggml-base.bin
#
# Available models (smallest → largest):
#   ggml-tiny.bin        ~  75 MB   (fastest, lowest quality)
#   ggml-base.bin        ~ 142 MB
#   ggml-small.bin       ~ 466 MB
#   ggml-medium.bin      ~1.5 GB
#   ggml-large-v3.bin    ~3.1 GB    (best quality)
#   ggml-large-v3-turbo.bin ~1.6 GB (recommended: near-large quality, much faster)

set -euo pipefail

PROFILE=""
MODEL_FILE=""

for arg in "$@"; do
    case "$arg" in
        --gpu) PROFILE="gpu" ;;
        --cpu) PROFILE="cpu" ;;
        *)     MODEL_FILE="$arg" ;;
    esac
done

if [ -z "$PROFILE" ]; then
    if command -v nvidia-smi &>/dev/null && nvidia-smi &>/dev/null && docker info 2>/dev/null | grep -qi nvidia; then
        PROFILE="gpu"
        echo "==> NVIDIA GPU detected, using GPU mode"
    else
        PROFILE="cpu"
        echo "==> No NVIDIA GPU detected, using CPU mode"
    fi
fi

MODEL_FILE="${MODEL_FILE:-ggml-large-v3-turbo.bin}"
BASE_URL="https://huggingface.co/ggerganov/whisper.cpp/resolve/main"
VOLUME_NAME="whispercpp_whisper-models"

echo "==> Mode: $PROFILE"
echo "==> Ensuring Docker volume exists..."
docker volume create "$VOLUME_NAME" 2>/dev/null || true

echo "==> Checking if model '$MODEL_FILE' is already downloaded..."
EXISTS=$(docker run --rm -v "$VOLUME_NAME:/models" alpine sh -c "[ -f /models/$MODEL_FILE ] && echo yes || echo no")

if [ "$EXISTS" = "yes" ]; then
    echo "    Model already present, skipping download."
else
    echo "==> Downloading $MODEL_FILE (this may take a while)..."
    docker run --rm -v "$VOLUME_NAME:/models" alpine sh -c \
        "apk add --no-cache curl && curl -L -o /models/$MODEL_FILE $BASE_URL/$MODEL_FILE"
    echo "    Download complete."
fi

echo "==> Starting whisper.cpp server ($PROFILE)..."
WHISPER_MODEL_FILE="$MODEL_FILE" docker compose --profile "$PROFILE" up -d

echo ""
echo "    whisper.cpp server is running at http://localhost:${WHISPER_CPP_PORT:-8080}"
echo "    Model: $MODEL_FILE"
echo "    Mode:  $PROFILE"
echo ""
echo "    Test with:"
echo "    curl http://localhost:${WHISPER_CPP_PORT:-8080}/inference -F file=@audio.wav -F response_format=json"
