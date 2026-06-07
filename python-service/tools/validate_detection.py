"""Manual validation harness for GH-55 photo->shots detection.

NOT part of the API surface and NOT run in CI — it makes real Claude calls.

Usage (inside the python-service container, with ANTHROPIC_API_KEY set):
    python tools/validate_detection.py manifest.json

manifest.json format:
    [
      {
        "photo": "/app/fixtures/kkp_25m_turn1.jpg",
        "target_type": "kkp_25m",
        "expected_shot_count": 5,
        "truth": [{"ring": 10}, {"ring": 9}, {"ring": 9}, {"ring": 8}, {"ring": 7}]
      }
    ]

Prints per-photo and aggregate count-accuracy and ring-accuracy.
"""
from __future__ import annotations

import json
import sys

import cv2

from app.config import TARGET_SPECS
from app.pipeline import analyze_target_v2
from app.validation.metrics import compare_turn


def main(manifest_path: str) -> None:
    with open(manifest_path, encoding="utf-8") as fh:
        cases = json.load(fh)

    total = 0
    count_ok = 0
    ring_acc_sum = 0.0
    for case in cases:
        image = cv2.imread(case["photo"])
        if image is None:
            print(f"SKIP (cannot read): {case['photo']}")
            continue
        spec = TARGET_SPECS[case["target_type"]]
        result = analyze_target_v2(image, spec, case.get("expected_shot_count"))
        pred = [{"ring": s["ring"]} for s in result.shots]
        m = compare_turn(case.get("truth", []), pred)
        total += 1
        count_ok += 1 if m["count_correct"] else 0
        ring_acc_sum += m["ring_accuracy"]
        print(
            f"{case['photo']}: count_correct={m['count_correct']} "
            f"ring_accuracy={m['ring_accuracy']:.2f} needs_review={result.needs_review}"
        )

    if total:
        print(
            f"\nAGGREGATE over {total}: count_accuracy={count_ok / total:.2f} "
            f"mean_ring_accuracy={ring_acc_sum / total:.2f}"
        )


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("usage: python tools/validate_detection.py <manifest.json>")
        sys.exit(1)
    main(sys.argv[1])
