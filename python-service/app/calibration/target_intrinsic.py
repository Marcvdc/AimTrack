"""
Intrinsic target calibration — perspective correction without ArUco markers.

Algorithm:
1. CLAHE-enhanced preprocessing for lighting normalisation
2. Rough target detection (black circular area → scaled to full target)
3. Ring detection via HoughCircles, parameterised from TargetSpec
4. Ellipse fitting per detected ring contour
5. Homography: map detected ellipse → circle in canonical 1000×1000px space
6. Warp image and measure RMS ring-fit error (mm) as confidence proxy
"""
from __future__ import annotations

import base64
import math
from dataclasses import dataclass

import cv2
import numpy as np

from app.config import TargetSpec

CANONICAL_SIZE: int = 1000
CANONICAL_MARGIN: float = 0.05  # 5% margin around ring-1 in canonical image
CANONICAL_RING1_RADIUS: float = CANONICAL_SIZE / 2 * (1.0 - CANONICAL_MARGIN)
MIN_RINGS_REQUIRED: int = 2


class CalibrationError(Exception):
    """Raised when calibration cannot converge."""

    def __init__(self, message: str, rings_detected: int = 0) -> None:
        super().__init__(message)
        self.rings_detected = rings_detected


@dataclass
class _DetectedEllipse:
    center: tuple[float, float]
    axes: tuple[float, float]  # (width, height) of bounding rect — full axes
    angle: float               # rotation in degrees (cv2 convention)
    ring_number: int           # which ring this ellipse corresponds to
    area: float

    @property
    def semi_major(self) -> float:
        return max(self.axes) / 2.0

    @property
    def semi_minor(self) -> float:
        return min(self.axes) / 2.0

    @property
    def eccentricity(self) -> float:
        a, b = self.semi_major, self.semi_minor
        if a == 0:
            return 0.0
        return math.sqrt(max(0.0, 1.0 - (b / a) ** 2))


@dataclass
class CalibrationResult:
    canonical_image: np.ndarray  # 1000×1000 BGR
    homography: np.ndarray        # 3×3 float64
    rms_error_mm: float
    confidence: float             # 0–1
    rings_detected: int
    target_spec: TargetSpec


# ---------------------------------------------------------------------------
# Public API
# ---------------------------------------------------------------------------


def calibrate(image: np.ndarray, spec: TargetSpec) -> CalibrationResult:
    """
    Perform intrinsic perspective calibration on a target photo.

    Args:
        image: BGR image as returned by cv2.imdecode.
        spec: TargetSpec for the discipline being shot.

    Returns:
        CalibrationResult with canonical image, homography, and quality metrics.

    Raises:
        CalibrationError: when fewer than MIN_RINGS_REQUIRED rings are detected.
    """
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    enhanced = _apply_clahe(gray)

    center_px, black_radius_px = _detect_black_area(enhanced)
    full_radius_px = black_radius_px * spec.total_to_black_scale

    # Primary reference: fit an ellipse directly on the black aiming-mark contour.
    # This is always visible regardless of sticker coverage on the outer rings.
    black_ellipse = _fit_ellipse_to_black_area(enhanced, center_px, black_radius_px, spec)

    ellipses = _detect_ring_ellipses(enhanced, center_px, full_radius_px, spec)

    if black_ellipse is None and len(ellipses) < MIN_RINGS_REQUIRED:
        raise CalibrationError(
            f"Te weinig ringen gevonden ({len(ellipses)}). "
            f"Minimaal {MIN_RINGS_REQUIRED} ringen nodig voor kalibratie. "
            "Zorg dat de roos niet volledig bedekt is met plakkers.",
            rings_detected=len(ellipses),
        )

    # Prefer the black-area ellipse as reference (always visible);
    # fall back to the best detected ring if black-area fit failed.
    if black_ellipse is not None:
        best_ellipse = black_ellipse
        rings_used = max(len(ellipses), 1)
    else:
        best_ellipse = _select_reference_ellipse(ellipses)
        rings_used = len(ellipses)

    H = _compute_homography(best_ellipse, spec)

    canonical = cv2.warpPerspective(image, H, (CANONICAL_SIZE, CANONICAL_SIZE))
    rms_mm = _compute_rms_error(canonical, H, ellipses, spec)
    confidence = _rms_to_confidence(rms_mm)

    return CalibrationResult(
        canonical_image=canonical,
        homography=H,
        rms_error_mm=round(rms_mm, 2),
        confidence=round(confidence, 3),
        rings_detected=rings_used,
        target_spec=spec,
    )


