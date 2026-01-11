<div class="flex flex-col gap-6">
    <div class="flex flex-wrap justify-between gap-3 items-center">
        <div class="flex gap-2 items-center">
            <span class="text-sm text-gray-500">Beurt</span>
            <select wire:model.live="currentTurnIndex" class="filament-forms-select-component">
                @foreach ($turnOptions as $turn)
                    <option value="{{ $turn }}">{{ $turn + 1 }}</option>
                @endforeach
            </select>
            @if ($canEdit)
                <button wire:click="addTurn" type="button" class="fi-btn fi-btn-size-sm fi-btn-color-primary">
                    Nieuwe beurt
                </button>
            @endif
        </div>
        <div class="text-sm text-gray-600">
            <span class="font-semibold">Totaal:</span>
            {{ $summary['total_score'] ?? 0 }} punten · {{ $summary['shot_count'] ?? 0 }} schoten · Gemiddelde {{ number_format($summary['average_score'] ?? 0, 1) }}
        </div>
    </div>

    <div
        x-data="targetBoard({
            recordShot: (x, y) => $wire.recordShot(x, y),
            canEdit: @js($canEdit),
            rawMarkers: @entangle('markers').live,
            turns: @entangle('turnOptions').live,
            currentTurn: @entangle('currentTurnIndex').live,
        })"
        class="w-full flex flex-col lg:flex-row gap-6"
    >
        <div
            class="relative w-full max-w-xl aspect-square bg-gradient-to-br from-gray-900 to-gray-800 rounded-3xl shadow-2xl overflow-hidden cursor-crosshair border border-gray-800/60"
            x-ref="board"
            wire:ignore
            @click="
                console.log('[SessionShotBoard] board click detected');
                handleClick($event);
            "
        >
            <canvas x-ref="canvas" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>
            <div
                x-show="canEdit"
                class="absolute inset-0 pointer-events-none"
                x-init="console.log('[SessionShotBoard] click readiness overlay', { canEdit })"
            ></div>
        </div>

        <div class="flex-1 space-y-4">
            <div class="bg-white/80 dark:bg-gray-900/60 rounded-2xl shadow">
                <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-100">Schotenoverzicht</h3>
                    <p class="text-xs text-gray-500">Gebruik de Filament-filters boven de tabel om een beurt of alle beurten te tonen.</p>
                </div>
                <div class="p-2">
                    {{ $this->table }}
                </div>
            </div>
        </div>
    </div>
</div>

