"""Tests for the /transcribe endpoint."""


def test_transcribe_returns_text(client, sample_wav):
    """POST /transcribe returns JSON with text field."""
    response = client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    data = response.json()

    assert response.status_code == 200
    assert "text" in data
    assert isinstance(data["text"], str)
    assert len(data["text"]) > 0


def test_transcribe_without_file_returns_422(client):
    """POST /transcribe without a file returns 422."""
    response = client.post("/transcribe", data={"language": "en"})
    assert response.status_code == 422


def test_transcribe_default_language_is_en(client, sample_wav):
    """POST /transcribe without language param defaults to 'en'."""
    response = client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
    )
    assert response.status_code == 200


def test_transcribe_returns_503_when_models_not_loaded(unready_client, sample_wav):
    """POST /transcribe returns 503 when models are not yet loaded."""
    response = unready_client.post(
        "/transcribe",
        files={"file": ("test.wav", sample_wav, "audio/wav")},
        data={"language": "en"},
    )
    assert response.status_code == 503
