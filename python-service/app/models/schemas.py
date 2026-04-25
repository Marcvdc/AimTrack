from __future__ import annotations

from pydantic import BaseModel, Field


class ShotResult(BaseModel):
    """Single detected shot — backwards-compatible with existing Laravel contract."""

    x: float = Field(description="Horizontale positie t.o.v. roos-centrum [-1, 1]")
    y: float = Field(description="Verticale positie t.o.v. roos-centrum [-1, 1]")
    confidence: float = Field(ge=0.0, le=1.0, description="Detectie-zekerheid")


class AnalyzeResponse(BaseModel):
    """Response voor /api/v1/analyze-target en /api/v1/analyze-target-v2."""

    success: bool
    shots: list[ShotResult]
    total_detected: int


class CalibrateResponse(BaseModel):
    """Response voor /api/v1/calibrate."""

    success: bool
    target_type: str = Field(description="Bijv. 'kkp_25m'")
    calibration_method: str = Field(default="intrinsic")
    calibration_confidence: float = Field(
        ge=0.0,
        le=1.0,
        description="Hoe betrouwbaar de kalibratie is (1.0 = perfect)",
    )
    rms_error_mm: float = Field(
        description="Gemiddelde afwijking van gedetecteerde ringen t.o.v. spec (mm)"
    )
    rings_detected: int = Field(description="Aantal gevonden ringen")
    canonical_image_b64: str = Field(description="Base64-gecodeerde canonieke PNG (1000×1000px)")
    homography_matrix: list[list[float]] = Field(description="3×3 perspectief-homografie")


class CalibrationErrorDetail(BaseModel):
    """Foutdetail bij mislukte kalibratie."""

    code: str
    message: str
    rings_detected: int
    minimum_required: int
