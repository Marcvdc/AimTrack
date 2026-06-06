# Photo → Shots Detection (Hybrid CV + Claude) — Design Spec

**Status:** DRAFT (awaiting user review)
**Date:** 2026-06-06
**Issue:** [GH-55](https://github.com/Marcvdc/AimTrack/issues/55) — "Via foto schoten bepalen op schotbord"
**Worktree / branch:** `aimtrack-55` / `feature/55`
**Author:** brainstorming session (Marc + Claude)

---

## 1. Context

Per turn, a shooter uploads a photo of their target ("roos"); the system must convert it
into shot markers with discipline-correct scoring — **fully automatically, zero-touch** for
the normal case. This is the highest-value feature in AimTrack.

Every algorithm to date has failed. The documented history (issue comments + worktree notes):

- **Legacy classical-CV detection** — shots land outside the target, picks up photo edges.
- **Intrinsic homography calibration (Fase 1)** — perspective-corrects the photo into a clean
  canonical 1000×1000 target; **this part works** (black centered, ~8mm registration RMS).
- **Classical-CV detection on the canonical** — fails at the real problem: it cannot tell a
  **printed ring number ("8"/"9") from a bullet hole** inside the black, drowns in paster
  clusters, and the canonical has no rotation anchor. The team's own conclusion, twice:
  *"no further classical-CV tweak will solve this."* The light-on-dark "Pass B" detector was
  disabled for exactly this reason.

**The reframe:** this is not a *pixel* problem, it is a *discrimination* problem — *is this dark
spot a hole, a printed digit, a paster, or a tear?* No threshold answers that; a model that
understands the image does. And the printed ring numbers that broke classical CV become an
**asset** for a vision model: a built-in anchor for orientation and scale.

### 1.1 Critical finding — calibration was never wired into production

`app/Jobs/AnalyzeTurnPhotoJob.php` currently calls `POST /api/v1/analyze-target-v2`, which runs
`_legacy_detect()` on the **raw, un-corrected photo**. The working `/api/v1/calibrate`
(homography) endpoint is **never called from production**. Separately, Laravel's
`calculateRing()` / `calculateScore()` use **hardcoded normalized thresholds** (0.05, 0.10, …)
that ignore the real per-discipline ring sizes already defined in `python-service/app/config.py`
(`TargetSpec.ring_diameters_mm`). So even a perfectly-placed shot is mis-scored on 4 of the 5
disciplines. Fixing this is in scope and is a prerequisite for zero-touch.

---

## 2. Goals & non-goals

### Goals
1. Upload a per-turn target photo → auto-detected shots with correct normalized coordinates.
2. **Zero-touch** for the normal case: shots are final without manual correction.
3. Discipline-correct ring + score using the real `TargetSpec`.
4. A **non-blocking review flag** for genuinely ambiguous photos (low confidence / count mismatch).
5. A **validation harness** that measures accuracy on real photos before we trust zero-touch.

### Non-goals (v1)
- Multi-target 25m KKG A3 sheet (deferred — hardest registration; user does not shoot it).
- Real-time camera capture (upload only).
- Self-hosted / on-premise ML model (cloud vision approved by user).
- Re-architecting the Filament upload UX beyond the new fields below.

### Disciplines in scope (the 5 already in `TARGET_SPECS`)
`kkp_25m`, `gkp_25m` (25m pistol), `kkg_50m` (50m rifle), `kkg_100m`, `gkg_100m` (100m rifle).

---

## 3. Architecture & data flow

```
Filament turn upload
   │  photo + target_type + expected_shot_count
   ▼
AnalyzeTurnPhotoJob (Laravel queue, existing)
   │  POST /api/v2/analyze-target   (NEW endpoint)
   │  multipart: file, target_type, expected_shot_count
   ▼
Python service — 5-stage pipeline
   │  returns: shots[{x,y,ring,score,confidence,kind}], calibration{rms,confidence},
   │           overall_confidence, needs_review, orientation
   ▼
AnalyzeTurnPhotoJob persists SessionShot rows (no local ring math)
```

**Ownership:** the Python service owns detection **and** scoring (it already holds `TargetSpec`).
Laravel persists results and stops computing rings/scores itself. The existing OpenAI AI-coach
(`config/ai.php`, `ShooterCoach`) is untouched — Claude vision is a separate concern living in
the Python service.

---

## 4. The 5-stage detection pipeline

All stages run server-side in the Python service behind `POST /api/v2/analyze-target`.

### Stage 1 — Register (existing, reused)
`calibrate(image, spec)` → `CalibrationResult`: canonical 1000×1000 BGR image, homography,
`rms_error_mm`, `confidence`, `rings_detected`. Black aiming area centered at (500,500); ring-1
edge at `CANONICAL_RING1_RADIUS` px. **No change** beyond calling it from the new endpoint.

### Stage 2 — Candidate detection (CV, high-recall)
Find every bullet-sized blob on the canonical — **both**:
- dark-on-light (paper / outer rings), and
- light-on-dark (inside the black — the previously-disabled "Pass B" returns, but now produces
  **candidates only**; false positives are acceptable and expected).

Each candidate keeps a **sub-pixel centroid** (`cv2.moments`) and a small crop bbox.
CV's responsibility is now **precision and recall of locations**, not judgment. Reuse the blob
machinery from `poc_canonical_detect.py` with loosened thresholds (drop the aggressive
circularity/solidity/donut rejections that cause misses; keep only size sanity bounds derived
from `spec.bullet_diameter_mm` and the canonical px/mm scale).

### Stage 3 — Discriminate & detect (Claude vision)
Send the **clean** canonical PNG to Claude Opus 4.8. Claude returns the pixel coordinate of every
**real** bullet hole, with instructions to ignore printed ring numbers, ring lines, pasters/patches
and cardboard tears; constrained to `expected_shot_count`; using printed numbers as the orientation
anchor. Structured output (forced tool-use, validated schema) — see §5. This is the stage every
prior algorithm lacked.

### Stage 4 — Reconcile (CV precision × Claude semantics)
For each Claude hole `(x_px, y_px)`:
- snap to the nearest Stage-2 candidate centroid within ~`bullet_radius_px` → use the **CV centroid**
  (sub-pixel precise) for scoring;
- if no candidate within tolerance (Claude found one CV missed), run a local centroid refinement in
  a small window around Claude's point; fall back to Claude's coordinate if refinement is empty.

Enforce count against `expected_shot_count`:
- more confirmed holes than N → drop the lowest-confidence extras;
- fewer than N → run a second, count-aware Claude pass (or lower CV thresholds locally) before giving
  up; if still short, return what we have and set `needs_review`.

### Stage 5 — Score (discipline-correct)
Convert each reconciled centroid to the existing `[-1, 1]` target-relative contract:
`x_norm = (x_px - 500) / CANONICAL_RING1_RADIUS` (same for y). Then score using **real** ring sizes:

- `center_distance_mm = sqrt(x_norm² + y_norm²) * (spec.ring1_diameter_mm / 2)`
- **ISSF edge gauging** (we have the data): `gauged_mm = max(0, center_distance_mm - spec.bullet_diameter_mm / 2)`
- `ring` = highest ring whose **outer radius** (`spec.ring_diameter_mm(r) / 2`) ≥ `gauged_mm`;
  miss → ring 0. `score = ring`.

Return ring + score in the response so Laravel persists them verbatim.

---

## 5. Claude integration spec

- **Location:** Python service (corrected image lives there). Official `anthropic` Python SDK.
- **Model:** `claude-opus-4-8` (env-configurable `AIMTRACK_VISION_MODEL`). Accuracy-first for the
  zero-touch bar; may drop to `claude-sonnet-4-6` later once the harness proves accuracy.
- **Params:** `thinking={"type":"adaptive"}`, `output_config={"effort":"high"}`. Stream not
  required (small output). Image as a base64 `image` content block (1000×1000 ≪ Opus 4.8's 2576px
  high-res limit; returned coords map 1:1 to pixels).
- **Structured output:** forced tool-use (`tool_choice={"type":"tool","name":"report_shots"}`) with
  `strict: true`. Schema:
  ```
  report_shots(
    shots: [{ x_px:int, y_px:int, confidence:number(0..1),
              kind:"hole"|"uncertain" }],
    orientation_note: string,     # e.g. "printed 10 at top"
    overall_confidence: number(0..1),
    count_matches_expected: boolean
  )
  ```
  Validated at the tool layer → no fragile text parsing. (Anthropic structured outputs do not
  support numeric min/max in-schema; clamp/validate client-side.)
- **Prompt strategy:** system text states the discipline, that the image is a perspective-corrected
  concentric target with the black centered, the expected shot count, and the explicit
  ignore-list (printed digits, ring lines, pasters, tears). Few-shot is unnecessary; Opus 4.8
  follows instructions literally — keep the prompt prescriptive, not aggressive.
- **Secrets:** `ANTHROPIC_API_KEY` via Python-service env only. Never logged. Photos already leave
  the server for the OpenAI coach context elsewhere; sending the corrected target to Anthropic is
  consistent with the existing data posture (document in the user privacy note).
- **Failure handling:** API error / timeout → endpoint returns `needs_review=true` with the
  Stage-2 CV candidates as best-effort shots (graceful degradation, never a hard failure that
  loses the user's photo). SDK retries (default) on 429/5xx.

---

## 6. Data model & Laravel changes

- **`sessions.target_type`** — new nullable string column; Filament `Select` of the 5 disciplines
  on the session form. Required before photo analysis (the job must send it).
- **`session_shots.metadata`** — store `kind`, `confidence`, `calibration_rms_mm`, model id,
  `original_x/y`. (`source` column already exists: `manual` / `photo_detected` / `photo_corrected`.)
- **Needs-review signal — dedicated tiny table `session_turn_analyses`** (DECIDED). One row per
  `(session_id, turn_index)` analysis run, `updateOrCreate` on re-analysis (mirrors the existing
  delete-then-reinsert idempotency). This is a *turn-level* fact and must survive a turn that
  detected **zero/garbage shots** — exactly the prime review case, where no `SessionShot` row exists
  to carry a metadata flag. Columns:
  ```
  session_turn_analyses
    id, session_id (fk), turn_index,
    needs_review (bool), overall_confidence (float),
    expected_shot_count (int), detected_count (int), count_matches_expected (bool),
    calibration_rms_mm (float, nullable), vision_model (string), analyzed_at (timestamp)
    unique(session_id, turn_index)
  ```
  Model `SessionTurnAnalysis` (matches `Session`/`SessionShot`/`SessionWeapon` naming);
  `hasMany` shots via `(session_id, turn_index)`; `Session hasMany turnAnalyses`. Non-blocking:
  shots are always saved regardless of `needs_review`. Per-**shot** facts (`confidence`, `kind`)
  stay in `SessionShot.metadata` — turn-level facts at turn level, shot-level facts at shot level.
- **`expected_shot_count`** — new per-turn input field at upload time, defaulting to a
  discipline-typical value; passed to the job and on to the pipeline.
- **`AnalyzeTurnPhotoJob`** —
  - call `POST /api/v2/analyze-target` with `file`, `target_type`, `expected_shot_count`;
  - persist Python-returned `ring`/`score`/coords verbatim;
  - **delete `calculateRing()` and `calculateScore()`** (discipline-wrong, replaced by Python);
  - set `needs_review` from the response; keep the existing delete-then-insert per (session, turn,
    `photo_detected`) idempotency.

---

## 7. Coordinate & scoring contract (single source of truth)

| Space | Definition |
|---|---|
| Canonical px | 1000×1000, center (500,500), ring-1 edge radius = `CANONICAL_RING1_RADIUS` |
| Target-relative | `x_norm = (x_px-500)/CANONICAL_RING1_RADIUS`; range ≈ [-1,1] at ring-1 edge |
| mm | `dist_mm = dist_norm * spec.ring1_diameter_mm/2` |
| Canvas (UI) | existing `0.5 + x_norm * 0.46` mapping, preserved for the shot board |

Scoring is **edge-gauged** using `spec.bullet_diameter_mm`. The current `[-1,1]` Python→Laravel
contract is preserved so the shot board renders unchanged; only the *source* of ring/score moves
to Python and becomes discipline-correct.

---

## 8. Confidence & zero-touch handling (decided)

- Normal case: shots auto-filled and final — **true zero-touch**.
- `needs_review = true` when `overall_confidence` below a tuned threshold **or**
  `count_matches_expected = false` **or** calibration confidence low. Shots are **still saved**
  (non-blocking); the turn shows a review badge the user may ignore. This costs nothing in the happy
  path and prevents silent wrong data on bad photos.

---

## 9. Validation harness (how we earn "trust it blindly")

A manually-run harness (not in CI; it makes real Claude calls):
- a small labelled set of **real** target photos per discipline with ground-truth shot positions;
- metrics: **count accuracy**, **ring-score accuracy** (% shots scored to the correct ring),
  **coordinate RMS (mm)**, false-positive rate (printed digits / pasters mis-detected);
- acceptance thresholds defined in the plan (e.g. ring-score accuracy ≥ X% before zero-touch is
  declared per discipline). Re-runnable as a regression guard.

The hardest case to nail in the harness: **shots in the black vs printed numbers** (25m pistol).

---

## 10. Testing strategy

- **Pest feature:** `AnalyzeTurnPhotoJob` with a mocked `/api/v2/analyze-target` response →
  asserts correct `SessionShot` rows, discipline-correct ring/score, `needs_review` propagation,
  idempotent re-run. Filament: `target_type` select + `expected_shot_count` field.
- **Python unit (pytest):** Stage-2 candidate recall on fixtures; Stage-4 reconciliation
  (snap-to-centroid, count enforcement); Stage-5 scoring against every `TargetSpec` (boundary
  cases at ring edges, edge-gauging with bullet radius). Claude call **mocked** in CI.
- **Validation harness (manual):** §9, real Claude calls, real photos.

---

## 11. Config, cost, privacy, errors

- **Config:** `ANTHROPIC_API_KEY`, `AIMTRACK_VISION_MODEL` (default `claude-opus-4-8`),
  `AIMTRACK_VISION_EFFORT` (default `high`) in the Python service env. `services.image_processor.url`
  already exists in Laravel.
- **Cost:** ~a few cents/photo on Opus 4.8; one call per turn upload. Acceptable; reducible via
  Sonnet later.
- **Latency:** async via the existing queue job (`timeout=120`); adaptive thinking + one image is
  well within budget.
- **Privacy:** corrected target image sent to Anthropic; documented in the user privacy note.
  Original photo retention policy unchanged (existing private disk).
- **Errors:** any pipeline failure degrades to CV-candidate best-effort + `needs_review`; the
  user's photo and turn are never lost.

---

## 12. Decisions log

1. Success bar: **fully automatic, zero-touch** (with a non-blocking review safety valve).
2. Vision provider: **cloud vision approved**; **Claude** chosen over the existing OpenAI path.
3. Approach: **Hybrid** (CV candidates + Claude discrimination + count constraint + CV precision),
   over pure-VLM and self-hosted YOLO.
4. Disciplines: the **5 single-target** types; multi-target A3 sheet deferred.
5. Shot count: **per-turn input field** (default discipline-typical).
6. Ambiguity: **flag for review, non-blocking**.
7. Model tier: **Opus 4.8 first**, env-configurable, tune to Sonnet later after harness proof.
8. `needs_review` storage: **dedicated `session_turn_analyses` tiny table** (turn-level); per-shot
   facts stay in `SessionShot.metadata`. Chosen because the flag must survive zero-shot/failed
   detections, where no shot row exists.

---

## 13. Open items for the plan

- Confirm `CANONICAL_RING1_RADIUS` value and `calibrate()` return shape (read during planning).
- Shot-board / Filament badge that surfaces `session_turn_analyses.needs_review` (table decided; UI
  wiring is plan-level detail).
- Default `expected_shot_count` per discipline.
- Acceptance thresholds per discipline for the validation harness.
- Pin `anthropic` Python SDK version + add to `python-service/requirements.txt`.
- Worktree freshness: ensure `feature/55` is rebased on current `main` before build
  (per repo worktree discipline).
