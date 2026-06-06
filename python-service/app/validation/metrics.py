# python-service/app/validation/metrics.py
from __future__ import annotations


def compare_turn(truth: list[dict], pred: list[dict]) -> dict:
    """Compare ground-truth shots to predicted shots for one turn.

    Pairs shots by sorted (ring desc) order — a coarse but stable proxy when the
    harness lacks per-shot correspondence. ``count_correct`` tracks the count
    dimension separately; ``ring_accuracy`` is measured over the shots actually
    compared (hits / pairs)."""
    count_correct = len(truth) == len(pred)
    if not truth:
        return {"count_correct": count_correct, "ring_accuracy": 1.0 if not pred else 0.0}
    if not pred:
        return {"count_correct": False, "ring_accuracy": 0.0}

    t_sorted = sorted((s["ring"] for s in truth), reverse=True)
    p_sorted = sorted((s["ring"] for s in pred), reverse=True)
    pairs = min(len(t_sorted), len(p_sorted))
    hits = sum(1 for i in range(pairs) if t_sorted[i] == p_sorted[i])
    return {"count_correct": count_correct, "ring_accuracy": hits / pairs}
