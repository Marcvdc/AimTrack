# python-service/tests/test_settings.py
from __future__ import annotations

import importlib


class TestSettings:
    def test_defaults(self, monkeypatch):
        for var in ("ANTHROPIC_API_KEY", "AIMTRACK_VISION_MODEL", "AIMTRACK_VISION_EFFORT",
                    "AIMTRACK_REVIEW_CONFIDENCE", "AIMTRACK_CAL_RMS_REVIEW_MM"):
            monkeypatch.delenv(var, raising=False)
        import app.settings as s
        importlib.reload(s)
        assert s.settings.vision_model == "claude-opus-4-8"
        assert s.settings.vision_effort == "high"
        assert s.settings.review_confidence_threshold == 0.6
        assert s.settings.cal_rms_review_mm == 20.0
        assert s.settings.anthropic_api_key == ""

    def test_env_overrides(self, monkeypatch):
        monkeypatch.setenv("AIMTRACK_VISION_MODEL", "claude-sonnet-4-6")
        monkeypatch.setenv("AIMTRACK_REVIEW_CONFIDENCE", "0.8")
        import app.settings as s
        importlib.reload(s)
        assert s.settings.vision_model == "claude-sonnet-4-6"
        assert s.settings.review_confidence_threshold == 0.8
