# python-service/tests/test_claude_detector.py
from __future__ import annotations

import json

import numpy as np
import pytest

from app.config import KKP_25M
from app.vision import claude_detector
from app.vision.claude_detector import (
    VisionError,
    _direct_system_prompt,
    _prepare_for_vision,
    _system_prompt,
    detect_holes,
    detect_holes_direct,
)


def _canonical() -> np.ndarray:
    return np.full((1000, 1000, 3), 255, np.uint8)


def _raw_photo() -> np.ndarray:
    return np.full((4032, 3024, 3), 200, np.uint8)


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
        monkeypatch.setattr(claude_detector, "_call_claude", lambda b64, system, api_key=None: json.dumps(payload))
        result = detect_holes(_canonical(), KKP_25M, expected_shot_count=2)
        assert len(result["shots"]) == 2
        assert result["shots"][0]["confidence"] == 1.0  # clamped
        assert result["shots"][0]["kind"] == "hole"
        assert result["overall_confidence"] == 0.92
        assert result["count_matches_expected"] is True

    def test_raises_vision_error_on_api_failure(self, monkeypatch):
        def boom(b64, system, api_key=None):
            raise RuntimeError("api down")
        monkeypatch.setattr(claude_detector, "_call_claude", boom)
        with pytest.raises(VisionError):
            detect_holes(_canonical(), KKP_25M, expected_shot_count=2)

    def test_raises_vision_error_on_bad_json(self, monkeypatch):
        monkeypatch.setattr(claude_detector, "_call_claude", lambda b64, system, api_key=None: "not json")
        with pytest.raises(VisionError):
            detect_holes(_canonical(), KKP_25M, expected_shot_count=2)


class TestSystemPrompt:
    def test_targets_fresh_holes_and_ignores_pasters(self):
        prompt = _system_prompt(KKP_25M, expected_shot_count=5).lower()
        assert "plakker" in prompt          # tells the model pasters are not shots
        assert "vers" in prompt             # only fresh holes
        assert "ringcijfers" in prompt      # ignore printed numbers
        assert "5 schoten" in prompt        # count guidance present


class TestDetectHolesDirect:
    def test_parses_normalized_and_clamps(self, monkeypatch):
        payload = {
            "shots": [
                {"x_norm": 0.1, "y_norm": -0.2, "ring": 9, "confidence": 1.3, "kind": "hole"},
                {"x_norm": 0.5, "y_norm": 0.4, "ring": 15, "confidence": 0.3, "kind": "uncertain"},
            ],
            "orientation_note": "kaart staat schuin",
            "overall_confidence": 0.8,
            "count_matches_expected": True,
        }
        monkeypatch.setattr(
            claude_detector, "_call_claude",
            lambda b64, system, api_key=None, schema=None: json.dumps(payload),
        )
        result = detect_holes_direct(_raw_photo(), KKP_25M, expected_shot_count=2)
        assert len(result["shots"]) == 2
        assert result["shots"][0]["x_norm"] == 0.1 and result["shots"][0]["y_norm"] == -0.2
        assert result["shots"][0]["confidence"] == 1.0   # clamped
        assert result["shots"][1]["ring"] == 10          # ring clamped to 0..10
        assert result["overall_confidence"] == 0.8

    def test_uses_direct_schema(self, monkeypatch):
        seen: dict = {}
        def capture(b64, system, api_key=None, schema=None):
            seen["schema"] = schema
            return json.dumps({"shots": [], "orientation_note": "", "overall_confidence": 0.0, "count_matches_expected": False})
        monkeypatch.setattr(claude_detector, "_call_claude", capture)
        detect_holes_direct(_raw_photo(), KKP_25M, expected_shot_count=None)
        assert seen["schema"] is claude_detector._DIRECT_SHOT_SCHEMA

    def test_raises_vision_error_on_bad_json(self, monkeypatch):
        monkeypatch.setattr(
            claude_detector, "_call_claude",
            lambda b64, system, api_key=None, schema=None: "not json",
        )
        with pytest.raises(VisionError):
            detect_holes_direct(_raw_photo(), KKP_25M, expected_shot_count=2)


class TestPrepareForVision:
    def test_downscales_oversized_preserving_aspect(self):
        out = _prepare_for_vision(_raw_photo(), max_dim=1500)
        assert max(out.shape[:2]) == 1500
        # 4032x3024 -> longest side 4032 scaled to 1500; aspect preserved
        assert out.shape[0] == 1500 and out.shape[1] == 1125

    def test_leaves_small_image_untouched(self):
        small = np.zeros((800, 600, 3), np.uint8)
        assert _prepare_for_vision(small, max_dim=1500).shape == (800, 600, 3)


class TestDirectSystemPrompt:
    def test_asks_for_normalized_and_directly_read_ring(self):
        prompt = _direct_system_prompt(KKP_25M, expected_shot_count=None).lower()
        assert "onbewerkte" in prompt        # raw photo, not calibrated
        assert "genormaliseerd" in prompt    # normalized position requested
        assert "direct" in prompt            # read the ring directly
        assert "plakker" in prompt           # ignore pasters
