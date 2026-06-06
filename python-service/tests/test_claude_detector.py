# python-service/tests/test_claude_detector.py
from __future__ import annotations

import json

import numpy as np
import pytest

from app.config import KKP_25M
from app.vision import claude_detector
from app.vision.claude_detector import VisionError, detect_holes


def _canonical() -> np.ndarray:
    return np.full((1000, 1000, 3), 255, np.uint8)


class TestDetectHoles:
    def test_parses_and_clamps(self, monkeypatch):
        payload = {
            "shots": [
                {"x_px": 500, "y_px": 500, "confidence": 1.4, "kind": "hole"},
                {"x_px": 700, "y_px": 480, "confidence": 0.9, "kind": "uncertain"},
            ],
            "orientation_note": "printed 10 at top",
            "overall_confidence": 0.92,
            "count_matches_expected": True,
        }
        monkeypatch.setattr(claude_detector, "_call_claude", lambda b64, system: json.dumps(payload))
        result = detect_holes(_canonical(), KKP_25M, expected_shot_count=2)
        assert len(result["shots"]) == 2
        assert result["shots"][0]["confidence"] == 1.0  # clamped
        assert result["shots"][0]["kind"] == "hole"
        assert result["overall_confidence"] == 0.92
        assert result["count_matches_expected"] is True

    def test_raises_vision_error_on_api_failure(self, monkeypatch):
        def boom(b64, system):
            raise RuntimeError("api down")
        monkeypatch.setattr(claude_detector, "_call_claude", boom)
        with pytest.raises(VisionError):
            detect_holes(_canonical(), KKP_25M, expected_shot_count=2)

    def test_raises_vision_error_on_bad_json(self, monkeypatch):
        monkeypatch.setattr(claude_detector, "_call_claude", lambda b64, system: "not json")
        with pytest.raises(VisionError):
            detect_holes(_canonical(), KKP_25M, expected_shot_count=2)
