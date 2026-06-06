# python-service/tests/test_pipeline.py
from __future__ import annotations

import numpy as np

from app.calibration.target_intrinsic import CalibrationError, CalibrationResult
from app.config import KKP_25M
from app import pipeline
from app.pipeline import analyze_target_v2
from app.vision.claude_detector import VisionError


def _fake_cal() -> CalibrationResult:
    return CalibrationResult(
        canonical_image=np.full((1000, 1000, 3), 255, np.uint8),
        homography=np.eye(3),
        rms_error_mm=8.0,
        confidence=0.18,
        rings_detected=7,
        target_spec=KKP_25M,
    )


class TestPipeline:
    def test_happy_path_scores_and_no_review(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal())
        monkeypatch.setattr(pipeline, "detect_candidates", lambda canon, spec: [(500.0, 500.0)])
        monkeypatch.setattr(pipeline, "detect_holes", lambda canon, spec, n: {
            "shots": [{"x_px": 500, "y_px": 500, "confidence": 0.95, "kind": "hole"}],
            "orientation_note": "ok", "overall_confidence": 0.95, "count_matches_expected": True,
        })
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=1)
        assert result.detected_count == 1
        assert result.shots[0]["ring"] == 10
        assert result.count_matches_expected is True
        assert result.needs_review is False

    def test_count_mismatch_flags_review(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal())
        monkeypatch.setattr(pipeline, "detect_candidates", lambda canon, spec: [])
        monkeypatch.setattr(pipeline, "detect_holes", lambda canon, spec, n: {
            "shots": [{"x_px": 500, "y_px": 500, "confidence": 0.95, "kind": "hole"}],
            "orientation_note": "", "overall_confidence": 0.95, "count_matches_expected": False,
        })
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=5)
        assert result.detected_count == 1
        assert result.count_matches_expected is False
        assert result.needs_review is True

    def test_vision_failure_degrades_to_candidates(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal())
        monkeypatch.setattr(pipeline, "detect_candidates", lambda canon, spec: [(500.0, 500.0), (600.0, 500.0)])
        def boom(canon, spec, n):
            raise VisionError("down")
        monkeypatch.setattr(pipeline, "detect_holes", boom)
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=2)
        assert result.detected_count == 2
        assert result.overall_confidence == 0.0
        assert result.needs_review is True

    def test_calibration_failure_returns_empty_review(self, monkeypatch):
        def boom(img, spec):
            raise CalibrationError("te weinig ringen", rings_detected=1)
        monkeypatch.setattr(pipeline, "calibrate", boom)
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=5)
        assert result.shots == []
        assert result.needs_review is True
        assert result.calibration["ok"] is False
