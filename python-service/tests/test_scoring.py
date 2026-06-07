"""Tests for discipline-correct edge-gauged shot scoring (app.scoring)."""
from __future__ import annotations

from app.config import KKG_50M, KKP_25M
from app.scoring import ScoredShot, score_shot

RING1_RADIUS_PX = 475.0


def _norm_to_px(dist_norm: float) -> float:
    """Convert a target-normalized distance to a canonical x-pixel (y stays at center)."""
    return 500.0 + dist_norm * RING1_RADIUS_PX


def _ring_boundary_norm(spec, ring: int) -> float:
    """Edge-gauged normalized distance at which a shot transitions out of `ring`."""
    ring_radius_mm = spec.ring_diameter_mm(ring) / 2.0
    bullet_radius_mm = spec.bullet_diameter_mm / 2.0
    ring1_radius_mm = spec.ring1_diameter_mm / 2.0
    return (ring_radius_mm + bullet_radius_mm) / ring1_radius_mm


class TestScoreShot:
    def test_center_is_ring_10(self):
        s = score_shot(500.0, 500.0, KKP_25M)
        assert isinstance(s, ScoredShot)
        assert s.ring == 10
        assert s.score == 10
        assert s.x == 0.0 and s.y == 0.0

    def test_far_outside_is_miss(self):
        # dist_norm = 1.0 is the ring-1 edge; 1.4 is comfortably past it.
        s = score_shot(_norm_to_px(1.4), 500.0, KKP_25M)
        assert s.ring == 0
        assert s.score == 0

    def test_normalization_matches_contract(self):
        s = score_shot(_norm_to_px(0.5), 500.0, KKP_25M)
        assert s.x == 0.5
        assert s.y == 0.0

    def test_edge_gauging_keeps_grazing_shot_in_ring_10(self):
        # Just INSIDE the gauged ring-10 boundary: the shot's CENTER is already
        # outside the ring-10 radius, but edge gauging still scores it 10.
        boundary = _ring_boundary_norm(KKP_25M, 10)
        s = score_shot(_norm_to_px(boundary - 0.01), 500.0, KKP_25M)
        assert s.ring == 10

    def test_ring_10_to_9_transition(self):
        # Just OUTSIDE the gauged ring-10 boundary -> drops to ring 9 (no off-by-one).
        boundary = _ring_boundary_norm(KKP_25M, 10)
        s = score_shot(_norm_to_px(boundary + 0.01), 500.0, KKP_25M)
        assert s.ring == 9

    def test_rifle_spec_boundaries(self):
        center = score_shot(500.0, 500.0, KKG_50M)
        assert center.ring == 10
        edge = score_shot(_norm_to_px(0.99), 500.0, KKG_50M)
        assert edge.ring == 1
