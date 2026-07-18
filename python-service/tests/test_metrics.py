# python-service/tests/test_metrics.py
from __future__ import annotations

from app.validation.metrics import aggregate, compare_turn


class TestCompareTurn:
    def test_perfect_match(self):
        truth = [{"ring": 10}, {"ring": 9}]
        pred = [{"ring": 10}, {"ring": 9}]
        m = compare_turn(truth, pred)
        assert m["count_correct"] is True
        assert m["ring_accuracy"] == 1.0
        assert m["ring_mae"] == 0.0
        assert m["count_delta"] == 0

    def test_count_mismatch_and_partial_rings(self):
        truth = [{"ring": 10}, {"ring": 9}, {"ring": 8}]
        pred = [{"ring": 10}, {"ring": 7}]
        m = compare_turn(truth, pred)
        assert m["count_correct"] is False
        assert m["ring_accuracy"] == 0.5
        # paired (10,10) and (9,7) -> abs errors 0 and 2 -> mae 1.0
        assert m["ring_mae"] == 1.0
        assert m["count_delta"] == -1

    def test_over_count_positive_delta(self):
        m = compare_turn([{"ring": 10}], [{"ring": 10}, {"ring": 6}])
        assert m["count_delta"] == 1

    def test_empty_prediction(self):
        truth = [{"ring": 10}]
        m = compare_turn(truth, [])
        assert m["count_correct"] is False
        assert m["ring_accuracy"] == 0.0
        assert m["ring_mae"] is None


class TestAggregate:
    def test_rolls_up_headline_metrics(self):
        results = [
            compare_turn([{"ring": 10}, {"ring": 9}], [{"ring": 10}, {"ring": 9}]),  # perfect
            compare_turn([{"ring": 8}], [{"ring": 8}, {"ring": 5}]),                 # over-count
        ]
        agg = aggregate(results)
        assert agg["turns"] == 2
        assert agg["count_accuracy"] == 0.5
        assert agg["over_count_turns"] == 1
        assert agg["under_count_turns"] == 0

    def test_empty(self):
        assert aggregate([]) == {"turns": 0}
