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
    review_reason: str
    orientation_note: str
    vision_model: str
    calibration: dict


def _build_review_reason(
    *,
    vision_ok: bool,
    count_ok: bool,
    detected: int,
    expected: int | None,
    overall_conf: float,
    rms: float,
) -> str:
    """Concrete Dutch reason why a turn was flagged for review (empty if it wasn't)."""
    if not vision_ok:
        return (
            "AI-herkenning niet beschikbaar (geen of ongeldige ANTHROPIC_API_KEY) — "
            "alleen ruwe detectie; stickers en gedrukte cijfers zijn NIET uitgefilterd. "
            "Controleer de schoten handmatig."
        )
    reasons: list[str] = []
    if not count_ok and expected is not None:
        reasons.append(
            f"Aantal gedetecteerd ({detected}) wijkt af van het ingevulde aantal ({expected})."
        )
    if overall_conf < settings.review_confidence_threshold:
        reasons.append(f"Lage zekerheid ({round(overall_conf * 100)}%).")
    if rms > settings.cal_rms_review_mm:
        reasons.append(f"Onnauwkeurige uitlijning van de roos ({round(rms)} mm afwijking).")
    return " ".join(reasons) or "Controleer de gedetecteerde schoten."


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
            review_reason="Kalibratie mislukt — de roos kon niet worden uitgelijnd. Maak een rechtere, scherpere foto van het hele schietbord.",
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
    review_reason = (
        _build_review_reason(
            vision_ok=vision_ok,
            count_ok=count_ok,
            detected=detected,
            expected=expected_shot_count,
            overall_conf=overall_conf,
            rms=cal.rms_error_mm,
        )
        if needs_review
        else ""
    )

    return AnalysisResult(
        shots=shots,
        expected_shot_count=expected_shot_count,
        detected_count=detected,
        count_matches_expected=count_ok,
        overall_confidence=round(overall_conf, 3),
        needs_review=needs_review,
        review_reason=review_reason,
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
