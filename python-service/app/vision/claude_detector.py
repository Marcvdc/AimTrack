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
        f"Er zijn precies {expected_shot_count} schoten gelost in deze beurt; rapporteer er bij "
        f"voorkeur exact {expected_shot_count} schoten, maar rapporteer NOOIT iets wat geen vers "
        f"kogelgat is alleen om dat aantal te halen."
        if expected_shot_count is not None
        else "Het aantal schoten is onbekend; rapporteer elk vers kogelgat waar je zeker van bent."
    )
    return (
        f"Je analyseert een perspectief-gecorrigeerde foto van een {spec.name} schietkaart "
        f"(1000x1000 px; het zwarte richtvlak staat exact gecentreerd op (500,500); de ring-1 "
        f"rand ligt op straal 475 px vanaf het centrum). "
        f"BELANGRIJK: oude treffers op deze kaart zijn dichtgeplakt met lichte (witte/lichtblauwe) "
        f"ronde plakkers (pasters) — dat zijn GEEN schoten. "
        f"Rapporteer UITSLUITEND de VERSE kogelgaten van deze beurt: donkere perforaties op het "
        f"lichte papier, of lichte doorschijn waar VERS door het zwarte vlak is geschoten. "
        f"Negeer expliciet: lichte plakkers/pasters, gedrukte ringcijfers (zoals 8, 9, 10), "
        f"ringlijnen, kartonscheuren en tape. "
        f"Liever minder gaten rapporteren waar je zeker van bent dan twijfelgevallen meetellen — "
        f"zet de confidence laag (onder 0.4) bij twijfel. "
        f"Gebruik de gedrukte ringcijfers alleen als orientatie-anker. {count_line}"
    )


