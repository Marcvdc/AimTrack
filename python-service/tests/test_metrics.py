# python-service/tests/test_metrics.py
from __future__ import annotations

from app.validation.metrics import compare_turn


class TestCompareTurn:
    def test_perfect_match(self):
        truth = [{"ring": 10}, {"ring": 9}]
        pred = [{"ring": 10}, {"ring": 9}]
        m = compare_turn(truth, pred)
        assert m["count_correct"] is True
        assert m["ring_accuracy"] == 1.0

    def test_count_mismatch_and_partial_rings(self):
        truth = [{"ring": 10}, {"ring": 9}, {"ring": 8}]
        pred = [{"ring": 10}, {"ring": 7}]
        m = compare_turn(truth, pred)
        assert m["count_correct"] is False
        assert m["ring_accuracy"] == 0.5

    def test_empty_prediction(self):
        truth = [{"ring": 10}]
        m = compare_turn(truth, [])
        assert m["count_correct"] is False
        assert m["ring_accuracy"] == 0.0
