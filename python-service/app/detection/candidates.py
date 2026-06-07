# python-service/app/detection/candidates.py
from __future__ import annotations

import cv2
import numpy as np

from app.calibration.target_intrinsic import CANONICAL_RING1_RADIUS
from app.config import TargetSpec


def _px_per_mm(spec: TargetSpec) -> float:
    return (2.0 * CANONICAL_RING1_RADIUS) / spec.ring1_diameter_mm


def _black_mask(shape: tuple[int, int], spec: TargetSpec) -> np.ndarray:
    h, w = shape
    black_radius_px = (spec.black_area_diameter_mm / 2.0) * _px_per_mm(spec)
    mask = np.zeros((h, w), np.uint8)
    cv2.circle(mask, (w // 2, h // 2), int(black_radius_px), 255, -1)
    return mask


def _dark_blobs(gray: np.ndarray) -> np.ndarray:
    """High-recall dark-on-light extraction (whole image; no black exclusion)."""
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    _, otsu = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
    _, fixed = cv2.threshold(blurred, 80, 255, cv2.THRESH_BINARY_INV)
    combined = cv2.bitwise_and(otsu, fixed)
    combined = cv2.morphologyEx(combined, cv2.MORPH_OPEN, np.ones((2, 2), np.uint8))
    combined = cv2.morphologyEx(combined, cv2.MORPH_CLOSE, np.ones((3, 3), np.uint8))
    return combined


def _light_blobs_in_black(gray: np.ndarray, black_mask: np.ndarray) -> np.ndarray:
    """High-recall light-on-dark extraction inside the black aiming area.

    Builds the actual dark region from the image by thresholding and then
    closing with a large kernel to fill small holes (bullet holes are light
    and would otherwise be excluded from the dark-region mask).  The search
    mask is the intersection of that filled dark region with the geometric
    black-area mask from the spec, ensuring white paper outside the printed
    black circle does not create false large contours.
    """
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    # Pixels that are genuinely dark in the image.
    _, actually_dark = cv2.threshold(blurred, 80, 255, cv2.THRESH_BINARY_INV)
    # Close with a large kernel to fill bullet-sized holes so the search mask
    # correctly covers the full black area including the light spot.
    filled_dark = cv2.morphologyEx(actually_dark, cv2.MORPH_CLOSE, np.ones((25, 25), np.uint8))
    # Restrict to the geometric spec region to avoid false detections on paper.
    search_mask = cv2.bitwise_and(filled_dark, black_mask)
    # Find light spots within the confirmed dark area.
    masked = cv2.bitwise_and(gray, gray, mask=search_mask)
    _, light = cv2.threshold(masked, 90, 255, cv2.THRESH_BINARY)
    light = cv2.bitwise_and(light, search_mask)
    light = cv2.morphologyEx(light, cv2.MORPH_OPEN, np.ones((2, 2), np.uint8))
    light = cv2.morphologyEx(light, cv2.MORPH_CLOSE, np.ones((3, 3), np.uint8))
    return light


def _centroids(binary: np.ndarray, min_area: float, max_area: float) -> list[tuple[float, float]]:
    contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    out: list[tuple[float, float]] = []
    for c in contours:
        area = cv2.contourArea(c)
        if area < min_area or area > max_area:
            continue
        m = cv2.moments(c)
        if m["m00"] == 0:
            continue
        out.append((m["m10"] / m["m00"], m["m01"] / m["m00"]))
    return out


def _dedupe(points: list[tuple[float, float]], radius: float) -> list[tuple[float, float]]:
    kept: list[tuple[float, float]] = []
    r2 = radius * radius
    for p in points:
        if all((p[0] - q[0]) ** 2 + (p[1] - q[1]) ** 2 > r2 for q in kept):
            kept.append(p)
    return kept


def detect_candidates(canonical: np.ndarray, spec: TargetSpec) -> list[tuple[float, float]]:
    """Return sub-pixel centroids of every bullet-sized blob (high recall; false
    positives expected — Claude discriminates downstream). Precision, not judgment."""
    gray = cv2.cvtColor(canonical, cv2.COLOR_BGR2GRAY)
    bullet_radius_px = (spec.bullet_diameter_mm / 2.0) * _px_per_mm(spec)
    bullet_area_px = np.pi * bullet_radius_px ** 2
    min_area = max(10.0, bullet_area_px * 0.03)
    max_area = bullet_area_px * 12.0

    black = _black_mask(gray.shape, spec)
    dark = _centroids(_dark_blobs(gray), min_area, max_area)
    light = _centroids(_light_blobs_in_black(gray, black), min_area, max_area)
    return _dedupe(dark + light, bullet_radius_px)
