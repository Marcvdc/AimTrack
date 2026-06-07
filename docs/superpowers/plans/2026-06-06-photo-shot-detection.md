# Photo → Shots Detection (Hybrid CV + Claude) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn a per-turn target photo into discipline-correctly-scored shot markers fully automatically (zero-touch), by wiring up the working homography calibration, adding a CV-candidate + Claude-vision discrimination pipeline, and fixing per-discipline scoring end to end.

**Architecture:** A new `POST /api/v2/analyze-target` endpoint in the Python service runs a 5-stage pipeline — calibrate (existing) → high-recall CV candidates → Claude Opus 4.8 discrimination (structured output) → reconcile (CV precision × Claude semantics, count-constrained) → discipline-correct scoring. Laravel's `AnalyzeTurnPhotoJob` calls it, persists the returned shots verbatim (no local ring math), and records a per-turn `session_turn_analyses` row carrying the non-blocking `needs_review` flag.

**Tech Stack:** Python 3.11 / FastAPI / OpenCV / `anthropic` SDK (Python service); Laravel 12 / Filament 5 / Livewire / Pest (app). Source spec: `docs/superpowers/specs/2026-06-06-photo-shot-detection-design.md`.

**Worktree:** all work happens in `/home/brandnetel/projects/aimtrack-55` (branch `feature/55`). Before starting, ensure `feature/55` is up to date with `main` (`git fetch origin && git rebase origin/main` per repo worktree discipline).

---

## Conventions for the implementer (read once)

- **Python service** lives at `python-service/`. Modules use `from __future__ import annotations`, PEP 604 unions (`int | None`), explicit return types, Pydantic v2, Dutch user-facing strings. Tests are **pytest** (not Pest) under `python-service/tests/`. Run a test file with:
  `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/<file>.py -v`
  (If you don't have a local venv with `requirements.txt` installed, run inside the container:
  `docker compose --env-file .env -f docker/compose.dev.yml exec python-service python -m pytest tests/<file>.py -v`.)
- **Laravel app** is the repo root. Models use the **legacy `protected $casts` property** (not `casts()`), relationship methods have **no return-type hints** (match existing files). Enums are plain backed string enums in `app/Enums/` with **TitleCase case names**. Tests are **Pest**; base `TestCase` already uses `LazilyRefreshDatabase` — do **not** add `RefreshDatabase` in test files. Run:
  `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=<name>`
  Always run `vendor/bin/pint --dirty` before committing PHP.
- **Coordinate contract (single source of truth):** canonical image is 1000×1000, center `(500, 500)`, ring-1 edge radius `CANONICAL_RING1_RADIUS = 475.0` (imported from `app.calibration.target_intrinsic` — **not 500**). Target-normalized coords are `x_norm = (x_px - 500)/475`, range ≈ [-1, 1] at the ring-1 edge. The Python v2 response returns `x`/`y` in this [-1, 1] space; Laravel maps to canvas with the existing `0.5 + coord*0.46`.

---

## File structure (what each new/changed file is responsible for)

**Python service**
- `python-service/app/scoring.py` *(new)* — pure: pixel centroid → discipline-correct ring/score (edge-gauged).
- `python-service/app/detection/__init__.py` *(new, empty)*.
- `python-service/app/detection/candidates.py` *(new)* — high-recall CV blob candidates (dark-on-light + light-in-black), deduped centroids.
- `python-service/app/detection/reconcile.py` *(new)* — snap Claude holes to CV centroids; count cap.
- `python-service/app/settings.py` *(new)* — env config (API key, model, effort, review thresholds).
- `python-service/app/vision/__init__.py` *(new, empty)*.
- `python-service/app/vision/claude_detector.py` *(new)* — Claude Opus 4.8 structured-output hole detection.
- `python-service/app/pipeline.py` *(new)* — orchestrates the 5 stages + needs_review + graceful degradation.
- `python-service/app/validation/__init__.py` *(new, empty)* + `python-service/app/validation/metrics.py` *(new)* — accuracy metrics (pure).
- `python-service/app/models/schemas.py` *(modify)* — add `ShotResultV2`, `AnalyzeV2Response`.
- `python-service/app/api/routes.py` *(modify)* — add `POST /api/v2/analyze-target`.
- `python-service/requirements.txt` *(modify)* — add `anthropic`.
- `python-service/Dockerfile` *(modify)* — default `AIMTRACK_VISION_MODEL` ENV (no secret).
- `docker-compose.yml` + `docker/compose.dev.yml` *(modify)* — pass `ANTHROPIC_API_KEY` etc. to python-service.
- `python-service/tools/validate_detection.py` *(new)* — manual validation harness (real Claude calls).

**Laravel app**
- `app/Enums/TargetType.php` *(new)* — 5 disciplines.
- `database/migrations/2026_06_06_090000_add_target_type_to_sessions_table.php` *(new)*.
- `app/Models/Session.php` *(modify)* — fillable + cast `target_type`; `turnAnalyses()` relation.
- `database/migrations/2026_06_06_090100_create_session_turn_analyses_table.php` *(new)*.
- `app/Models/SessionTurnAnalysis.php` *(new)*.
- `app/Filament/Resources/SessionResource.php` *(modify)* — `target_type` Select.
- `app/Livewire/SessionShotBoard.php` *(modify)* — `expectedShotCount` + pass to job.
- `app/Jobs/AnalyzeTurnPhotoJob.php` *(modify)* — call v2, persist returned scoring, write turn analysis, drop local ring math.

---

## Phase 1 — Python: discipline-correct scoring (pure, no deps)

### Task 1: Scoring module

**Files:**
- Create: `python-service/app/scoring.py`
- Test: `python-service/tests/test_scoring.py`

- [ ] **Step 1: Write the failing test**

```python
# python-service/tests/test_scoring.py
from __future__ import annotations

from app.config import KKP_25M, KKG_50M
from app.scoring import ScoredShot, score_shot


class TestScoreShot:
    def test_center_is_ring_10(self):
        s = score_shot(500.0, 500.0, KKP_25M)
        assert isinstance(s, ScoredShot)
        assert s.ring == 10
        assert s.score == 10
        assert s.x == 0.0 and s.y == 0.0

    def test_far_outside_is_miss(self):
        # Well beyond the ring-1 edge (x_px=500+1.4*475)
        s = score_shot(500.0 + 1.4 * 475.0, 500.0, KKP_25M)
        assert s.ring == 0
        assert s.score == 0

    def test_normalization_matches_contract(self):
        s = score_shot(500.0 + 0.5 * 475.0, 500.0, KKP_25M)
        assert s.x == 0.5
        assert s.y == 0.0

    def test_edge_gauging_uses_bullet_radius(self):
        # KKP_25M: ring10 diameter 50mm -> radius 25mm; bullet .22 = 5.6mm -> 2.8mm radius.
        # Place a shot whose CENTER is at 27mm (just outside ring-10 radius 25mm).
        # ring1 radius mm = ring1_diameter_mm/2 = 250. dist_norm = 27/250 = 0.108.
        x_px = 500.0 + 0.108 * 475.0
        s = score_shot(x_px, 500.0, KKP_25M)
        # gauged = 27 - 2.8 = 24.2mm <= 25mm -> still ring 10 thanks to edge gauging
        assert s.ring == 10

    def test_rifle_spec_boundaries(self):
        # KKG_50M ring10 diameter is small; center scores 10, a large offset scores low/miss.
        center = score_shot(500.0, 500.0, KKG_50M)
        assert center.ring == 10
        edge = score_shot(500.0 + 0.99 * 475.0, 500.0, KKG_50M)
        assert edge.ring in (0, 1)
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_scoring.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'app.scoring'`.

- [ ] **Step 3: Write minimal implementation**

```python
# python-service/app/scoring.py
from __future__ import annotations

from dataclasses import dataclass
from math import hypot

from app.calibration.target_intrinsic import CANONICAL_RING1_RADIUS
from app.config import TargetSpec

CANONICAL_CENTER: float = 500.0  # CANONICAL_SIZE / 2


@dataclass
class ScoredShot:
    """A reconciled shot scored against a discipline's real ring geometry."""

    x: float  # target-normalized horizontal position [-1, 1] (ring-1 edge = 1.0)
    y: float  # target-normalized vertical position [-1, 1]
    ring: int  # 0 (miss) .. 10
    score: int  # equal to ring
    distance_norm: float


def score_shot(x_px: float, y_px: float, spec: TargetSpec, center: float = CANONICAL_CENTER) -> ScoredShot:
    """Score a canonical-pixel centroid using ISSF edge gauging (a hole counts the
    higher ring if its edge touches the ring line — uses the bullet radius)."""
    x_norm = (x_px - center) / CANONICAL_RING1_RADIUS
    y_norm = (y_px - center) / CANONICAL_RING1_RADIUS
    dist_norm = hypot(x_norm, y_norm)

    center_distance_mm = dist_norm * (spec.ring1_diameter_mm / 2.0)
    gauged_mm = max(0.0, center_distance_mm - spec.bullet_diameter_mm / 2.0)

    ring = 0
    for r in range(10, 0, -1):
        if gauged_mm <= spec.ring_diameter_mm(r) / 2.0:
            ring = r
            break

    return ScoredShot(
        x=round(x_norm, 4),
        y=round(y_norm, 4),
        ring=ring,
        score=ring,
        distance_norm=round(dist_norm, 4),
    )
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_scoring.py -v`
Expected: PASS (5 passed).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/scoring.py python-service/tests/test_scoring.py
git commit -m "feat(55): discipline-correct edge-gauged shot scoring"
```

---

## Phase 2 — Python: high-recall CV candidate detection

### Task 2: Candidate detector

**Files:**
- Create: `python-service/app/detection/__init__.py` (empty), `python-service/app/detection/candidates.py`
- Test: `python-service/tests/test_candidates.py`

- [ ] **Step 1: Write the failing test**

```python
# python-service/tests/test_candidates.py
from __future__ import annotations

