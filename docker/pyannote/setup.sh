#!/usr/bin/env bash
#
# Builds the pyannote diarization Docker image and starts the service.
#
# Usage:
#   ./setup.sh              # Auto-detect: NVIDIA GPU → CUDA, else → CPU
#   ./setup.sh --cuda       # Force CUDA mode
#   ./setup.sh --cpu        # Force CPU mode
#
# Prerequisites:
#   1. Create a HuggingFace account at https://huggingface.co/join
#   2. Accept the pyannote/speaker-diarization-community-1 license:
#      https://huggingface.co/pyannote/speaker-diarization-community-1
#   3. Accept the pyannote/segmentation-3.0 license:
#      https://huggingface.co/pyannote/segmentation-3.0
#   4. Generate an access token at https://huggingface.co/settings/tokens
#   5. Copy .env.example to .env and set HUGGINGFACE_TOKEN
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Load .env if present
if [ -f .env ]; then
    set -a
    source .env
    set +a
fi

PROFILE=""

for arg in "$@"; do
    case "$arg" in
        --cuda) PROFILE="cuda" ;;
        --cpu)  PROFILE="cpu" ;;
        *)      echo "Unknown argument: $arg"; exit 1 ;;
    esac
done

# Validate HuggingFace token
if [ -z "${HUGGINGFACE_TOKEN:-}" ]; then
    echo ""
    echo "ERROR: HUGGINGFACE_TOKEN is not set."
    echo ""
    echo "The pyannote speaker diarization model is gated and requires a HuggingFace token."
    echo ""
    echo "To get started:"
    echo "  1. Create a HuggingFace account at https://huggingface.co/join"
    echo "  2. Accept the model licenses:"
    echo "     - https://huggingface.co/pyannote/speaker-diarization-community-1"
    echo "     - https://huggingface.co/pyannote/segmentation-3.0"
    echo "  3. Generate an access token at https://huggingface.co/settings/tokens"
    echo "  4. Copy .env.example to .env and set HUGGINGFACE_TOKEN=hf_..."
    echo ""
    exit 1
fi

# Auto-detect GPU
if [ -z "$PROFILE" ]; then
    if command -v nvidia-smi &>/dev/null && nvidia-smi &>/dev/null; then
        PROFILE="cuda"
        echo "==> NVIDIA GPU detected, using CUDA mode"
    else
        PROFILE="cpu"
        echo "==> No NVIDIA GPU detected, using CPU mode"
    fi
fi

VOLUME_NAME="pyannote_pyannote-models"

echo "==> Mode: $PROFILE"
echo "==> Ensuring Docker volume exists..."
docker volume create "$VOLUME_NAME" 2>/dev/null || true

echo "==> Building pyannote image ($PROFILE)..."
docker compose --profile "$PROFILE" build

echo "==> Starting pyannote diarization service ($PROFILE)..."
docker compose --profile "$PROFILE" up -d

echo ""
echo "    Pyannote diarization service is running at http://localhost:${PYANNOTE_PORT:-8081}"
echo "    Mode: $PROFILE"
echo ""
echo "    Note: First request will be slow as models are downloaded to the Docker volume."
echo ""
echo "    Health check:"
echo "    curl http://localhost:${PYANNOTE_PORT:-8081}/health"
echo ""
echo "    Test with:"
echo "    curl http://localhost:${PYANNOTE_PORT:-8081}/diarize -F file=@audio.wav -F language=en"
