# python-service/app/detection/reconcile.py
from __future__ import annotations

from dataclasses import dataclass
from math import hypot

from app.calibration.target_intrinsic import CANONICAL_RING1_RADIUS
from app.config import TargetSpec
from app.settings import settings


@dataclass
class Hole:
    """A confirmed hole after reconciling Claude semantics with CV precision."""

    x_px: float
    y_px: float
    confidence: float
    kind: str


def reconcile(
    claude_shots: list[dict],
    candidates: list[tuple[float, float]],
    spec: TargetSpec,
    expected_shot_count: int | None,
) -> list[Hole]:
    """For each Claude hole, snap to the nearest unused CV candidate centroid for
    sub-pixel precision; otherwise keep Claude's coordinate. Drop holes below the
    confidence floor (``settings.min_shot_confidence``) rather than padding up to the
    expected count, then cap any remaining surplus to the expected count, dropping the
    lowest-confidence extras."""
    px_per_mm = (2.0 * CANONICAL_RING1_RADIUS) / spec.ring1_diameter_mm
    bullet_radius_px = (spec.bullet_diameter_mm / 2.0) * px_per_mm
    tol = max(bullet_radius_px * 1.5, 8.0)

    holes: list[Hole] = []
    used: set[int] = set()
    for s in claude_shots:
        best_i: int | None = None
        best_d = tol
        for i, (cx, cy) in enumerate(candidates):
            if i in used:
                continue
            d = hypot(cx - s["x_px"], cy - s["y_px"])
            if d <= best_d:
                best_d = d
                best_i = i
        if best_i is not None:
            cx, cy = candidates[best_i]
            used.add(best_i)
            holes.append(Hole(cx, cy, s["confidence"], s["kind"]))
        else:
            holes.append(Hole(float(s["x_px"]), float(s["y_px"]), s["confidence"], s["kind"]))

    # Drop low-confidence detections rather than placing dubious markers (e.g. a
    # paster Claude wasn't sure about). Better to report fewer, confident holes and
    # flag needs_review than to pad up to the expected count with junk.
    holes = [h for h in holes if h.confidence >= settings.min_shot_confidence]

    if expected_shot_count is not None and len(holes) > expected_shot_count:
        holes.sort(key=lambda h: h.confidence, reverse=True)
        holes = holes[:expected_shot_count]
    return holes
