# python-service/tests/test_schemas_v2.py
from __future__ import annotations

from app.models.schemas import AnalyzeV2Response, ShotResultV2


class TestV2Schemas:
    def test_shot_result_v2_fields(self):
        s = ShotResultV2(x=0.1, y=-0.2, ring=9, score=9, confidence=0.8, kind="hole")
        assert s.ring == 9 and s.kind == "hole"

    def test_analyze_v2_response_roundtrip(self):
        r = AnalyzeV2Response(
            success=True,
            shots=[ShotResultV2(x=0.0, y=0.0, ring=10, score=10, confidence=0.9, kind="hole")],
            total_detected=1,
            expected_shot_count=1,
            detected_count=1,
            count_matches_expected=True,
            overall_confidence=0.9,
            needs_review=False,
            orientation_note="",
            vision_model="claude-opus-4-8",
            calibration={"ok": True, "rms_error_mm": 8.2, "confidence": 0.18, "rings_detected": 7, "error": None},
        )
        dumped = r.model_dump()
        assert dumped["shots"][0]["ring"] == 10
        assert dumped["needs_review"] is False
        assert dumped["expected_shot_count"] == 1
