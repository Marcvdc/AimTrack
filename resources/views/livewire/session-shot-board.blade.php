<div class="mt-6 space-y-6" x-data="{ deleteConfirmationMessage: '' }"
    x-on:shot-delete-requested="deleteConfirmationMessage = $event.detail.message">
    @php
        $legendEntries = collect($turnLegend ?? [])->map(fn ($entry) => [
            'label' => $entry['label'] ?? 'Beurt',
            'color' => $entry['color'] ?? '#3b82f6',
        ])->values()->all();

    @endphp

    <!-- Delete Confirmation Modal -->
    <x-filament::modal id="delete-shot-modal">
        <x-slot name="heading">
            Schot verwijderen?
        </x-slot>
        
        <div>
            <p>Weet je zeker dat je dit schot wilt verwijderen?</p>
            <p class="text-sm text-gray-600 dark:text-gray-400" x-text="deleteConfirmationMessage"></p>
        </div>
        
        <x-slot name="footer">
            <x-filament::button
                type="button"
                color="danger"
                wire:click="confirmDeleteShot"
            >
                Verwijderen
            </x-filament::button>
            <x-filament::button
                type="button"
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'delete-shot-modal' })"
            >
                Annuleren
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <!-- Controlebalk: beurt-selectie · nieuwe beurt · ringnummers-toggle -->
    <div class="mb-5" style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px; padding: 16px; background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-2xl);">
        <div style="display: flex; flex-direction: column; gap: 6px;">
            <span class="at-label">Beurt</span>
            <select wire:model.live="currentTurnIndex"
                style="background: var(--at-bg); border: 1px solid var(--at-line); color: var(--at-text); border-radius: var(--at-r-lg); padding: 8px 14px; font-size: 13px; font-family: var(--at-font-mono);">
                <option value="{{ \App\Livewire\SessionShotBoard::ALL_TURNS_VALUE }}">Alle beurten</option>
                @foreach ($turnOptions as $turn)
                    <option value="{{ $turn }}">Beurt {{ $turn + 1 }}</option>
                @endforeach
            </select>
        </div>

        @if ($canEdit)
            <button type="button" wire:click="addTurn"
                style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 44px; padding: 9px 14px; border-radius: var(--at-r-lg); background: var(--at-accent); color: var(--at-cta-text); font-weight: 600; font-size: 13px; border: none; cursor: pointer;">
                <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                Nieuwe beurt
            </button>
        @endif

        <button type="button" wire:click="toggleRings" role="switch" aria-checked="{{ $showRings ? 'true' : 'false' }}" aria-label="Toon ringnummers op de roos"
            style="display: inline-flex; align-items: center; gap: 10px; margin-left: auto; min-height: 44px; padding: 8px 12px; border-radius: var(--at-r-lg); background: var(--at-panel-2); border: 1px solid var(--at-line); color: var(--at-text); font-size: 13px; cursor: pointer;">
            <span style="width: 30px; height: 18px; border-radius: 999px; background: {{ $showRings ? 'var(--at-accent)' : 'var(--at-line)' }}; position: relative; flex-shrink: 0; transition: background .15s ease;">
                <span style="position: absolute; top: 2px; left: {{ $showRings ? '14px' : '2px' }}; width: 14px; height: 14px; border-radius: 50%; background: var(--at-cta-text); transition: left .15s ease;"></span>
            </span>
            Ringnummers
        </button>
    </div>

    <div style="display: flex; flex-direction: column; gap: 20px; padding: 20px; background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-2xl);">
        <div
            x-data="targetBoard({
                recordShot: (x, y) => $wire.recordShot(x, y),
                canEdit: @js($canEdit),
                rawMarkers: @entangle('markers').live,
                turns: @entangle('turnOptions').live,
                currentTurn: @entangle('currentTurnIndex').live,
                turnLegend: @entangle('turnLegend').live,
                showRings: @entangle('showRings').live,
                allTurnsValue: @js(\App\Livewire\SessionShotBoard::ALL_TURNS_VALUE),
            })"
            class="w-full flex flex-col lg:flex-row gap-6"
        >
            <!-- Interactieve Roos: tik=schot, rechtsklik=verwijderen -->
            <div class="w-full max-w-xl space-y-3">
                <div
                    class="relative w-full aspect-square overflow-hidden"
                    style="border-radius: var(--at-r-2xl); border: 1px solid var(--at-line); box-shadow: 0 18px 44px -26px rgba(0,0,0,0.85);"
                    x-ref="board"
                    wire:ignore
                >
                    <canvas x-ref="canvas" class="absolute inset-0 w-full h-full" style="cursor: crosshair; touch-action: manipulation;"
                    @pointerdown="handlePointerDown($event)"
                    @pointerup="handlePointerUp($event)"
                    @pointercancel="cancelPointer()"
                    @contextmenu.prevent="handleCanvasRightClick($event)"
                ></canvas>
                </div>
                <p style="font-size: 11px; color: var(--at-muted); font-family: var(--at-font-mono); letter-spacing: 0.04em;">
                    @if ($canEdit)
                        TIK = SCHOT · RECHTSKLIK OP EEN MARKER = VERWIJDEREN · OF VIA DE TABEL
                    @else
                        ALLEEN-LEZEN WEERGAVE
                    @endif
                </p>
            </div>

            <!-- Doelgebied & Schoten -->
            <div class="flex-1 space-y-3" style="min-width: 0;">
                <div class="at-label">Doelgebied &amp; schoten</div>
                <p style="font-size: 12px; color: var(--at-muted); margin: 0;">
                    Beheer beurten en bekijk schotinformatie. Gebruik de filters om specifieke beurten te tonen.
                </p>
                <div>
                    {{ $this->table }}
                </div>
            </div>
        </div>
    </div>
