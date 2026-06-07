# python-service/tests/test_reconcile.py
from __future__ import annotations

from app.config import KKP_25M
from app.detection.reconcile import Hole, reconcile


class TestReconcile:
    def test_snaps_to_nearest_candidate(self):
        claude = [{"x_px": 702, "y_px": 498, "confidence": 0.9, "kind": "hole"}]
        candidates = [(700.0, 500.0), (100.0, 100.0)]
        holes = reconcile(claude, candidates, KKP_25M, expected_shot_count=1)
        assert len(holes) == 1
        assert holes[0].x_px == 700.0 and holes[0].y_px == 500.0

    def test_keeps_claude_coord_when_no_candidate_near(self):
        claude = [{"x_px": 300, "y_px": 300, "confidence": 0.8, "kind": "hole"}]
        candidates = [(900.0, 900.0)]
        holes = reconcile(claude, candidates, KKP_25M, expected_shot_count=1)
        assert holes[0].x_px == 300.0 and holes[0].y_px == 300.0

    def test_caps_to_expected_keeping_highest_confidence(self):
        claude = [
            {"x_px": 500, "y_px": 500, "confidence": 0.5, "kind": "hole"},
            {"x_px": 600, "y_px": 500, "confidence": 0.95, "kind": "hole"},
        ]
        holes = reconcile(claude, [], KKP_25M, expected_shot_count=1)
        assert len(holes) == 1
        assert holes[0].confidence == 0.95

    def test_no_cap_when_expected_none(self):
        claude = [
            {"x_px": 500, "y_px": 500, "confidence": 0.5, "kind": "hole"},
            {"x_px": 600, "y_px": 500, "confidence": 0.6, "kind": "hole"},
        ]
        holes = reconcile(claude, [], KKP_25M, expected_shot_count=None)
        assert len(holes) == 2

    def test_one_candidate_not_reused(self):
        claude = [
            {"x_px": 700, "y_px": 500, "confidence": 0.9, "kind": "hole"},
            {"x_px": 701, "y_px": 500, "confidence": 0.8, "kind": "hole"},
        ]
        candidates = [(700.0, 500.0)]
        holes = reconcile(claude, candidates, KKP_25M, expected_shot_count=2)
        snapped = [h for h in holes if h.x_px == 700.0 and h.y_px == 500.0]
        assert len(snapped) == 1

    def test_drops_shots_below_confidence_floor(self):
        # confidence 0.2 < default floor 0.4 -> dropped, even though count would allow it
        claude = [
            {"x_px": 500, "y_px": 500, "confidence": 0.9, "kind": "hole"},
            {"x_px": 600, "y_px": 500, "confidence": 0.2, "kind": "uncertain"},
        ]
        holes = reconcile(claude, [], KKP_25M, expected_shot_count=2)
        assert len(holes) == 1
        assert holes[0].confidence == 0.9

    def test_does_not_pad_to_expected_with_low_confidence(self):
        # only one confident hole; do NOT force a second to reach expected=3
        claude = [
            {"x_px": 500, "y_px": 500, "confidence": 0.8, "kind": "hole"},
            {"x_px": 600, "y_px": 500, "confidence": 0.1, "kind": "uncertain"},
            {"x_px": 700, "y_px": 500, "confidence": 0.1, "kind": "uncertain"},
        ]
        holes = reconcile(claude, [], KKP_25M, expected_shot_count=3)
        assert len(holes) == 1
