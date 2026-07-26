"""Manual validation harness for GH-55 photo->shots detection.

NOT part of the API surface and NOT run in CI — it makes real Claude calls.

Usage (inside the python-service container, with ANTHROPIC_API_KEY set):
    python tools/validate_detection.py manifest.json
(Run from the python-service root; the script adds that root to sys.path itself.)

manifest.json format:
    [
      {
        "photo": "fixtures/kkp_25m_turn1.jpg",
        "target_type": "kkp_25m",
        "expected_shot_count": 5,
        "truth": [{"ring": 10}, {"ring": 9}, {"ring": 9}, {"ring": 8}, {"ring": 7}]
      }
    ]

Prints per-photo count/ring metrics plus the calibration method + RMS (so a
discipline mismatch is visible), and an aggregate rollup.
"""
from __future__ import annotations

import json
import os
import sys

# Allow `python tools/validate_detection.py` from the python-service root without
# requiring PYTHONPATH: add that root (the script's parent's parent) to sys.path.
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import cv2

from app.config import TARGET_SPECS
from app.pipeline import analyze_target_v2
from app.validation.metrics import aggregate, compare_turn


def main(manifest_path: str) -> None:
    with open(manifest_path, encoding="utf-8") as fh:
        cases = json.load(fh)

    results: list[dict] = []
    header = f"{'photo':22s} {'discipline':10s} {'det/exp':>8s} {'cnt':>4s} {'ringMAE':>8s} {'method':13s} {'rms':>5s}  review"
    print(header)
    print("-" * len(header))
    for case in cases:
        name = case["photo"].split("/")[-1]
        image = cv2.imread(case["photo"])
        if image is None:
            print(f"{name:22s} SKIP (cannot read: {case['photo']})")
            continue
        spec = TARGET_SPECS[case["target_type"]]
        result = analyze_target_v2(image, spec, case.get("expected_shot_count"))
        m = compare_turn(case.get("truth", []), [{"ring": s["ring"]} for s in result.shots])
        results.append(m)

        exp = case.get("expected_shot_count")
        mae = m["ring_mae"]
        rms = result.calibration.get("rms_error_mm")
        cnt = "OK" if m["count_correct"] else f"{m['count_delta']:+d}"
        print(
            f"{name:22s} {case['target_type']:10s} "
            f"{result.detected_count}/{exp if exp is not None else '?'!s:>3} "
            f"{cnt:>4s} "
            f"{(f'{mae:.2f}' if mae is not None else '-'):>8s} "
            f"{result.calibration.get('method', ''):13s} "
            f"{(f'{rms:.0f}' if rms is not None else '-'):>5s}  "
            f"{'REVIEW' if result.needs_review else 'ok'}"
        )

    agg = aggregate(results)
    if agg["turns"]:
        mae = agg["mean_ring_mae"]
        print(
            f"\nAGGREGATE over {agg['turns']}: "
            f"count_accuracy={agg['count_accuracy']:.2f}  "
            f"mean_ring_accuracy={agg['mean_ring_accuracy']:.2f}  "
            f"mean_ring_mae={(f'{mae:.2f}' if mae is not None else '-')}  "
            f"over/under-count={agg['over_count_turns']}/{agg['under_count_turns']}"
        )


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("usage: python tools/validate_detection.py <manifest.json>")
        sys.exit(1)
    main(sys.argv[1])
