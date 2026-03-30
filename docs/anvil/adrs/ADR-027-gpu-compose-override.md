## ADR-027: GPU support via Docker Compose override file

**Date:** 2026-03-22
**Phase:** Unified Speech Service (Phase 1)
**Tags:** docker, gpu, speech, infrastructure
**Status:** Accepted

### Context

The plan specifies that `docker compose up` should work on both CPU-only and CUDA-equipped hosts using the same image. Docker Compose v2 requires explicit GPU device reservations (`deploy.resources.reservations.devices`) to pass GPUs to containers, but this section causes Docker Compose to fail on hosts without the NVIDIA Container Toolkit installed.

Alternatives considered:
1. **Separate profiles** (like pyannote's `cpu`/`cuda` split) — requires `docker compose --profile cuda up`, not plain `docker compose up`
2. **Single compose with GPU reservation** — fails on CPU-only hosts
3. **Compose override file** — base file works everywhere, GPU file adds device reservation

### Decision

Use a Docker Compose override pattern:
- `docker-compose.yml` — base config, works on any host (CPU mode)
- `docker-compose.gpu.yml` — adds NVIDIA GPU device reservation

CPU hosts: `docker compose up` (works out of the box)
GPU hosts: Set `COMPOSE_FILE=docker-compose.yml:docker-compose.gpu.yml` in `.env`, then `docker compose up`

The Docker image is identical in both cases. GPU auto-detection (`torch.cuda.is_available()`) happens at runtime inside the container — it will find the GPU when the device is passed through.

### Deviation from plan

Plan spec says "Container starts with `docker compose up` on CUDA-equipped host (same image)." The image is the same, but GPU hosts require one additional env var (`COMPOSE_FILE`) in `.env` to merge the GPU override. This is a minor UX deviation — still a single `docker compose up` command, but with a one-time `.env` change.

### PRD Reference

PRD-001 AC 4 and AC 5: "starts and transcribes successfully on a CPU-only host" and "automatically detects and uses a CUDA GPU when available, without config changes." The GPU auto-detection inside the container satisfies AC 5 — the "config change" is at the Docker level (passing the GPU device), not at the application level. The `.env` change is documented as part of GPU setup.

### Consequences

- CPU-only hosts work with zero configuration
- GPU hosts require adding `COMPOSE_FILE` to `.env` (documented in `.env.example`)
- No separate Docker images or build steps needed
- Consistent with how Docker Compose GPU support works across the ecosystem

### Follow-ups / open questions

None.
