# python-service/app/scoring.py
from __future__ import annotations

from dataclasses import dataclass
from math import hypot

from app.calibration.target_intrinsic import CANONICAL_RING1_RADIUS, CANONICAL_SIZE
from app.config import TargetSpec

CANONICAL_CENTER: float = CANONICAL_SIZE / 2.0  # canonical image center (px)


@dataclass(frozen=True)
class ScoredShot:
    """A reconciled shot scored against a discipline's real ring geometry.

    ``score`` equals ``ring`` for every discipline in scope (ISSF integer ring
    scoring). It is kept as a distinct field because the downstream contract
    (``ShotResultV2.score`` / the ``session_shots.score`` column) stores score
    separately from ring.
    """

    x: float  # target-normalized horizontal position [-1, 1] (ring-1 edge = 1.0)
    y: float  # target-normalized vertical position [-1, 1]
    ring: int  # 0 (miss) .. 10
    score: int  # equal to ring
    distance_norm: float


def score_shot(x_px: float, y_px: float, spec: TargetSpec, center: float = CANONICAL_CENTER) -> ScoredShot:
    """Score a canonical-pixel centroid using ISSF edge gauging (a hole counts the
    higher ring if its edge touches the ring line — uses the bullet radius)."""
    x_norm = (x_px - center) / CANONICAL_RING1_RADIUS
    y_norm = (y_px - center) / CANONICAL_RING1_RADIUS
    dist_norm = hypot(x_norm, y_norm)

    center_distance_mm = dist_norm * (spec.ring1_diameter_mm / 2.0)
    gauged_mm = max(0.0, center_distance_mm - spec.bullet_diameter_mm / 2.0)

    ring = 0
    for r in range(10, 0, -1):
        if gauged_mm <= spec.ring_diameter_mm(r) / 2.0:
            ring = r
            break

    return ScoredShot(
        x=round(x_norm, 4),
        y=round(y_norm, 4),
        ring=ring,
        score=ring,
        distance_norm=round(dist_norm, 4),
    )