def canonical_to_b64_png(canonical: np.ndarray) -> str:
    """Encode a canonical BGR image to a base64 PNG string."""
    ok, buf = cv2.imencode(".png", canonical)
    if not ok:
        raise RuntimeError("PNG-codering mislukt")
    return base64.b64encode(buf.tobytes()).decode("ascii")


def homography_to_list(H: np.ndarray) -> list[list[float]]:
    """Convert 3×3 numpy homography matrix to nested Python list."""
    return H.tolist()


# ---------------------------------------------------------------------------
# Internal helpers
# ---------------------------------------------------------------------------


def _apply_clahe(gray: np.ndarray) -> np.ndarray:
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    return clahe.apply(gray)


def _fit_ellipse_to_black_area(
    gray: np.ndarray,
    center: tuple[float, float],
    black_radius_px: float,
    spec: TargetSpec,
) -> _DetectedEllipse | None:
    """
    Fit an ellipse directly to the boundary of the black aiming-mark area.

    The black circle is always visible regardless of sticker coverage on outer
    rings, making it the most reliable perspective-correction anchor. It
    corresponds to the outer edge of the black area (black_area_diameter_mm).
    """
    h, w = gray.shape
    cx, cy = center

    # Isolate the black area with a lenient threshold
    _, binary = cv2.threshold(gray, 90, 255, cv2.THRESH_BINARY_INV)

    # Mask: keep only what's within ~1.3× the expected black radius
    mask = np.zeros_like(binary)
    cv2.circle(mask, (int(cx), int(cy)), int(black_radius_px * 1.3), 255, -1)
    binary = cv2.bitwise_and(binary, mask)

    contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_NONE)
    if not contours:
        return None

    # Pick the largest contour near the target centre
    def _contour_score(c: np.ndarray) -> float:
        area = cv2.contourArea(c)
        m = cv2.moments(c)
        if m["m00"] == 0:
            return 0.0
        ccx = m["m10"] / m["m00"]
        ccy = m["m01"] / m["m00"]
        dist = math.hypot(ccx - cx, ccy - cy)
        return area / (1.0 + dist)

    best = max(contours, key=_contour_score)
    if len(best) < 5:
        return None

    # Use convex hull to smooth torn/irregular edges (bullet holes, stickers)
    # before fitting the ellipse — gives a much cleaner boundary estimate.
    hull = cv2.convexHull(best)
    if len(hull) < 5:
        hull = best

    try:
        (ecx, ecy), (ma, mi), angle = cv2.fitEllipse(hull)
    except cv2.error:
        return None

    # The black area corresponds to black_area_diameter_mm in the spec.
    # Find which ring number that maps to (typically ring 7 for pistol targets).
    black_diam_mm = spec.black_area_diameter_mm
    ring_number = min(
        spec.ring_diameters_mm.keys(),
        key=lambda r: abs(spec.ring_diameters_mm[r] - black_diam_mm),
    )

    return _DetectedEllipse(
        center=(ecx, ecy),
        axes=(ma, mi),
        angle=angle,
        ring_number=ring_number,
        area=cv2.contourArea(best),
    )


def _detect_black_area(
    gray: np.ndarray,
) -> tuple[tuple[float, float], float]:
    """
    Detect the dark circular aiming-mark area.

    Returns:
        (center_x, center_y), radius_in_pixels
    """
    h, w = gray.shape

    # Threshold to isolate dark region
    _, binary = cv2.threshold(gray, 100, 255, cv2.THRESH_BINARY_INV)
    contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    best: tuple[float, float, float] | None = None
    best_score = 0.0

    for contour in contours:
        area = cv2.contourArea(contour)
        min_area = (min(h, w) ** 2) * 0.03
        if area < min_area:
            continue

        perimeter = cv2.arcLength(contour, True)
        if perimeter == 0:
            continue

        circularity = 4.0 * math.pi * area / (perimeter**2)
        if circularity < 0.5:
            continue

        (cx, cy), radius = cv2.minEnclosingCircle(contour)
        score = area * circularity
        if score > best_score:
            best_score = score
            best = (cx, cy, radius)

    if best is not None:
        cx, cy, radius = best
        return (cx, cy), radius

    # Fallback: use Hough circles
    edges = cv2.Canny(gray, 30, 100)
    circles = cv2.HoughCircles(
        edges,
        cv2.HOUGH_GRADIENT,
        dp=1,
        minDist=h // 2,
        param1=50,
        param2=30,
        minRadius=int(min(h, w) * 0.15),
        maxRadius=int(min(h, w) * 0.65),
    )
    if circles is not None and len(circles[0]) > 0:
        cx, cy, r = circles[0][0]
        return (float(cx), float(cy)), float(r)

    # Last resort: image centre
    return (w / 2.0, h / 2.0), min(h, w) * 0.40