import cv2
import numpy as np

from app.config import KKP_25M
from app.detection.candidates import detect_candidates


def _blank_canonical() -> np.ndarray:
    # White paper, with a centered black aiming area (so light-in-black pass has somewhere to look).
    img = np.full((1000, 1000, 3), 255, np.uint8)
    cv2.circle(img, (500, 500), 180, (0, 0, 0), -1)  # black bullseye
    return img


class TestDetectCandidates:
    def test_finds_dark_hole_on_paper(self):
        img = _blank_canonical()
        cv2.circle(img, (700, 500), 6, (10, 10, 10), -1)  # dark hole on white paper
        cands = detect_candidates(img, KKP_25M)
        assert any(abs(x - 700) < 12 and abs(y - 500) < 12 for (x, y) in cands)

    def test_finds_light_hole_inside_black(self):
        img = _blank_canonical()
        cv2.circle(img, (500, 470), 6, (245, 245, 245), -1)  # paper showing through the black
        cands = detect_candidates(img, KKP_25M)
        assert any(abs(x - 500) < 12 and abs(y - 470) < 12 for (x, y) in cands)

    def test_dedupes_near_duplicates(self):
        img = _blank_canonical()
        cv2.circle(img, (700, 500), 6, (10, 10, 10), -1)
        cands = detect_candidates(img, KKP_25M)
        near = [c for c in cands if abs(c[0] - 700) < 12 and abs(c[1] - 500) < 12]
        assert len(near) == 1
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_candidates.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'app.detection'`.

- [ ] **Step 3: Write minimal implementation**

```python
# python-service/app/detection/__init__.py
```

```python
# python-service/app/detection/candidates.py
from __future__ import annotations

import cv2
import numpy as np

from app.calibration.target_intrinsic import CANONICAL_RING1_RADIUS
from app.config import TargetSpec


def _px_per_mm(spec: TargetSpec) -> float:
    return (2.0 * CANONICAL_RING1_RADIUS) / spec.ring1_diameter_mm


