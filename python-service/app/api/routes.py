from __future__ import annotations

import cv2
import numpy as np
from typing import Union

from fastapi import APIRouter, File, Form, HTTPException, UploadFile
from fastapi.responses import JSONResponse

from app.calibration.target_intrinsic import (
    CalibrationError,
    CalibrationResult,
    calibrate,
    canonical_to_b64_png,
    homography_to_list,
)
from app.config import TARGET_SPECS
from app.models.schemas import (
    AnalyzeResponse,
    CalibrationErrorDetail,
    CalibrateResponse,
    ShotResult,
)

router = APIRouter()


# ---------------------------------------------------------------------------
# Kalibratie — nieuw endpoint (Fase 1)
# ---------------------------------------------------------------------------


@router.post("/api/v1/calibrate", response_model=CalibrateResponse)
async def calibrate_target(
    file: UploadFile = File(...),
    target_type: str = Form(...),
) -> Union[CalibrateResponse, JSONResponse]:
    """
    Voer perspectiefcorrectie uit op een roos-foto via intrinsieke kalibratie.

    Parameters:
        file: Foto van de schietschijf (JPEG/PNG).
        target_type: Discipline-sleutel, bijv. 'kkp_25m', 'kkg_50m', 'gkg_100m'.

    Returns:
        Canonieke 1000×1000px afbeelding (base64 PNG) + homografie-matrix.
    """
    if not file.content_type or not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Bestand moet een afbeelding zijn")

    spec = TARGET_SPECS.get(target_type)
    if spec is None:
        valid = ", ".join(sorted(TARGET_SPECS.keys()))
        raise HTTPException(
            status_code=400,
            detail=f"Onbekend roos-type '{target_type}'. Geldige waarden: {valid}",
        )

    image_data = await file.read()
    image = _decode_image(image_data)

    try:
        result: CalibrationResult = calibrate(image, spec)
    except CalibrationError as exc:
        detail = CalibrationErrorDetail(
            code="INSUFFICIENT_RINGS",
            message=str(exc),
            rings_detected=exc.rings_detected,
            minimum_required=2,
        )
        return JSONResponse(status_code=422, content={"success": False, **detail.model_dump()})

    return CalibrateResponse(
        success=True,
        target_type=target_type,
        calibration_method="intrinsic",
        calibration_confidence=result.confidence,
        rms_error_mm=result.rms_error_mm,
        rings_detected=result.rings_detected,
        canonical_image_b64=canonical_to_b64_png(result.canonical_image),
        homography_matrix=homography_to_list(result.homography),
    )


# ---------------------------------------------------------------------------
# Bestaande endpoints — backwards-compatible, ongewijzigd output-contract
# ---------------------------------------------------------------------------


@router.post("/api/v1/analyze-target", response_model=AnalyzeResponse)
async def analyze_target(file: UploadFile = File(...)) -> AnalyzeResponse:
    """
    Detecteer inschoten op een roos-foto.

    .. deprecated::
        Gebruik /api/v2/analyze-target (Fase 3). Dit endpoint blijft beschikbaar
        voor backwards-compatibiliteit met de bestaande Laravel-frontend.
    """
    if not file.content_type or not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Bestand moet een afbeelding zijn")

    image_data = await file.read()
    shots = _legacy_detect(image_data)

    return AnalyzeResponse(success=True, shots=shots, total_detected=len(shots))


@router.post("/api/v1/analyze-target-v2", response_model=AnalyzeResponse)
async def analyze_target_v2(file: UploadFile = File(...)) -> AnalyzeResponse:
    """
    Detecteer inschoten (max 10) op een roos-foto.

    .. deprecated::
        Gebruik /api/v2/analyze-target (Fase 3). Dit endpoint blijft beschikbaar
        voor backwards-compatibiliteit met de bestaande Laravel-frontend.
    """
    if not file.content_type or not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Bestand moet een afbeelding zijn")

    image_data = await file.read()
    shots = _legacy_detect(image_data)[:10]

    return AnalyzeResponse(success=True, shots=shots, total_detected=len(shots))


# ---------------------------------------------------------------------------
# Health
# ---------------------------------------------------------------------------


@router.get("/api/v1/health")
async def health_check() -> dict[str, str]:
    return {"status": "healthy", "service": "aimtrack-image-processor"}


@router.get("/")
async def root() -> dict[str, object]:
    return {
        "message": "AimTrack Image Processing Service",
        "version": "2.0.0",
        "endpoints": {
            "calibrate": "/api/v1/calibrate (POST) — perspectiefcorrectie",
            "analyze_target": "/api/v1/analyze-target (POST) — [deprecated]",
            "analyze_target_v2": "/api/v1/analyze-target-v2 (POST) — [deprecated]",
            "health": "/api/v1/health (GET)",
        },
        "supported_target_types": list(TARGET_SPECS.keys()),
    }