def _detect_ring_ellipses(
    gray: np.ndarray,
    center: tuple[float, float],
    full_radius_px: float,
    spec: TargetSpec,
) -> list[_DetectedEllipse]:
    """
    Detect concentric ring contours and fit ellipses to them.

    Uses adaptive thresholding around the target area so that ring boundaries
    — which are thin printed lines — become detectable edges.
    """
    h, w = gray.shape
    cx, cy = center

    # Crop to target area with 20% padding
    pad = 1.2
    x1 = max(0, int(cx - full_radius_px * pad))
    y1 = max(0, int(cy - full_radius_px * pad))
    x2 = min(w, int(cx + full_radius_px * pad))
    y2 = min(h, int(cy + full_radius_px * pad))

    roi = gray[y1:y2, x1:x2]

    # Edge detection to find ring boundaries
    blurred = cv2.GaussianBlur(roi, (5, 5), 0)
    edges = cv2.Canny(blurred, 20, 60)
    dilated = cv2.dilate(edges, np.ones((2, 2), np.uint8), iterations=1)

    contours, _ = cv2.findContours(dilated, cv2.RETR_LIST, cv2.CHAIN_APPROX_NONE)

    ellipses: list[_DetectedEllipse] = []

    # Expected pixel sizes for each ring based on spec + current scale
    scale = full_radius_px / (spec.ring1_diameter_mm / 2.0)

    for contour in contours:
        if len(contour) < 5:
            continue

        area = cv2.contourArea(contour)
        if area < 100:
            continue

        # Fit ellipse
        try:
            (ecx, ecy), (ma, mi), angle = cv2.fitEllipse(contour)
        except cv2.error:
            continue

        # Restore to full image coordinates
        ecx += x1
        ecy += y1

        # Centre must be near the target centre
        dist_from_center = math.hypot(ecx - cx, ecy - cy)
        if dist_from_center > full_radius_px * 0.3:
            continue

        # The mean axis should match one of the known ring diameters
        mean_radius_px = (ma + mi) / 4.0  # /4 because ma, mi are full axes
        ring_match = _match_ring_by_radius(mean_radius_px, scale, spec)

        if ring_match is None:
            continue

        # Reject very elongated ellipses (eccentricity too high = bad fit)
        if mi > 0 and ma / mi > 3.0:
            continue

        ellipses.append(
            _DetectedEllipse(
                center=(ecx, ecy),
                axes=(ma, mi),
                angle=angle,
                ring_number=ring_match,
                area=area,
            )
        )

    # Deduplicate: keep one ellipse per ring number (largest area wins)
    by_ring: dict[int, _DetectedEllipse] = {}
    for e in ellipses:
        if e.ring_number not in by_ring or e.area > by_ring[e.ring_number].area:
            by_ring[e.ring_number] = e

    return sorted(by_ring.values(), key=lambda e: e.ring_number)


def _match_ring_by_radius(
    radius_px: float,
    scale: float,  # px per mm
    spec: TargetSpec,
    tolerance: float = 0.20,
) -> int | None:
    """
    Find which ring number best matches a detected radius in pixels.

    Args:
        radius_px: detected radius in pixels.
        scale: pixels-per-mm conversion factor.
        spec: target spec with ring diameters.
        tolerance: fractional tolerance (0.20 = ±20%).

    Returns:
        Ring number (1–10) or None if no match within tolerance.
    """
    best_ring: int | None = None
    best_delta = math.inf

    for ring, diam_mm in spec.ring_diameters_mm.items():
        expected_radius_px = (diam_mm / 2.0) * scale
        if expected_radius_px == 0:
            continue
        delta = abs(radius_px - expected_radius_px) / expected_radius_px
        if delta < tolerance and delta < best_delta:
            best_delta = delta
            best_ring = ring

    return best_ring


def _select_reference_ellipse(ellipses: list[_DetectedEllipse]) -> _DetectedEllipse:
    """
    Select the best ellipse to base the homography on.

    Preference: outermost ring (lowest ring number = largest diameter),
    with a penalty for high eccentricity (distorted fit).
    """
    def score(e: _DetectedEllipse) -> float:
        size_score = e.semi_major
        eccentricity_penalty = e.eccentricity * e.semi_major * 0.3
        return size_score - eccentricity_penalty

    return max(ellipses, key=score)


