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
    Heuristic shot detection (pre-calibration / pre-ML).

    Used by /api/v1/analyze-target and -v2 for backwards-compat with the Laravel
    frontend. Returns shots in the [-1, +1] target-relative coordinate contract.

    Pipeline:
      1. detect_target_area → (cx, cy, r) of the target circle
      2. dual-threshold dark-spot extraction
      3. shape filters (area, circularity, solidity, aspect)
      4. sticker rejection (uniform-dark blobs / large machine-cut circles)
      5. bidirectional donut score (handles holes in light AND dark areas)
    """
    arr = np.frombuffer(image_data, np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    if img is None:
        raise HTTPException(status_code=400, detail="Ongeldig afbeeldingsformaat")

    h, w = img.shape[:2]
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    target_cx, target_cy, target_r = _legacy_detect_target_area(gray)
    print(
        f"DEBUG: image {w}×{h} target=({target_cx:.0f},{target_cy:.0f}) r={target_r:.0f}"
    )

    image_area = h * w
    min_area = image_area * 0.000015
    max_area = image_area * 0.0005

    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    _, otsu = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
    _, fixed = cv2.threshold(blurred, 80, 255, cv2.THRESH_BINARY_INV)
    combined = cv2.bitwise_and(otsu, fixed)

    kernel_open = np.ones((2, 2), np.uint8)
    kernel_close = np.ones((3, 3), np.uint8)
    combined = cv2.morphologyEx(combined, cv2.MORPH_OPEN, kernel_open)
    combined = cv2.morphologyEx(combined, cv2.MORPH_CLOSE, kernel_close)

    contours, _ = cv2.findContours(combined, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    shots: list[ShotResult] = []

    for contour in contours:
        area = cv2.contourArea(contour)
        if area < min_area or area > max_area:
            continue

        M = cv2.moments(contour)
        if M["m00"] == 0:
            continue
        x = M["m10"] / M["m00"]
        y = M["m01"] / M["m00"]

        perimeter = cv2.arcLength(contour, True)
        if perimeter == 0:
            continue
        circularity = 4.0 * np.pi * area / (perimeter ** 2)
        if circularity < 0.35:
            continue

        hull = cv2.convexHull(contour)
        hull_area = cv2.contourArea(hull)
        solidity = float(area) / hull_area if hull_area > 0 else 0
        if solidity < 0.55:
            continue

        _, _, wr, hr = cv2.boundingRect(contour)
        aspect = float(wr) / hr if hr > 0 else 0
        if aspect < 0.4 or aspect > 2.5:
            continue

        if _legacy_is_sticker(gray, contour, area, circularity):
            continue

        radius_est = float(np.sqrt(area / np.pi))
        ds = _legacy_donut_score(gray, int(x), int(y), radius_est)
        if abs(ds) < 8.0:
            continue

        x_norm = (x - target_cx) / target_r
        y_norm = (y - target_cy) / target_r
        if np.sqrt(x_norm ** 2 + y_norm ** 2) > 1.30:
            continue

        donut_norm = min(abs(ds) / 40.0, 1.0)
        confidence = round(0.4 * min(circularity / 0.9, 1.0) + 0.6 * donut_norm, 3)

        shots.append(
            ShotResult(x=round(x_norm, 3), y=round(y_norm, 3), confidence=confidence)
        )

    shots.sort(key=lambda s: s.confidence, reverse=True)
    return shots[:12]


def _legacy_is_sticker(
    gray: np.ndarray, contour: np.ndarray, area: float, circularity: float
) -> bool:
    """Stickers: uniformly dark interior OR very circular and large."""
    rect = cv2.boundingRect(contour)
    x0, y0, rw, rh = rect
    pad = max(3, int(max(rw, rh) * 0.15))
    x1 = max(0, x0 - pad)
    y1 = max(0, y0 - pad)
    x2 = min(gray.shape[1], x0 + rw + pad)
    y2 = min(gray.shape[0], y0 + rh + pad)
    region = gray[y1:y2, x1:x2]
    if region.size == 0:
        return False

    mean_intensity = float(np.mean(region))
    std_intensity = float(np.std(region))
    if mean_intensity < 55 and std_intensity < 12:
        return True
    if circularity > 0.82 and area > 400:
        return True
    return False


def _legacy_donut_score(
    gray: np.ndarray, cx: int, cy: int, radius: float
) -> float:
    """Centre brightness − ring brightness. Holes have strong contrast (any sign);
    stickers are uniform → near zero."""
    h, w = gray.shape
    inner_r = max(1, int(radius * 0.35))
    outer_r = max(inner_r + 2, int(radius * 0.85))

    mask_inner = np.zeros((h, w), dtype=np.uint8)
    mask_ring = np.zeros((h, w), dtype=np.uint8)
    cv2.circle(mask_inner, (cx, cy), inner_r, 255, -1)
    cv2.circle(mask_ring, (cx, cy), outer_r, 255, -1)
    cv2.circle(mask_ring, (cx, cy), inner_r, 0, -1)

    inner_pixels = gray[mask_inner == 255]
    ring_pixels = gray[mask_ring == 255]
    if inner_pixels.size == 0 or ring_pixels.size == 0:
        return 0.0
    return float(np.mean(inner_pixels)) - float(np.mean(ring_pixels))


def _legacy_detect_target_area(gray: np.ndarray) -> tuple[float, float, float]:
    """
    Find the central black bullseye via distance transform on enclosed dark
    components. Robust against asymmetric extensions (text, tape, bullet-hole
    nicks). Components touching the image border (cardboard backing) are
    skipped. Falls back to image centre if no plausible blob is found.
    """
    h, w = gray.shape
    min_dim = min(h, w)

    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    _, binary = cv2.threshold(blurred, 90, 255, cv2.THRESH_BINARY_INV)
    kernel = np.ones((3, 3), np.uint8)
    binary = cv2.morphologyEx(binary, cv2.MORPH_CLOSE, kernel)

    n_labels, labels, stats, _ = cv2.connectedComponentsWithStats(binary, connectivity=8)

    min_area = (min_dim ** 2) * 0.005
    max_area = h * w * 0.5

    best_radius = 0.0
    best_center: tuple[int, int] | None = None

    for label_id in range(1, n_labels):
        bx, by, bw_, bh_, area = stats[label_id]
        if bx == 0 or by == 0 or bx + bw_ >= w or by + bh_ >= h:
            continue
        if area < min_area or area > max_area:
            continue
        mask = (labels == label_id).astype(np.uint8) * 255
        local_dist = cv2.distanceTransform(mask, cv2.DIST_L2, 5)
        _, local_max, _, local_loc = cv2.minMaxLoc(local_dist)
        if local_max > best_radius:
            best_radius = float(local_max)
            best_center = (int(local_loc[0]), int(local_loc[1]))

    min_r = min_dim * 0.05
    max_r = min_dim * 0.50

    if best_center is not None and min_r <= best_radius <= max_r:
        cx, cy = best_center
        # Black area (rings 7-10) ≈ 0.60 of full target radius
        return (float(cx), float(cy), best_radius / 0.60)

    return w / 2.0, h / 2.0, min_dim * 0.46
