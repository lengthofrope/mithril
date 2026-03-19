FROM python:3.11-slim

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y --no-install-recommends ffmpeg libsndfile1 && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app

RUN pip install --no-cache-dir \
    "pyannote.audio>=4.0,<5" \
    "faster-whisper>=1.1,<2" \
    requests \
    fastapi \
    "uvicorn[standard]" \
    python-multipart

COPY server.py .

EXPOSE 8081

CMD ["uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8081"]
