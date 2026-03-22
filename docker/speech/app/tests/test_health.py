"""Tests for the /health endpoint."""


def test_health_returns_status(client):
    """Health endpoint returns expected structure when models are loaded."""
    response = client.get("/health")
    data = response.json()

    assert response.status_code == 200
    assert data["ready"] is True
    assert data["device"] in ("cpu", "cuda")
    assert "models" in data
    assert "whisper" in data["models"]
    assert "queue_depth" in data
    assert isinstance(data["queue_depth"], int)
    assert "diarization_engine" in data


def test_health_reports_not_ready_before_models_load(unready_client):
    """Health endpoint reports ready=false when models are not yet loaded."""
    response = unready_client.get("/health")
    data = response.json()

    assert response.status_code == 200
    assert data["ready"] is False
