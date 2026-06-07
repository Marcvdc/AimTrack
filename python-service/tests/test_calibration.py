"""Tests for intrinsic calibration module."""
from __future__ import annotations

import math

import cv2
import numpy as np
import pytest

from app.calibration.target_intrinsic import (
    CANONICAL_SIZE,
    MIN_RINGS_REQUIRED,
    CalibrationError,
    _DetectedEllipse,
    _apply_clahe,
    _compute_homography,
    _detect_black_area,
    _match_ring_by_radius,
    _rms_to_confidence,
    _select_reference_ellipse,
    calibrate,
    canonical_to_b64_png,
    homography_to_list,
)
from app.config import KKP_25M, GKG_100M


# ---------------------------------------------------------------------------
# Synthetic image helpers
# ---------------------------------------------------------------------------


def _make_concentric_rings_image(
    size: int = 600,
    rings: dict[int, int] | None = None,  # ring_number → radius_px
    bg_gray: int = 240,
    ring_gray: int = 50,
    line_thickness: int = 3,
) -> np.ndarray:
    """Create a synthetic BGR image with concentric rings on a grey background."""
    img = np.full((size, size, 3), bg_gray, dtype=np.uint8)
    cx, cy = size // 2, size // 2
    if rings is None:
        rings = {1: 260, 3: 220, 5: 160, 7: 100, 10: 40}
    for _, radius in sorted(rings.items(), reverse=True):
        cv2.circle(img, (cx, cy), radius, (ring_gray, ring_gray, ring_gray), line_thickness)
    # Fill innermost circle to simulate black aiming mark
    min_r = min(rings.values())
    cv2.circle(img, (cx, cy), min_r, (20, 20, 20), -1)
    return img


def _apply_perspective(img: np.ndarray, tilt_x: float = 0.0, tilt_y: float = 0.0) -> np.ndarray:
    """
    Apply a synthetic perspective transform to simulate camera tilt.
    tilt_x/tilt_y are fractions of image size (small values = small tilt).
    """
    h, w = img.shape[:2]
    src = np.float32([[0, 0], [w, 0], [w, h], [0, h]])
    dx = int(w * tilt_x)
    dy = int(h * tilt_y)
    dst = np.float32([[dx, dy], [w - dx, dy], [w, h], [0, h]])
    M = cv2.getPerspectiveTransform(src, dst)
    return cv2.warpPerspective(img, M, (w, h))


# ---------------------------------------------------------------------------
# Unit tests for helper functions
# ---------------------------------------------------------------------------


class TestApplyClahe:
    def test_output_same_shape(self) -> None:
        gray = np.random.randint(0, 256, (300, 300), dtype=np.uint8)
        result = _apply_clahe(gray)
        assert result.shape == gray.shape

    def test_output_is_uint8(self) -> None:
        gray = np.zeros((100, 100), dtype=np.uint8)
        result = _apply_clahe(gray)
        assert result.dtype == np.uint8


class TestDetectBlackArea:
    def test_detects_circle_on_white_background(self) -> None:
        img = np.full((500, 500), 255, dtype=np.uint8)
        cv2.circle(img, (250, 250), 120, 0, -1)
        center, radius = _detect_black_area(img)
        assert abs(center[0] - 250) < 20
        assert abs(center[1] - 250) < 20
        assert abs(radius - 120) < 25

    def test_fallback_on_blank_image(self) -> None:
        img = np.full((400, 400), 200, dtype=np.uint8)
        center, radius = _detect_black_area(img)
        assert 0 < radius < 400


class TestMatchRingByRadius:
    def test_exact_match_ring10_kkp(self) -> None:
        scale = 1.0  # 1 px per mm
        radius_px = KKP_25M.ring_diameters_mm[10] / 2.0
        result = _match_ring_by_radius(radius_px, scale, KKP_25M)
        assert result == 10

    def test_no_match_outside_tolerance(self) -> None:
        scale = 1.0
        result = _match_ring_by_radius(9999.0, scale, KKP_25M)
        assert result is None

    def test_match_within_tolerance(self) -> None:
        scale = 2.0  # 2 px per mm
        expected_radius_px = KKP_25M.ring_diameters_mm[5] / 2.0 * scale
        # 5% off — still closest to ring 5, not ring 4 (which is 16.7% further)
        close_radius = expected_radius_px * 1.05
        result = _match_ring_by_radius(close_radius, scale, KKP_25M, tolerance=0.15)
        assert result == 5


