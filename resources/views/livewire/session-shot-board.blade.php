<div class="mt-6 space-y-6" x-data="{
        deleteConfirmationMessage: '',
        pendingDeleteShotId: null,
        ...targetBoard({
            recordShot: (x, y) => $wire.recordShot(x, y),
            canEdit: @js($canEdit),
            rawMarkers: @entangle('markers').live,
            turns: @entangle('turnOptions').live,
            currentTurn: @entangle('currentTurnIndex').live,
            turnLegend: @entangle('turnLegend').live,
            allTurnsValue: @js(\App\Livewire\SessionShotBoard::ALL_TURNS_VALUE),
        })
    }">
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

    <!-- Beurt Selectie -->
    <div class="mb-6 rounded-3xl border border-gray-200/70 bg-white/80 px-5 py-4 shadow-sm dark:border-gray-800/60 dark:bg-gray-900/60">
        <div class="flex flex-col gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Beurt selectie</span>
            <div class="relative">
                <select wire:model.live="currentTurnIndex" class="rounded-2xl border border-gray-300 bg-white px-3 py-1.5 text-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="{{ \App\Livewire\SessionShotBoard::ALL_TURNS_VALUE }}">Alle beurten</option>
                    @foreach ($turnOptions as $turn)
                        <option value="{{ $turn }}">Beurt {{ $turn + 1 }}</option>
                    @endforeach
                </select>
                @if ($canEdit)
                    <x-filament::button
                        type="button"
                        size="sm"
                        color="primary"
                        icon="heroicon-m-plus"
                        wire:click="addTurn"
                        style="float: right;"
                    >
                        Nieuwe beurt
                    </x-filament::button>
                @endif
                <div style="clear: both;"></div>
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-gray-200/70 bg-white/80 px-5 py-5 shadow-sm dark:border-gray-800/60 dark:bg-gray-900/60">
        <div class="mt-6"
            x-data="targetBoard({
            recordShot: (x, y) => $wire.recordShot(x, y),
            canEdit: @js($canEdit),
            rawMarkers: @entangle('markers').live,
            turns: @entangle('turnOptions').live,
            currentTurn: @entangle('currentTurnIndex').live,
            turnLegend: @entangle('turnLegend').live,
            allTurnsValue: @js(\App\Livewire\SessionShotBoard::ALL_TURNS_VALUE),
        })"
            class="w-full flex flex-col lg:flex-row gap-6"
            x-on:keydown.escape.window="closeContextMenu()"
        >
            <!-- Interactieve Roos -->
            <div class="w-full max-w-xl space-y-4">
                <div
                    class="relative w-full aspect-square bg-gradient-to-br from-gray-900 to-gray-800 rounded-3xl shadow-2xl overflow-hidden border border-gray-800/60"
                    x-ref="board"
                    wire:ignore
                >
                    <canvas x-ref="canvas" class="absolute inset-0 w-full h-full" style="cursor: crosshair;"
                    @click="handleCanvasClick($event)"
                    @contextmenu.prevent="handleCanvasRightClick($event)"
                ></canvas>
                </div>
            </div>

            <!-- Doelgebied & Schoten -->
            <div class="flex-1 space-y-4">
                <div class="bg-white/80 dark:bg-gray-900/60 rounded-2xl shadow">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-800">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-100">Doelgebied & schoten</h3>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Beheer beurten en bekijk gedetailleerde schotinformatie. Gebruik de filters om specifieke beurten te tonen.</p>
                    </div>
                    <div class="p-2">
                        {{ $this->table }}
                    </div>
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

            window.targetBoard = ({ recordShot, canEdit, rawMarkers, turns, currentTurn, turnLegend, allTurnsValue }) => ({
                rawMarkers,
                renderMarkers: [],
                currentMarkers: [],
                turns: Array.isArray(turns) ? [...turns] : [],
                currentTurn: Number(currentTurn ?? 0),
                turnLegend: Array.isArray(turnLegend) ? [...turnLegend] : [],
                allTurnsValue: allTurnsValue ?? -1,
                canEdit,
                longPressTimeout: null,
                longPressTarget: null,
                longPressTriggered: false,
                longPressDelay: 2000,
                contextMenu: {
                    open: false,
                    x: 0,
                    y: 0,
                    marker: null,
                },
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
                    this.$watch('turnLegend', (value) => {
                        console.log('[SessionShotBoard] turnLegend updated', value);
                        this.turnLegend = Array.isArray(value) ? [...value] : [];
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
                        // Use raw normalized coordinates directly (0-1 range) for canvas positioning
                        const x = (marker.x ?? 0.5) * size;
                        const y = (marker.y ?? 0.5) * size;
                        const radius = Math.max(4, size * 0.012);

                        ctx.save();
                        ctx.beginPath();
                        ctx.arc(x, y, radius, 0, Math.PI * 2);
                        ctx.fillStyle = marker.color ?? 'rgba(59,130,246,0.9)';
                        ctx.fill();
                        ctx.lineWidth = Math.max(2, size * 0.004);
                        ctx.strokeStyle = 'rgba(255,255,255,0.9)';
                        ctx.stroke();
                        ctx.shadowColor = (marker.color ?? 'rgba(15,23,42,0.4)').replace('1)', '0.4)');
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
                            if (currentTurn === this.allTurnsValue) {
                                return true;
                            }

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
                handleCanvasRightClick(event) {
                    // Check if right-click is on a marker
                    const clickedMarker = this.getMarkerAtPosition(event);
                    
                    if (clickedMarker) {
                        // Set confirmation data and open modal
                        this.$root.deleteConfirmationMessage = `Schot uit ${clickedMarker.turn_label || 'deze beurt'} verwijderen?`;
                        this.$wire.set('pendingDeleteShotId', clickedMarker.id);
                        this.$dispatch('open-modal', { id: 'delete-shot-modal' });
                    }
                },
                handleCanvasClick(event) {
                    // Check if click is on a marker
                    const clickedMarker = this.getMarkerAtPosition(event);
                    
                    if (clickedMarker) {
                        // Start long press for marker deletion
                        this.startLongPress(clickedMarker);
                        return;
                    }
                    
                    // Otherwise, handle as normal shot registration
                    this.handleClick(event);
                },
                getMarkerAtPosition(event) {
                    if (! Array.isArray(this.currentMarkers) || this.currentMarkers.length === 0) {
                        return null;
                    }

                    const canvas = this.$refs.canvas;
                    const rect = canvas.getBoundingClientRect();
                    const x = event.clientX - rect.left;
                    const y = event.clientY - rect.top;
                    
                    // Check each marker to see if click is within bounds
                    for (const marker of this.currentMarkers) {
                        // Use raw normalized coordinates for marker position
                        const markerX = (marker.x ?? 0.5) * rect.width;
                        const markerY = (marker.y ?? 0.5) * rect.height;
                        const markerRadius = 15; // Click radius for markers
                        
                        const distance = Math.sqrt(
                            Math.pow(x - markerX, 2) + Math.pow(y - markerY, 2)
                        );
                        
                        if (distance <= markerRadius) {
                            return marker;
                        }
                    }
                    
                    return null;
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

                    console.log('[SessionShotBoard] normalized coordinates', {
                        xNormalized,
                        yNormalized,
                    });

                    // Send raw normalized coordinates directly to backend
                    recordShot(xNormalized, yNormalized);

                    console.log('[SessionShotBoard] recordShot dispatched');
                },
                normalizeMarker(marker, index) {
                    if (! marker) {
                        return null;
                    }

                    const normalizeCoordinate = (value) => {
                        if (typeof value !== 'number' || Number.isNaN(value)) {
                            return 0.5;
                        }

                        return value > 1 ? value / 100 : value;
                    };

                    // Backend coordinates are already normalized (0-1 range), convert directly to percentages
                    const boardX = normalizeCoordinate(marker.x ?? marker.left ?? 0.5) * 100;
                    const boardY = normalizeCoordinate(marker.y ?? marker.top ?? 0.5) * 100;

                    return {
                        ...marker,
                        left: boardX,
                        top: boardY,
                        idx: `${marker.id}-${index}-${boardX}-${boardY}`,
                    };
                },
                markerStyle(marker) {
                    const size = 26;
                    const color = marker.color ?? '#3b82f6';

                    return `left: ${marker.left}%; top: ${marker.top}%; width: ${size}px; height: ${size}px; background-color: ${color}; border-color: ${color};`;
                },
                markerClasses(marker) {
                    return this.currentTurn === this.allTurnsValue ? 'opacity-80' : 'opacity-100';
                },
                shouldDisplayMarker(marker) {
                    if (this.currentTurn === this.allTurnsValue) {
                        return true;
                    }

                    const markerTurn = Number(marker.turn_index ?? marker.turnIndex ?? marker.turn ?? null);

                    return ! Number.isNaN(markerTurn) && markerTurn === this.currentTurn;
                },
                startLongPress(marker) {
                    this.queueLongPress(marker);
                },
                startKeyLongPress(marker, event) {
                    if (event?.repeat) {
                        return;
                    }

                    this.queueLongPress(marker);
                },
                queueLongPress(marker) {
                    if (! this.canEdit) {
                        return;
                    }

                    this.cancelLongPress();
                    this.closeContextMenu();
                    this.longPressTarget = marker;
                    this.longPressTriggered = false;
                    this.longPressTimeout = setTimeout(() => {
                        this.longPressTriggered = true;
                        this.cancelLongPress({ keepTriggered: true });
                        
                        // Set confirmation data and open modal
                        this.$root.deleteConfirmationMessage = `Schot uit ${marker.turn_label || 'deze beurt'} verwijderen?`;
                        this.$wire.set('pendingDeleteShotId', marker.id);
                        this.$dispatch('open-modal', { id: 'delete-shot-modal' });
                    }, this.longPressDelay);
                },
                endLongPress(forceCancel = false) {
                    if (this.longPressTriggered && ! forceCancel) {
                        this.longPressTriggered = false;

                        return;
                    }

                    this.cancelLongPress();
                },
                cancelLongPress(options = { keepTriggered: false }) {
                    if (this.longPressTimeout) {
                        clearTimeout(this.longPressTimeout);
                    }

                    this.longPressTimeout = null;

                    if (! options.keepTriggered) {
                        this.longPressTriggered = false;
                    }

                    this.longPressTarget = null;
                },
                openContextMenu(event, marker) {
                    if (! this.canEdit || ! marker) {
                        return;
                    }

                    this.cancelLongPress();

                    const rect = this.$refs.board.getBoundingClientRect();
                    const padding = 12;
                    const menuWidth = 200;
                    const menuHeight = 140;

                    const relativeX = event.clientX - rect.left;
                    const relativeY = event.clientY - rect.top;

                    this.contextMenu.open = true;
                    this.contextMenu.marker = marker;
                    this.contextMenu.x = Math.min(Math.max(relativeX, padding), rect.width - menuWidth);
                    this.contextMenu.y = Math.min(Math.max(relativeY, padding), rect.height - menuHeight);
                },
                closeContextMenu() {
                    this.contextMenu.open = false;
                    this.contextMenu.marker = null;
                },
                deleteMarker(marker, options = { requireConfirm: true }) {
                    if (! this.canEdit || ! marker) {
                        return;
                    }

                    if (options.requireConfirm !== false) {
                        // Call Livewire method that shows Filament confirmation
                        this.$wire.confirmDeleteShot(marker.id);
                        this.longPressTriggered = false;
                        this.closeContextMenu();
                        return;
                    }

                    // Direct deletion without confirmation
                    this.$wire.deleteShot(marker.id);
                    this.longPressTriggered = false;
                    this.closeContextMenu();
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
