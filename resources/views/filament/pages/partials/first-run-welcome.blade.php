@php
    /** @var \App\Filament\Pages\Dashboard $page */
    $page = $this;
    $onboarding = $page->getOnboarding();
    $user = $onboarding->user;

    $stepFirstWeapon = $onboarding->hasFirstWeapon();
    $stepProfileDone = $user->email_verified_at !== null;
    $stepFirstSession = $onboarding->hasFirstSession();

    $steps = [
        ['n' => 1, 'label' => 'Voeg je eerste wapen toe', 'done' => $stepFirstWeapon],
        ['n' => 2, 'label' => 'Maak je profiel af',       'done' => $stepProfileDone],
        ['n' => 3, 'label' => 'Log je eerste sessie',     'done' => $stepFirstSession],
    ];

    $currentIndex = null;
    foreach ($steps as $i => $step) {
        if (! $step['done']) {
            $currentIndex = $i;
            break;
        }
    }

    if ($currentIndex === null) {
        $continueUrl = \App\Filament\Resources\SessionResource::getUrl('create');
    } elseif ($currentIndex === 0) {
        $continueUrl = \App\Filament\Resources\WeaponResource::getUrl('create');
    } else {
        $continueUrl = \App\Filament\Resources\SessionResource::getUrl('create');
    }

    $logoUrl = asset('img/aimtrack-logo.svg');
    $logoStyle = sprintf(
        'display: inline-block; width: 56px; height: 56px; background: var(--at-accent); '
        .'-webkit-mask: url(%1$s) center/contain no-repeat; mask: url(%1$s) center/contain no-repeat;',
        $logoUrl
    );
@endphp

<div
    data-testid="first-run-welcome"
    style="position: relative; display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 200px); overflow: hidden;"
>
    {{-- Big ambient reticle watermark behind everything --}}
    <x-aimtrack.watermark-bg center :size="680" :opacity="0.06" :stroke="1" :dot="true" color="var(--at-accent)" />

    <div style="position: relative; z-index: 1; max-width: 520px; width: 100%; text-align: center; padding: 32px 16px;">
        {{-- Logo in rounded panel --}}
        <div
            style="display: inline-flex; align-items: center; justify-content: center; width: 90px; height: 90px; border-radius: 22px; background: var(--at-panel); border: 1px solid var(--at-line);"
        >
            <span aria-hidden="true" style="{{ $logoStyle }}"></span>
        </div>

        <div
            style="font-family: var(--at-font-mono); font-size: 10px; letter-spacing: 0.22em; color: var(--at-accent); margin-top: 18px; text-transform: uppercase;"
        >
            ● WELKOM
        </div>

        <h1
            style="font-family: var(--at-font-display); font-size: 44px; font-weight: 600; letter-spacing: -0.025em; margin: 12px 0 14px; color: var(--at-text); line-height: 1.1;"
        >
            Klaar voor je <span style="color: var(--at-accent);">eerste sessie?</span>
        </h1>

        <p
            style="font-size: 15px; color: var(--at-muted); line-height: 1.6; max-width: 420px; margin: 0 auto;"
        >
            Drie korte stappen — wapen toevoegen, profiel afmaken, je eerste sessie loggen. Daarna kijkt AimTrack mee.
        </p>

        {{-- Step checklist --}}
        <div style="margin: 32px auto 0; display: flex; flex-direction: column; gap: 10px; max-width: 380px;">
            @foreach ($steps as $i => $step)
                @php
                    $isCurrent = $i === $currentIndex;
                    $bg = $isCurrent ? 'var(--at-accent-12, rgba(100, 244, 179, 0.12))' : 'var(--at-panel)';
                    $border = $isCurrent
                        ? '1px solid var(--at-accent-25, rgba(100, 244, 179, 0.25))'
                        : '1px solid var(--at-line)';
                    $textWeight = $isCurrent ? 600 : 500;
                    $circleBg = $step['done'] ? 'var(--at-accent)' : 'transparent';
                    $circleBorder = $step['done'] || $isCurrent ? 'var(--at-accent)' : 'var(--at-line)';
                    $circleColor = $step['done'] ? 'var(--at-cta-text)' : 'var(--at-accent)';
                @endphp

                <div
                    data-testid="first-run-step-{{ $step['n'] }}"
                    @class([
                        'first-run-step',
                        'first-run-step-done' => $step['done'],
                        'first-run-step-current' => $isCurrent,
                    ])
                    style="padding: 12px 14px; border-radius: 10px; background: {{ $bg }}; border: {{ $border }}; display: flex; align-items: center; gap: 12px; text-align: left;"
                >
                    <div
                        style="width: 24px; height: 24px; border-radius: 50%; background: {{ $circleBg }}; border: 1.5px solid {{ $circleBorder }}; color: {{ $circleColor }}; display: flex; align-items: center; justify-content: center; font-family: var(--at-font-mono); font-size: 11px; font-weight: 700; flex: 0 0 24px;"
                    >
                        @if ($step['done'])
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        @else
                            {{ $step['n'] }}
                        @endif
                    </div>
                    <div style="flex: 1; font-size: 13px; color: var(--at-text); font-weight: {{ $textWeight }};">
                        {{ $step['label'] }}
                    </div>
                    @if ($isCurrent)
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--at-accent)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- CTAs --}}
        <div style="margin-top: 28px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
            <a
                href="{{ $continueUrl }}"
                data-testid="first-run-continue"
                style="padding: 12px 22px; border-radius: 10px; background: var(--at-accent); color: var(--at-cta-text); font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;"
            >
                Verder waar ik was
            </a>
            <button
                type="button"
                wire:click="seedDemoData"
                data-testid="first-run-demo"
                style="padding: 12px 22px; border-radius: 10px; border: 1px solid var(--at-line); background: transparent; color: var(--at-text); font-size: 14px; cursor: pointer;"
            >
                Demo-data inladen
            </button>
        </div>
    </div>
</div>