def _black_mask(shape: tuple[int, int], spec: TargetSpec) -> np.ndarray:
    h, w = shape
    black_radius_px = (spec.black_area_diameter_mm / 2.0) * _px_per_mm(spec)
    mask = np.zeros((h, w), np.uint8)
    cv2.circle(mask, (w // 2, h // 2), int(black_radius_px), 255, -1)
    return mask


def _dark_blobs(gray: np.ndarray) -> np.ndarray:
    """High-recall dark-on-light extraction (whole image; no black exclusion)."""
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    _, otsu = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
    _, fixed = cv2.threshold(blurred, 80, 255, cv2.THRESH_BINARY_INV)
    combined = cv2.bitwise_and(otsu, fixed)
    combined = cv2.morphologyEx(combined, cv2.MORPH_OPEN, np.ones((2, 2), np.uint8))
    combined = cv2.morphologyEx(combined, cv2.MORPH_CLOSE, np.ones((3, 3), np.uint8))
    return combined


def _light_blobs_in_black(gray: np.ndarray, black_mask: np.ndarray) -> np.ndarray:
    """High-recall light-on-dark extraction inside the black aiming area."""
    masked = cv2.bitwise_and(gray, gray, mask=black_mask)
    blurred = cv2.GaussianBlur(masked, (5, 5), 0)
    _, light = cv2.threshold(blurred, 90, 255, cv2.THRESH_BINARY)
    light = cv2.bitwise_and(light, black_mask)
    light = cv2.morphologyEx(light, cv2.MORPH_OPEN, np.ones((2, 2), np.uint8))
    light = cv2.morphologyEx(light, cv2.MORPH_CLOSE, np.ones((3, 3), np.uint8))
    return light


def _centroids(binary: np.ndarray, min_area: float, max_area: float) -> list[tuple[float, float]]:
    contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    out: list[tuple[float, float]] = []
    for c in contours:
        area = cv2.contourArea(c)
        if area < min_area or area > max_area:
            continue
        m = cv2.moments(c)
        if m["m00"] == 0:
            continue
        out.append((m["m10"] / m["m00"], m["m01"] / m["m00"]))
    return out


def _dedupe(points: list[tuple[float, float]], radius: float) -> list[tuple[float, float]]:
    kept: list[tuple[float, float]] = []
    r2 = radius * radius
    for p in points:
        if all((p[0] - q[0]) ** 2 + (p[1] - q[1]) ** 2 > r2 for q in kept):
            kept.append(p)
    return kept


def detect_candidates(canonical: np.ndarray, spec: TargetSpec) -> list[tuple[float, float]]:
    """Return sub-pixel centroids of every bullet-sized blob (high recall; false
    positives expected — Claude discriminates downstream). Precision, not judgment."""
    gray = cv2.cvtColor(canonical, cv2.COLOR_BGR2GRAY)
    bullet_radius_px = (spec.bullet_diameter_mm / 2.0) * _px_per_mm(spec)
    bullet_area_px = np.pi * bullet_radius_px ** 2
    min_area = max(10.0, bullet_area_px * 0.03)
    max_area = bullet_area_px * 12.0

    black = _black_mask(gray.shape, spec)
    dark = _centroids(_dark_blobs(gray), min_area, max_area)
    light = _centroids(_light_blobs_in_black(gray, black), min_area, max_area)
    return _dedupe(dark + light, bullet_radius_px)
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_candidates.py -v`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/detection/__init__.py python-service/app/detection/candidates.py python-service/tests/test_candidates.py
git commit -m "feat(55): high-recall CV candidate detector (dark + light-in-black)"
```

---

## Phase 3 — Python: settings + Claude vision client

### Task 3: Settings module + dependency

**Files:**
- Create: `python-service/app/settings.py`
- Modify: `python-service/requirements.txt`
- Test: `python-service/tests/test_settings.py`

- [ ] **Step 1: Write the failing test**

```python
# python-service/tests/test_settings.py
from __future__ import annotations

import importlib


class TestSettings:
    def test_defaults(self, monkeypatch):
        for var in ("ANTHROPIC_API_KEY", "AIMTRACK_VISION_MODEL", "AIMTRACK_VISION_EFFORT",
                    "AIMTRACK_REVIEW_CONFIDENCE", "AIMTRACK_CAL_RMS_REVIEW_MM"):
            monkeypatch.delenv(var, raising=False)
        import app.settings as s
        importlib.reload(s)
        assert s.settings.vision_model == "claude-opus-4-8"
        assert s.settings.vision_effort == "high"
        assert s.settings.review_confidence_threshold == 0.6
        assert s.settings.cal_rms_review_mm == 20.0
        assert s.settings.anthropic_api_key == ""

    def test_env_overrides(self, monkeypatch):
        monkeypatch.setenv("AIMTRACK_VISION_MODEL", "claude-sonnet-4-6")
        monkeypatch.setenv("AIMTRACK_REVIEW_CONFIDENCE", "0.8")
        import app.settings as s
        importlib.reload(s)
        assert s.settings.vision_model == "claude-sonnet-4-6"
        assert s.settings.review_confidence_threshold == 0.8
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_settings.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'app.settings'`.

- [ ] **Step 3: Write minimal implementation**

```python
# python-service/app/settings.py
from __future__ import annotations

import os


class Settings:
    """Runtime configuration sourced from the environment. There is no pre-existing
    settings layer in this service; this is the single env-config entry point."""

    def __init__(self) -> None:
        self.anthropic_api_key: str = os.getenv("ANTHROPIC_API_KEY", "")
        self.vision_model: str = os.getenv("AIMTRACK_VISION_MODEL", "claude-opus-4-8")
        self.vision_effort: str = os.getenv("AIMTRACK_VISION_EFFORT", "high")
        self.review_confidence_threshold: float = float(os.getenv("AIMTRACK_REVIEW_CONFIDENCE", "0.6"))
        self.cal_rms_review_mm: float = float(os.getenv("AIMTRACK_CAL_RMS_REVIEW_MM", "20.0"))


settings = Settings()
```

Append to `python-service/requirements.txt` (after the existing `httpx>=0.24.0` line, keeping the existing lines unchanged):

```
anthropic>=0.49.0
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_settings.py -v`
Expected: PASS (2 passed). (Note: `anthropic` is not imported by `settings.py`, so this passes even before the dep is installed; it is installed in the container at build time.)

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/settings.py python-service/requirements.txt python-service/tests/test_settings.py
git commit -m "feat(55): python-service env settings + anthropic dependency"
```

### Task 4: Claude vision detector

**Files:**
- Create: `python-service/app/vision/__init__.py` (empty), `python-service/app/vision/claude_detector.py`
- Test: `python-service/tests/test_claude_detector.py`

- [ ] **Step 1: Write the failing test**

```python
# python-service/tests/test_claude_detector.py
from __future__ import annotations

import json

import numpy as np
import pytest

from app.config import KKP_25M
from app.vision import claude_detector
from app.vision.claude_detector import VisionError, detect_holes


def _canonical() -> np.ndarray:
    return np.full((1000, 1000, 3), 255, np.uint8)


class TestDetectHoles:
    def test_parses_and_clamps(self, monkeypatch):
        payload = {
            "shots": [
                {"x_px": 500, "y_px": 500, "confidence": 1.4, "kind": "hole"},
                {"x_px": 700, "y_px": 480, "confidence": 0.9, "kind": "uncertain"},
            ],
            "orientation_note": "printed 10 at top",
            "overall_confidence": 0.92,
            "count_matches_expected": True,
        }
        monkeypatch.setattr(claude_detector, "_call_claude", lambda b64, system: json.dumps(payload))
        result = detect_holes(_canonical(), KKP_25M, expected_shot_count=2)
        assert len(result["shots"]) == 2
        assert result["shots"][0]["confidence"] == 1.0  # clamped
        assert result["shots"][0]["kind"] == "hole"
        assert result["overall_confidence"] == 0.92
        assert result["count_matches_expected"] is True

    def test_raises_vision_error_on_api_failure(self, monkeypatch):
        def boom(b64, system):
            raise RuntimeError("api down")
        monkeypatch.setattr(claude_detector, "_call_claude", boom)
        with pytest.raises(VisionError):
            detect_holes(_canonical(), KKP_25M, expected_shot_count=2)

    def test_raises_vision_error_on_bad_json(self, monkeypatch):
        monkeypatch.setattr(claude_detector, "_call_claude", lambda b64, system: "not json")
        with pytest.raises(VisionError):
            detect_holes(_canonical(), KKP_25M, expected_shot_count=2)
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_claude_detector.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'app.vision'`.

- [ ] **Step 3: Write minimal implementation**

```python
# python-service/app/vision/__init__.py
```

```python
# python-service/app/vision/claude_detector.py
from __future__ import annotations

import base64
import json

import cv2
import numpy as np

from app.config import TargetSpec
from app.settings import settings


class VisionError(Exception):
    """Raised when the vision model is unreachable or returns unusable output."""


_SHOT_SCHEMA: dict = {
    "type": "object",
    "additionalProperties": False,
    "properties": {
        "shots": {
            "type": "array",
            "items": {
                "type": "object",
                "additionalProperties": False,
                "properties": {
                    "x_px": {"type": "integer"},
                    "y_px": {"type": "integer"},
                    "confidence": {"type": "number"},
                    "kind": {"type": "string", "enum": ["hole", "uncertain"]},
                },
                "required": ["x_px", "y_px", "confidence", "kind"],
            },
        },
        "orientation_note": {"type": "string"},
        "overall_confidence": {"type": "number"},
        "count_matches_expected": {"type": "boolean"},
    },
    "required": ["shots", "orientation_note", "overall_confidence", "count_matches_expected"],
}


def _system_prompt(spec: TargetSpec, expected_shot_count: int | None) -> str:
    count_line = (
        f"Er zijn precies {expected_shot_count} schoten gelost in deze beurt; "
        f"rapporteer er zoveel mogelijk exact dat aantal."
        if expected_shot_count is not None
        else "Het aantal schoten is onbekend; rapporteer elk zichtbaar kogelgat."
    )
    return (
        f"Je analyseert een perspectief-gecorrigeerde foto van een {spec.name} schietkaart "
        f"(1000x1000 px; het zwarte richtvlak staat exact gecentreerd op (500,500); "
        f"de ring-1 rand ligt op straal 475 px vanaf het centrum). "
        f"Geef de pixelcoordinaat (x_px, y_px) van het MIDDEN van elk ECHT kogelgat. "
        f"Negeer expliciet: gedrukte ringnummers (zoals 8, 9, 10), ringlijnen, "
        f"witte of zwarte plakkers (pasters) en kartonscheuren — dit zijn GEEN schoten. "
        f"Gebruik de gedrukte ringnummers uitsluitend als orientatie-anker. "
        f"{count_line}"
    )


def _call_claude(canonical_b64: str, system: str) -> str:
    """Single network call. Isolated so tests can monkeypatch it. Returns the raw
    JSON text the model produced under the structured-output format constraint."""
    from anthropic import Anthropic

    client = Anthropic(api_key=settings.anthropic_api_key or None)
    response = client.messages.create(
        model=settings.vision_model,
        max_tokens=4096,
        system=system,
        thinking={"type": "adaptive"},
        output_config={
            "effort": settings.vision_effort,
            "format": {"type": "json_schema", "schema": _SHOT_SCHEMA},
        },
        messages=[
            {
                "role": "user",
                "content": [
                    {
                        "type": "image",
                        "source": {"type": "base64", "media_type": "image/png", "data": canonical_b64},
                    },
                    {"type": "text", "text": "Rapporteer alle kogelgaten als JSON volgens het schema."},
                ],
            }
        ],
    )
    for block in response.content:
        if block.type == "text":
            return block.text
    raise VisionError("Geen tekst-antwoord van het vision-model")


def detect_holes(canonical: np.ndarray, spec: TargetSpec, expected_shot_count: int | None) -> dict:
    """Send the canonical image to Claude; return validated/clamped hole detections.

    Returns: {shots: [{x_px,y_px,confidence,kind}], orientation_note, overall_confidence,
              count_matches_expected}. Raises VisionError on any failure."""
    ok, buf = cv2.imencode(".png", canonical)
    if not ok:
        raise VisionError("PNG-codering mislukt")
    b64 = base64.b64encode(buf.tobytes()).decode("ascii")

    try:
        raw = _call_claude(b64, _system_prompt(spec, expected_shot_count))
    except VisionError:
        raise
    except Exception as exc:  # anthropic.APIError, network, etc.
        raise VisionError(str(exc)) from exc

    try:
        data = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise VisionError("Ongeldige JSON van vision-model") from exc

    shots: list[dict] = []
    for s in data.get("shots", []):
        shots.append(
            {
                "x_px": int(s["x_px"]),
                "y_px": int(s["y_px"]),
                "confidence": max(0.0, min(1.0, float(s.get("confidence", 0.0)))),
                "kind": s.get("kind", "hole"),
            }
        )
    return {
        "shots": shots,
        "orientation_note": str(data.get("orientation_note", "")),
        "overall_confidence": max(0.0, min(1.0, float(data.get("overall_confidence", 0.0)))),
        "count_matches_expected": bool(data.get("count_matches_expected", False)),
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_claude_detector.py -v`
Expected: PASS (3 passed). The `anthropic` import lives inside `_call_claude`, which is monkeypatched in tests, so tests pass without the package installed locally.

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/vision/__init__.py python-service/app/vision/claude_detector.py python-service/tests/test_claude_detector.py
git commit -m "feat(55): Claude Opus 4.8 vision hole detector (structured output)"
```

---

## Phase 4 — Python: reconcile + schemas + pipeline + endpoint

### Task 5: Reconcile module

**Files:**
- Create: `python-service/app/detection/reconcile.py`
- Test: `python-service/tests/test_reconcile.py`

- [ ] **Step 1: Write the failing test**

```python
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
        assert holes[0].x_px == 700.0 and holes[0].y_px == 500.0  # snapped to CV centroid

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
        assert len(snapped) == 1  # candidate consumed once
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_reconcile.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'app.detection.reconcile'`.

- [ ] **Step 3: Write minimal implementation**

```python
# python-service/app/detection/reconcile.py
from __future__ import annotations

from dataclasses import dataclass
from math import hypot

from app.calibration.target_intrinsic import CANONICAL_RING1_RADIUS
from app.config import TargetSpec


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
    sub-pixel precision; otherwise keep Claude's coordinate. Cap to expected count,
    dropping the lowest-confidence extras."""
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

    if expected_shot_count is not None and len(holes) > expected_shot_count:
        holes.sort(key=lambda h: h.confidence, reverse=True)
        holes = holes[:expected_shot_count]
    return holes
```

> **v1 scope note:** when Claude returns *fewer* than `expected_shot_count`, reconcile does **not** fabricate extra shots from leftover CV candidates (the spec floated a second count-aware pass; we keep v1 deterministic and cheap). The under-count instead drives `needs_review` in the pipeline.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_reconcile.py -v`
Expected: PASS (5 passed).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/detection/reconcile.py python-service/tests/test_reconcile.py
git commit -m "feat(55): reconcile Claude holes with CV centroids (count-capped)"
```

### Task 6: v2 response schemas

**Files:**
- Modify: `python-service/app/models/schemas.py` (append; do not change existing models)
- Test: `python-service/tests/test_schemas_v2.py`

- [ ] **Step 1: Write the failing test**

```python
# python-service/tests/test_schemas_v2.py
from __future__ import annotations

from app.models.schemas import AnalyzeV2Response, ShotResultV2


class TestV2Schemas:
    def test_shot_result_v2_fields(self):
        s = ShotResultV2(x=0.1, y=-0.2, ring=9, score=9, confidence=0.8, kind="hole")
        assert s.ring == 9 and s.kind == "hole"

    def test_analyze_v2_response_roundtrip(self):
        r = AnalyzeV2Response(
            success=True,
            shots=[ShotResultV2(x=0.0, y=0.0, ring=10, score=10, confidence=0.9, kind="hole")],
            total_detected=1,
            expected_shot_count=1,
            detected_count=1,
            count_matches_expected=True,
            overall_confidence=0.9,
            needs_review=False,
            orientation_note="",
            vision_model="claude-opus-4-8",
            calibration={"ok": True, "rms_error_mm": 8.2, "confidence": 0.18, "rings_detected": 7, "error": None},
        )
        dumped = r.model_dump()
        assert dumped["shots"][0]["ring"] == 10
        assert dumped["needs_review"] is False
        assert dumped["expected_shot_count"] == 1
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_schemas_v2.py -v`
Expected: FAIL — `ImportError: cannot import name 'AnalyzeV2Response'`.

- [ ] **Step 3: Write minimal implementation**

Append to `python-service/app/models/schemas.py` (after the existing `CalibrationErrorDetail` class, keeping all existing classes unchanged):

```python


class ShotResultV2(BaseModel):
    x: float = Field(description="Horizontale positie t.o.v. roos-centrum [-1, 1]")
    y: float = Field(description="Verticale positie t.o.v. roos-centrum [-1, 1]")
    ring: int = Field(description="Ring 0 (mis) t/m 10")
    score: int = Field(description="Score (gelijk aan ring)")
    confidence: float = Field(description="Detectie-zekerheid 0-1")
    kind: str = Field(description="'hole' of 'uncertain'")


class AnalyzeV2Response(BaseModel):
    success: bool
    shots: list[ShotResultV2]
    total_detected: int
    expected_shot_count: int | None
    detected_count: int
    count_matches_expected: bool
    overall_confidence: float
    needs_review: bool
    orientation_note: str
    vision_model: str
    calibration: dict
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_schemas_v2.py -v`
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/models/schemas.py python-service/tests/test_schemas_v2.py
git commit -m "feat(55): v2 analyze response schemas"
```

### Task 7: Pipeline orchestration

**Files:**
- Create: `python-service/app/pipeline.py`
- Test: `python-service/tests/test_pipeline.py`

- [ ] **Step 1: Write the failing test**

```python
# python-service/tests/test_pipeline.py
from __future__ import annotations

import numpy as np

from app.calibration.target_intrinsic import CalibrationError, CalibrationResult
from app.config import KKP_25M
from app import pipeline
from app.pipeline import analyze_target_v2
from app.vision.claude_detector import VisionError


def _fake_cal() -> CalibrationResult:
    return CalibrationResult(
        canonical_image=np.full((1000, 1000, 3), 255, np.uint8),
        homography=np.eye(3),
        rms_error_mm=8.0,
        confidence=0.18,
        rings_detected=7,
        target_spec=KKP_25M,
    )


class TestPipeline:
    def test_happy_path_scores_and_no_review(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal())
        monkeypatch.setattr(pipeline, "detect_candidates", lambda canon, spec: [(500.0, 500.0)])
        monkeypatch.setattr(pipeline, "detect_holes", lambda canon, spec, n: {
            "shots": [{"x_px": 500, "y_px": 500, "confidence": 0.95, "kind": "hole"}],
            "orientation_note": "ok", "overall_confidence": 0.95, "count_matches_expected": True,
        })
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=1)
        assert result.detected_count == 1
        assert result.shots[0]["ring"] == 10
        assert result.count_matches_expected is True
        assert result.needs_review is False

    def test_count_mismatch_flags_review(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal())
        monkeypatch.setattr(pipeline, "detect_candidates", lambda canon, spec: [])
        monkeypatch.setattr(pipeline, "detect_holes", lambda canon, spec, n: {
            "shots": [{"x_px": 500, "y_px": 500, "confidence": 0.95, "kind": "hole"}],
            "orientation_note": "", "overall_confidence": 0.95, "count_matches_expected": False,
        })
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=5)
        assert result.detected_count == 1
        assert result.count_matches_expected is False
        assert result.needs_review is True

    def test_vision_failure_degrades_to_candidates(self, monkeypatch):
        monkeypatch.setattr(pipeline, "calibrate", lambda img, spec: _fake_cal())
        monkeypatch.setattr(pipeline, "detect_candidates", lambda canon, spec: [(500.0, 500.0), (600.0, 500.0)])
        def boom(canon, spec, n):
            raise VisionError("down")
        monkeypatch.setattr(pipeline, "detect_holes", boom)
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=2)
        assert result.detected_count == 2  # fell back to CV candidates
        assert result.overall_confidence == 0.0
        assert result.needs_review is True

    def test_calibration_failure_returns_empty_review(self, monkeypatch):
        def boom(img, spec):
            raise CalibrationError("te weinig ringen", rings_detected=1)
        monkeypatch.setattr(pipeline, "calibrate", boom)
        result = analyze_target_v2(np.zeros((10, 10, 3), np.uint8), KKP_25M, expected_shot_count=5)
        assert result.shots == []
        assert result.needs_review is True
        assert result.calibration["ok"] is False
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_pipeline.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'app.pipeline'`.

- [ ] **Step 3: Write minimal implementation**

```python
# python-service/app/pipeline.py
from __future__ import annotations

