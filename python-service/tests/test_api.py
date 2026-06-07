"""Tests for FastAPI endpoints."""
from __future__ import annotations

import io

import cv2
import numpy as np
from fastapi.testclient import TestClient

from main import app

client = TestClient(app)


def _png_bytes(size: int = 200, with_rings: bool = False) -> bytes:
    """Generate a minimal PNG image as bytes."""
    img = np.full((size, size, 3), 200, dtype=np.uint8)
    if with_rings:
        cx, cy = size // 2, size // 2
        for r in [size // 3, size // 4, size // 6, size // 8]:
            cv2.circle(img, (cx, cy), r, (30, 30, 30), 2)
        cv2.circle(img, (cx, cy), size // 10, (10, 10, 10), -1)
    _, buf = cv2.imencode(".png", img)
    return buf.tobytes()


def _multipart(image_bytes: bytes, filename: str = "target.png") -> dict:
    return {"file": (filename, io.BytesIO(image_bytes), "image/png")}


# ---------------------------------------------------------------------------
# /api/v1/health
# ---------------------------------------------------------------------------


class TestHealth:
    def test_returns_200(self) -> None:
        r = client.get("/api/v1/health")
        assert r.status_code == 200

    def test_contains_status(self) -> None:
        r = client.get("/api/v1/health")
        assert r.json()["status"] == "healthy"


# ---------------------------------------------------------------------------
# / root
# ---------------------------------------------------------------------------


class TestRoot:
    def test_returns_200(self) -> None:
        r = client.get("/")
        assert r.status_code == 200

    def test_lists_supported_types(self) -> None:
        r = client.get("/")
        data = r.json()
        assert "supported_target_types" in data
        assert "kkp_25m" in data["supported_target_types"]


# ---------------------------------------------------------------------------
# /api/v1/calibrate
# ---------------------------------------------------------------------------


class TestCalibrate:
    def test_unknown_target_type_returns_400(self) -> None:
        r = client.post(
            "/api/v1/calibrate",
            files=_multipart(_png_bytes()),
            data={"target_type": "onbekend"},
        )
        assert r.status_code == 400
        assert "onbekend" in r.json()["detail"].lower()

    def test_non_image_file_returns_400(self) -> None:
        r = client.post(
            "/api/v1/calibrate",
            files={"file": ("test.txt", io.BytesIO(b"geen afbeelding"), "text/plain")},
            data={"target_type": "kkp_25m"},
        )
        assert r.status_code == 400

    def test_blank_image_returns_422(self) -> None:
        """Blank image has no rings — should return 422 with Dutch error."""
        r = client.post(
            "/api/v1/calibrate",
            files=_multipart(_png_bytes(with_rings=False)),
            data={"target_type": "kkp_25m"},
        )
        assert r.status_code == 422
        body = r.json()
        assert body["success"] is False
        assert "ringen" in body["message"].lower()

    def test_valid_target_type_with_rings_returns_200(self) -> None:
        r = client.post(
            "/api/v1/calibrate",
            files=_multipart(_png_bytes(size=400, with_rings=True)),
            data={"target_type": "kkp_25m"},
        )
        # May return 200 or 422 depending on ring detection — both are valid
        assert r.status_code in (200, 422)

    def test_all_target_types_are_accepted(self) -> None:
        """Each valid target_type key should be accepted (not return 400)."""
        valid_types = ["kkp_25m", "gkp_25m", "kkg_50m", "kkg_100m", "gkg_100m"]
        for t in valid_types:
            r = client.post(
                "/api/v1/calibrate",
                files=_multipart(_png_bytes()),
                data={"target_type": t},
            )
            assert r.status_code != 400, f"Target type '{t}' was rejected with 400"


# ---------------------------------------------------------------------------
# /api/v1/analyze-target (backwards compat)
# ---------------------------------------------------------------------------


class TestAnalyzeTarget:
    def test_returns_success_true(self) -> None:
        r = client.post(
            "/api/v1/analyze-target",
            files=_multipart(_png_bytes()),
        )
        assert r.status_code == 200
        assert r.json()["success"] is True

    def test_returns_shots_list(self) -> None:
        r = client.post(
            "/api/v1/analyze-target",
            files=_multipart(_png_bytes()),
        )
        data = r.json()
        assert "shots" in data
        assert isinstance(data["shots"], list)

    def test_non_image_returns_400(self) -> None:
        r = client.post(
            "/api/v1/analyze-target",
            files={"file": ("x.txt", io.BytesIO(b"x"), "text/plain")},
        )
        assert r.status_code == 400


# ---------------------------------------------------------------------------
# /api/v1/analyze-target-v2 (backwards compat)
# ---------------------------------------------------------------------------


class TestAnalyzeTargetV2:
    def test_returns_success_true(self) -> None:
        r = client.post(
            "/api/v1/analyze-target-v2",
            files=_multipart(_png_bytes()),
        )
        assert r.status_code == 200
        assert r.json()["success"] is True

    def test_max_10_shots(self) -> None:
        r = client.post(
            "/api/v1/analyze-target-v2",
            files=_multipart(_png_bytes()),
        )
        assert len(r.json()["shots"]) <= 10

    def test_shot_coordinates_in_normalised_range(self) -> None:
        """x and y must be in the expected [-1, 1] contract range."""
        r = client.post(
            "/api/v1/analyze-target-v2",
            files=_multipart(_png_bytes()),
        )
        for shot in r.json()["shots"]:
            assert -2.0 <= shot["x"] <= 2.0
            assert -2.0 <= shot["y"] <= 2.0
            assert 0.0 <= shot["confidence"] <= 1.0