class TestSelectReferenceEllipse:
    def _make_ellipse(self, ring: int, semi_major: float, ecc: float = 0.0) -> _DetectedEllipse:
        b = semi_major * math.sqrt(max(0, 1 - ecc**2))
        return _DetectedEllipse(
            center=(100.0, 100.0),
            axes=(semi_major * 2, b * 2),
            angle=0.0,
            ring_number=ring,
            area=math.pi * semi_major * b,
        )

    def test_prefers_outermost_ring(self) -> None:
        e1 = self._make_ellipse(ring=1, semi_major=200.0)
        e5 = self._make_ellipse(ring=5, semi_major=100.0)
        assert _select_reference_ellipse([e1, e5]) is e1

    def test_penalises_high_eccentricity(self) -> None:
        e_good = self._make_ellipse(ring=1, semi_major=200.0, ecc=0.1)
        e_bad = self._make_ellipse(ring=1, semi_major=210.0, ecc=0.9)
        assert _select_reference_ellipse([e_good, e_bad]) is e_good


class TestComputeHomography:
    def test_returns_3x3_matrix(self) -> None:
        ellipse = _DetectedEllipse(
            center=(300.0, 300.0),
            axes=(200.0, 200.0),
            angle=0.0,
            ring_number=1,
            area=math.pi * 100**2,
        )
        H = _compute_homography(ellipse, KKP_25M)
        assert H.shape == (3, 3)

    def test_identity_for_perfect_circle(self) -> None:
        """A perfect circle at canonical position should yield near-identity H."""
        from app.calibration.target_intrinsic import CANONICAL_RING1_RADIUS

        cx = CANONICAL_SIZE / 2.0
        cy = CANONICAL_SIZE / 2.0
        r = CANONICAL_RING1_RADIUS
        ellipse = _DetectedEllipse(
            center=(cx, cy),
            axes=(r * 2, r * 2),
            angle=0.0,
            ring_number=1,
            area=math.pi * r**2,
        )
        H = _compute_homography(ellipse, KKP_25M)
        identity = np.eye(3)
        H_norm = H / H[2, 2]
        assert np.allclose(H_norm, identity, atol=5.0)


class TestRmsToConfidence:
    def test_zero_rms_is_full_confidence(self) -> None:
        assert _rms_to_confidence(0.0) == pytest.approx(1.0)

    def test_max_rms_is_zero_confidence(self) -> None:
        assert _rms_to_confidence(10.0) == pytest.approx(0.0)

    def test_clamps_to_zero(self) -> None:
        assert _rms_to_confidence(999.0) == pytest.approx(0.0)

    def test_intermediate(self) -> None:
        assert _rms_to_confidence(5.0) == pytest.approx(0.5)


class TestCanonicalToB64Png:
    def test_returns_string(self) -> None:
        img = np.zeros((100, 100, 3), dtype=np.uint8)
        result = canonical_to_b64_png(img)
        assert isinstance(result, str)
        assert len(result) > 0

    def test_is_valid_base64(self) -> None:
        import base64

        img = np.zeros((50, 50, 3), dtype=np.uint8)
        result = canonical_to_b64_png(img)
        decoded = base64.b64decode(result)
        assert decoded[:4] == b"\x89PNG"


class TestHomographyToList:
    def test_returns_nested_list(self) -> None:
        H = np.eye(3, dtype=np.float64)
        result = homography_to_list(H)
        assert len(result) == 3
        assert all(len(row) == 3 for row in result)

    def test_values_are_floats(self) -> None:
        H = np.eye(3, dtype=np.float64) * 2.5
        result = homography_to_list(H)
        assert result[0][0] == pytest.approx(2.5)


# ---------------------------------------------------------------------------
# Integration test: calibrate() on a synthetic image
# ---------------------------------------------------------------------------


class TestCalibrateIntegration:
    def test_calibrate_straight_photo(self) -> None:
        """Calibrate should succeed on a clean synthetic target image."""
        img = _make_concentric_rings_image(
            size=800,
            rings={1: 350, 3: 280, 5: 200, 7: 120, 10: 50},
        )
        result = calibrate(img, KKP_25M)
        assert result.rings_detected >= MIN_RINGS_REQUIRED
        assert result.canonical_image.shape == (CANONICAL_SIZE, CANONICAL_SIZE, 3)
        assert result.homography.shape == (3, 3)
        assert 0.0 <= result.confidence <= 1.0

    def test_calibrate_raises_on_blank_image(self) -> None:
        """Blank image has no rings → CalibrationError."""
        img = np.full((400, 400, 3), 200, dtype=np.uint8)
        with pytest.raises(CalibrationError) as exc_info:
            calibrate(img, KKP_25M)
        assert exc_info.value.rings_detected < MIN_RINGS_REQUIRED

    def test_calibrate_woerden_target(self) -> None:
        """Works with a GKG 100m Woerden-style target."""
        img = _make_concentric_rings_image(
            size=600,
            rings={1: 270, 4: 210, 7: 120, 10: 35},
            ring_gray=40,
        )
        result = calibrate(img, GKG_100M)
        assert result.rings_detected >= MIN_RINGS_REQUIRED
        assert result.canonical_image.shape == (CANONICAL_SIZE, CANONICAL_SIZE, 3)