from dataclasses import dataclass

import numpy as np

from app.calibration.target_intrinsic import CalibrationError, calibrate
from app.config import TargetSpec
from app.detection.candidates import detect_candidates
from app.detection.reconcile import Hole, reconcile
from app.scoring import score_shot
from app.settings import settings
from app.vision.claude_detector import VisionError, detect_holes


@dataclass
class AnalysisResult:
    shots: list[dict]
    expected_shot_count: int | None
    detected_count: int
    count_matches_expected: bool
    overall_confidence: float
    needs_review: bool
    orientation_note: str
    vision_model: str
    calibration: dict


def analyze_target_v2(image: np.ndarray, spec: TargetSpec, expected_shot_count: int | None) -> AnalysisResult:
    """5-stage pipeline: calibrate -> CV candidates -> Claude discrimination ->
    reconcile -> discipline-correct scoring. Never raises for normal failure modes;
    degrades to needs_review so the user's photo is never lost."""
    try:
        cal = calibrate(image, spec)
    except CalibrationError as exc:
        return AnalysisResult(
            shots=[],
            expected_shot_count=expected_shot_count,
            detected_count=0,
            count_matches_expected=False,
            overall_confidence=0.0,
            needs_review=True,
            orientation_note="",
            vision_model=settings.vision_model,
            calibration={
                "ok": False,
                "error": str(exc),
                "rms_error_mm": None,
                "confidence": None,
                "rings_detected": exc.rings_detected,
            },
        )

    candidates = detect_candidates(cal.canonical_image, spec)

    try:
        vision = detect_holes(cal.canonical_image, spec, expected_shot_count)
        holes = reconcile(vision["shots"], candidates, spec, expected_shot_count)
        overall_conf = vision["overall_confidence"]
        orientation = vision["orientation_note"]
        vision_ok = True
    except VisionError:
        holes = [Hole(x, y, 0.0, "uncertain") for (x, y) in candidates]
        if expected_shot_count is not None and len(holes) > expected_shot_count:
            holes = holes[:expected_shot_count]
        overall_conf = 0.0
        orientation = ""
        vision_ok = False

    shots: list[dict] = []
    for h in holes:
        sc = score_shot(h.x_px, h.y_px, spec)
        shots.append(
            {"x": sc.x, "y": sc.y, "ring": sc.ring, "score": sc.score,
             "confidence": round(h.confidence, 3), "kind": h.kind}
        )

    detected = len(shots)
    count_ok = expected_shot_count is None or detected == expected_shot_count
    needs_review = (
        not vision_ok
        or not count_ok
        or overall_conf < settings.review_confidence_threshold
        or cal.rms_error_mm > settings.cal_rms_review_mm
    )

    return AnalysisResult(
        shots=shots,
        expected_shot_count=expected_shot_count,
        detected_count=detected,
        count_matches_expected=count_ok,
        overall_confidence=round(overall_conf, 3),
        needs_review=needs_review,
        orientation_note=orientation,
        vision_model=settings.vision_model,
        calibration={
            "ok": True,
            "error": None,
            "rms_error_mm": cal.rms_error_mm,
            "confidence": cal.confidence,
            "rings_detected": cal.rings_detected,
        },
    )
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_pipeline.py -v`
Expected: PASS (4 passed).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/pipeline.py python-service/tests/test_pipeline.py
git commit -m "feat(55): v2 analysis pipeline with graceful degradation + review flag"
```

