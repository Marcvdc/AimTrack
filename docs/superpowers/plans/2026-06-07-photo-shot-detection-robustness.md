# Foto→schoten robuustheid — Implementatieplan (Fase 1 + 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Maak foto→schoten betrouwbaar op echte plakker-rijke rozen (plakken-per-beurt) door (1) de detectie te focussen op verse donkere gaten en twijfelgevallen niet meer als schot te forceren, en (2) een vlotte correctie-UI (versleepbare markers + "Bevestigd"-knop) toe te voegen.

**Architecture:** Behoudt de v1-hybride (homografie → CV-kandidaten → Claude → reconcile → scoring). Fase 1 verscherpt de Claude-prompt + voegt een confidence-drempel in `reconcile` toe (python-service). Fase 2 voegt `moveShot`/`confirmTurnReview` toe aan de `SessionShotBoard` Livewire-component + sleep-interactie en een Bevestigd-knop in de blade.

**Tech Stack:** Python 3.11 / FastAPI / OpenCV (python-service); Laravel 12 / Filament 5 / Livewire 4 / Alpine.js / Pest (app).

**Spec:** `docs/superpowers/specs/2026-06-07-photo-shot-detection-robustness-design.md`. **Scope:** Fase 1 + 2. Fase 3 (handmatige kalibratie) en Fase 4 (validatie-harness op echte foto's) krijgen een eigen plan/activiteit.

---

## Conventies (één keer lezen)

- **Python-tests** (pytest), draai in de container:
  `cd /home/brandnetel/projects/aimtrack-55 && docker compose --env-file .env -f docker/compose.dev.yml exec -T python-service python -m pytest tests/<file>.py -v`
- **Laravel-tests** (Pest), op de host:
  `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=<naam>`
  Vóór committen: `vendor/bin/pint --dirty`.
- Models gebruiken de legacy `$casts`/`$fillable`-PROPERTY; relatie-methods zonder return-type hints. Enums in `app/Enums/` met TitleCase cases. `SessionShot.$fillable` bevat al `source`. `source`-waarden: `manual` (default), `photo_detected`, `photo_corrected`.
- Coördinaten-conventie: `x_normalized`/`y_normalized` ∈ [0,1], centrum (0.5,0.5); de blade rendert markers op `(x*size, y*size)` en mapt canvas-klik → `(clientX-rect.left)/rect.width`.

---

## File structure

**Python (Fase 1)**
- `python-service/app/settings.py` *(modify)* — `min_shot_confidence`.
- `python-service/app/detection/reconcile.py` *(modify)* — confidence-drempel vóór de count-cap.
- `python-service/app/vision/claude_detector.py` *(modify)* — `_system_prompt` op verse-gaten/plakkers.
- Tests: `python-service/tests/test_reconcile.py`, `test_settings.py`, `test_claude_detector.py`.

**Laravel (Fase 2)**
- `app/Services/Sessions/SessionShotService.php` *(modify)* — `moveShot()`.
- `app/Livewire/SessionShotBoard.php` *(modify)* — `moveShot()`, `confirmTurnReview()`.
- `resources/views/livewire/session-shot-board.blade.php` *(modify)* — sleep-interactie + Bevestigd-knop.
- Tests: `tests/Feature/SessionShotMoveTest.php`, `tests/Feature/SessionShotBoardConfirmReviewTest.php`.

---

## Fase 1 — Detectie scherper

### Task 1: Confidence-drempel in settings

**Files:**
- Modify: `python-service/app/settings.py`
- Test: `python-service/tests/test_settings.py`

- [ ] **Step 1: Add the failing assertion** — append to the `test_defaults` method in `python-service/tests/test_settings.py`, right after the existing `assert s.settings.cal_rms_review_mm == 20.0` line:

```python
        assert s.settings.min_shot_confidence == 0.4
```

And append to `test_env_overrides`, after its last assertion:

```python
        monkeypatch.setenv("AIMTRACK_MIN_SHOT_CONFIDENCE", "0.55")
        importlib.reload(s)
        assert s.settings.min_shot_confidence == 0.55
```

- [ ] **Step 2: Run it, confirm FAIL** — `AttributeError: 'Settings' object has no attribute 'min_shot_confidence'`.

Run: `cd /home/brandnetel/projects/aimtrack-55 && docker compose --env-file .env -f docker/compose.dev.yml exec -T python-service python -m pytest tests/test_settings.py -v`

- [ ] **Step 3: Implement** — in `python-service/app/settings.py`, add this line inside `Settings.__init__`, right after the `self.cal_rms_review_mm = ...` line:

```python
        self.min_shot_confidence: float = float(os.getenv("AIMTRACK_MIN_SHOT_CONFIDENCE", "0.4"))
```

- [ ] **Step 4: Run, confirm PASS.**

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/settings.py python-service/tests/test_settings.py
git commit -m "feat(55): min_shot_confidence setting"
```

### Task 2: Reconcile dropt twijfelgevallen i.p.v. forceren

**Files:**
- Modify: `python-service/app/detection/reconcile.py`
- Test: `python-service/tests/test_reconcile.py`

- [ ] **Step 1: Write the failing test** — append to `python-service/tests/test_reconcile.py` (inside `class TestReconcile`):

```python
    def test_drops_shots_below_confidence_floor(self):
        # confidence 0.2 < default floor 0.4 -> dropped, even though count would allow it
        claude = [
            {"x_px": 500, "y_px": 500, "confidence": 0.9, "kind": "hole"},
            {"x_px": 600, "y_px": 500, "confidence": 0.2, "kind": "uncertain"},
        ]
        holes = reconcile(claude, [], KKP_25M, expected_shot_count=2)
        assert len(holes) == 1
        assert holes[0].confidence == 0.9

    def test_does_not_pad_to_expected_with_low_confidence(self):
        # only one confident hole; do NOT force a second one to reach expected=2
        claude = [
            {"x_px": 500, "y_px": 500, "confidence": 0.8, "kind": "hole"},
            {"x_px": 600, "y_px": 500, "confidence": 0.1, "kind": "uncertain"},
            {"x_px": 700, "y_px": 500, "confidence": 0.1, "kind": "uncertain"},
        ]
        holes = reconcile(claude, [], KKP_25M, expected_shot_count=3)
        assert len(holes) == 1
```

- [ ] **Step 2: Run it, confirm FAIL** (currently keeps the low-confidence holes).

Run: `cd /home/brandnetel/projects/aimtrack-55 && docker compose --env-file .env -f docker/compose.dev.yml exec -T python-service python -m pytest tests/test_reconcile.py -v`

- [ ] **Step 3: Implement** — in `python-service/app/detection/reconcile.py`:

(a) Add the settings import near the top, after `from app.config import TargetSpec`:

```python
from app.settings import settings
```

(b) In `reconcile()`, replace the final count-cap block:

```python
    if expected_shot_count is not None and len(holes) > expected_shot_count:
        holes.sort(key=lambda h: h.confidence, reverse=True)
        holes = holes[:expected_shot_count]
    return holes
```

with:

```python
    # Drop low-confidence detections rather than placing dubious markers (e.g. a
    # paster Claude wasn't sure about). Better to report fewer, confident holes and
    # flag needs_review than to pad up to the expected count with junk.
    holes = [h for h in holes if h.confidence >= settings.min_shot_confidence]

    if expected_shot_count is not None and len(holes) > expected_shot_count:
        holes.sort(key=lambda h: h.confidence, reverse=True)
        holes = holes[:expected_shot_count]
    return holes
```

- [ ] **Step 4: Run, confirm PASS** (run the full reconcile file — the existing snap/cap tests must stay green; note: the existing tests use confidence ≥ 0.5, above the 0.4 floor, so they remain unaffected).

- [ ] **Step 5: Commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/detection/reconcile.py python-service/tests/test_reconcile.py
git commit -m "feat(55): reconcile drops sub-floor detections instead of padding to count"
```

### Task 3: Claude-prompt op verse gaten / negeer plakkers

**Files:**
- Modify: `python-service/app/vision/claude_detector.py`
- Test: `python-service/tests/test_claude_detector.py`

- [ ] **Step 1: Write the failing test** — append to `python-service/tests/test_claude_detector.py`:

```python
from app.vision.claude_detector import _system_prompt


class TestSystemPrompt:
    def test_targets_fresh_holes_and_ignores_pasters(self):
        prompt = _system_prompt(KKP_25M, expected_shot_count=5).lower()
        assert "plakker" in prompt          # tells the model pasters are not shots
        assert "vers" in prompt             # only fresh holes
        assert "ringcijfers" in prompt      # ignore printed numbers
        assert "5 schoten" in prompt        # count guidance present
```

- [ ] **Step 2: Run it, confirm FAIL** (current prompt lacks "plakker"/"vers").

Run: `cd /home/brandnetel/projects/aimtrack-55 && docker compose --env-file .env -f docker/compose.dev.yml exec -T python-service python -m pytest tests/test_claude_detector.py -v`

- [ ] **Step 3: Implement** — in `python-service/app/vision/claude_detector.py`, replace the whole `_system_prompt` function with:

```python
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
```

- [ ] **Step 4: Run, confirm PASS** (and the existing `TestDetectHoles` tests stay green — they monkeypatch `_call_claude`, so the prompt text doesn't affect them).

- [ ] **Step 5: Run the full python suite + commit**

Run: `cd /home/brandnetel/projects/aimtrack-55 && docker compose --env-file .env -f docker/compose.dev.yml exec -T python-service python -m pytest -q` → all green.

```bash
cd /home/brandnetel/projects/aimtrack-55
git add python-service/app/vision/claude_detector.py python-service/tests/test_claude_detector.py
git commit -m "feat(55): Claude prompt targets fresh holes, ignores pasters/numbers"
```

---

## Fase 2 — Correctie-UI

### Task 4: SessionShotService::moveShot

**Files:**
- Modify: `app/Services/Sessions/SessionShotService.php`
- Test: `tests/Feature/SessionShotMoveTest.php`

- [ ] **Step 1: Write the failing test** — create `tests/Feature/SessionShotMoveTest.php`:

```php
<?php

use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Services\Sessions\SessionShotService;

test('moveShot updates position, re-scores, and marks the shot as corrected', function () {
    $session = Session::factory()->create(['user_id' => User::factory()]);
    $shot = SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'shot_index' => 1,
        'x_normalized' => 0.9,
        'y_normalized' => 0.9,
        'ring' => 1,
        'score' => 1,
        'source' => 'photo_detected',
    ]);

    $service = app(SessionShotService::class);
    $service->moveShot($shot, 0.5, 0.5); // exact centre

    $fresh = $shot->refresh();
    expect((float) $fresh->x_normalized)->toBe(0.5);
    expect((float) $fresh->y_normalized)->toBe(0.5);
    expect($fresh->ring)->toBe(10);          // centre scores 10 via ShotScoringService
    expect($fresh->score)->toBe(10);
    expect($fresh->source)->toBe('photo_corrected');
    expect((float) $fresh->distance_from_center)->toBe(0.0);
});

test('moveShot clamps out-of-range coordinates to [0,1]', function () {
    $session = Session::factory()->create(['user_id' => User::factory()]);
    $shot = SessionShot::factory()->create(['session_id' => $session->id, 'turn_index' => 0, 'shot_index' => 1]);

    app(SessionShotService::class)->moveShot($shot, 1.4, -0.3);

    $fresh = $shot->refresh();
    expect((float) $fresh->x_normalized)->toBe(1.0);
    expect((float) $fresh->y_normalized)->toBe(0.0);
});
```

- [ ] **Step 2: Run it, confirm FAIL** — `Method ... moveShot does not exist`.

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionShotMoveTest`

- [ ] **Step 3: Implement** — in `app/Services/Sessions/SessionShotService.php`, add this method right after `recordShot()` (before `deleteShot()`):

```php
    public function moveShot(SessionShot $shot, float $xNormalized, float $yNormalized): SessionShot
    {
        $x = $this->clamp($xNormalized);
        $y = $this->clamp($yNormalized);

        $scoreData = $this->scoringService->scoreShot($x, $y);

        $shot->update([
            'x_normalized' => $x,
            'y_normalized' => $y,
            'distance_from_center' => $scoreData['distance_from_center'],
            'ring' => $scoreData['ring'],
            'score' => $scoreData['score'],
            'source' => 'photo_corrected',
        ]);

        return $shot;
    }
```

- [ ] **Step 4: Run, confirm PASS** (2 passed).

- [ ] **Step 5: pint + commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
vendor/bin/pint --dirty
git add app/Services/Sessions/SessionShotService.php tests/Feature/SessionShotMoveTest.php
git commit -m "feat(55): SessionShotService::moveShot (reposition + re-score + photo_corrected)"
```

### Task 5: SessionShotBoard::moveShot

**Files:**
- Modify: `app/Livewire/SessionShotBoard.php`
- Test: `tests/Feature/SessionShotMoveTest.php` (append)

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/SessionShotMoveTest.php`:

```php
test('SessionShotBoard moveShot moves a shot belonging to the session', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);
    $shot = SessionShot::factory()->create([
        'session_id' => $session->id, 'turn_index' => 0, 'shot_index' => 1,
        'x_normalized' => 0.9, 'y_normalized' => 0.9, 'source' => 'photo_detected',
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->call('moveShot', $shot->id, 0.5, 0.5);

    $fresh = $shot->refresh();
    expect((float) $fresh->x_normalized)->toBe(0.5);
    expect($fresh->ring)->toBe(10);
    expect($fresh->source)->toBe('photo_corrected');
});

test('SessionShotBoard moveShot ignores a shot from another session', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);
    $otherShot = SessionShot::factory()->create([
        'session_id' => Session::factory()->create()->id, 'turn_index' => 0, 'shot_index' => 1,
        'x_normalized' => 0.9, 'y_normalized' => 0.9,
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->call('moveShot', $otherShot->id, 0.5, 0.5);

    expect((float) $otherShot->refresh()->x_normalized)->toBe(0.9); // unchanged
});
```

- [ ] **Step 2: Run it, confirm FAIL** — `Method moveShot does not exist`.

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionShotMoveTest`

- [ ] **Step 3: Implement** — in `app/Livewire/SessionShotBoard.php`, add this method right after the existing `recordShot()` method:

```php
    public function moveShot(int $shotId, float $xNormalized, float $yNormalized): void
    {
        if (! $this->canEdit) {
            return;
        }

        $shot = $this->session->shots()->whereKey($shotId)->first();

        if (! $shot instanceof SessionShot) {
            return;
        }

        $this->shotService->moveShot($shot, $xNormalized, $yNormalized);

        $this->refreshData();
        $this->resetTable();
    }
```

- [ ] **Step 4: Run, confirm PASS** (4 passed in the file now).

- [ ] **Step 5: pint + commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
vendor/bin/pint --dirty
git add app/Livewire/SessionShotBoard.php tests/Feature/SessionShotMoveTest.php
git commit -m "feat(55): SessionShotBoard::moveShot (scoped to session, re-render)"
```

### Task 6: SessionShotBoard::confirmTurnReview

**Files:**
- Modify: `app/Livewire/SessionShotBoard.php`
- Test: `tests/Feature/SessionShotBoardConfirmReviewTest.php`

- [ ] **Step 1: Write the failing test** — create `tests/Feature/SessionShotBoardConfirmReviewTest.php`:

```php
<?php

use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Models\User;

test('confirmTurnReview clears needs_review for the turn', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    SessionTurnAnalysis::create([
        'session_id' => $session->id, 'turn_index' => 0,
        'needs_review' => true, 'review_reason' => 'Lage zekerheid (40%).',
        'detected_count' => 5,
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->call('confirmTurnReview', 0)
        ->assertNotified();

    expect(
        SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 0)->first()->needs_review
    )->toBeFalse();
});
```

- [ ] **Step 2: Run it, confirm FAIL** — `Method confirmTurnReview does not exist`.

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionShotBoardConfirmReviewTest`

- [ ] **Step 3: Implement** — in `app/Livewire/SessionShotBoard.php`, add this method right after `moveShot()`:

```php
    public function confirmTurnReview(int $turnIndex): void
    {
        if (! $this->canEdit) {
            return;
        }

        SessionTurnAnalysis::where('session_id', $this->session->id)
            ->where('turn_index', $turnIndex)
            ->update(['needs_review' => false]);

        Notification::make()
            ->title('Beurt afgetekend.')
            ->success()
            ->send();

        $this->refreshData();
    }
```

(`SessionTurnAnalysis` and `Notification` are already imported in this file.)

- [ ] **Step 4: Run, confirm PASS** (1 passed).

- [ ] **Step 5: pint + commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
vendor/bin/pint --dirty
git add app/Livewire/SessionShotBoard.php tests/Feature/SessionShotBoardConfirmReviewTest.php
git commit -m "feat(55): confirmTurnReview clears the needs_review flag"
```

### Task 7: Bevestigd-knop in de blade

**Files:**
- Modify: `resources/views/livewire/session-shot-board.blade.php`
- Test: `tests/Feature/SessionShotBoardConfirmReviewTest.php` (append)

- [ ] **Step 1: Write the failing test** — append to `tests/Feature/SessionShotBoardConfirmReviewTest.php`:

```php
test('shot board shows a Bevestigd button when the turn needs review', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    SessionTurnAnalysis::create([
        'session_id' => $session->id, 'turn_index' => 0, 'needs_review' => true, 'detected_count' => 5,
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->assertSee('Bevestigd');
});
```

- [ ] **Step 2: Run it, confirm FAIL** (no "Bevestigd" in the rendered HTML yet).

Run: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact --filter=SessionShotBoardConfirmReviewTest`

- [ ] **Step 3: Implement** — in `resources/views/livewire/session-shot-board.blade.php`, find the needs-review block (the `<div class="space-y-1">` that contains the warning badge "Controleren — foto-analyse onzeker"). Inside that `<div class="space-y-1">`, AFTER the `@if (filled($turnReview[...]['review_reason'] ?? null)) ... @endif` block and BEFORE the closing `</div>`, add a Bevestigd button:

```blade
                        @if ($canEdit)
                            <div>
                                <x-filament::button
                                    size="sm"
                                    color="success"
                                    icon="heroicon-m-check"
                                    wire:click="confirmTurnReview({{ $currentTurnIndex }})"
                                >
                                    Bevestigd
                                </x-filament::button>
                            </div>
                        @endif
```

- [ ] **Step 4: Run, confirm PASS** (2 passed in the file).

- [ ] **Step 5: commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add resources/views/livewire/session-shot-board.blade.php tests/Feature/SessionShotBoardConfirmReviewTest.php
git commit -m "feat(55): Bevestigd button to clear the review badge"
```

### Task 8: Versleepbare markers (Alpine canvas)

**Files:**
- Modify: `resources/views/livewire/session-shot-board.blade.php`

> Dit is canvas-JS (Alpine); niet unit-testbaar. De Livewire-kant (`moveShot`) is in Task 5 getest. Verificatie hier is **handmatig** (Step 4).

- [ ] **Step 1: Add the pointer-drag handlers to the Alpine component.** In the `targetBoard` Alpine object, add these three methods right after the existing `handleClick(event) { ... }` method (use `this.recordShot`/`this.$wire` consistent with the surrounding code; note the closure `recordShot` is in scope, and `$wire` is available as `this.$wire`):

```javascript
                onPointerDown(event) {
                    if (! this.canEdit) {
                        return;
                    }
                    const marker = this.getMarkerAtPosition(event);
                    this.drag = marker
                        ? { id: marker.id, moved: false }
                        : null;
                },
                onPointerMove(event) {
                    if (! this.drag) {
                        return;
                    }
                    const rect = this.$refs.board.getBoundingClientRect();
                    const x = Math.min(Math.max((event.clientX - rect.left) / rect.width, 0), 1);
                    const y = Math.min(Math.max((event.clientY - rect.top) / rect.height, 0), 1);
                    // live visual feedback: move the dragged marker locally and redraw
                    const m = this.currentMarkers.find((mk) => mk.id === this.drag.id);
                    if (m) {
                        m.x = x;
                        m.y = y;
                        this.drag.moved = true;
                        this.scheduleDraw();
                    }
                },
                onPointerUp(event) {
                    if (! this.drag) {
                        return;
                    }
                    const drag = this.drag;
                    this.drag = null;
                    if (! drag.moved) {
                        // a tap on a marker (no drag) -> keep existing delete behaviour
                        const marker = this.getMarkerAtPosition(event);
                        if (marker) {
                            this.startLongPress(marker);
                        }
                        return;
                    }
                    const rect = this.$refs.board.getBoundingClientRect();
                    const x = Math.min(Math.max((event.clientX - rect.left) / rect.width, 0), 1);
                    const y = Math.min(Math.max((event.clientY - rect.top) / rect.height, 0), 1);
                    this.$wire.moveShot(drag.id, x, y);
                },
```

- [ ] **Step 2: Initialise the `drag` state.** In the same Alpine object, find the state declarations near the top (where `renderMarkers`, `currentMarkers` etc. are declared) and add:

```javascript
                drag: null,
```

- [ ] **Step 3: Wire the canvas events.** Find the canvas element (the one with `@click="handleCanvasClick($event)"`, near the `x-ref="canvas"`). Replace its `@click="handleCanvasClick($event)"` attribute with pointer handlers, keeping the right-click delete:

```blade
                    @pointerdown="onPointerDown($event)"
                    @pointermove="onPointerMove($event)"
                    @pointerup="onPointerUp($event)"
                    @click="handleCanvasClick($event)"
                    @contextmenu.prevent="handleCanvasRightClick($event)"
```

> Behaviour: empty-canvas click still adds a shot (via `handleCanvasClick` → `handleClick`); a marker drag fires `moveShot`; a marker tap without movement keeps the existing long-press delete. The `@click` still fires after a non-drag pointer sequence, but `onPointerUp` only acts when `drag.moved` is true, so there's no double-handling for plain clicks (a plain click on empty canvas sets `drag=null` in `onPointerDown`, so `onPointerUp` returns early and `handleClick` adds the shot).

- [ ] **Step 4: Manual verification.** Rebuild assets and check in the browser:

```bash
cd /home/brandnetel/projects/aimtrack-55 && npm run build
```
Then on `localhost:19082`, open a session shot board with detected shots and verify: (a) dragging a marker repositions it and the ring/score updates after release; (b) clicking empty space still adds a shot; (c) right-click still opens the delete modal. Confirm `php artisan test --compact --filter=SessionShotMoveTest` is still green (the `moveShot` backend the drag calls).

- [ ] **Step 5: commit**

```bash
cd /home/brandnetel/projects/aimtrack-55
git add resources/views/livewire/session-shot-board.blade.php
git commit -m "feat(55): drag-to-move markers on the shot board canvas"
```

---

## Final verification

- [ ] Python full suite: `cd /home/brandnetel/projects/aimtrack-55/.. ` → `docker compose --env-file .env -f docker/compose.dev.yml exec -T python-service python -m pytest -q` → all green.
- [ ] Laravel full suite: `cd /home/brandnetel/projects/aimtrack-55 && php artisan test --compact` → only the known pre-existing unrelated failures, if any; all new tests green.
- [ ] `vendor/bin/pint --test` → clean.
- [ ] Manual: drag + Bevestigd flow works in the browser; re-uploading a clean (pasted) photo with the key set now drops dubious detections instead of placing pasters.

---

## Self-review (tegen de spec)

**Spec-dekking:**
- Fase 1 detectie-prompt (verse gaten, negeer plakkers) → Task 3. ✅
- Fase 1 confidence-drempel, geen forcering → Tasks 1+2. ✅
- Fase 2 versleepbare markers → Task 8; verplaatsen herberekent ring/score + `photo_corrected` → Tasks 4+5. ✅
- Fase 2 Bevestigd-knop wist `needs_review` → Tasks 6+7. ✅
- Fase 3 (handmatige kalibratie) + Fase 4 (validatie) → bewust uitgesteld naar eigen plan (zie scope). ✅

**Placeholders:** geen — alle code voluit; de enige niet-geautomatiseerde stap (Task 8 canvas-JS) heeft een expliciete handmatige verificatie, want canvas-drag is niet unit-testbaar.

**Type/naam-consistentie:** `moveShot(SessionShot, float, float)` (service) ↔ `moveShot(int, float, float)` (Livewire) ↔ `$wire.moveShot(id, x, y)` (Alpine); `confirmTurnReview(int)` ↔ `wire:click="confirmTurnReview(...)"`; `min_shot_confidence` (settings) ↔ `reconcile`. Consistent. ✅

**Bewuste deviatie:** in de VisionError/CV-fallback bouwt de pipeline holes met confidence 0.0 buiten `reconcile` om, dus de drempel raakt die niet — dat is correct: zonder Claude blijft de ruwe CV-voorzet staan + `needs_review` (geen key). De drempel verscherpt alleen de mét-Claude-uitkomst.