@pushOnce('scripts')
<script>
    (() => {
        console.log('[SessionShotBoard] script bootstrap starting');

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

        const registerTargetBoard = () => {
            console.log('[SessionShotBoard] registerTargetBoard executed');

            window.targetBoard = ({ recordShot, canEdit, rawMarkers, turns, currentTurn }) => ({
                rawMarkers,
                renderMarkers: [],
                currentMarkers: [],
                turns: Array.isArray(turns) ? [...turns] : [],
                currentTurn: Number(currentTurn ?? 0),
                canEdit,
                init() {
                    console.log('[SessionShotBoard] targetBoard init', {
                        markersCount: this.renderMarkers?.length ?? 0,
                        canEdit: this.canEdit,
                        turns: this.turns,
                        currentTurn: this.currentTurn,
                    });

                    this.scheduleDraw();
                    this.onMarkersUpdated(this.rawMarkers);
                    this.$watch('rawMarkers', this.onMarkersUpdated.bind(this));
                    this.$watch('turns', (value) => {
                        console.log('[SessionShotBoard] turns updated', value);
                        this.turns = Array.isArray(value) ? [...value] : [];
                    });
                    this.$watch('currentTurn', (value) => {
                        const normalizedValue = this.normalizeTurnValue(value);
                        console.log('[SessionShotBoard] currentTurn updated', {
                            raw: value,
                            normalized: normalizedValue,
                        });

                        this.currentTurn = normalizedValue;
                        this.updateCurrentMarkers();
                        this.scheduleDraw();
                    });
                    this.$watch('$wire.currentTurnIndex', (value) => {
                        const normalizedValue = this.normalizeTurnValue(value);

                        console.log('[SessionShotBoard] $wire.currentTurnIndex updated', {
                            raw: value,
                            normalized: normalizedValue,
                        });

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
                mapToBackend(value) {
                    const offset = value - 0.5;
                    const scale = 0.5 / TARGET_RADIUS_RATIO;
                    return Math.min(Math.max(0.5 + offset * scale, 0), 1);
                },
                mapToBoard(value) {
                    const offset = value - 0.5;
                    const scale = TARGET_RADIUS_RATIO / 0.5;
                    return 0.5 + offset * scale;
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

                    ctx.lineWidth = size * 0.004;
                    ctx.strokeStyle = '#0f172a';
                    ctx.beginPath();
                    ctx.rect(size * 0.02, size * 0.02, size * 0.96, size * 0.96);
                    ctx.stroke();

                    this.drawMarkers(ctx, size);
                },
                drawMarkers(ctx, size) {
                    if (! Array.isArray(this.currentMarkers) || this.currentMarkers.length === 0) {
                        return;
                    }

                    this.currentMarkers.forEach((marker) => {
                        const x = this.mapToBoard(marker.x ?? marker.left ?? 0.5) * size;
                        const y = this.mapToBoard(marker.y ?? marker.top ?? 0.5) * size;
                        const radius = Math.max(4, size * 0.012);

                        ctx.save();
                        ctx.beginPath();
                        ctx.arc(x, y, radius, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(59,130,246,0.9)';
                        ctx.fill();
                        ctx.lineWidth = Math.max(2, size * 0.004);
                        ctx.strokeStyle = 'rgba(255,255,255,0.9)';
                        ctx.stroke();
                        ctx.shadowColor = 'rgba(15,23,42,0.4)';
                        ctx.shadowBlur = radius;
                        ctx.restore();
                    });
                },
                onMarkersUpdated(value) {
                    console.log('[SessionShotBoard] markers updated', {
                        count: value?.length ?? 0,
                        sample: value?.slice?.(0, 3) ?? value,
                    });

                    if (! Array.isArray(value)) {
                        return;
                    }

                    this.renderMarkers = value.map((marker, index) => this.normalizeMarker(marker, index));
                    this.updateCurrentMarkers();
                    this.scheduleDraw();
                },
                updateCurrentMarkers() {
                    const numericTurn = Number(this.currentTurn ?? 0);
                    const currentTurn = Number.isNaN(numericTurn) ? 0 : numericTurn;

                    const markersForTurn = this.renderMarkers
                        .filter(Boolean)
                        .filter((marker) => {
                            const markerTurn = Number(marker.turn_index ?? marker.turnIndex ?? marker.turn ?? null);
                            return ! Number.isNaN(markerTurn) && markerTurn === currentTurn;
                        });

                    console.log('[SessionShotBoard] updateCurrentMarkers', {
                        currentTurn,
                        totalMarkers: this.renderMarkers.length,
                        markersForTurn: markersForTurn.length,
                        sample: markersForTurn.slice(0, 3),
                    });

                    this.currentMarkers = markersForTurn;
                },
                handleClick(event) {
                    console.log('[SessionShotBoard] handleClick', {
                        canEdit: this.canEdit,
                        eventType: event.type,
                    });

                    if (! this.canEdit) {
                        console.log('[SessionShotBoard] click ignored because canEdit=false');
                        return;
                    }

                    const rect = this.$refs.board.getBoundingClientRect();
                    const x = (event.clientX - rect.left) / rect.width;
                    const y = (event.clientY - rect.top) / rect.height;
                    const xNormalized = Math.min(Math.max(x, 0), 1);
                    const yNormalized = Math.min(Math.max(y, 0), 1);
                    const backendX = this.mapToBackend(xNormalized);
                    const backendY = this.mapToBackend(yNormalized);

                    console.log('[SessionShotBoard] normalized coordinates', {
                        xNormalized,
                        yNormalized,
                        backendX,
                        backendY,
                    });

                    recordShot(backendX, backendY);

                    console.log('[SessionShotBoard] recordShot dispatched');
                },
                normalizeMarker(marker, index) {
                    if (! marker) {
                        return null;
                    }

                    const left = (marker.x ?? marker.left ?? 0) * 100;
                    const top = (marker.y ?? marker.top ?? 0) * 100;

                    return {
                        ...marker,
                        left,
                        top,
                        idx: `${marker.id}-${index}-${left}-${top}`,
                    };
                },
            });
        };

        if (window.Alpine) {
            console.log('[SessionShotBoard] Alpine already present, registering immediately');
            registerTargetBoard();
        } else {
            console.log('[SessionShotBoard] Alpine not ready, awaiting alpine:init');
            document.addEventListener('alpine:init', registerTargetBoard, { once: true });
        }
    })();
</script>
@endpushOnce