### Task 8: v2 endpoint

**Files:**
- Modify: `python-service/app/api/routes.py`
- Test: `python-service/tests/test_v2_endpoint.py`

- [ ] **Step 1: Write the failing test**

```python
# python-service/tests/test_v2_endpoint.py
from __future__ import annotations

import cv2
import numpy as np
from fastapi.testclient import TestClient

from app.pipeline import AnalysisResult
from app.config import KKP_25M
from app.api import routes
from main import app

client = TestClient(app)


def _png_bytes() -> bytes:
    img = np.full((50, 50, 3), 255, np.uint8)
    ok, buf = cv2.imencode(".png", img)
    return buf.tobytes()


def _fake_result() -> AnalysisResult:
    return AnalysisResult(
        shots=[{"x": 0.0, "y": 0.0, "ring": 10, "score": 10, "confidence": 0.9, "kind": "hole"}],
        expected_shot_count=1, detected_count=1, count_matches_expected=True,
        overall_confidence=0.9, needs_review=False, orientation_note="ok",
        vision_model="claude-opus-4-8",
        calibration={"ok": True, "error": None, "rms_error_mm": 8.0, "confidence": 0.18, "rings_detected": 7},
    )


class TestV2Endpoint:
    def test_returns_scored_shots(self, monkeypatch):
        monkeypatch.setattr(routes, "analyze_target_v2", lambda image, spec, n: _fake_result())
        resp = client.post(
            "/api/v2/analyze-target",
            files={"file": ("t.png", _png_bytes(), "image/png")},
            data={"target_type": "kkp_25m", "expected_shot_count": "1"},
        )
        assert resp.status_code == 200
        body = resp.json()
        assert body["success"] is True
        assert body["shots"][0]["ring"] == 10
        assert body["needs_review"] is False
        assert body["count_matches_expected"] is True

    def test_unknown_target_type_is_400(self):
        resp = client.post(
            "/api/v2/analyze-target",
            files={"file": ("t.png", _png_bytes(), "image/png")},
            data={"target_type": "bogus"},
        )
        assert resp.status_code == 400

    def test_non_image_is_400(self):
        resp = client.post(
            "/api/v2/analyze-target",
            files={"file": ("t.txt", b"hello", "text/plain")},
            data={"target_type": "kkp_25m"},
        )
        assert resp.status_code == 400
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_v2_endpoint.py -v`
Expected: FAIL — 404 on `/api/v2/analyze-target` (route not defined) → assertion error.

- [ ] **Step 3: Write minimal implementation**

In `python-service/app/api/routes.py`, add imports near the top (after the existing `from app.models.schemas import (...)` block):

```python
from app.models.schemas import AnalyzeV2Response, ShotResultV2
from app.pipeline import analyze_target_v2
```

Then add the endpoint immediately after the existing `calibrate_target` function (before the "Bestaande endpoints" banner):

```python
@router.post("/api/v2/analyze-target", response_model=AnalyzeV2Response)
async def analyze_target_v2_endpoint(
    file: UploadFile = File(...),
    target_type: str = Form(...),
    expected_shot_count: int | None = Form(default=None),
) -> AnalyzeV2Response:
    """Hybrid CV + Claude shot detection on a perspective-corrected target.

    Returns discipline-correctly-scored shots plus a non-blocking needs_review flag.
    """
    if not file.content_type or not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="Bestand moet een afbeelding zijn")

    spec = TARGET_SPECS.get(target_type)
    if spec is None:
        valid = ", ".join(sorted(TARGET_SPECS.keys()))
        raise HTTPException(
            status_code=400,
            detail=f"Onbekend roos-type '{target_type}'. Geldige waarden: {valid}",
        )

    image_data = await file.read()
    image = _decode_image(image_data)

    result = analyze_target_v2(image, spec, expected_shot_count)

    return AnalyzeV2Response(
        success=True,
        shots=[ShotResultV2(**s) for s in result.shots],
        total_detected=result.detected_count,
        expected_shot_count=result.expected_shot_count,
        detected_count=result.detected_count,
        count_matches_expected=result.count_matches_expected,
        overall_confidence=result.overall_confidence,
        needs_review=result.needs_review,
        orientation_note=result.orientation_note,
        vision_model=result.vision_model,
        calibration=result.calibration,
    )
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_v2_endpoint.py -v`
Expected: PASS (3 passed).

- [ ] **Step 5: Run the full Python suite + commit**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest -q`
Expected: all green (existing + new).

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/api/routes.py python-service/tests/test_v2_endpoint.py
git commit -m "feat(55): POST /api/v2/analyze-target endpoint"
```

---

## Phase 5 — Laravel: TargetType enum + Session.target_type + Filament Select

### Task 9: TargetType enum

**Files:**
- Create: `app/Enums/TargetType.php`
- Test: `tests/Unit/TargetTypeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/TargetTypeTest.php

use App\Enums\TargetType;

test('target type has the five supported disciplines', function () {
    $values = array_map(fn (TargetType $t) => $t->value, TargetType::cases());

    expect($values)->toBe(['kkp_25m', 'gkp_25m', 'kkg_50m', 'kkg_100m', 'gkg_100m']);
});

test('target type values match the python TARGET_SPECS keys', function () {
    expect(TargetType::Kkp25m->value)->toBe('kkp_25m');
    expect(TargetType::Gkg100m->value)->toBe('gkg_100m');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=TargetTypeTest`
Expected: FAIL — `Class "App\Enums\TargetType" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Enums;

enum TargetType: string
{
    case Kkp25m = 'kkp_25m';
    case Gkp25m = 'gkp_25m';
    case Kkg50m = 'kkg_50m';
    case Kkg100m = 'kkg_100m';
    case Gkg100m = 'gkg_100m';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=TargetTypeTest`
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
vendor/bin/pint --dirty
git add app/Enums/TargetType.php tests/Unit/TargetTypeTest.php
git commit -m "feat(55): TargetType enum (5 disciplines)"
```

### Task 10: Session.target_type column + cast

**Files:**
- Create: `database/migrations/2026_06_06_090000_add_target_type_to_sessions_table.php`
- Modify: `app/Models/Session.php`
- Test: `tests/Unit/SessionTargetTypeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/SessionTargetTypeTest.php

use App\Enums\TargetType;
use App\Models\Session;
use App\Models\User;

test('session stores and casts target_type', function () {
    $user = User::factory()->create();

    $session = Session::factory()->create([
        'user_id' => $user->id,
        'target_type' => TargetType::Kkp25m->value,
    ]);

    expect($session->refresh()->target_type)->toBe(TargetType::Kkp25m);
});