def _compute_homography(
    ellipse: _DetectedEllipse,
    spec: TargetSpec,
) -> np.ndarray:
    """
    Compute a 3×3 perspective homography that maps the detected ellipse
    to the corresponding circle in the 1000×1000 canonical image.

    Samples 12 points uniformly on the ellipse and their canonical equivalents,
    then uses cv2.findHomography (RANSAC) for a robust fit.
    """
    cx, cy = ellipse.center
    a = ellipse.axes[0] / 2.0  # semi-axis along major direction
    b = ellipse.axes[1] / 2.0  # semi-axis along minor direction
    theta = math.radians(ellipse.angle)

    # Expected canonical radius for this ring
    ring_diam_mm = spec.ring_diameter_mm(ellipse.ring_number)
    ring1_diam_mm = spec.ring1_diameter_mm
    canonical_radius = CANONICAL_RING1_RADIUS * (ring_diam_mm / ring1_diam_mm)
    canonical_cx = CANONICAL_SIZE / 2.0
    canonical_cy = CANONICAL_SIZE / 2.0

    n_points = 12
    angles = np.linspace(0, 2.0 * math.pi, n_points, endpoint=False)

    # Points on detected ellipse (image space)
    src_pts = np.array(
        [
            [
                cx + a * math.cos(t) * math.cos(theta) - b * math.sin(t) * math.sin(theta),
                cy + a * math.cos(t) * math.sin(theta) + b * math.sin(t) * math.cos(theta),
            ]
            for t in angles
        ],
        dtype=np.float32,
    )

    # Corresponding points on circle in canonical space
    dst_pts = np.array(
        [
            [
                canonical_cx + canonical_radius * math.cos(t),
                canonical_cy + canonical_radius * math.sin(t),
            ]
            for t in angles
        ],
        dtype=np.float32,
    )

    H, _ = cv2.findHomography(src_pts, dst_pts, cv2.RANSAC, ransacReprojThreshold=3.0)

    if H is None:
        # Fallback: direct solve without RANSAC
        H, _ = cv2.findHomography(src_pts, dst_pts, 0)

    if H is None:
        raise CalibrationError(
            "Kon de perspectief-correctie niet berekenen. "
            "Probeer een scherpe foto recht boven de roos."
        )

    return H


def _compute_rms_error(
    canonical: np.ndarray,
    H: np.ndarray,
    ellipses: list[_DetectedEllipse],
    spec: TargetSpec,
) -> float:
    """
    Estimate calibration quality by measuring how circular the warped rings are.

    For each detected ellipse, warp its sample points through H and compare
    the resulting point distances from the canonical centre to the expected radius.
    Returns RMS error in mm.
    """
    if not ellipses:
        return 99.0

    ring1_diam_mm = spec.ring1_diameter_mm
    pixels_per_mm = CANONICAL_RING1_RADIUS / (ring1_diam_mm / 2.0)
    canonical_cx = CANONICAL_SIZE / 2.0
    canonical_cy = CANONICAL_SIZE / 2.0

    squared_errors: list[float] = []
    n_sample = 16
    sample_angles = np.linspace(0, 2.0 * math.pi, n_sample, endpoint=False)

    for ellipse in ellipses:
        cx, cy = ellipse.center
        a = ellipse.axes[0] / 2.0
        b = ellipse.axes[1] / 2.0
        theta = math.radians(ellipse.angle)

        expected_radius_mm = spec.ring_diameter_mm(ellipse.ring_number) / 2.0
        expected_radius_px = expected_radius_mm * pixels_per_mm

        for t in sample_angles:
            px = cx + a * math.cos(t) * math.cos(theta) - b * math.sin(t) * math.sin(theta)
            py = cy + a * math.cos(t) * math.sin(theta) + b * math.sin(t) * math.cos(theta)

            # Warp the point through H
            pt = np.array([[[px, py]]], dtype=np.float32)
            warped = cv2.perspectiveTransform(pt, H)
            wx, wy = warped[0][0]

            actual_radius_px = math.hypot(wx - canonical_cx, wy - canonical_cy)
            error_px = abs(actual_radius_px - expected_radius_px)
            error_mm = error_px / pixels_per_mm
            squared_errors.append(error_mm**2)

    if not squared_errors:
        return 99.0

    return math.sqrt(sum(squared_errors) / len(squared_errors))


def _rms_to_confidence(rms_mm: float, max_rms_mm: float = 10.0) -> float:
    """Convert RMS error in mm to a 0–1 confidence score."""
    return max(0.0, 1.0 - rms_mm / max_rms_mm)