# ---------------------------------------------------------------------------
# Legacy detection — preserved from original main.py for backwards compat
# ---------------------------------------------------------------------------


def _decode_image(data: bytes) -> np.ndarray:
    arr = np.frombuffer(data, np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    if img is None:
        raise HTTPException(status_code=400, detail="Ongeldig afbeeldingsformaat")
    return img


def _legacy_detect(image_data: bytes) -> list[ShotResult]:
    """
    Preserved original detection logic.
    Returns shots in the [-1, 1] normalised coordinate contract.
    """
    import math as _math

    from skimage.feature import local_binary_pattern

    arr = np.frombuffer(image_data, np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    if img is None:
        raise HTTPException(status_code=400, detail="Ongeldig afbeeldingsformaat")

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    target_cx, target_cy, target_r = _legacy_detect_target_area(gray)

    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    _, thresh = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
    _, dark = cv2.threshold(blurred, 70, 255, cv2.THRESH_BINARY_INV)
    combined = cv2.bitwise_and(thresh, dark)
    kernel2 = np.ones((2, 2), np.uint8)
    kernel3 = np.ones((3, 3), np.uint8)
    combined = cv2.morphologyEx(combined, cv2.MORPH_OPEN, kernel2)
    combined = cv2.morphologyEx(combined, cv2.MORPH_CLOSE, kernel3)

    contours, _ = cv2.findContours(combined, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    shots: list[ShotResult] = []

    for contour in contours:
        area = cv2.contourArea(contour)
        if area < 15 or area > 300:
            continue
        M = cv2.moments(contour)
        if M["m00"] == 0:
            continue
        x = M["m10"] / M["m00"]
        y = M["m01"] / M["m00"]
        perimeter = cv2.arcLength(contour, True)
        if perimeter == 0:
            continue
        circularity = 4.0 * _math.pi * area / (perimeter**2)
        if circularity < 0.4:
            continue
        hull = cv2.convexHull(contour)
        hull_area = cv2.contourArea(hull)
        if hull_area == 0 or float(area) / hull_area < 0.6:
            continue
        _, _, wr, hr = cv2.boundingRect(contour)
        aspect = float(wr) / hr if hr > 0 else 0
        if aspect < 0.5 or aspect > 2.0:
            continue

        region = _extract_region(gray, contour)
        lbp = local_binary_pattern(region, P=8, R=1, method="uniform") if region.size >= 9 else np.array([0.0])
        texture = min(float(np.var(lbp)) / 50.0, 1.0)
        likelihood = texture * 0.40 + 0.60 * circularity

        if likelihood < 0.35:
            continue

        x_norm = (x - target_cx) / target_r
        y_norm = (y - target_cy) / target_r
        shots.append(ShotResult(x=round(x_norm, 3), y=round(y_norm, 3), confidence=round(likelihood, 3)))

    shots.sort(key=lambda s: s.confidence, reverse=True)
    return shots[:12]


def _extract_region(gray: np.ndarray, contour: np.ndarray) -> np.ndarray:
    rect = cv2.boundingRect(contour)
    x, y, w, h = rect
    pad = max(5, int(max(w, h) * 0.3))
    x1 = max(0, x - pad)
    y1 = max(0, y - pad)
    x2 = min(gray.shape[1], x + w + pad)
    y2 = min(gray.shape[0], y + h + pad)
    return gray[y1:y2, x1:x2]


def _legacy_detect_target_area(gray: np.ndarray) -> tuple[float, float, float]:
    import math as _math

    h, w = gray.shape
    _, binary = cv2.threshold(gray, 100, 255, cv2.THRESH_BINARY_INV)
    contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    best: tuple[float, float, float] | None = None
    best_score = 0.0
    for contour in contours:
        area = cv2.contourArea(contour)
        if area < (min(h, w) ** 2) * 0.05:
            continue
        perimeter = cv2.arcLength(contour, True)
        if perimeter == 0:
            continue
        circularity = 4.0 * _math.pi * area / perimeter**2
        if circularity < 0.6:
            continue
        (cx, cy), radius = cv2.minEnclosingCircle(contour)
        score = area * circularity
        if score > best_score:
            best_score = score
            best = (cx, cy, radius * 1.67)

    if best:
        return best

    edges = cv2.Canny(gray, 30, 100)
    circles = cv2.HoughCircles(edges, cv2.HOUGH_GRADIENT, 1, h // 2, param1=50, param2=30,
                                minRadius=int(min(h, w) * 0.2), maxRadius=int(min(h, w) * 0.7))
    if circles is not None and len(circles[0]) > 0:
        cx, cy, r = circles[0][0]
        return float(cx), float(cy), float(r)

    return w / 2.0, h / 2.0, min(h, w) * 0.46
