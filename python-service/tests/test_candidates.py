# python-service/tests/test_candidates.py
from __future__ import annotations

import cv2
import numpy as np

from app.config import KKP_25M
from app.detection.candidates import detect_candidates


def _blank_canonical() -> np.ndarray:
    img = np.full((1000, 1000, 3), 255, np.uint8)
    cv2.circle(img, (500, 500), 180, (0, 0, 0), -1)
    return img


class TestDetectCandidates:
    def test_finds_dark_hole_on_paper(self):
        img = _blank_canonical()
        cv2.circle(img, (700, 500), 6, (10, 10, 10), -1)
        cands = detect_candidates(img, KKP_25M)
        assert any(abs(x - 700) < 12 and abs(y - 500) < 12 for (x, y) in cands)

    def test_finds_light_hole_inside_black(self):
        img = _blank_canonical()
        cv2.circle(img, (500, 470), 6, (245, 245, 245), -1)
        cands = detect_candidates(img, KKP_25M)
        assert any(abs(x - 500) < 12 and abs(y - 470) < 12 for (x, y) in cands)

    def test_dedupes_near_duplicates(self):
        img = _blank_canonical()
        cv2.circle(img, (700, 500), 6, (10, 10, 10), -1)
        cands = detect_candidates(img, KKP_25M)
        near = [c for c in cands if abs(c[0] - 700) < 12 and abs(c[1] - 500) < 12]
        assert len(near) == 1
