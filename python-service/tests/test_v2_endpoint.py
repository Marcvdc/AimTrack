# python-service/tests/test_v2_endpoint.py
from __future__ import annotations

import cv2
import numpy as np
from fastapi.testclient import TestClient

from app.pipeline import AnalysisResult
from app.config import KKP_25M
from app.api import routes
from main import app

client = TestClient(app)


def _png_bytes() -> bytes:
    img = np.full((50, 50, 3), 255, np.uint8)
    ok, buf = cv2.imencode(".png", img)
    return buf.tobytes()


def _fake_result() -> AnalysisResult:
    return AnalysisResult(
        shots=[{"x": 0.0, "y": 0.0, "ring": 10, "score": 10, "confidence": 0.9, "kind": "hole"}],
        expected_shot_count=1, detected_count=1, count_matches_expected=True,
        overall_confidence=0.9, needs_review=False, orientation_note="ok",
        vision_model="claude-opus-4-8",
        calibration={"ok": True, "error": None, "rms_error_mm": 8.0, "confidence": 0.18, "rings_detected": 7},
    )


class TestV2Endpoint:
    def test_returns_scored_shots(self, monkeypatch):
        monkeypatch.setattr(routes, "analyze_target_v2", lambda image, spec, n: _fake_result())
        resp = client.post(
            "/api/v2/analyze-target",
            files={"file": ("t.png", _png_bytes(), "image/png")},
            data={"target_type": "kkp_25m", "expected_shot_count": "1"},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["success"] is True
        assert body["shots"][0]["ring"] == 10
        assert body["needs_review"] is False
        assert body["count_matches_expected"] is True

    def test_unknown_target_type_is_400(self):
        resp = client.post(
            "/api/v2/analyze-target",
            files={"file": ("t.png", _png_bytes(), "image/png")},
            data={"target_type": "bogus"},
        )
        assert resp.status_code == 400

    def test_non_image_is_400(self):
        resp = client.post(
            "/api/v2/analyze-target",
            files={"file": ("t.txt", b"hello", "text/plain")},
            data={"target_type": "kkp_25m"},
        )
        assert resp.status_code == 400