</div>

@pushOnce('scripts')
<script>
    (() => {
        document.addEventListener('livewire:init', () => {
            const componentName = 'session-shot-board';
            Livewire.hook('message.sent', (message, component) => {
                if (component.fingerprint?.name !== componentName) {
                    return;
                }

                component.__aimtrackScrollTop = window.scrollY;
            });

            Livewire.hook('message.processed', (message, component) => {
                if (component.fingerprint?.name !== componentName) {
                    return;
                }

                if (typeof component.__aimtrackScrollTop === 'number') {
                    window.scrollTo({ top: component.__aimtrackScrollTop });
                }
            });
        });

        const TARGET_RADIUS_RATIO = 0.46;
        // Een aanraking die verder dan dit (in pixels) beweegt tussen neerzetten en
        // loslaten is een veeg of scroll, geen tik, en registreert geen schot.
        const TAP_MAX_MOVEMENT = 10;

        const registerTargetBoard = () => {
            window.targetBoard = ({ recordShot, canEdit, rawMarkers, turns, currentTurn, turnLegend, showRings, allTurnsValue }) => ({
                rawMarkers,
                renderMarkers: [],
                currentMarkers: [],
                turns: Array.isArray(turns) ? [...turns] : [],
                currentTurn: Number(currentTurn ?? 0),
                turnLegend: Array.isArray(turnLegend) ? [...turnLegend] : [],
                allTurnsValue: allTurnsValue ?? -1,
                showRings: showRings ?? false,
                canEdit,
                pointerStart: null,
                init() {
                    this.scheduleDraw();
                    this.onMarkersUpdated(this.rawMarkers);
                    this.$watch('rawMarkers', this.onMarkersUpdated.bind(this));
                    this.$watch('turns', (value) => {
                        this.turns = Array.isArray(value) ? [...value] : [];
                    });
                    this.$watch('turnLegend', (value) => {
                        this.turnLegend = Array.isArray(value) ? [...value] : [];
                    });
                    this.$watch('showRings', (value) => {
                        this.showRings = Boolean(value);
                        this.scheduleDraw();
                    });
                    this.$watch('currentTurn', (value) => {
                        this.currentTurn = this.normalizeTurnValue(value);
                        this.updateCurrentMarkers();
                        this.scheduleDraw();
                    });
                    this.$watch('$wire.currentTurnIndex', (value) => {
                        const normalizedValue = this.normalizeTurnValue(value);

                        if (this.currentTurn === normalizedValue) {
                            return;
                        }

                        this.currentTurn = normalizedValue;
                        this.updateCurrentMarkers();
                        this.scheduleDraw();
                    });
                    window.addEventListener('resize', this.scheduleDraw.bind(this));
                    this.updateCurrentMarkers();
                },
                normalizeTurnValue(value) {
                    const numericValue = Number(value ?? 0);

                    return Number.isNaN(numericValue) ? 0 : numericValue;
                },
                scheduleDraw() {
                    requestAnimationFrame(() => this.drawTarget());
                },
                drawTarget() {
                    const canvas = this.$refs.canvas;
                    const board = this.$refs.board;

                    if (! canvas || ! board) {
                        return;
                    }

                    const ctx = canvas.getContext('2d');
                    const size = canvas.width = canvas.height = board.offsetWidth || 400;

                    if (! ctx) {
                        return;
                    }

                    if (size === 0) {
                        requestAnimationFrame(() => this.drawTarget());

                        return;
                    }

                    ctx.clearRect(0, 0, size, size);
                    ctx.fillStyle = '#020617';
                    ctx.fillRect(0, 0, size, size);
                    const center = size / 2;
                    const outerRadius = size * TARGET_RADIUS_RATIO;

                    const drawRing = (radius, color) => {
                        ctx.beginPath();
                        ctx.arc(center, center, radius, 0, Math.PI * 2);
                        ctx.fillStyle = color;
                        ctx.fill();
                    };

                    const rings = [
                        { label: 1, color: '#0b1220' },
                        { label: 2, color: '#111827' },
                        { label: 3, color: '#1c2434' },
                        { label: 4, color: '#253041' },
                        { label: 5, color: '#2f3c4f' },
                        { label: 6, color: '#3a485c' },
                        { label: 7, color: '#4a576d' },
                        { label: 8, color: '#64748b' },
                        { label: 9, color: '#94a3b8' },
                        { label: 10, color: '#f8fafc' },
                    ];

                    const ringCount = rings.length;
                    const ringThickness = outerRadius / ringCount;

                    rings.forEach((ring, index) => {
                        const radius = outerRadius - index * ringThickness;
                        drawRing(radius, ring.color);

                        ctx.lineWidth = Math.max(1, size * 0.0015);
                        ctx.strokeStyle = '#94a3b8';
                        ctx.beginPath();
                        ctx.arc(center, center, radius, 0, Math.PI * 2);
                        ctx.stroke();
                    });

                    if (this.showRings) {
                        ctx.fillStyle = '#f8fafc';
                        ctx.font = `${Math.floor(size * 0.034)}px 'Space Grotesk', sans-serif`;
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';

                        rings.forEach((ring, index) => {
                            const radius = outerRadius - (index + 0.5) * ringThickness;
                            const angles = [0, Math.PI / 2, Math.PI, (3 * Math.PI) / 2];
                            angles.forEach((angle) => {
                                const x = center + Math.cos(angle) * radius;
                                const y = center + Math.sin(angle) * radius;
                                ctx.fillText(ring.label, x, y);
                            });
                        });
                    }

                    ctx.lineWidth = size * 0.004;
                    ctx.strokeStyle = '#0f172a';
                    ctx.beginPath();
                    ctx.rect(size * 0.02, size * 0.02, size * 0.96, size * 0.96);
                    ctx.stroke();

                    this.drawMarkers(ctx, size);
                },
                markerRadius(size) {
                    return Math.max(4, size * 0.012);
                },
                drawMarkers(ctx, size) {
                    if (! Array.isArray(this.currentMarkers) || this.currentMarkers.length === 0) {
                        return;
                    }

                    this.currentMarkers.forEach((marker) => {
                        const x = (marker.x ?? 0.5) * size;
                        const y = (marker.y ?? 0.5) * size;
                        const radius = this.markerRadius(size);

                        ctx.save();
                        ctx.beginPath();
                        ctx.arc(x, y, radius, 0, Math.PI * 2);
                        ctx.fillStyle = marker.color ?? 'rgba(59,130,246,0.9)';
                        ctx.fill();
                        ctx.lineWidth = Math.max(2, size * 0.004);
                        ctx.strokeStyle = 'rgba(255,255,255,0.9)';
                        ctx.stroke();
                        ctx.restore();
                    });
                },
                onMarkersUpdated(value) {
                    if (! Array.isArray(value)) {
                        return;
                    }

                    this.renderMarkers = value.map((marker) => marker ?? null).filter(Boolean);
                    this.updateCurrentMarkers();
                    this.scheduleDraw();
                },
                updateCurrentMarkers() {
                    const currentTurn = this.normalizeTurnValue(this.currentTurn);

                    this.currentMarkers = this.renderMarkers.filter((marker) => {
                        if (currentTurn === this.allTurnsValue) {
                            return true;
                        }

                        const markerTurn = Number(marker.turn_index ?? marker.turnIndex ?? marker.turn ?? null);

                        return ! Number.isNaN(markerTurn) && markerTurn === currentTurn;
                    });
                },
                /*
                 * Het dichtstbijzijnde schot onder de aanwijzer, voor verwijderen via
                 * rechtsklik. De trefzone is de getekende markerstraal plus een kleine
                 * marge en schaalt mee met het bord. Een tik plaatst altijd een schot en
                 * gebruikt dit dus niet.
                 */
                getMarkerAtPosition(event) {
                    const rect = this.$refs.canvas.getBoundingClientRect();
                    const x = event.clientX - rect.left;
                    const y = event.clientY - rect.top;
                    const hitRadius = this.markerRadius(rect.width) + 4;
                    let nearest = null;
                    let nearestDistance = Infinity;

                    for (const marker of this.currentMarkers) {
                        const distance = Math.hypot(
                            x - (marker.x ?? 0.5) * rect.width,
                            y - (marker.y ?? 0.5) * rect.height,
                        );

                        if (distance <= hitRadius && distance < nearestDistance) {
                            nearest = marker;
                            nearestDistance = distance;
                        }
                    }

                    return nearest;
                },
                handleCanvasRightClick(event) {
                    this.pointerStart = null;

                    const isMouseRightButton = (event.pointerType ?? 'mouse') === 'mouse' && event.button === 2;

                    if (! this.canEdit || ! isMouseRightButton) {
                        return;
                    }

                    const marker = this.getMarkerAtPosition(event);

                    if (! marker) {
                        return;
                    }

                    this.$dispatch('shot-delete-requested', {
                        message: `Schot uit ${marker.turn_label || 'deze beurt'} verwijderen?`,
                    });
                    this.$wire.set('pendingDeleteShotId', marker.id);
                    this.$dispatch('open-modal', { id: 'delete-shot-modal' });
                },
                handlePointerDown(event) {
                    if (event.button !== 0 && event.pointerType === 'mouse') {
                        this.pointerStart = null;

                        return;
                    }

                    this.pointerStart = {
                        id: event.pointerId,
                        x: event.clientX,
                        y: event.clientY,
                    };
                },
                handlePointerUp(event) {
                    const start = this.pointerStart;

                    this.pointerStart = null;

                    if (! start || start.id !== event.pointerId || ! this.canEdit) {
                        return;
                    }

                    if (Math.hypot(event.clientX - start.x, event.clientY - start.y) > TAP_MAX_MOVEMENT) {
                        return;
                    }

                    const rect = this.$refs.board.getBoundingClientRect();
                    const x = Math.min(Math.max((event.clientX - rect.left) / rect.width, 0), 1);
                    const y = Math.min(Math.max((event.clientY - rect.top) / rect.height, 0), 1);

                    recordShot(x, y);
                },
                cancelPointer() {
                    this.pointerStart = null;
                },
            });
        };

        if (window.Alpine) {
            registerTargetBoard();
        } else {
            document.addEventListener('alpine:init', registerTargetBoard, { once: true });
        }
    })();
</script>
@endpushOnce
