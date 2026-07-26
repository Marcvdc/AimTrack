# python-service/app/validation/metrics.py
from __future__ import annotations


def compare_turn(truth: list[dict], pred: list[dict]) -> dict:
    """Compare ground-truth shots to predicted shots for one turn.

    Pairs shots by sorted (ring desc) order — a coarse but stable proxy when the
    harness lacks per-shot correspondence.

    Returns:
        count_correct: predicted count equals truth count.
        count_delta:   pred - truth (positive = over-counting, the paster failure mode).
        ring_accuracy: exact-ring hits / paired shots.
        ring_mae:      mean absolute ring error over paired shots (None when no pairs).
    """
    count_correct = len(truth) == len(pred)
    count_delta = len(pred) - len(truth)
    if not truth:
        return {
            "count_correct": count_correct,
            "count_delta": count_delta,
            "ring_accuracy": 1.0 if not pred else 0.0,
            "ring_mae": 0.0 if not pred else None,
        }
    if not pred:
        return {"count_correct": False, "count_delta": count_delta, "ring_accuracy": 0.0, "ring_mae": None}

    t_sorted = sorted((s["ring"] for s in truth), reverse=True)
    p_sorted = sorted((s["ring"] for s in pred), reverse=True)
    pairs = min(len(t_sorted), len(p_sorted))
    hits = sum(1 for i in range(pairs) if t_sorted[i] == p_sorted[i])
    mae = sum(abs(t_sorted[i] - p_sorted[i]) for i in range(pairs)) / pairs
    return {
        "count_correct": count_correct,
        "count_delta": count_delta,
        "ring_accuracy": hits / pairs,
        "ring_mae": mae,
    }


def aggregate(results: list[dict]) -> dict:
    """Roll per-turn ``compare_turn`` dicts up into headline validation metrics."""
    n = len(results)
    if n == 0:
        return {"turns": 0}
    maes = [r["ring_mae"] for r in results if r.get("ring_mae") is not None]
    return {
        "turns": n,
        "count_accuracy": sum(1 for r in results if r["count_correct"]) / n,
        "mean_ring_accuracy": sum(r["ring_accuracy"] for r in results) / n,
        "mean_ring_mae": (sum(maes) / len(maes)) if maes else None,
        "over_count_turns": sum(1 for r in results if r["count_delta"] > 0),
        "under_count_turns": sum(1 for r in results if r["count_delta"] < 0),
    }
