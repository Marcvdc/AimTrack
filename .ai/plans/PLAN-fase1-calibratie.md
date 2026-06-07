# PLAN — Fase 1: Service-Refactor & Intrinsieke Kalibratie
**Status:** APPROVED
**Branch:** `feature/phase-1-calibration`
**Datum:** 2026-04-19
**Auteur:** Claude Code

---

## Samenvatting

De huidige `python-service/main.py` (543 regels, één flat file) wordt vervangen door een
modulaire package-structuur. Kerntoevoeging is een intrinsieke kalibratie-module die
perspectiefcorrectie uitvoert op basis van de roos-ringen zelf — zonder fysieke
ArUco-markers op de schietbaan.

Ondersteunde rozentypes in Fase 1 (5 van de 6):

| Discipline | Ring 10 Ø | Ring 1 Ø | Zwart Ø |
|---|---|---|---|
| 25m KKP | 50 mm | 500 mm | 200 mm |
| 25m GKP | 50 mm | 500 mm | 200 mm |
| 50m KKG | 10.4 mm | 154.4 mm | 112.4 mm |
| 100m KKG | 28 mm | 328 mm | 195 mm |
| 100m GKG | 28 mm | 328 mm | 195 mm |

Buiten scope: **25m KKG** (multi-target A3-vel) → Fase 3.

---

## Acceptance Criteria

- [ ] **AC1** — Gegeven een scherpe foto van een KKP/GKP/KKG roos, produceert het nieuwe
  `/api/v1/calibrate` endpoint een canonieke 1000×1000px PNG waarin de roos gecentreerd
  en recht staat, samen met de homografie-matrix en een `calibration_confidence` score.

- [ ] **AC2** — Gegeven een scheve foto (camerahoek ≤20° afwijking van loodrecht), wijkt
  de geprojecteerde positie van ring 10 niet meer dan 5% af van de werkelijke positie
  t.o.v. de totale roos-diameter.

- [ ] **AC3** — Als de kalibratie niet convergeert (te weinig ringen detecteerbaar, bv.
  volledig overdekte roos), geeft de API een duidelijke foutmelding in het Nederlands
  met uitleg over de oorzaak, en status 422.

- [ ] **AC4** — De bestaande endpoints `/api/v1/analyze-target` en
  `/api/v1/analyze-target-v2` blijven exact hetzelfde werken als vóór de refactor
  (output-contract ongewijzigd). Bestaande Pest-tests in Laravel slagen ongewijzigd.

- [ ] **AC5** — Alle nieuwe Python-modules hebben ≥80% test-coverage (pytest). Ruff en
  mypy (strict) rapporteren nul fouten.

- [ ] **AC6** — Marc kan een eigen foto uploaden via de `/api/v1/calibrate` endpoint en
  het gecorrigeerde resultaat visueel verifiëren (PNG-response in browser of curl).

---

## Technische Aanpak

### Nieuwe package-structuur

```
python-service/
├── app/
│   ├── __init__.py
│   ├── config.py               # TargetSpec dataclasses + TARGET_SPECS dict
│   ├── api/
│   │   ├── __init__.py
│   │   └── routes.py           # FastAPI routers
│   ├── calibration/
│   │   ├── __init__.py
│   │   └── target_intrinsic.py # Hough + ellipsfit kalibratie
│   └── models/
│       ├── __init__.py
│       └── schemas.py          # Pydantic request/response modellen
├── tests/
│   ├── __init__.py
│   ├── test_config.py
│   ├── test_calibration.py
│   └── test_api.py
├── main.py                     # Entry point (ongewijzigd voor Docker-compat)
├── requirements.txt            # + opencv-contrib-python voor Hough
└── Dockerfile                  # Ongewijzigd
```

### TargetSpec dataclass (`app/config.py`)

```python
@dataclass
class TargetSpec:
    name: str
    distance_m: int
    ring_diameters_mm: dict[int, float]  # {10: 50.0, 9: 100.0, ...}
    black_area_diameter_mm: float
    bullet_diameter_mm: float
```

### Kalibratie-algoritme (`app/calibration/target_intrinsic.py`)

1. **Preprocessing**: CLAHE contrast-normalisatie → adaptieve threshold
2. **Ring-detectie**: `cv2.HoughCircles` met parameterranges afgeleid van `TargetSpec`
3. **Ellips-fitting**: Voor elke gedetecteerde ring `cv2.fitEllipse` → bij scheve foto
   zijn ringen ellipsen in plaats van cirkels
4. **Homografie-berekening**: Projecteer ellips-parameters naar cirkels (homografische
   transformatie). Minimaal 2 ringen nodig voor stabiele homografie.
5. **Warp**: `cv2.warpPerspective` → canonieke 1000×1000px output
6. **Confidence**: RMS-fout van ring-fits na warp (in mm), genormaliseerd naar 0–1

### Nieuwe Pydantic-modellen (`app/models/schemas.py`)

```python
class CalibrateResponse(BaseModel):
    success: bool
    target_type: str
    calibration_method: str        # "intrinsic"
    calibration_confidence: float  # 0.0–1.0
    rms_error_mm: float
    canonical_image_b64: str       # base64 PNG
    homography_matrix: list[list[float]]  # 3x3

class AnalyzeResponse(BaseModel):   # Bestaand contract, ongewijzigd
    success: bool
    shots: list[ShotResult]
    total_detected: int

class ShotResult(BaseModel):
    x: float   # [-1, 1] — backwards-compat
    y: float   # [-1, 1] — backwards-compat
    confidence: float
```

### Nieuw endpoint

```
POST /api/v1/calibrate
  Body: multipart/form-data { file: image, target_type: str }
  Response: CalibrateResponse (JSON met base64 PNG)
```

---

## Niet in scope (Fase 1)

- Shot-detectie verbeteren (YOLO) → Fase 3
- 25m KKG multi-target format → Fase 3
- Label Studio setup → Fase 2
- mm-coördinaten output → Fase 3
- ArUco marker ondersteuning (gebruiker heeft gekozen voor keuze B)

---

## Risico's

| Risico | Kans | Impact | Mitigatie |
|--------|------|--------|-----------|
| Hough Circles detecteert ringen niet bij volle roos (veel plakkers) | Middel | Hoog | Geef AC3-foutmelding; Fase 3 (YOLO) lost dit structureel op |
| `opencv-contrib-python` conflicteert met bestaande `opencv-python` | Laag | Middel | Vervang in requirements.txt; test in Docker |
| Homografie instabiel bij slechts 2 zichtbare ringen | Middel | Middel | Minimaal 3 ringen eisen; duidelijke foutmelding bij te weinig |

---

## Definition of Done (Fase 1)

- [ ] Alle AC's groen
- [ ] `vendor/bin/pint` equivalent: `ruff check` + `mypy` nul fouten
- [ ] `pytest --cov=app --cov-report=term-missing` toont ≥80% coverage
- [ ] Docker-container bouwt en draait zonder wijzigingen aan `docker-compose.yml`
- [ ] Marc heeft AC6 visueel geverifieerd
- [ ] Korte README-update in `python-service/README.md` met uitleg nieuw endpoint
- [ ] Commit goedgekeurd door Marc

---

## Gevraagde goedkeuring

Wijzig de status bovenaan naar `APPROVED` of geef expliciet "akkoord" om te starten.