test('target_type is nullable', function () {
    $session = Session::factory()->create();

    expect($session->target_type)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionTargetTypeTest`
Expected: FAIL — SQL error: no column `target_type` (or cast/fillable missing).

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// database/migrations/2026_06_06_090000_add_target_type_to_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('target_type')->nullable()->after('turn_count');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('target_type');
        });
    }
};
```

In `app/Models/Session.php`, add `'target_type'` to `$fillable` (after `'turn_count'`) and the cast to `$casts`:

```php
    protected $fillable = [
        'user_id',
        'date',
        'range_name',
        'location',
        'location_id',
        'range_location_id',
        'notes_raw',
        'manual_reflection',
        'turn_count',
        'target_type',
    ];

    protected $casts = [
        'date' => 'date',
        'turn_count' => 'integer',
        'target_type' => \App\Enums\TargetType::class,
    ];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionTargetTypeTest`
Expected: PASS (2 passed). (The in-memory sqlite DB is migrated fresh per run by `LazilyRefreshDatabase`.)

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
vendor/bin/pint --dirty
git add database/migrations/2026_06_06_090000_add_target_type_to_sessions_table.php app/Models/Session.php tests/Unit/SessionTargetTypeTest.php
git commit -m "feat(55): add sessions.target_type column + enum cast"
```

### Task 11: Filament Select for target_type

**Files:**
- Modify: `app/Filament/Resources/SessionResource.php`
- Test: `tests/Feature/SessionResourceTargetTypeTest.php`

> The repo has no existing Resource page test; this uses the documented Filament edit-page pattern (`livewire(EditSession::class, ['record' => ...])->fillForm(...)->call('save')`). It requires `pestphp/pest-plugin-livewire` (the `livewire()` helper). If that import is unavailable, use `Livewire\Livewire::test(...)` instead (the style used elsewhere in this repo). Locate the EditSession page class first — it is `App\Filament\Resources\SessionResource\Pages\EditSession` unless the resource uses a different page namespace; confirm via `app/Filament/Resources/SessionResource/Pages/`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SessionResourceTargetTypeTest.php

use App\Enums\TargetType;
use App\Filament\Resources\SessionResource\Pages\EditSession;
use App\Models\Session;
use App\Models\User;

use function Pest\Livewire\livewire;

test('session edit form saves target_type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $session = Session::factory()->create(['user_id' => $user->id]);

    livewire(EditSession::class, ['record' => $session->id])
        ->fillForm(['target_type' => TargetType::Kkg50m->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($session->refresh()->target_type)->toBe(TargetType::Kkg50m);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionResourceTargetTypeTest`
Expected: FAIL — form has no `target_type` field, so the value is not persisted (or fillForm errors on unknown field).

- [ ] **Step 3: Write minimal implementation**

In `app/Filament/Resources/SessionResource.php`, add the import near the other `use` statements:

```php
use App\Enums\TargetType;
```

Inside the `InfoSection::make('Sessie')` schema array, add this `Select` immediately after the `DatePicker::make('date')` component:

```php
            Select::make('target_type')
                ->label('Discipline')
                ->options([
                    'kkp_25m' => 'KKP 25m (klein kaliber pistool)',
                    'gkp_25m' => 'GKP 25m (groot kaliber pistool)',
                    'kkg_50m' => 'KKG 50m (klein kaliber geweer)',
                    'kkg_100m' => 'KKG 100m (klein kaliber geweer)',
                    'gkg_100m' => 'GKG 100m (groot kaliber geweer)',
                ])
                ->helperText('Vereist om foto-analyse te kunnen uitvoeren.')
                ->native(false),
```

(The `TargetType` import is used by the test and keeps the discipline list authoritative; the literal option labels are the Dutch user-facing strings.)

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionResourceTargetTypeTest`
Expected: PASS (1 passed).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
vendor/bin/pint --dirty
git add app/Filament/Resources/SessionResource.php tests/Feature/SessionResourceTargetTypeTest.php
git commit -m "feat(55): target_type discipline Select on session form"
```

---

## Phase 6 — Laravel: per-turn analysis table

### Task 12: session_turn_analyses table + model

**Files:**
- Create: `database/migrations/2026_06_06_090100_create_session_turn_analyses_table.php`
- Create: `app/Models/SessionTurnAnalysis.php`
- Modify: `app/Models/Session.php` (add relation)
- Test: `tests/Unit/SessionTurnAnalysisTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/SessionTurnAnalysisTest.php

use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Models\User;

test('a turn analysis belongs to a session and casts its fields', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    $analysis = SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'needs_review' => true,
        'overall_confidence' => 0.42,
        'expected_shot_count' => 5,
        'detected_count' => 4,
        'count_matches_expected' => false,
        'calibration_rms_mm' => 8.2,
        'vision_model' => 'claude-opus-4-8',
        'analyzed_at' => now(),
    ]);

    $fresh = $analysis->refresh();
    expect($fresh->needs_review)->toBeTrue();
    expect($fresh->count_matches_expected)->toBeFalse();
    expect($fresh->overall_confidence)->toBe(0.42);
    expect($fresh->session->id)->toBe($session->id);
    expect($session->turnAnalyses()->count())->toBe(1);
});

test('turn analysis is unique per session and turn', function () {
    $session = Session::factory()->create();

    SessionTurnAnalysis::updateOrCreate(
        ['session_id' => $session->id, 'turn_index' => 0],
        ['needs_review' => true, 'detected_count' => 3]
    );
    SessionTurnAnalysis::updateOrCreate(
        ['session_id' => $session->id, 'turn_index' => 0],
        ['needs_review' => false, 'detected_count' => 5]
    );

    expect(SessionTurnAnalysis::where('session_id', $session->id)->count())->toBe(1);
    expect(SessionTurnAnalysis::where('session_id', $session->id)->first()->detected_count)->toBe(5);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionTurnAnalysisTest`
Expected: FAIL — `Class "App\Models\SessionTurnAnalysis" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php
// database/migrations/2026_06_06_090100_create_session_turn_analyses_table.php

use App\Models\Session;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_turn_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Session::class)->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('turn_index');
            $table->boolean('needs_review')->default(false);
            $table->float('overall_confidence')->nullable();
            $table->unsignedSmallInteger('expected_shot_count')->nullable();
            $table->unsignedSmallInteger('detected_count')->default(0);
            $table->boolean('count_matches_expected')->default(false);
            $table->float('calibration_rms_mm')->nullable();
            $table->string('vision_model')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'turn_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_turn_analyses');
    }
};
```

```php
<?php
// app/Models/SessionTurnAnalysis.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionTurnAnalysis extends Model
{
    protected $fillable = [
        'session_id',
        'turn_index',
        'needs_review',
        'overall_confidence',
        'expected_shot_count',
        'detected_count',
        'count_matches_expected',
        'calibration_rms_mm',
        'vision_model',
        'analyzed_at',
    ];

    protected $casts = [
        'turn_index' => 'integer',
        'needs_review' => 'boolean',
        'overall_confidence' => 'float',
        'expected_shot_count' => 'integer',
        'detected_count' => 'integer',
        'count_matches_expected' => 'boolean',
        'calibration_rms_mm' => 'float',
        'analyzed_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(Session::class);
    }
}
```

In `app/Models/Session.php`, add this relation (after the existing `shots()` method, matching the untyped style):

```php
    public function turnAnalyses()
    {
        return $this->hasMany(SessionTurnAnalysis::class);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionTurnAnalysisTest`
Expected: PASS (2 passed).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
vendor/bin/pint --dirty
git add database/migrations/2026_06_06_090100_create_session_turn_analyses_table.php app/Models/SessionTurnAnalysis.php app/Models/Session.php tests/Unit/SessionTurnAnalysisTest.php
git commit -m "feat(55): session_turn_analyses table + model (turn-level needs_review)"
```

---

## Phase 7 — Laravel: capture count + rewire the job

### Task 13: Capture expected_shot_count in SessionShotBoard

**Files:**
- Modify: `app/Livewire/SessionShotBoard.php`
- Test: `tests/Feature/SessionShotBoardExpectedCountTest.php`

> Read `app/Livewire/SessionShotBoard.php` first. Add a public property `public ?int $expectedShotCount = null;` next to `public $photo = null;` (around line 76). In `uploadPhoto()` (around lines 175-207), pass the count as the new 4th dispatch argument. The job's 4th constructor param is added in Task 14 — to keep tasks independently runnable, this task only adds the property and the dispatch argument; the job already tolerates the extra arg once Task 14 lands. Do Task 13 and Task 14 together if running strictly in order, or run Task 14 first. The test below asserts the property is settable and survives validation.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SessionShotBoardExpectedCountTest.php

use App\Jobs\AnalyzeTurnPhotoJob;
use App\Models\Session;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('uploadPhoto passes expectedShotCount to the analysis job', function () {
    Queue::fake();
    Storage::fake('private');

    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->set('currentTurnIndex', 0)
        ->set('expectedShotCount', 5)
        ->set('photo', UploadedFile::fake()->image('target.jpg'))
        ->call('uploadPhoto');

    Queue::assertPushed(AnalyzeTurnPhotoJob::class, function (AnalyzeTurnPhotoJob $job) {
        return $job->expectedShotCountForTest() === 5;
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionShotBoardExpectedCountTest`
Expected: FAIL — property `expectedShotCount` doesn't exist and/or job has no `expectedShotCountForTest()`.

> The `expectedShotCountForTest()` accessor is added on the job in Task 14 (it exposes the private constructor value for assertions). If running Task 13 in isolation first, temporarily assert on `Queue::assertPushed(AnalyzeTurnPhotoJob::class)` only, then tighten after Task 14. The canonical order is: do Task 14's constructor change, then Task 13.

- [ ] **Step 3: Write minimal implementation**

In `app/Livewire/SessionShotBoard.php`:

Add the property near `public $photo = null;`:

```php
    public ?int $expectedShotCount = null;
```

In `uploadPhoto()`, change the dispatch (currently `AnalyzeTurnPhotoJob::dispatch($this->session, $this->currentTurnIndex, $privatePath);`) to:

```php
        AnalyzeTurnPhotoJob::dispatch(
            $this->session,
            $this->currentTurnIndex,
            $privatePath,
            $this->expectedShotCount,
        );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionShotBoardExpectedCountTest`
Expected: PASS (1 passed) — after Task 14 adds the constructor param + accessor.

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
vendor/bin/pint --dirty
git add app/Livewire/SessionShotBoard.php tests/Feature/SessionShotBoardExpectedCountTest.php
git commit -m "feat(55): capture per-turn expected shot count in shot board"
```

> **UI note (manual, no test):** add a number input bound to `wire:model="expectedShotCount"` inside the photo-upload modal in `resources/views/livewire/session-shot-board.blade.php` (find the modal containing the file input bound to `photo`). Label it "Aantal schoten deze beurt" (Dutch). This is presentation-only; verify visually after `npm run build`.

### Task 14: Rewrite AnalyzeTurnPhotoJob to call v2

**Files:**
- Modify: `app/Jobs/AnalyzeTurnPhotoJob.php`
- Test: `tests/Unit/AnalyzeTurnPhotoJobV2Test.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/AnalyzeTurnPhotoJobV2Test.php

use App\Jobs\AnalyzeTurnPhotoJob;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionTurnAnalysis;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
});

