@php
    // Decimaal-numpad uit new-session-wizard.jsx — exact deze volgorde/labels.
    $numpad = ['10.9', '10.8', '10.7', '10.6', '10.5', '10.4', '10.3', '10.2', '10.1', '10.0', '9.9', '9.8', '9.7', '9.6', '9.5', '9.0', '8', '0'];

    // Preview-tekst (geen runtime AI-call): kadert de live-feature i.p.v. nep-cijfers.
    $aiTip = 'Zodra je schoten logt geeft de coach hier live tips op basis van je cadans en groepering.';

    // Live wizard-state (geen sample data): toon het écht gekozen wapen + sessie
    // uit de eerdere stappen. $this->data is de wizard-form-state op deze pagina.
    $wizardData = is_array($this->data ?? null) ? $this->data : [];
    $weaponRows = $wizardData['sessionWeapons'] ?? [];
    $firstRow = is_array($weaponRows) ? (collect($weaponRows)->first() ?? []) : [];
    $plannedShots = collect($weaponRows)->sum(fn ($r) => (int) ($r['rounds_fired'] ?? 0));

    $chosenWeapon = ! empty($firstRow['weapon_id'])
        ? \App\Models\Weapon::find($firstRow['weapon_id'])
        : null;
    $chosenDistance = $firstRow['distance_m'] ?? null;

    $sessionDate = ! empty($wizardData['date']) ? \Illuminate\Support\Carbon::parse($wizardData['date'])->translatedFormat('d M Y') : null;
    $sessionRange = $wizardData['range_name'] ?? ($wizardData['location'] ?? null);
@endphp

