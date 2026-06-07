# MIGRATION.md — AimTrack Shot Detection Overhaul

> Auteur: Claude Code (senior CV engineer rol)
> Datum: 2026-04-18
> Doel: Documenteert wat behouden blijft, wat vervangen wordt, en waarom.

---

## Wat blijft

| Component | Locatie | Reden |
|-----------|---------|-------|
| FastAPI framework | `main.py` (wordt geherstructureerd) | Goede keuze, geen reden om te wisselen |
| Uvicorn ASGI server | `main.py` + `Dockerfile` | Stabiel, productie-klaar |
| Docker / docker-compose setup | `Dockerfile`, `../docker-compose.yml` | Infrastructuur hoeft niet mee te veranderen |
| `/api/v1/health` endpoint | `main.py:513` | Monitoring-afhankelijkheden behouden |
| `/api/v1/analyze-target` + `/api/v1/analyze-target-v2` | `main.py:426,465` | Blijven bestaan als **deprecated backwards-compat** endpoints |
| Output-contract `{x, y, confidence}` in `[-1, 1]` | `AnalyzeTurnPhotoJob.php:87-130` | Laravel-frontend en Pest-tests zijn hiervan afhankelijk |

---

## Wat volledig vervangen wordt

### 1. `detect_target_area()` — `main.py:187-276`

**Probleem:** Gebruikt vaste drempelwaarde 100 voor binaire threshold en de magic number
`1.67` om het zwarte doelwit (ringen 7-10) op te schalen naar het volledige ISSF-roos.
Dit werkt alleen voor één specifiek rozentype, faalt bij slechte belichting, en
heeft geen perspectiefcorrectie.

**Vervanging:** ArUco-marker homografie (Fase 1) en roos-intrinsieke ellipsfitting (Fase 2).

---

### 2. Alle heuristische analysefuncties — `main.py:29-185`

| Functie | Regels | Probleem |
|---------|--------|---------|
| `analyze_texture_features()` | 29-52 | LBP-variance genormaliseerd op `/50.0` — een magic number zonder fysieke basis |
| `analyze_edge_characteristics()` | 54-89 | Drempelwaarden `0.05-0.25` en `30/100` voor Canny zijn belichting-afhankelijk |
| `analyze_shadow_pattern()` | 91-136 | Sobel-gradiëntbereik `10-40` is empirisch en faalt op andere achtergronden |
| `calculate_hole_likelihood()` | 138-185 | Gewichten `0.40/0.25/0.20/0.15` zijn empirisch, niet valideerbaar |

**Vervanging:** YOLOv8/v11-nano object detection getraind op gelabelde rozen-foto's (Fase 3).

---

### 3. `detect_bullet_holes()` — `main.py:278-420`

**Problemen:**
- Pixelbereik `area < 15 or area > 300` schaalt niet mee met resolutie
- Circularity-drempel `0.4` is te laag om plakkers te filteren
- Maximum van 12 schoten is hard-coded
- Coördinaten-normalisatie leunt volledig op de foutieve `detect_target_area()`
- Geen perspectiefcorrectie: scheve foto → scheve coördinaten

**Vervanging:** YOLO-inference op gewarpte canonieke afbeelding, output in mm.

---

### 4. Hele single-file architectuur — `main.py`

**Probleem:** 543 regels, geen modules, geen config-file, geen type-safe Pydantic-modellen
voor API I/O, geen structured logging, geen tests.

**Vervanging:** Gemodulariseerde package-structuur:

```
python-service/
├── app/
│   ├── api/
│   │   └── routes.py          # FastAPI endpoints
│   ├── calibration/
│   │   ├── aruco.py           # Fase 1: ArUco homografie
│   │   └── target_intrinsic.py # Fase 2: ellipsfitting fallback
│   ├── detection/
│   │   └── yolo_detector.py   # Fase 3: YOLO inference
│   ├── scoring/
│   │   └── ring_score.py      # mm → ringscore conversie
│   ├── models/
│   │   └── schemas.py         # Pydantic request/response modellen
│   └── config.py              # TargetSpec dataclasses, alle config
├── tests/
│   ├── test_calibration.py
│   ├── test_detection.py
│   └── test_scoring.py
├── main.py                    # Enkel entry point (behouden voor compat)
├── requirements.txt           # Uitgebreid met opencv-contrib, ultralytics
├── Dockerfile
└── MIGRATION.md               # Dit bestand
```

---

## Impact op Laravel-kant

| Component | Aanpassing nodig? | Details |
|-----------|-------------------|---------|
| `AnalyzeTurnPhotoJob.php` | **Nee** (tijdelijk) | Blijft werken via backwards-compat endpoint |
| `config/services.php` | Nee | URL-config ongewijzigd |
| `SessionShot` model | Mogelijk Fase 3 | Nieuwe velden voor `x_mm`, `y_mm`, `ring_decimal` |
| Pest-tests `PhotoAnalysisTest.php` | Mogelijk Fase 3 | Als output-contract uitgebreid wordt |
| Pest-tests `AnalyzeTurnPhotoJobTest.php` | Mogelijk Fase 3 | Coördinaat-conversie kan veranderen |
| Frontend roos-rendering | Fase 3 (optioneel) | Kan overstappen op mm-coördinaten |

---

## Bekende risico's

1. **OpenCV ArUco API-wijziging**: `cv2.aruco` is verplaatst naar `opencv-contrib-python`
   in nieuwere versies — `requirements.txt` moet bijgewerkt worden.
2. **YOLO model-grootte**: YOLOv8-nano (~6MB) past op de TransIP-server; full-size YOLO
   zou te zwaar zijn voor CPU-inference.
3. **Training data**: Zonder gelabelde foto's kan Fase 3 niet starten. Dit is de
   grootste blokkerende factor.
4. **Backwards-compat**: De factor `0.46` in `AnalyzeTurnPhotoJob.php:89` en de
   ring-berekening op basis van genormaliseerde afstand zijn gebaseerd op de huidige
   output. Zolang het backwards-compat endpoint actief is, mag dit niet veranderen.

---

## Deprecated endpoints (sunset-plan)

| Endpoint | Status | Sunset-datum |
|----------|--------|-------------|
| `POST /api/v1/analyze-target` | `Deprecated` na Fase 3 | T.b.d. door Marc |
| `POST /api/v1/analyze-target-v2` | `Deprecated` na Fase 3 | T.b.d. door Marc |
| `GET /api/v1/debug` | Verwijderd | Na Fase 1 (dev-only artefact) |

Nieuwe primaire endpoint: `POST /api/v2/analyze-target`