test('job calls v2 endpoint with target_type and expected count, persists returned scoring', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true,
            'shots' => [
                ['x' => 0.0, 'y' => 0.0, 'ring' => 10, 'score' => 10, 'confidence' => 0.95, 'kind' => 'hole'],
                ['x' => 0.5, 'y' => 0.0, 'ring' => 7, 'score' => 7, 'confidence' => 0.80, 'kind' => 'hole'],
            ],
            'total_detected' => 2,
            'expected_shot_count' => 2,
            'detected_count' => 2,
            'count_matches_expected' => true,
            'overall_confidence' => 0.9,
            'needs_review' => false,
            'orientation_note' => 'ok',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => true, 'rms_error_mm' => 8.2, 'confidence' => 0.18, 'rings_detected' => 7, 'error' => null],
        ], 200),
    ]);

    (new AnalyzeTurnPhotoJob($session, 0, $imagePath, 2))->handle();

    $shots = SessionShot::where('session_id', $session->id)->where('turn_index', 0)->orderBy('shot_index')->get();
    expect($shots)->toHaveCount(2);
    // ring/score come from the python response verbatim, NOT recomputed:
    expect($shots[0]->ring)->toBe(10);
    expect($shots[0]->score)->toBe(10);
    expect($shots[1]->ring)->toBe(7);
    // canvas mapping preserved (0.5 + x*0.46):
    expect((float) $shots[0]->x_normalized)->toBe(0.5);
    expect((float) $shots[1]->x_normalized)->toBe(0.73);
    expect($shots[0]->source)->toBe('photo_detected');
    expect($shots[0]->metadata['kind'])->toBe('hole');

    // turn analysis recorded:
    $analysis = SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 0)->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->needs_review)->toBeFalse();
    expect($analysis->detected_count)->toBe(2);
    expect($analysis->vision_model)->toBe('claude-opus-4-8');

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/api/v2/analyze-target')
            && $request->hasFile('file');
    });
});

test('job flags needs_review when python says so', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'x');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true, 'shots' => [], 'total_detected' => 0,
            'expected_shot_count' => 5, 'detected_count' => 0, 'count_matches_expected' => false,
            'overall_confidence' => 0.0, 'needs_review' => true, 'orientation_note' => '',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => false, 'rms_error_mm' => null, 'confidence' => null, 'rings_detected' => 1, 'error' => 'te weinig ringen'],
        ], 200),
    ]);

    (new AnalyzeTurnPhotoJob($session, 0, $imagePath, 5))->handle();

    $analysis = SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 0)->first();
    expect($analysis->needs_review)->toBeTrue();
    expect($analysis->detected_count)->toBe(0);
});

test('job records review without calling python when target_type missing', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => null]);
    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'x');
    Http::fake();

    (new AnalyzeTurnPhotoJob($session, 0, $imagePath, 5))->handle();

    $analysis = SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 0)->first();
    expect($analysis->needs_review)->toBeTrue();
    Http::assertNothingSent();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=AnalyzeTurnPhotoJobV2Test`
Expected: FAIL — constructor has no 4th param; job calls `/api/v1/analyze-target-v2`; no `SessionTurnAnalysis` written.

- [ ] **Step 3: Write minimal implementation**

Replace the contents of `app/Jobs/AnalyzeTurnPhotoJob.php` with:

```php
<?php

namespace App\Jobs;

