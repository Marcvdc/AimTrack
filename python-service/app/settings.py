# python-service/app/settings.py
from __future__ import annotations

import os


class Settings:
    """Runtime configuration sourced from the environment. There is no pre-existing
    settings layer in this service; this is the single env-config entry point."""

    def __init__(self) -> None:
        self.anthropic_api_key: str = os.getenv("ANTHROPIC_API_KEY", "")
        self.vision_model: str = os.getenv("AIMTRACK_VISION_MODEL", "claude-opus-4-8")
        self.vision_effort: str = os.getenv("AIMTRACK_VISION_EFFORT", "high")
        self.review_confidence_threshold: float = float(os.getenv("AIMTRACK_REVIEW_CONFIDENCE", "0.6"))
        self.cal_rms_review_mm: float = float(os.getenv("AIMTRACK_CAL_RMS_REVIEW_MM", "20.0"))


settings = Settings()
