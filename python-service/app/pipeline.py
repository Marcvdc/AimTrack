# python-service/app/pipeline.py
from __future__ import annotations

from dataclasses import dataclass

import numpy as np

from app.calibration.target_intrinsic import CalibrationError, CalibrationResult, calibrate
from app.config import TargetSpec
from app.detection.candidates import detect_candidates
from app.detection.reconcile import Hole, reconcile
from app.scoring import score_from_vision, score_shot
from app.settings import settings
from app.vision.claude_detector import VisionError, detect_holes, detect_holes_direct


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


_NO_KEY_REASON = (
    "AI-herkenning niet beschikbaar (geen geldige Claude-key) — stel je Claude-key "
    "in bij AI-instellingen. Er draaide alleen ruwe detectie; stickers en gedrukte "
    "cijfers zijn NIET uitgefilterd. Controleer de schoten handmatig."
)


def _build_review_reason(
    *,
    vision_ok: bool,
    count_ok: bool,
    detected: int,
    expected: int | None,
    overall_conf: float,
    rms: float,
) -> str:
    """Concrete Dutch reason why a canonical-path turn was flagged for review."""
    if not vision_ok:
        return _NO_KEY_REASON
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


def _build_review_reason_direct(
    *,
    vision_ok: bool,
    count_ok: bool,
    detected: int,
    expected: int | None,
    overall_conf: float,
    cal_rms: float | None,
    cal_failed: bool,
) -> str:
    """Reason for the vision-direct path (calibration failed/weak, so positions are
    approximate but the rings were read directly). A high RMS with a converged
    calibration is a strong signal the chosen discipline does not match the target
    (e.g. a rifle target scored as pistol-25m), so we say so explicitly."""
    if not vision_ok:
        return _NO_KEY_REASON
    if cal_failed:
        lead = (
            "De roos kon niet worden uitgelijnd — maak een rechtere, scherpere foto van de "
            "hele roos. De schoten zijn via directe ring-aflezing geplaatst; controleer de posities."
        )
    else:
        mm = f" ({round(cal_rms)} mm afwijking)" if cal_rms is not None else ""
        lead = (
            f"De roos past slecht bij de gekozen discipline{mm} — controleer of het roostype "
            "(discipline) klopt. De schoten zijn via directe ring-aflezing geplaatst; controleer de posities."
        )
    reasons = [lead]
    if not count_ok and expected is not None:
        reasons.append(
            f"Aantal gedetecteerd ({detected}) wijkt af van het ingevulde aantal ({expected})."
        )
    if overall_conf < settings.review_confidence_threshold:
        reasons.append(f"Lage zekerheid ({round(overall_conf * 100)}%).")
    return " ".join(reasons)


def _cap_vision_shots(shots: list[dict], expected_shot_count: int | None) -> list[dict]:
    """Drop sub-confidence vision-direct shots, then cap any surplus to the expected
    count (dropping the lowest-confidence extras). Mirrors ``reconcile`` for the path
    that has no CV candidates to snap to: fewer confident shots + needs_review beats
    padding up to the count with dubious markers."""
    kept = [s for s in shots if s["confidence"] >= settings.min_shot_confidence]
    if expected_shot_count is not None and len(kept) > expected_shot_count:
        kept = sorted(kept, key=lambda s: s["confidence"], reverse=True)[:expected_shot_count]
    return kept


def analyze_target_v2(image: np.ndarray, spec: TargetSpec, expected_shot_count: int | None, api_key: str | None = None) -> AnalysisResult:
    """Detect shots on a target photo and score them discipline-correctly.

    Calibration is a non-blocking hint, not a hard gate: when the intrinsic homography
    converges accurately (RMS within ``cal_rms_fallback_mm``) the canonical path runs
    (CV candidates -> Claude discrimination -> reconcile -> pixel scoring). When
    calibration fails or is too weak, the vision-direct path takes over — the model
    reads the ring straight off the raw photo — so a bad homography never loses the
    turn. Never raises for normal failure modes; degrades to needs_review."""
    cal: CalibrationResult | None = None
    cal_error: CalibrationError | None = None
    try:
        cal = calibrate(image, spec)
    except CalibrationError as exc:
        cal_error = exc

    if cal is not None and cal.rms_error_mm <= settings.cal_rms_fallback_mm:
        return _analyze_canonical(image, spec, expected_shot_count, api_key, cal)
    return _analyze_vision_direct(image, spec, expected_shot_count, api_key, cal, cal_error)


def _analyze_canonical(
    image: np.ndarray,
    spec: TargetSpec,
    expected_shot_count: int | None,
    api_key: str | None,
    cal: CalibrationResult,
) -> AnalysisResult:
    """Homography-corrected path: CV candidates -> Claude discrimination -> reconcile
    -> ISSF pixel scoring on the canonical 1000x1000 image."""
    candidates = detect_candidates(cal.canonical_image, spec)

    try:
        vision = detect_holes(cal.canonical_image, spec, expected_shot_count, api_key)
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
            "method": "canonical",
        },
    )


def _analyze_vision_direct(
    image: np.ndarray,
    spec: TargetSpec,
    expected_shot_count: int | None,
    api_key: str | None,
    cal: CalibrationResult | None,
    cal_error: CalibrationError | None,
) -> AnalysisResult:
    """Fallback path when calibration failed or is too weak: the model reads the ring
    directly off the raw photo and reports target-normalized positions. The turn is
    still flagged needs_review because absolute placement is approximate without a
    homography, but the shots are never discarded."""
    try:
        vision = detect_holes_direct(image, spec, expected_shot_count, api_key)
        kept = _cap_vision_shots(vision["shots"], expected_shot_count)
        overall_conf = vision["overall_confidence"]
        orientation = vision["orientation_note"]
        vision_ok = True
    except VisionError:
        kept = []
        overall_conf = 0.0
        orientation = ""
        vision_ok = False

    shots: list[dict] = []
    for s in kept:
        sc = score_from_vision(s["x_norm"], s["y_norm"], s["ring"])
        shots.append(
            {"x": sc.x, "y": sc.y, "ring": sc.ring, "score": sc.score,
             "confidence": round(s["confidence"], 3), "kind": s["kind"]}
        )

    detected = len(shots)
    count_ok = expected_shot_count is None or detected == expected_shot_count
    review_reason = _build_review_reason_direct(
        vision_ok=vision_ok,
        count_ok=count_ok,
        detected=detected,
        expected=expected_shot_count,
        overall_conf=overall_conf,
        cal_rms=(cal.rms_error_mm if cal is not None else None),
        cal_failed=(cal is None),
    )

    if cal_error is not None:
        cal_err_msg = str(cal_error)
        rings = cal_error.rings_detected
    else:
        cal_err_msg = f"RMS te hoog ({round(cal.rms_error_mm)} mm) — homografie onbetrouwbaar" if cal is not None else "kalibratie niet uitgevoerd"
        rings = cal.rings_detected if cal is not None else 0

    return AnalysisResult(
        shots=shots,
        expected_shot_count=expected_shot_count,
        detected_count=detected,
        count_matches_expected=count_ok,
        overall_confidence=round(overall_conf, 3),
        needs_review=True,
        review_reason=review_reason,
        orientation_note=orientation,
        vision_model=settings.vision_model,
        calibration={
            "ok": False,
            "error": cal_err_msg,
            "rms_error_mm": (cal.rms_error_mm if cal is not None else None),
            "confidence": (cal.confidence if cal is not None else None),
            "rings_detected": rings,
            "method": "vision_direct",
        },
    )
