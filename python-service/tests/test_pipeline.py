# python-service/tests/test_pipeline.py
from __future__ import annotations

import numpy as np

from app.calibration.target_intrinsic import CalibrationError, CalibrationResult
from app.config import KKP_25M
from app import pipeline
from app.pipeline import analyze_target_v2
from app.vision.claude_detector import VisionError


def _fake_cal(rms: float = 8.0) -> CalibrationResult:
    return CalibrationResult(
        canonical_image=np.full((1000, 1000, 3), 255, np.uint8),
        homography=np.eye(3),
        rms_error_mm=rms,
        confidence=0.18,
        rings_detected=7,
        target_spec=KKP_25M,
    )


def _fake_direct(shots: list[dict], overall: float = 0.9):
    """Build a detect_holes_direct stub returning the given vision-direct shots."""
    def _impl(image, spec, n, api_key=None):
        return {
            "shots": shots,
            "orientation_note": "raw",
            "overall_confidence": overall,
            "count_matches_expected": True,
        }
    return _impl


class TestPipeline:
    def test_happy_path_scores_and_no_review(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal())
        monkeypatch.setattr(pipeline, "detect_candidates", lambda canon, spec: [(500.0, 500.0)])
        monkeypatch.setattr(pipeline, "detect_holes", lambda canon, spec, n, api_key=None: {
            "shots": [{"x_px": 500, "y_px": 500, "confidence": 0.95, "kind": "hole"}],
            "orientation_note": "ok", "overall_confidence": 0.95, "count_matches_expected": True,
        })
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=1)
        assert result.detected_count == 1
        assert result.shots[0]["ring"] == 10
        assert result.count_matches_expected is True
        assert result.needs_review is False
        assert result.review_reason == ""
        assert result.calibration["method"] == "canonical"

    def test_count_mismatch_flags_review(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal())
        monkeypatch.setattr(pipeline, "detect_candidates", lambda canon, spec: [])
        monkeypatch.setattr(pipeline, "detect_holes", lambda canon, spec, n, api_key=None: {
            "shots": [{"x_px": 500, "y_px": 500, "confidence": 0.95, "kind": "hole"}],
            "orientation_note": "", "overall_confidence": 0.95, "count_matches_expected": False,
        })
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=5)
        assert result.detected_count == 1
        assert result.count_matches_expected is False
        assert result.needs_review is True
        assert "Aantal gedetecteerd" in result.review_reason

    def test_vision_failure_degrades_to_candidates(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal())
        monkeypatch.setattr(pipeline, "detect_candidates", lambda canon, spec: [(500.0, 500.0), (600.0, 500.0)])
        def boom(canon, spec, n, api_key=None):
            raise VisionError("down")
        monkeypatch.setattr(pipeline, "detect_holes", boom)
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=2)
        assert result.detected_count == 2
        assert result.overall_confidence == 0.0
        assert result.needs_review is True
        assert "Claude-key" in result.review_reason


class TestVisionDirectFallback:
    """Calibration is a hint, not a gate: a failed/weak homography falls back to the
    vision-direct path so the turn's shots are never discarded."""

    def test_calibration_failure_falls_back_to_vision_direct(self, monkeypatch):
        def boom(img, spec):
            raise CalibrationError("te weinig ringen", rings_detected=1)
        monkeypatch.setattr(pipeline, "calibrate", boom)
        monkeypatch.setattr(pipeline, "detect_holes_direct", _fake_direct([
            {"x_norm": 0.0, "y_norm": 0.0, "ring": 10, "confidence": 0.9, "kind": "hole"},
            {"x_norm": 0.3, "y_norm": -0.1, "ring": 8, "confidence": 0.8, "kind": "hole"},
        ]))
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=2)
        assert result.detected_count == 2                     # NOT empty anymore
        assert [s["ring"] for s in result.shots] == [10, 8]   # directly-read rings
        assert result.calibration["ok"] is False
        assert result.calibration["method"] == "vision_direct"
        assert result.needs_review is True
        assert "directe ring-aflezing" in result.review_reason

    def test_weak_calibration_uses_vision_direct(self, monkeypatch):
        # RMS above the fallback threshold -> homography unreliable -> vision-direct.
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal(rms=35.0))
        called = {"canonical": False}
        monkeypatch.setattr(pipeline, "detect_holes", lambda *a, **k: called.__setitem__("canonical", True))
        monkeypatch.setattr(pipeline, "detect_holes_direct", _fake_direct([
            {"x_norm": -0.2, "y_norm": 0.2, "ring": 7, "confidence": 0.7, "kind": "hole"},
        ]))
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=1)
        assert called["canonical"] is False                   # canonical path skipped
        assert result.detected_count == 1
        assert result.calibration["method"] == "vision_direct"
        assert result.calibration["rms_error_mm"] == 35.0

    def test_vision_direct_no_key_degrades_to_empty(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal(rms=40.0))
        def boom(image, spec, n, api_key=None):
            raise VisionError("no key")
        monkeypatch.setattr(pipeline, "detect_holes_direct", boom)
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=3)
        assert result.shots == []
        assert result.needs_review is True
        assert "Claude-key" in result.review_reason

    def test_vision_direct_filters_and_caps(self, monkeypatch):
        # One sub-confidence shot dropped; surplus over expected capped to the top-N.
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal(rms=50.0))
        monkeypatch.setattr(pipeline, "detect_holes_direct", _fake_direct([
            {"x_norm": 0.0, "y_norm": 0.0, "ring": 10, "confidence": 0.9, "kind": "hole"},
            {"x_norm": 0.1, "y_norm": 0.1, "ring": 9, "confidence": 0.8, "kind": "hole"},
            {"x_norm": 0.9, "y_norm": 0.0, "ring": 1, "confidence": 0.2, "kind": "uncertain"},
        ]))
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=2)
        assert result.detected_count == 2                      # 0.2 dropped, 2 kept
        assert sorted(s["ring"] for s in result.shots) == [9, 10]

    def test_high_rms_hints_at_wrong_discipline(self, monkeypatch):
        # A converged-but-poor fit (RMS well above fallback) usually means the chosen
        # discipline doesn't match the target -> the reason must say so, with the mm.
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal(rms=40.0))
        monkeypatch.setattr(pipeline, "detect_holes_direct", _fake_direct([
            {"x_norm": 0.0, "y_norm": 0.0, "ring": 10, "confidence": 0.7, "kind": "hole"},
        ]))
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=1)
        assert "discipline" in result.review_reason.lower()
        assert "40 mm" in result.review_reason

    def test_calibration_failure_reason_is_not_discipline(self, monkeypatch):
        # A hard CalibrationError is a photo problem, not a discipline mismatch.
        def boom(img, spec):
            raise CalibrationError("te weinig ringen", rings_detected=1)
        monkeypatch.setattr(pipeline, "calibrate", boom)
        monkeypatch.setattr(pipeline, "detect_holes_direct", _fake_direct([
            {"x_norm": 0.0, "y_norm": 0.0, "ring": 10, "confidence": 0.7, "kind": "hole"},
        ]))
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=1)
        assert "discipline" not in result.review_reason.lower()
        assert "rechtere" in result.review_reason.lower()