def _call_claude(canonical_b64: str, system: str, api_key: str | None = None, schema: dict | None = None) -> str:
    """Single network call. Isolated so tests can monkeypatch it. Returns the raw
    JSON text the model produced under the structured-output format constraint.

    ``api_key`` is the per-user BYO Claude key (resolved by Laravel, forwarded per
    request); it takes precedence over the optional service-level env key.
    ``schema`` is the JSON schema the structured output must satisfy; it defaults to
    the canonical-pixel ``_SHOT_SCHEMA`` so existing callers are unchanged."""
    from anthropic import Anthropic

    client = Anthropic(api_key=api_key or settings.anthropic_api_key or None)
    response = client.messages.create(
        model=settings.vision_model,
        max_tokens=4096,
        system=system,
        thinking={"type": "adaptive"},
        output_config={
            "effort": settings.vision_effort,
            "format": {"type": "json_schema", "schema": schema or _SHOT_SCHEMA},
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


def detect_holes(canonical: np.ndarray, spec: TargetSpec, expected_shot_count: int | None, api_key: str | None = None) -> dict:
    """Send the canonical image to Claude; return validated/clamped hole detections.

    ``api_key`` is the per-user BYO Claude key (resolved by Laravel, forwarded per
    request). Returns: {shots: [{x_px,y_px,confidence,kind}], orientation_note,
    overall_confidence, count_matches_expected}. Raises VisionError on any failure."""
    ok, buf = cv2.imencode(".png", canonical)
    if not ok:
        raise VisionError("PNG-codering mislukt")
    b64 = base64.b64encode(buf.tobytes()).decode("ascii")

    try:
        raw = _call_claude(b64, _system_prompt(spec, expected_shot_count), api_key)
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


# ---------------------------------------------------------------------------
# Vision-direct — fallback when calibration failed or is too weak for a reliable
# homography. The model analyses the RAW (uncalibrated) photo and reports each shot
# as a target-normalized position plus the ring it reads directly off the printed
# rings, so a failed homography no longer loses the turn.
# ---------------------------------------------------------------------------

_DIRECT_SHOT_SCHEMA: dict = {
    "type": "object",
    "additionalProperties": False,
    "properties": {
        "shots": {
            "type": "array",
            "items": {
                "type": "object",
                "additionalProperties": False,
                "properties": {
                    "x_norm": {"type": "number"},
                    "y_norm": {"type": "number"},
                    "ring": {"type": "integer"},
                    "confidence": {"type": "number"},
                    "kind": {"type": "string", "enum": ["hole", "uncertain"]},
                },
                "required": ["x_norm", "y_norm", "ring", "confidence", "kind"],
            },
        },
        "orientation_note": {"type": "string"},
        "overall_confidence": {"type": "number"},
        "count_matches_expected": {"type": "boolean"},
    },
    "required": ["shots", "orientation_note", "overall_confidence", "count_matches_expected"],
}

_VISION_MAX_DIM: int = 1500


def _direct_system_prompt(spec: TargetSpec, expected_shot_count: int | None) -> str:
    count_line = (
        f"Er zijn precies {expected_shot_count} schoten gelost in deze beurt; rapporteer er bij "
        f"voorkeur exact {expected_shot_count} schoten, maar rapporteer NOOIT iets wat geen vers "
        f"kogelgat is alleen om dat aantal te halen."
        if expected_shot_count is not None
        else "Het aantal schoten is onbekend; rapporteer elk vers kogelgat waar je zeker van bent."
    )
    return (
        f"Je analyseert een ONBEWERKTE foto van een {spec.name} schietkaart; het perspectief is "
        f"NIET gecorrigeerd, dus de kaart kan schuin of gedraaid in beeld staan. "
        f"Bepaal zelf het centrum van het zwarte richtvlak en lees de concentrische ringen af. "
        f"Rapporteer per VERS kogelgat: (1) de positie GENORMALISEERD t.o.v. het roos-centrum, waarbij "
        f"(0,0) het centrum is en straal 1.0 de BUITENrand van ring 1 (rechts = +x_norm, omlaag = "
        f"+y_norm); (2) de RING (1-10, of 0 als het schot buiten ring 1 valt) die je DIRECT van de "
        f"gedrukte ringen afleest — NIET uit de afstand berekent, want door het perspectief is de "
        f"afstand geen betrouwbare ringmaat. "
        f"Oude treffers zijn dichtgeplakt met lichte (witte/lichtblauwe) plakkers op het papier of "
        f"ZWARTE plakkers op het zwarte vlak — dat zijn GEEN schoten. Rapporteer UITSLUITEND verse "
        f"kogelgaten: donkere perforaties op licht papier, of lichte/gescheurde kraters waar VERS door "
        f"het zwart is geschoten. Negeer expliciet: gladde plakkers/pasters, gedrukte ringcijfers "
        f"(zoals 8, 9, 10), ringlijnen, kartonscheuren en tape. "
        f"Liever minder gaten rapporteren waar je zeker van bent dan twijfelgevallen meetellen — zet "
        f"de confidence laag (onder 0.4) bij twijfel. {count_line}"
    )


def _prepare_for_vision(image: np.ndarray, max_dim: int = _VISION_MAX_DIM) -> np.ndarray:
    """Downscale an oversized photo so the token cost of the vision call stays bounded.
    Aspect ratio is preserved, so fractional/normalized coordinates are unaffected."""
    h, w = image.shape[:2]
    longest = max(h, w)
    if longest <= max_dim:
        return image
    scale = max_dim / float(longest)
    return cv2.resize(image, (int(round(w * scale)), int(round(h * scale))), interpolation=cv2.INTER_AREA)


def detect_holes_direct(image: np.ndarray, spec: TargetSpec, expected_shot_count: int | None, api_key: str | None = None) -> dict:
    """Vision-direct fallback: analyse the RAW photo and return shots with a
    target-normalized position (centre=(0,0), ring-1 edge=1.0) plus the directly-read
    ring. Used when intrinsic calibration failed or is too weak for a reliable
    homography. Returns: {shots: [{x_norm,y_norm,ring,confidence,kind}], orientation_note,
    overall_confidence, count_matches_expected}. Raises VisionError on any failure."""
    ok, buf = cv2.imencode(".png", _prepare_for_vision(image))
    if not ok:
        raise VisionError("PNG-codering mislukt")
    b64 = base64.b64encode(buf.tobytes()).decode("ascii")

    try:
        raw = _call_claude(
            b64, _direct_system_prompt(spec, expected_shot_count), api_key, schema=_DIRECT_SHOT_SCHEMA
        )
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
                "x_norm": float(s["x_norm"]),
                "y_norm": float(s["y_norm"]),
                "ring": max(0, min(10, int(s.get("ring", 0)))),
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