<div class="aimtrack-wizard-shots" style="display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 16px; align-items: start;">
    <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
        <div style="display: flex; align-items: flex-end; gap: 24px; flex-wrap: wrap;">
            <div>
                <div class="at-label">VOORTGANG</div>
                <div style="display: flex; align-items: baseline; gap: 6px; margin-top: 6px;">
                    <div style="font-family: var(--at-font-mono); font-size: 36px; font-weight: 600; color: var(--at-text); line-height: 1; letter-spacing: -0.02em;">0</div>
                    <div style="font-family: var(--at-font-mono); font-size: 14px; color: var(--at-muted);">/ {{ $plannedShots > 0 ? $plannedShots : 60 }}</div>
                </div>
            </div>
            <div>
                <div class="at-label">LOPENDE SCORE</div>
                <div style="font-family: var(--at-font-mono); font-size: 36px; font-weight: 600; color: var(--at-accent); line-height: 1; margin-top: 6px; letter-spacing: -0.02em;">—</div>
            </div>
            <div>
                <div class="at-label">GEM. PER SCHOT</div>
                <div style="font-family: var(--at-font-mono); font-size: 36px; font-weight: 600; color: var(--at-text); line-height: 1; margin-top: 6px; letter-spacing: -0.02em;">—</div>
            </div>
            <div style="margin-left: auto; display: flex; align-items: center; gap: 10px; padding: 6px 10px; border-radius: var(--at-r-md); background: var(--at-accent-12); border: 1px solid var(--at-accent-25);">
                <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--at-accent);"></span>
                <span style="font-family: var(--at-font-mono); font-size: 10px; letter-spacing: 0.16em; color: var(--at-accent);">OPNAME START NA OPSLAAN</span>
            </div>
        </div>

        <x-aimtrack.shot-strip :shots="[]" :total-slots="$plannedShots > 0 ? $plannedShots : 60" :show-legend="false" />

        <div>
            <div class="at-label">VOLGEND SCHOT · PREVIEW</div>
            <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin-top: 10px; opacity: 0.55; pointer-events: none;" aria-hidden="true">
                @foreach ($numpad as $value)
                    @php
                        $isTen = str_starts_with($value, '10');
                        $isMiss = $value === '0';
                        $bg = $isTen ? 'var(--at-accent-12)' : ($isMiss ? 'color-mix(in srgb, var(--at-warn) 8%, transparent)' : 'var(--at-bg)');
                        $border = $isTen ? 'var(--at-accent-25)' : ($isMiss ? 'color-mix(in srgb, var(--at-warn) 20%, transparent)' : 'var(--at-line)');
                        $color = $isTen ? 'var(--at-accent)' : ($isMiss ? 'var(--at-warn)' : 'var(--at-text)');
                    @endphp
                    <div style="padding: 11px 0; border-radius: var(--at-r-md); background: {{ $bg }}; border: 1px solid {{ $border }}; color: {{ $color }}; text-align: center; font-family: var(--at-font-mono); font-size: 14px; font-weight: 600; letter-spacing: -0.01em;">{{ $value }}</div>
                @endforeach
            </div>

            <div style="display: flex; align-items: center; gap: 8px; margin-top: 12px; font-size: 11px; color: var(--at-muted); font-family: var(--at-font-mono);">
                <span style="margin-left: auto; letter-spacing: 0.08em;">OF VUL HANDMATIG IN ↓</span>
            </div>
            <div style="margin-top: 10px; display: flex; align-items: center; gap: 10px; opacity: 0.55; pointer-events: none;" aria-hidden="true">
                <input readonly value="10." style="flex: 1; padding: 12px 14px; border-radius: var(--at-r-lg); background: var(--at-bg); border: 2px solid var(--at-accent); color: var(--at-text); font-family: var(--at-font-mono); font-size: 18px; font-weight: 600; letter-spacing: -0.01em; outline: none;" />
                <span style="padding: 12px 18px; border-radius: var(--at-r-lg); background: var(--at-accent); color: var(--at-cta-text); font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 6px;">Bevestig →</span>
            </div>
        </div>

        <div style="display: flex; align-items: flex-start; gap: 10px; padding: 14px; border: 1px dashed var(--at-line); border-radius: var(--at-r-md); background: var(--at-bg);">
            <span style="color: var(--at-accent); font-size: 16px; line-height: 1.2;" role="presentation">◎</span>
            <div style="font-size: 12px; line-height: 1.55; color: var(--at-muted);">
                Het scorebord hierboven is een voorbeeld. Je legt de echte schoten vast op het
                <span style="color: var(--at-text); font-weight: 600;">schotenbord</span>, dat direct opent zodra je deze sessie afrondt.
                Daar tik je elk schot op de roos — score en ring worden automatisch berekend.
            </div>
        </div>
    </div>

    <aside style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
        <div>
            <div class="at-label">SESSIE</div>
            <div style="font-size: 14px; font-weight: 600; color: var(--at-text); margin-top: 6px;">{{ $sessionDate ?? 'Nieuwe sessie' }}</div>
            <div style="font-size: 12px; color: var(--at-muted); font-family: var(--at-font-mono); margin-top: 2px;">{{ $sessionRange ?? 'Datum & baan: stap Sessie' }}</div>
        </div>

        <div>
            <div class="at-label">WAPEN</div>
            <div style="margin-top: 6px; padding: 10px 12px; border-radius: var(--at-r-lg); background: var(--at-panel-2); border: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                <span style="color: var(--at-accent); font-size: 16px; line-height: 1;" role="presentation">⦿</span>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">{{ $chosenWeapon?->name ?? 'Kies in stap Wapen' }}</div>
                    <div style="font-family: var(--at-font-mono); font-size: 11px; color: var(--at-muted);">{{ $chosenWeapon ? trim(($chosenWeapon->weapon_type?->value ? ucfirst($chosenWeapon->weapon_type->value) : '').($chosenWeapon->caliber ? ' · '.$chosenWeapon->caliber : '').($chosenDistance ? ' · '.$chosenDistance.'m' : '')) : 'wapen · kaliber · afstand' }}</div>
                </div>
            </div>
        </div>

        <div>
            <div class="at-label">OPTIES</div>
            @php
                // Echte, persisterende user-voorkeuren (CreateSession::$boardPreferences).
                // Label-volgorde conform new-session-wizard.jsx.
                $optionRows = [
                    ['key' => 'auto_ai_reflection', 'label' => 'AI-reflectie'],
                    ['key' => 'decimal_notation', 'label' => 'Decimaal-notatie'],
                    ['key' => 'board_show_rings', 'label' => 'Toon ringen-view'],
                ];
            @endphp
            <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 8px;">
                @foreach ($optionRows as $option)
                    @php $on = (bool) ($this->boardPreferences[$option['key']] ?? false); @endphp
                    <button
                        type="button"
                        wire:click="togglePreference('{{ $option['key'] }}')"
                        role="switch"
                        aria-checked="{{ $on ? 'true' : 'false' }}"
                        aria-label="{{ $option['label'] }}"
                        style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--at-text); background: none; border: none; padding: 2px 0; cursor: pointer; text-align: left; width: 100%;"
                    >
                        <span style="width: 30px; height: 18px; border-radius: 999px; background: {{ $on ? 'var(--at-accent)' : 'var(--at-line)' }}; position: relative; flex-shrink: 0; transition: background 0.15s ease;">
                            <span style="position: absolute; top: 2px; left: {{ $on ? '14px' : '2px' }}; width: 14px; height: 14px; border-radius: 50%; background: var(--at-cta-text); transition: left 0.15s ease;"></span>
                        </span>
                        <span>{{ $option['label'] }}</span>
                    </button>
                @endforeach
            </div>
            <div style="margin-top: 8px; font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); letter-spacing: 0.08em;">VOORKEUREN · GELDEN VOOR AL JE SESSIES</div>
        </div>

        <x-aimtrack.bracket-frame :rounded="8" :padding="14" style="background: var(--at-accent-12); display: flex; flex-direction: column; gap: 6px;">
            <div style="font-family: var(--at-font-mono); font-size: 10px; color: var(--at-accent); letter-spacing: 0.14em;">● AI TIJDENS SESSIE</div>
            <div style="font-size: 12px; line-height: 1.5; color: var(--at-text);">{{ $aiTip }}</div>
        </x-aimtrack.bracket-frame>
    </aside>
</div>