use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionTurnAnalysis;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AnalyzeTurnPhotoJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        private Session $session,
        private int $turnIndex,
        private string $photoPath,
        private ?int $expectedShotCount = null,
    ) {}

    public function expectedShotCountForTest(): ?int
    {
        return $this->expectedShotCount;
    }

    public function handle(): void
    {
        try {
            if (! Storage::disk('private')->exists($this->photoPath)) {
                Log::error("Photo not found: {$this->photoPath}", [
                    'session_id' => $this->session->id,
                    'turn_index' => $this->turnIndex,
                ]);

                return;
            }

            $targetType = $this->session->target_type?->value;

            if (blank($targetType)) {
                Log::warning('[AnalyzeTurnPhotoJob] No target_type on session; cannot analyze photo', [
                    'session_id' => $this->session->id,
                    'turn_index' => $this->turnIndex,
                ]);

                $this->recordTurnAnalysis(
                    needsReview: true,
                    overallConfidence: 0.0,
                    detectedCount: 0,
                    countMatchesExpected: false,
                    calibrationRmsMm: null,
                    visionModel: null,
                );

                return;
            }

            $imageContent = Storage::disk('private')->get($this->photoPath);
            $baseUrl = config('services.image_processor.url');

            $response = Http::timeout(90)
                ->attach('file', $imageContent, basename($this->photoPath))
                ->post($baseUrl.'/api/v2/analyze-target', [
                    'target_type' => $targetType,
                    'expected_shot_count' => $this->expectedShotCount,
                ]);

            if (! $response->successful()) {
                throw new Exception("Image processing failed: {$response->status()}");
            }

            $data = $response->json();

            if (! ($data['success'] ?? false) || ! isset($data['shots'])) {
                throw new Exception('Invalid response from image processor');
            }

            SessionShot::where('session_id', $this->session->id)
                ->where('turn_index', $this->turnIndex)
                ->where('source', 'photo_detected')
                ->delete();

            $targetRadiusRatio = 0.46;

            foreach ($data['shots'] as $index => $shot) {
                $x = (float) $shot['x'];
                $y = (float) $shot['y'];

                SessionShot::create([
                    'session_id' => $this->session->id,
                    'turn_index' => $this->turnIndex,
                    'shot_index' => $index + 1,
                    'x_normalized' => max(0, min(1, 0.5 + $x * $targetRadiusRatio)),
                    'y_normalized' => max(0, min(1, 0.5 + $y * $targetRadiusRatio)),
                    'distance_from_center' => sqrt($x ** 2 + $y ** 2),
                    'ring' => (int) ($shot['ring'] ?? 0),
                    'score' => (int) ($shot['score'] ?? 0),
                    'source' => 'photo_detected',
                    'metadata' => [
                        'confidence' => $shot['confidence'] ?? null,
                        'kind' => $shot['kind'] ?? null,
                        'photo_path' => $this->photoPath,
                        'processed_at' => now()->toISOString(),
                        'original_x' => $x,
                        'original_y' => $y,
                        'vision_model' => $data['vision_model'] ?? null,
                    ],
                ]);
            }

            $this->recordTurnAnalysis(
                needsReview: (bool) ($data['needs_review'] ?? true),
                overallConfidence: (float) ($data['overall_confidence'] ?? 0.0),
                detectedCount: (int) ($data['detected_count'] ?? count($data['shots'])),
                countMatchesExpected: (bool) ($data['count_matches_expected'] ?? false),
                calibrationRmsMm: $data['calibration']['rms_error_mm'] ?? null,
                visionModel: $data['vision_model'] ?? null,
            );

            Log::info('Successfully processed turn photo (v2)', [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
                'shots_detected' => count($data['shots']),
                'needs_review' => $data['needs_review'] ?? null,
            ]);
        } catch (HttpClientException $e) {
            Log::error('HTTP error while processing photo', [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        } catch (Exception $e) {
            Log::error('Error while processing turn photo', [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }

    private function recordTurnAnalysis(
        bool $needsReview,
        float $overallConfidence,
        int $detectedCount,
        bool $countMatchesExpected,
        ?float $calibrationRmsMm,
        ?string $visionModel,
    ): void {
        SessionTurnAnalysis::updateOrCreate(
            [
                'session_id' => $this->session->id,
                'turn_index' => $this->turnIndex,
            ],
            [
                'needs_review' => $needsReview,
                'overall_confidence' => $overallConfidence,
                'expected_shot_count' => $this->expectedShotCount,
                'detected_count' => $detectedCount,
                'count_matches_expected' => $countMatchesExpected,
                'calibration_rms_mm' => $calibrationRmsMm,
                'vision_model' => $visionModel,
                'analyzed_at' => now(),
            ]
        );
    }
}
```

> This deletes the old `calculateRing()` and `calculateScore()` methods (discipline-wrong; scoring is now done in Python). The job no longer recomputes rings.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=AnalyzeTurnPhotoJobV2Test`
Expected: PASS (3 passed).

- [ ] **Step 5: Check the legacy job test + commit**

The old `tests/Unit/AnalyzeTurnPhotoJobTest.php` faked `/api/v1/analyze-target-v2` and expected local ring math. Run it:
Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=AnalyzeTurnPhotoJobTest`
If it fails because the job now calls v2, **update** that test file to fake `*/api/v2/analyze-target` with a v2-shaped body (shots include `ring`/`score`) and assert the persisted `ring`/`score` equal the response values (do **not** delete the test — per repo policy tests are core). Mirror the assertions from `AnalyzeTurnPhotoJobV2Test`. Re-run until green.

```bash
cd /home/brandnetel/projects/aimtrack-55
vendor/bin/pint --dirty
git add app/Jobs/AnalyzeTurnPhotoJob.php tests/Unit/AnalyzeTurnPhotoJobV2Test.php tests/Unit/AnalyzeTurnPhotoJobTest.php
git commit -m "feat(55): rewire AnalyzeTurnPhotoJob to v2 (Python scoring + turn analysis)"
```

---

## Phase 8 — Deploy wiring, validation harness, docs

### Task 15: Compose env + Dockerfile default model

**Files:**
- Modify: `docker-compose.yml` (python-service block, ~lines 108-122)
- Modify: `docker/compose.dev.yml` (python-service block, ~lines 66-80)
- Modify: `python-service/Dockerfile`
- Test: `python-service/tests/test_settings.py` already covers settings; this task adds no logic, only wiring. Verify via the import test below.

- [ ] **Step 1: Add an `environment:` block to the python-service service in BOTH compose files.**

In `docker-compose.yml`, inside the `python-service:` service definition, add:

```yaml
    environment:
      ANTHROPIC_API_KEY: ${ANTHROPIC_API_KEY}
      AIMTRACK_VISION_MODEL: ${AIMTRACK_VISION_MODEL:-claude-opus-4-8}
      AIMTRACK_VISION_EFFORT: ${AIMTRACK_VISION_EFFORT:-high}
      AIMTRACK_REVIEW_CONFIDENCE: ${AIMTRACK_REVIEW_CONFIDENCE:-0.6}
      AIMTRACK_CAL_RMS_REVIEW_MM: ${AIMTRACK_CAL_RMS_REVIEW_MM:-20.0}
```

In `docker/compose.dev.yml`, add the identical block to the `python-service:` service.

> **Secret handling (CLAUDE.md STOP #7):** `ANTHROPIC_API_KEY` must come from the host environment / an untracked `.env`, never committed. Document in the worktree `.env.example` if one exists; do not hardcode a key anywhere.

- [ ] **Step 2: Add a non-secret default to the Dockerfile.**

In `python-service/Dockerfile`, add before the `CMD` line:

```dockerfile
ENV AIMTRACK_VISION_MODEL=claude-opus-4-8
```

(Do **not** add `ENV ANTHROPIC_API_KEY` — secrets are injected at runtime by compose.)

- [ ] **Step 3: Verify settings still import cleanly**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_settings.py -v`
Expected: PASS (2 passed).

- [ ] **Step 4: Rebuild + smoke-check the service health (manual)**

```bash
cd /home/brandnetel/projects/aimtrack-55
docker compose --env-file .env -f docker/compose.dev.yml up -d --build python-service
docker compose --env-file .env -f docker/compose.dev.yml exec python-service python -c "import anthropic; from app.settings import settings; print(settings.vision_model)"
```
Expected: prints `claude-opus-4-8` and no import error (confirms `anthropic` installed + settings wired).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add docker-compose.yml docker/compose.dev.yml python-service/Dockerfile
git commit -m "chore(55): wire ANTHROPIC_API_KEY + vision config into python-service"
```

### Task 16: Validation harness (metrics + manual runner)

**Files:**
- Create: `python-service/app/validation/__init__.py` (empty), `python-service/app/validation/metrics.py`
- Create: `python-service/tools/validate_detection.py`
- Test: `python-service/tests/test_metrics.py`

- [ ] **Step 1: Write the failing test**

```python
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
        # 2 predictions compared in order: 10==10 (hit), 7!=9 (miss) -> 1/2
        assert m["ring_accuracy"] == 0.5

    def test_empty_prediction(self):
        truth = [{"ring": 10}]
        m = compare_turn(truth, [])
        assert m["count_correct"] is False
        assert m["ring_accuracy"] == 0.0
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_metrics.py -v`
Expected: FAIL — `ModuleNotFoundError: No module named 'app.validation'`.

- [ ] **Step 3: Write minimal implementation**

```python
# python-service/app/validation/__init__.py
```

```python
# python-service/app/validation/metrics.py
from __future__ import annotations


def compare_turn(truth: list[dict], pred: list[dict]) -> dict:
    """Compare ground-truth shots to predicted shots for one turn.

    Pairs shots by sorted (ring desc) order — a coarse but stable proxy when the
    harness lacks per-shot correspondence. Returns count_correct + ring_accuracy."""
    count_correct = len(truth) == len(pred)
    if not truth:
        return {"count_correct": count_correct, "ring_accuracy": 1.0 if not pred else 0.0}
    if not pred:
        return {"count_correct": False, "ring_accuracy": 0.0}

    t_sorted = sorted((s["ring"] for s in truth), reverse=True)
    p_sorted = sorted((s["ring"] for s in pred), reverse=True)
    pairs = min(len(t_sorted), len(p_sorted))
    hits = sum(1 for i in range(pairs) if t_sorted[i] == p_sorted[i])
    return {"count_correct": count_correct, "ring_accuracy": hits / len(t_sorted)}
```

```python
# python-service/tools/validate_detection.py
"""Manual validation harness for GH-55 photo→shots detection.

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

Prints per-photo and aggregate count-accuracy and ring-accuracy. Use the numbers
to decide, per discipline, whether zero-touch accuracy is acceptable.
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest tests/test_metrics.py -v`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/validation/__init__.py python-service/app/validation/metrics.py python-service/tools/validate_detection.py python-service/tests/test_metrics.py
git commit -m "feat(55): validation harness (accuracy metrics + manual runner)"
```

---

## Final verification

- [ ] **Run the full Python suite:**
  `cd /home/brandnetel/projects/aimtrack-55/python-service && python -m pytest -q` → all green.
- [ ] **Run the full Laravel suite:**
  `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact` → all green.
- [ ] **Lint PHP:** `cd /home/brandnetel/projects/aimtrack-55 && vendor/bin/pint --test` → no issues (run `vendor/bin/pint` to fix).
- [ ] **Manual end-to-end (real Claude):** set `ANTHROPIC_API_KEY`, rebuild the dev stack, set a session's discipline, upload a real target photo per turn with the shot count, confirm shots appear scored correctly and `needs_review` behaves. Then run `tools/validate_detection.py` against a labelled manifest per discipline and record the accuracy numbers before declaring zero-touch ready.

---

## Self-review (against the spec)

**Spec coverage:**
- §1.1 calibration-never-wired + wrong scoring → Task 7 (pipeline calls `calibrate`), Task 14 (job calls v2, deletes local ring math). ✅
- §4 Stage 1 register → reused in Task 7. ✅
- §4 Stage 2 candidates (incl. re-enabled light-in-black) → Task 2. ✅
- §4 Stage 3 Claude discrimination, structured output, count constraint, anchor → Task 4. ✅
- §4 Stage 4 reconcile (snap + cap) → Task 5 (with documented v1 under-count behavior). ✅
- §4 Stage 5 discipline-correct edge-gauged scoring → Task 1. ✅
- §5 Claude integration (Opus 4.8, adaptive thinking, effort high, env config, graceful degradation) → Tasks 3, 4, 7, 15. ✅
- §6 data model: `sessions.target_type` + Select → Tasks 10, 11; `session_turn_analyses` tiny table + model → Task 12; job rewrite → Task 14; per-shot `confidence`/`kind` in metadata → Task 14. ✅
- §7 coordinate/scoring contract (475.0, [-1,1], canvas 0.46) → Tasks 1, 14. ✅
- §8 non-blocking review (still saves shots; flag) → Tasks 7, 14. ✅
- §9 validation harness + metrics → Task 16. ✅
- §11 config/secrets/cost/privacy/errors → Tasks 3, 15; degradation → Task 7. ✅
- §12 decision #5 per-turn count field → Task 13; #8 tiny table → Task 12. ✅

**Type/name consistency:** `detect_candidates`, `detect_holes`, `reconcile`/`Hole`, `score_shot`/`ScoredShot`, `analyze_target_v2`/`AnalysisResult`, `ShotResultV2`/`AnalyzeV2Response`, `settings`, `TargetType`, `SessionTurnAnalysis`, `expectedShotCount`/`expectedShotCountForTest()` — used identically across producing and consuming tasks. ✅

**Known v1 simplifications (documented, intentional):** (a) under-count is flagged via `needs_review` rather than back-filled from CV candidates (Task 5 note); (b) the needs-review UI badge + the per-turn count input field are presentation-only manual steps (Task 13 UI note) — wire and verify visually, no automated test.
