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


def _call_claude(canonical_b64: str, system: str, api_key: str | None = None) -> str:
    """Single network call. Isolated so tests can monkeypatch it. Returns the raw
    JSON text the model produced under the structured-output format constraint.

    ``api_key`` is the per-user BYO Claude key (resolved by Laravel, forwarded per
    request); it takes precedence over the optional service-level env key."""
    from anthropic import Anthropic

    client = Anthropic(api_key=api_key or settings.anthropic_api_key or None)
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
